#!/usr/bin/env python3
"""Inferential statistics for PCE results.

Outputs are written to LLM_eval/<dataset>/stats/.

Implemented analyses:
1) 95% bootstrap CIs for main Table-8 metrics
2) Paired Wilcoxon signed-rank tests on per-file weighted discharge contributions
3) Holm correction for multiple comparisons
4) Effect sizes (rank-biserial correlation, median paired difference)
5) Spearman correlations (+ bootstrap CIs) for discharge vs robustness diagnostics
6) Sensitivity check for all-files vs analyzable-only scoring
"""

from __future__ import annotations

import argparse
import json
import math
import re
from dataclasses import dataclass
from pathlib import Path
from typing import Any, Dict, Iterable, List, Tuple

import numpy as np
import pandas as pd
from scipy.stats import rankdata, spearmanr, wilcoxon

import sys

sys.path.insert(0, str(Path(__file__).parent.parent))
from config import LLM_EVAL_SUBDIR, LLM_MIGRATION_SUBDIR, SELECTED_100_FILES_DIR  # noqa: E402

SEED = 42
RNG = np.random.default_rng(SEED)
UPGRADE_RULE_PREFIX = "Rector\\Php"

MODELS = {
    "gemini_2_5_pro": "Gemini 2.5 Pro",
    "gpt_5_codex": "GPT-5 Codex",
    "gemini_2_5_flash": "Gemini 2.5 Flash",
    "claude_sonnet_4_20250514": "Claude Sonnet 4",
    "meta_llama_llama_3_3_70b_instruct": "Llama 3.3 70B",
}

ALL_MODELS_WITH_BASELINE = {
    "Rector_Baseline": "Rector (reference)",
    **MODELS,
}

DEFAULT_COMPARISONS = [
    ("gemini_2_5_pro", "gpt_5_codex"),
    ("gemini_2_5_pro", "claude_sonnet_4_20250514"),
    ("gpt_5_codex", "claude_sonnet_4_20250514"),
    ("gemini_2_5_pro", "gemini_2_5_flash"),
    ("gpt_5_codex", "gemini_2_5_flash"),
    ("gemini_2_5_pro", "meta_llama_llama_3_3_70b_instruct"),
    ("gpt_5_codex", "meta_llama_llama_3_3_70b_instruct"),
]


@dataclass
class BootstrapSummary:
    estimate: float
    ci_low: float
    ci_high: float


def _is_upgrade_rule(rule: Any) -> bool:
    return isinstance(rule, str) and rule.startswith(UPGRADE_RULE_PREFIX)


def _to_float(series: pd.Series) -> pd.Series:
    return pd.to_numeric(series, errors="coerce")


def bootstrap_ci(values: np.ndarray, alpha: float) -> Tuple[float, float]:
    if values.size == 0:
        return float("nan"), float("nan")
    lo = float(np.quantile(values, alpha / 2.0))
    hi = float(np.quantile(values, 1.0 - alpha / 2.0))
    return lo, hi


def _benchmark_order_key(filename: str, fallback_id: int) -> Tuple[int, int]:
    """Sort key for benchmark files using numeric filename prefix when present."""
    m = re.match(r"^(\d+)_", str(filename))
    if m:
        return (int(m.group(1)), fallback_id)
    return (10**9, fallback_id)


