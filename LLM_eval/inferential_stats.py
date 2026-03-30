
"""Inferential statistics for PCE results.

Outputs are written to LLM_eval/stats/ and include only the statistics

1) 95% bootstrap CIs for the primary Table-8 metric:
   weighted discharge rate across all 100 files
2) Paired Wilcoxon signed-rank tests (with Holm correction)
   on per-file weighted discharge contributions
3) Sensitivity check for all-files vs analyzable-only scoring
"""

from __future__ import annotations

import argparse
import json
import re
from dataclasses import dataclass
from pathlib import Path
from typing import Any, Dict, List, Tuple

import numpy as np
import pandas as pd
from scipy.stats import wilcoxon

import sys

sys.path.insert(0, str(Path(__file__).parent.parent))
from config import LLM_EVAL_SUBDIR, SELECTED_100_FILES_DIR  # noqa: E402

SEED = 42
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

# Keep only the comparisons that help the paper story:
# - Pro vs everyone
# - Codex vs the middle tier / weakest model
# - Flash vs Claude to check whether that middle pair is really separable
DEFAULT_COMPARISONS = [
    ("gemini_2_5_pro", "gpt_5_codex"),
    ("gemini_2_5_pro", "gemini_2_5_flash"),
    ("gemini_2_5_pro", "claude_sonnet_4_20250514"),
    ("gemini_2_5_pro", "meta_llama_llama_3_3_70b_instruct"),
    ("gpt_5_codex", "gemini_2_5_flash"),
    ("gpt_5_codex", "claude_sonnet_4_20250514"),
    ("gpt_5_codex", "meta_llama_llama_3_3_70b_instruct"),
    ("gemini_2_5_flash", "claude_sonnet_4_20250514"),
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

    if df.duplicated(subset=["original_file_id", "filename"]).any():
        dups = df[df.duplicated(subset=["original_file_id", "filename"], keep=False)]
        raise ValueError(f"Duplicate baseline file rows detected:\n{dups}")

    df = df.sort_values(
        by=["filename", "original_file_id"],
        key=lambda s: s.map(
            lambda v: _benchmark_order_key(v, 10**9)[0] if s.name == "filename" else int(v)
        ),
    ).reset_index(drop=True)
    df["benchmark_id"] = np.arange(1, len(df) + 1, dtype=int)
    df = df[["benchmark_id", "original_file_id", "filename", "original_obligations"]]

    if len(df) != 100:
        raise ValueError(f"Expected 100 benchmark files, found {len(df)}")

    if (df["original_obligations"] <= 0).any():
        raise ValueError(
            "Found baseline files with zero Rector\\Php* obligations; "
            "expected all selected files to be obligated."
        )

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

    if df.duplicated(subset=["original_file_id", "filename"]).any():
        dups = df[df.duplicated(subset=["original_file_id", "filename"], keep=False)]
        raise ValueError(f"Duplicate evaluation rows detected for {model_folder}:\n{dups}")

    return df


def build_per_file_panel(model_folder: str, base_df: pd.DataFrame) -> Tuple[pd.DataFrame, pd.DataFrame]:
    eval_df = load_eval_results(model_folder)

    analyzable = eval_df[
        [
            "original_file_id",
            "filename",
            "original_obligations",
            "discharged_obligations",
            "introduced_obligations",
            "remaining_obligations",
            "discharge_rate",
        ]
    ].copy()
    analyzable["analyzable"] = True

    full = base_df.merge(
        analyzable[
            [
                "original_file_id",
                "filename",
                "discharged_obligations",
                "introduced_obligations",
                "remaining_obligations",
                "discharge_rate",
                "analyzable",
            ]
        ],
        on=["original_file_id", "filename"],
        how="left",
        validate="one_to_one",
    )

    for col in [
        "discharged_obligations",
        "introduced_obligations",
        "remaining_obligations",
        "discharge_rate",
    ]:
        full[col] = _to_float(full[col])

    full["analyzable"] = full["analyzable"].fillna(False)
    full["discharged_obligations"] = full["discharged_obligations"].fillna(0.0)

    total_obligations_all = float(base_df["original_obligations"].sum())
    full["weighted_discharge_contrib"] = (
        full["discharged_obligations"] / total_obligations_all * 100.0
    )

    return full, analyzable


def bootstrap_primary_metric(
    full_df: pd.DataFrame,
    n_boot: int,
    alpha: float,
    seed_offset: int,
) -> BootstrapSummary:
    rng = np.random.default_rng(SEED + seed_offset)

    n_all = len(full_df)
    idx = rng.integers(0, n_all, size=(n_boot, n_all))

    discharged = full_df["discharged_obligations"].to_numpy(float)
    obligations = full_df["original_obligations"].to_numpy(float)

    discharged_sum_boot = discharged[idx].sum(axis=1)
    obligations_sum_boot = obligations[idx].sum(axis=1)

    weighted_boot = np.divide(
        discharged_sum_boot,
        obligations_sum_boot,
        out=np.full_like(discharged_sum_boot, np.nan, dtype=float),
        where=obligations_sum_boot > 0,
    ) * 100.0

    estimate = float(full_df["weighted_discharge_contrib"].sum())
    ci_low, ci_high = bootstrap_ci(weighted_boot, alpha)

    return BootstrapSummary(estimate=estimate, ci_low=ci_low, ci_high=ci_high)


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


def run_wilcoxon_tests(
    per_file_contrib: pd.DataFrame,
    comparisons: List[Tuple[str, str]],
) -> pd.DataFrame:
    rows = []

    for m1, m2 in comparisons:
        x = per_file_contrib[m1].to_numpy(float)
        y = per_file_contrib[m2].to_numpy(float)
        d = x - y
        nonzero_n = int(np.sum(d != 0.0))

        try:
            stat, p_value = wilcoxon(
                x,
                y,
                zero_method="wilcox",
                alternative="two-sided",
                mode="auto",
            )
            stat = float(stat)
            p_value = float(p_value)
        except ValueError:
            stat, p_value = float("nan"), float("nan")

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
                "median_paired_diff_pct_points": float(np.median(d)),
                "mean_paired_diff_pct_points": float(np.mean(d)),
            }
        )

    out = pd.DataFrame(rows)
    valid = out["p_value"].notna()
    if valid.any():
        out.loc[valid, "holm_p"] = _holm_adjust(out.loc[valid, "p_value"].tolist())
    return out