def load_baseline_file_obligations() -> pd.DataFrame:
    selection_json = SELECTED_100_FILES_DIR / "selection_data.json"
    with open(selection_json, "r", encoding="utf-8") as f:
        data = json.load(f)

    rows = []
    for item in data:
        rules = item.get("rules_triggered", [])
        upgrade_rules = {r for r in rules if _is_upgrade_rule(r)}
        original_file_id = int(item["file_id"])
        filename = item.get("new_filename") or item.get("filename") or str(original_file_id)
        rows.append(
            {
                "original_file_id": original_file_id,
                "filename": filename,
                "original_obligations": int(len(upgrade_rules)),
            }
        )

    df = pd.DataFrame(rows)
    df = df.sort_values(
        by=["filename", "original_file_id"],
        key=lambda s: s.map(
            lambda v: _benchmark_order_key(v, 10**9)[0] if s.name == "filename" else int(v)
        ),
    ).reset_index(drop=True)
    df["benchmark_id"] = np.arange(1, len(df) + 1, dtype=int)
    df = df[["benchmark_id", "original_file_id", "filename", "original_obligations"]]
    if (df["original_obligations"] <= 0).any():
        raise ValueError("Found baseline files with zero Rector\\Php* obligations; expected all 100 selected files to be obligated.")
    return df


def load_eval_results(model_folder: str) -> pd.DataFrame:
    path = LLM_EVAL_SUBDIR / model_folder / "evaluation_results.csv"
    df = pd.read_csv(path)
    df["original_file_id"] = pd.to_numeric(df["original_file_id"], errors="raise").astype(int)
    numeric_cols = [
        "original_obligations",
        "discharged_obligations",
        "introduced_obligations",
        "remaining_obligations",
        "discharge_rate",
    ]
    for col in numeric_cols:
        df[col] = _to_float(df[col])
    return df


def build_per_file_panel(model_folder: str, base_df: pd.DataFrame) -> Tuple[pd.DataFrame, pd.DataFrame]:
    eval_df = load_eval_results(model_folder)

    analyzable = eval_df[[
        "original_file_id",
        "filename",
        "original_obligations",
        "discharged_obligations",
        "introduced_obligations",
        "remaining_obligations",
        "discharge_rate",
    ]].copy()
    analyzable["analyzable"] = True

    full = base_df.merge(
        analyzable[[
            "original_file_id",
            "filename",
            "discharged_obligations",
            "introduced_obligations",
            "remaining_obligations",
            "discharge_rate",
            "analyzable",
        ]],
        on=["original_file_id", "filename"],
        how="left",
    )
    for col in ["discharged_obligations", "introduced_obligations", "remaining_obligations", "discharge_rate"]:
        full[col] = _to_float(full[col])
    full["analyzable"] = full["analyzable"].fillna(False)

    full["discharged_obligations"] = full["discharged_obligations"].fillna(0.0)
    total_obligations_all = float(base_df["original_obligations"].sum())
    full["weighted_discharge_contrib"] = full["discharged_obligations"] / total_obligations_all * 100.0

    return full, analyzable


def bootstrap_table8_metrics(full_df: pd.DataFrame, analyzable_df: pd.DataFrame, n_boot: int, alpha: float) -> Dict[str, BootstrapSummary]:
    n_all = len(full_df)
    n_an = len(analyzable_df)

    full_idx = RNG.integers(0, n_all, size=(n_boot, n_all))
    an_idx = RNG.integers(0, n_an, size=(n_boot, n_an)) if n_an > 0 else np.empty((0, 0), dtype=int)

    contrib = full_df["weighted_discharge_contrib"].to_numpy(float)
    discharged = full_df["discharged_obligations"].to_numpy(float)
    obligations = full_df["original_obligations"].to_numpy(float)

    mean_rho = analyzable_df["discharge_rate"].to_numpy(float)
    rem = analyzable_df["remaining_obligations"].to_numpy(float)
    intro = analyzable_df["introduced_obligations"].to_numpy(float)

    discharged_sum_boot = discharged[full_idx].sum(axis=1)
    obligations_sum_boot = obligations[full_idx].sum(axis=1)
    weighted_boot = np.divide(
        discharged_sum_boot,
        obligations_sum_boot,
        out=np.full_like(discharged_sum_boot, np.nan, dtype=float),
        where=obligations_sum_boot > 0,
    ) * 100.0
    discharged_boot = discharged[full_idx].sum(axis=1)

    if n_an > 0:
        mean_boot = np.nanmean(mean_rho[an_idx], axis=1)
        remaining_boot = rem[an_idx].sum(axis=1)
        introduced_boot = intro[an_idx].sum(axis=1)
        clean_boot = (rem[an_idx] == 0).sum(axis=1)
        low50_boot = (mean_rho[an_idx] < 50.0).sum(axis=1)
    else:
        mean_boot = np.array([np.nan] * n_boot)
        remaining_boot = np.array([np.nan] * n_boot)
        introduced_boot = np.array([np.nan] * n_boot)
        clean_boot = np.array([np.nan] * n_boot)
        low50_boot = np.array([np.nan] * n_boot)

    out = {
        "mean_discharge_rate_analyzable": BootstrapSummary(
            estimate=float(np.nanmean(mean_rho)) if n_an > 0 else float("nan"),
            ci_low=bootstrap_ci(mean_boot, alpha)[0],
            ci_high=bootstrap_ci(mean_boot, alpha)[1],
        ),
        "weighted_discharge_rate_all_files": BootstrapSummary(
            estimate=float(contrib.sum()),
            ci_low=bootstrap_ci(weighted_boot, alpha)[0],
            ci_high=bootstrap_ci(weighted_boot, alpha)[1],
        ),
        "discharged_obligations_all_files": BootstrapSummary(
            estimate=float(discharged.sum()),
            ci_low=bootstrap_ci(discharged_boot, alpha)[0],
            ci_high=bootstrap_ci(discharged_boot, alpha)[1],
        ),
        "remaining_obligations_analyzable": BootstrapSummary(
            estimate=float(np.nansum(rem)),
            ci_low=bootstrap_ci(remaining_boot, alpha)[0],
            ci_high=bootstrap_ci(remaining_boot, alpha)[1],
        ),
        "introduced_obligations_analyzable": BootstrapSummary(
            estimate=float(np.nansum(intro)),
            ci_low=bootstrap_ci(introduced_boot, alpha)[0],
            ci_high=bootstrap_ci(introduced_boot, alpha)[1],
        ),
        "contract_clean_files_analyzable": BootstrapSummary(
            estimate=float(np.sum(rem == 0)),
            ci_low=bootstrap_ci(clean_boot, alpha)[0],
            ci_high=bootstrap_ci(clean_boot, alpha)[1],
        ),
        "low_compliance_files_lt50_analyzable": BootstrapSummary(
            estimate=float(np.sum(mean_rho < 50.0)) if n_an > 0 else float("nan"),
            ci_low=bootstrap_ci(low50_boot, alpha)[0],
            ci_high=bootstrap_ci(low50_boot, alpha)[1],
        ),
    }
    return out


def _holm_adjust(p_values: List[float]) -> List[float]:
    m = len(p_values)
    order = np.argsort(p_values)
    sorted_p = np.array([p_values[i] for i in order], dtype=float)
    adjusted_sorted = np.empty(m, dtype=float)

    running_max = 0.0
    for i, p in enumerate(sorted_p):
        adj = (m - i) * p
        running_max = max(running_max, adj)
        adjusted_sorted[i] = min(running_max, 1.0)

    adjusted = np.empty(m, dtype=float)
    for rank, idx in enumerate(order):
        adjusted[idx] = adjusted_sorted[rank]
    return adjusted.tolist()


def _bh_adjust(p_values: List[float]) -> List[float]:
    m = len(p_values)
    if m == 0:
        return []

    order = np.argsort(p_values)
    sorted_p = np.array([p_values[i] for i in order], dtype=float)
    adjusted_sorted = np.empty(m, dtype=float)

    running_min = 1.0
    for i in range(m - 1, -1, -1):
        rank = i + 1
        adj = sorted_p[i] * m / rank
        running_min = min(running_min, adj)
        adjusted_sorted[i] = min(running_min, 1.0)

    adjusted = np.empty(m, dtype=float)
    for rank, idx in enumerate(order):
        adjusted[idx] = adjusted_sorted[rank]
    return adjusted.tolist()