def run_sensitivity_check(
    model_folder: str,
    full_df: pd.DataFrame,
    analyzable_df: pd.DataFrame,
) -> Dict[str, Any]:
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
    parser = argparse.ArgumentParser(description="Minimal inferential stats for PHP migration evaluation")
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

    # Include Rector baseline for CI + sensitivity outputs
    for folder in ALL_MODELS_WITH_BASELINE:
        full_df, an_df = build_per_file_panel(folder, base_df)
        model_panels_full[folder] = full_df
        model_panels_an[folder] = an_df

    # 1) Bootstrap CI for the primary Table-8 metric only
    bootstrap_rows = []
    for i, folder in enumerate(ALL_MODELS_WITH_BASELINE):
        full_df = model_panels_full[folder]
        bs = bootstrap_primary_metric(
            full_df=full_df,
            n_boot=args.n_boot,
            alpha=args.alpha,
            seed_offset=i + 1,
        )
        bootstrap_rows.append(
            {
                "model": folder,
                "model_name": ALL_MODELS_WITH_BASELINE.get(folder, folder),
                "metric": "weighted_discharge_rate_all_files",
                "estimate": bs.estimate,
                "ci_low": bs.ci_low,
                "ci_high": bs.ci_high,
                "alpha": args.alpha,
                "n_boot": args.n_boot,
            }
        )

    bootstrap_df = pd.DataFrame(bootstrap_rows).sort_values("estimate", ascending=False)
    bootstrap_df.to_csv(out_dir / "table8_bootstrap_cis.csv", index=False)

    # 2) Paired Wilcoxon + Holm (LLM models only)
    per_file_matrix = base_df[["benchmark_id", "filename"]].copy()
    for folder in MODELS:
        ordered = model_panels_full[folder].sort_values("benchmark_id")
        per_file_matrix[folder] = ordered["weighted_discharge_contrib"].to_numpy(float)

    per_file_matrix.to_csv(out_dir / "per_file_weighted_discharge_contrib.csv", index=False)

    wilcox_df = run_wilcoxon_tests(per_file_matrix[list(MODELS.keys())], comparisons)
    wilcox_df.to_csv(out_dir / "wilcoxon_paired_weighted_discharge.csv", index=False)

    # 3) Sensitivity check: all-files vs analyzable-only
    sensitivity_rows = []
    for folder in ALL_MODELS_WITH_BASELINE:
        sensitivity_rows.append(
            run_sensitivity_check(
                folder,
                model_panels_full[folder],
                model_panels_an[folder],
            )
        )

    sensitivity_df = pd.DataFrame(sensitivity_rows)
    sensitivity_df["rank_all_files"] = sensitivity_df["weighted_all_files_pct"].rank(
        ascending=False, method="min"
    )
    sensitivity_df["rank_analyzable_only"] = sensitivity_df["weighted_analyzable_only_pct"].rank(
        ascending=False, method="min"
    )
    sensitivity_df["rank_shift"] = (
        sensitivity_df["rank_analyzable_only"] - sensitivity_df["rank_all_files"]
    )
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
            "sensitivity_all_vs_analyzable.csv",
        ],
        "comparisons": [{"model_a": a, "model_b": b} for a, b in comparisons],
        "notes": [
            "Only the primary all-files weighted discharge metric is bootstrapped.",
            "Spearman analyses and effect sizes were intentionally removed to keep the paper-facing statistics minimal.",
        ],
    }

    with open(out_dir / "stats_manifest.json", "w", encoding="utf-8") as f:
        json.dump(manifest, f, indent=2)

    print(f"Saved minimal inferential statistics outputs to: {out_dir}")


if __name__ == "__main__":
    main()