def rank_biserial_from_paired_diff(diff: np.ndarray) -> float:
    nonzero = diff[diff != 0]
    if nonzero.size == 0:
        return 0.0
    ranks = rankdata(np.abs(nonzero), method="average")
    w_plus = float(ranks[nonzero > 0].sum())
    w_minus = float(ranks[nonzero < 0].sum())
    denom = w_plus + w_minus
    if denom == 0.0:
        return 0.0
    return (w_plus - w_minus) / denom


def run_wilcoxon_tests(per_file_contrib: pd.DataFrame, comparisons: List[Tuple[str, str]]) -> pd.DataFrame:
    rows = []

    for m1, m2 in comparisons:
        x = per_file_contrib[m1].to_numpy(float)
        y = per_file_contrib[m2].to_numpy(float)
        d = x - y
        nonzero_n = int(np.sum(d != 0.0))

        try:
            stat, p_value = wilcoxon(x, y, zero_method="wilcox", alternative="two-sided", mode="auto")
            stat = float(stat)
            p_value = float(p_value)
        except ValueError:
            stat, p_value = float("nan"), float("nan")

        rbc = rank_biserial_from_paired_diff(d)
        rows.append(
            {
                "model_a": m1,
                "model_b": m2,
                "model_a_name": MODELS.get(m1, m1),
                "model_b_name": MODELS.get(m2, m2),
                "n_pairs": int(len(d)),
                "n_nonzero": nonzero_n,
                "wilcoxon_W": stat,
                "p_value": p_value,
                "holm_p": float("nan"),
                "rank_biserial": rbc,
                "median_paired_diff_pct_points": float(np.median(d)),
                "mean_paired_diff_pct_points": float(np.mean(d)),
            }
        )

    out = pd.DataFrame(rows)
    valid = out["p_value"].notna()
    if valid.any():
        adjusted = _holm_adjust(out.loc[valid, "p_value"].tolist())
        out.loc[valid, "holm_p"] = adjusted
    return out


def parse_float_or_nan(v: Any) -> float:
    if v is None:
        return float("nan")
    if isinstance(v, (int, float)):
        return float(v)
    s = str(v).strip()
    if not s or s.upper() in {"N/A", "NA", "NONE"}:
        return float("nan")
    try:
        return float(s)
    except ValueError:
        return float("nan")


def load_syntax_table(model_folder: str) -> pd.DataFrame:
    p = LLM_MIGRATION_SUBDIR / "outputs" / "validation_reports" / model_folder / f"syntax_validation_{model_folder}.csv"
    df = pd.read_csv(p)
    df["syntax_new_error"] = (df["status"].astype(str) == "NEW_ERROR").astype(int)
    df["syntax_migrated_valid"] = df["migrated_valid"].astype(str).str.lower().map({"true": 1, "false": 0})
    return df[["filename", "syntax_new_error", "syntax_migrated_valid"]]


def load_completeness_table(model_folder: str) -> pd.DataFrame:
    p = LLM_MIGRATION_SUBDIR / "outputs" / "validation_reports" / model_folder / f"completeness_summary_{model_folder}.csv"
    df = pd.read_csv(p)
    metrics = [
        "token_preservation",
        "token_sequence_similarity",
        "function_preservation",
        "class_preservation",
        "structural_similarity",
        "body_preservation_avg",
        "signature_stability",
        "trivial_stub_rate",
    ]
    for m in metrics:
        if m in df.columns:
            df[m] = df[m].map(parse_float_or_nan)
    keep = [c for c in ["filename", *metrics] if c in df.columns]
    return df[keep]


def load_loadability_table(model_folder: str) -> pd.DataFrame:
    p = Path(__file__).parent.parent / "tests" / "wordpress" / "results" / f"comparison_{model_folder}.json"
    with open(p, "r", encoding="utf-8") as f:
        payload = json.load(f)

    rows = []
    for item in payload.get("results", []):
        comp = item.get("comparison", {})
        rows.append(
            {
                "filename": item.get("file"),
                "load_regression": 1 if comp.get("status") == "REGRESSION" else 0,
                "load_pass": 1 if bool(comp.get("migrated_success")) else 0,
            }
        )
    return pd.DataFrame(rows)


def spearman_with_bootstrap_ci(
    x: np.ndarray,
    y: np.ndarray,
    n_boot: int,
    alpha: float,
) -> Tuple[float, float, float, float]:
    ok = np.isfinite(x) & np.isfinite(y)
    x = x[ok]
    y = y[ok]
    n = len(x)
    if n < 3:
        return float("nan"), float("nan"), float("nan"), float("nan")

    rho, pval = spearmanr(x, y)
    if not np.isfinite(rho):
        return float("nan"), float("nan"), float("nan"), float("nan")

    boot_vals = []
    for _ in range(n_boot):
        idx = RNG.integers(0, n, size=n)
        bx = x[idx]
        by = y[idx]
        if np.std(bx) == 0.0 or np.std(by) == 0.0:
            continue
        brho, _ = spearmanr(bx, by)
        if np.isfinite(brho):
            boot_vals.append(float(brho))

    if not boot_vals:
        return float(rho), float(pval), float("nan"), float("nan")

    boot_arr = np.array(boot_vals, dtype=float)
    ci_low, ci_high = bootstrap_ci(boot_arr, alpha)

    m = float(boot_arr.size)
    p_left = (float(np.sum(boot_arr <= 0.0)) + 1.0) / (m + 1.0)
    p_right = (float(np.sum(boot_arr >= 0.0)) + 1.0) / (m + 1.0)
    p_boot = min(1.0, 2.0 * min(p_left, p_right))
    return float(rho), float(p_boot), ci_low, ci_high


def run_spearman_analyses(model_folder: str, analyzable_df: pd.DataFrame, n_boot: int, alpha: float) -> pd.DataFrame:
    syntax_df = load_syntax_table(model_folder)
    comp_df = load_completeness_table(model_folder)
    load_df = load_loadability_table(model_folder)

    core = analyzable_df[["filename", "discharge_rate"]].copy()

    merged = core.merge(syntax_df, on="filename", how="left").merge(comp_df, on="filename", how="left")

    rows = []

    metrics = [
        "syntax_new_error",
        "syntax_migrated_valid",
        "token_preservation",
        "token_sequence_similarity",
        "function_preservation",
        "class_preservation",
        "structural_similarity",
        "body_preservation_avg",
        "signature_stability",
        "trivial_stub_rate",
    ]

    for metric in metrics:
        if metric not in merged.columns:
            continue
        x = merged["discharge_rate"].to_numpy(float)
        y = merged[metric].to_numpy(float)
        rho, pval, lo, hi = spearman_with_bootstrap_ci(x, y, n_boot=n_boot, alpha=alpha)
        n = int(np.sum(np.isfinite(x) & np.isfinite(y)))
        rows.append(
            {
                "model": model_folder,
                "model_name": MODELS.get(model_folder, model_folder),
                "metric": metric,
                "scope": "analyzable_only",
                "n": n,
                "spearman_rho": rho,
                "p_value": pval,
                "ci_low": lo,
                "ci_high": hi,
            }
        )

    # 55-file execution slice metrics
    merged_load = core.merge(load_df, on="filename", how="inner")
    for metric in ["load_regression", "load_pass"]:
        if metric not in merged_load.columns:
            continue
        x = merged_load["discharge_rate"].to_numpy(float)
        y = merged_load[metric].to_numpy(float)
        rho, pval, lo, hi = spearman_with_bootstrap_ci(x, y, n_boot=n_boot, alpha=alpha)
        n = int(np.sum(np.isfinite(x) & np.isfinite(y)))
        rows.append(
            {
                "model": model_folder,
                "model_name": MODELS.get(model_folder, model_folder),
                "metric": metric,
                "scope": "55_file_slice_analyzable",
                "n": n,
                "spearman_rho": rho,
                "p_value": pval,
                "ci_low": lo,
                "ci_high": hi,
            }
        )

    return pd.DataFrame(rows)


def add_multiple_testing_columns(df: pd.DataFrame, p_col: str) -> pd.DataFrame:
    out = df.copy()
    out["holm_p"] = float("nan")
    out["fdr_bh_p"] = float("nan")

    valid = out[p_col].notna()
    if valid.any():
        pvals = out.loc[valid, p_col].astype(float).tolist()
        out.loc[valid, "holm_p"] = _holm_adjust(pvals)
        out.loc[valid, "fdr_bh_p"] = _bh_adjust(pvals)
    return out


def run_sensitivity_check(model_folder: str, full_df: pd.DataFrame, analyzable_df: pd.DataFrame) -> Dict[str, Any]:
    weighted_all = float(full_df["weighted_discharge_contrib"].sum())
    if analyzable_df.empty:
        weighted_an = float("nan")
        delta = float("nan")
        rel = float("nan")
    else:
        total_dis = float(analyzable_df["discharged_obligations"].sum())
        total_orig_an = float(analyzable_df["original_obligations"].sum())
        weighted_an = total_dis / total_orig_an * 100.0 if total_orig_an > 0 else float("nan")
        delta = weighted_an - weighted_all
        rel = (delta / weighted_all * 100.0) if weighted_all != 0 else float("nan")

    return {
        "model": model_folder,
        "model_name": ALL_MODELS_WITH_BASELINE.get(model_folder, model_folder),
        "weighted_all_files_pct": weighted_all,
        "weighted_analyzable_only_pct": weighted_an,
        "delta_an_minus_all_pct_points": delta,
        "relative_delta_percent_of_all": rel,
        "n_analyzable": int(len(analyzable_df)),
        "n_total": int(len(full_df)),
    }


def parse_comparisons(raw: str | None) -> List[Tuple[str, str]]:
    if not raw:
        return DEFAULT_COMPARISONS
    pairs = []
    for chunk in raw.split(","):
        chunk = chunk.strip()
        if not chunk:
            continue
        if ":" not in chunk:
            raise ValueError(f"Invalid comparison '{chunk}'. Expected format modelA:modelB")
        a, b = [p.strip() for p in chunk.split(":", 1)]
        if a not in MODELS or b not in MODELS:
            raise ValueError(f"Unknown model in comparison '{chunk}'")
        pairs.append((a, b))
    return pairs


def main() -> None:
    parser = argparse.ArgumentParser(description="Inferential stats for PHP migration evaluation")
    parser.add_argument("--n-boot", type=int, default=10000, help="Bootstrap resamples (default: 10000)")
    parser.add_argument("--alpha", type=float, default=0.05, help="Two-sided alpha for CIs (default: 0.05)")
    parser.add_argument(
        "--comparisons",
        type=str,
        default=None,
        help="Comma-separated modelA:modelB pairs for Wilcoxon tests",
    )
    args = parser.parse_args()

    out_dir = LLM_EVAL_SUBDIR / "stats"
    out_dir.mkdir(parents=True, exist_ok=True)

    comparisons = parse_comparisons(args.comparisons)

    base_df = load_baseline_file_obligations()

    model_panels_full: Dict[str, pd.DataFrame] = {}
    model_panels_an: Dict[str, pd.DataFrame] = {}

    # Include Rector baseline for Table-8 CI and sensitivity outputs
    for folder in ALL_MODELS_WITH_BASELINE:
        full_df, an_df = build_per_file_panel(folder, base_df)
        model_panels_full[folder] = full_df
        model_panels_an[folder] = an_df

    # 1) Bootstrap CIs for main Table-8 metrics
    bootstrap_rows = []
    for folder, full_df in model_panels_full.items():
        an_df = model_panels_an[folder]
        summary = bootstrap_table8_metrics(full_df, an_df, n_boot=args.n_boot, alpha=args.alpha)
        for metric, bs in summary.items():
            bootstrap_rows.append(
                {
                    "model": folder,
                    "model_name": ALL_MODELS_WITH_BASELINE.get(folder, folder),
                    "metric": metric,
                    "estimate": bs.estimate,
                    "ci_low": bs.ci_low,
                    "ci_high": bs.ci_high,
                    "alpha": args.alpha,
                    "n_boot": args.n_boot,
                }
            )

    bootstrap_df = pd.DataFrame(bootstrap_rows)
    bootstrap_df.to_csv(out_dir / "table8_bootstrap_cis.csv", index=False)

    # 2,3,4) Wilcoxon + Holm + effect sizes (LLM models only)
    per_file_matrix = base_df[["benchmark_id", "filename"]].copy()
    for folder in MODELS:
        per_file_matrix[folder] = model_panels_full[folder].sort_values("benchmark_id")["weighted_discharge_contrib"].to_numpy(float)

    per_file_matrix.to_csv(out_dir / "per_file_weighted_discharge_contrib.csv", index=False)

    wilcox_df = run_wilcoxon_tests(per_file_matrix[list(MODELS.keys())], comparisons)
    wilcox_df.to_csv(out_dir / "wilcoxon_paired_weighted_discharge.csv", index=False)

    # 5) Spearman correlations with bootstrap CIs
    spearman_frames = []
    for folder in MODELS:
        spearman_frames.append(
            run_spearman_analyses(folder, model_panels_an[folder], n_boot=max(3000, args.n_boot // 2), alpha=args.alpha)
        )
    spearman_df = pd.concat(spearman_frames, ignore_index=True)
    spearman_df = add_multiple_testing_columns(spearman_df, p_col="p_value")
    spearman_df.to_csv(out_dir / "spearman_discharge_vs_diagnostics.csv", index=False)

    # 6) Sensitivity check
    sensitivity_rows = []
    for folder in ALL_MODELS_WITH_BASELINE:
        sensitivity_rows.append(run_sensitivity_check(folder, model_panels_full[folder], model_panels_an[folder]))
    sensitivity_df = pd.DataFrame(sensitivity_rows)
    sensitivity_df["rank_all_files"] = sensitivity_df["weighted_all_files_pct"].rank(ascending=False, method="min")
    sensitivity_df["rank_analyzable_only"] = sensitivity_df["weighted_analyzable_only_pct"].rank(ascending=False, method="min")
    sensitivity_df["rank_shift"] = sensitivity_df["rank_analyzable_only"] - sensitivity_df["rank_all_files"]
    sensitivity_df = sensitivity_df.sort_values("weighted_all_files_pct", ascending=False)
    sensitivity_df.to_csv(out_dir / "sensitivity_all_vs_analyzable.csv", index=False)

    manifest = {
        "seed": SEED,
        "alpha": args.alpha,
        "n_boot": args.n_boot,
        "outputs": [
            "table8_bootstrap_cis.csv",
            "per_file_weighted_discharge_contrib.csv",
            "wilcoxon_paired_weighted_discharge.csv",
            "spearman_discharge_vs_diagnostics.csv",
            "sensitivity_all_vs_analyzable.csv",
        ],
        "comparisons": [{"model_a": a, "model_b": b} for a, b in comparisons],
    }
    with open(out_dir / "stats_manifest.json", "w", encoding="utf-8") as f:
        json.dump(manifest, f, indent=2)

    print(f"Saved inferential statistics outputs to: {out_dir}")


if __name__ == "__main__":
    main()
