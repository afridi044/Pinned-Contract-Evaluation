"""
Analyze diff counts from migrated files for thesis metrics.
Extracts and compares diff_line_count across different LLMs.

"""

import json
from pathlib import Path
from typing import Dict, Optional

import numpy as np
import pandas as pd
import matplotlib.pyplot as plt


def _extract_file_id(json_path: Path) -> str:
    # Matches your previous convention
    return json_path.stem.replace("_rector", "")


def load_diff_counts_model(model_name: str, base_path: Path) -> Dict[str, Optional[int]]:
    """
    Load diff_line_count for a model from:
      base_path / model_name / "individual_files" / *.json

    Returns: dict[file_id] -> diff_line_count (int) OR None if missing/unreadable.
    """
    model_path = base_path / model_name / "individual_files"
    out: Dict[str, Optional[int]] = {}

    if not model_path.exists():
        print(f"⚠ Warning: Path not found for model '{model_name}': {model_path}")
        return out

    for json_file in sorted(model_path.glob("*.json")):
        file_id = _extract_file_id(json_file)
        try:
            with open(json_file, "r", encoding="utf-8") as f:
                data = json.load(f)

            diff = (
                data.get("rector_analysis", {})
                    .get("diff_line_count", None)
            )

            # Normalize to int or None
            if diff is None:
                out[file_id] = None
            else:
                try:
                    out[file_id] = int(diff)
                except Exception:
                    out[file_id] = None

        except Exception as e:
            print(f"⚠ Error reading {json_file}: {e}")
            out[file_id] = None

    return out


def load_diff_counts_baseline(baseline_individual_files_dir: Path) -> Dict[str, Optional[int]]:
    """
    Load baseline diff_line_count from a directory that directly contains *.json.
    Returns dict[file_id] -> diff_line_count (int) OR None if missing/unreadable.
    """
    out: Dict[str, Optional[int]] = {}

    if not baseline_individual_files_dir.exists():
        print(f"⚠ Warning: Baseline path not found: {baseline_individual_files_dir}")
        return out

    for json_file in sorted(baseline_individual_files_dir.glob("*.json")):
        file_id = _extract_file_id(json_file)
        try:
            with open(json_file, "r", encoding="utf-8") as f:
                data = json.load(f)

            diff = data.get("rector_analysis", {}).get("diff_line_count", None)

            if diff is None:
                out[file_id] = None
            else:
                try:
                    out[file_id] = int(diff)
                except Exception:
                    out[file_id] = None

        except Exception as e:
            print(f"⚠ Error reading {json_file}: {e}")
            out[file_id] = None

    return out


def calculate_statistics(diff_counts: Dict[str, Optional[int]]) -> Dict[str, float]:
    """
    Computes stats over valid numeric values only.
    Also returns missing_count so you can report coverage.
    """
    vals = [v for v in diff_counts.values() if isinstance(v, int)]
    missing = sum(1 for v in diff_counts.values() if v is None)

    if not vals:
        return {
            "total_files": float(len(diff_counts)),
            "missing_count": float(missing),
        }

    arr = np.array(vals, dtype=float)

    # For thesis: these are usually useful
    total_files = len(diff_counts)  # includes missing
    valid_files = len(vals)

    return {
        "total_files": float(total_files),
        "valid_files": float(valid_files),
        "missing_count": float(missing),

        "total_diff_lines": float(arr.sum()),
        "mean": float(arr.mean()),
        "median": float(np.median(arr)),
        "p90": float(np.percentile(arr, 90)),
        "p95": float(np.percentile(arr, 95)),
        "min": float(arr.min()),
        "max": float(arr.max()),

        "files_with_changes": float(np.sum(arr > 0)),
        "files_no_changes": float(np.sum(arr == 0)),
        "pct_with_changes": float(np.sum(arr > 0) / valid_files * 100.0),

        # “Non-clean mean” style analogue: mean among files with >0 diffs
        "nonzero_mean": float(arr[arr > 0].mean()) if np.any(arr > 0) else 0.0,

        # concentration: share of total diffs in top-5 files
        "top5_share_pct": float((np.sort(arr)[-5:].sum() / arr.sum() * 100.0) if arr.sum() > 0 else 0.0),
    }


def make_summary_df(all_diff_counts: Dict[str, Dict[str, Optional[int]]]) -> pd.DataFrame:
    rows = []
    for model, diffs in all_diff_counts.items():
        stats = calculate_statistics(diffs)
        stats["model"] = model
        rows.append(stats)

    df = pd.DataFrame(rows)

    # nicer column order for paper/export
    cols = [
        "model",
        "total_files", "valid_files", "missing_count",
        "files_with_changes", "pct_with_changes", "files_no_changes",
        "total_diff_lines", "mean", "median", "p90", "p95",
        "min", "max", "nonzero_mean", "top5_share_pct",
    ]
    cols = [c for c in cols if c in df.columns]
    return df[cols]


def make_detailed_df(all_diff_counts: Dict[str, Dict[str, Optional[int]]], model_order: list[str]) -> pd.DataFrame:
    file_ids = sorted(set().union(*[set(d.keys()) for d in all_diff_counts.values()]))

    rows = []
    for fid in file_ids:
        row = {"file_id": fid}
        for m in model_order:
            v = all_diff_counts.get(m, {}).get(fid, None)
            row[m] = v if isinstance(v, int) else np.nan
        rows.append(row)

    return pd.DataFrame(rows)


def generate_plots(summary_df: pd.DataFrame, detailed_df: pd.DataFrame, model_order: list[str], outdir: Path) -> None:
    outdir.mkdir(exist_ok=True)

    # --- Plot 1: total diff lines
    df1 = summary_df.copy()
    df1 = df1.sort_values("total_diff_lines", ascending=False)

    plt.figure()
    plt.bar(df1["model"], df1["total_diff_lines"])
    plt.xticks(rotation=45, ha="right")
    plt.ylabel("Total diff lines")
    plt.title("Total diff lines by model")
    plt.tight_layout()
    plt.savefig(outdir / "total_diff_lines_by_model.png", dpi=300)
    plt.close()

    # --- Plot 2: mean diff lines per file (valid-only)
    df2 = summary_df.copy()
    df2 = df2.sort_values("mean", ascending=False)

    plt.figure()
    plt.bar(df2["model"], df2["mean"])
    plt.xticks(rotation=45, ha="right")
    plt.ylabel("Mean diff lines (valid files)")
    plt.title("Mean diff lines per file by model")
    plt.tight_layout()
    plt.savefig(outdir / "mean_diff_lines_by_model.png", dpi=300)
    plt.close()

    # --- Plot 3: boxplot distribution (valid-only)
    plt.figure()
    data = []
    labels = []
    for m in model_order:
        if m not in detailed_df.columns:
            continue
        vals = detailed_df[m].dropna().values
        data.append(vals)
        labels.append(m)

    plt.boxplot(data, labels=labels, showfliers=False)
    plt.xticks(rotation=45, ha="right")
    plt.ylabel("Diff lines")
    plt.title("Distribution of diff line counts by model (valid-only)")
    plt.tight_layout()
    plt.savefig(outdir / "diff_lines_boxplot.png", dpi=300)
    plt.close()

    print(f"[OK] Plots saved to: {outdir}")


def compare_with_baseline(all_diff_counts: Dict[str, Dict[str, Optional[int]]],
                          baseline_key: str,
                          model_order: list[str],
                          outdir: Path) -> None:
    """
    Calculate diff closure:
    - Higher percentage = more Rector-required diffs closed by LLM migration
    - diff_closure_pct = ((baseline - model) / baseline) * 100
    """
    baseline = all_diff_counts.get(baseline_key, {})
    if not baseline:
        print("⚠ Warning: baseline not found, skipping baseline comparison.")
        return

    all_file_ids = sorted(set().union(*[set(d.keys()) for d in all_diff_counts.values()]))

    # Build wide-format detailed DataFrame (one row per file, columns for each model)
    detail_rows = []
    for fid in all_file_ids:
        row = {"file_id": fid}
        for m in model_order:
            if m == baseline_key:
                continue
            diffs = all_diff_counts.get(m, {})
            b = baseline.get(fid, None)
            x = diffs.get(fid, None)

            if isinstance(b, int) and isinstance(x, int):
                # Calculate diff closure percentage
                if b > 0:
                    diff_closure_pct = ((b - x) / b) * 100
                else:
                    diff_closure_pct = 100.0 if x == 0 else 0.0
                row[m] = round(diff_closure_pct, 2)
            else:
                row[m] = np.nan
        detail_rows.append(row)

    detailed_df = pd.DataFrame(detail_rows)
    detailed_df.to_csv(outdir / "diff_closure_details.csv", index=False)

    # Build summary statistics for each model
    summary_rows = []
    for m in model_order:
        if m == baseline_key:
            continue
        diffs = all_diff_counts.get(m, {})
        
        baseline_total = 0
        model_total = 0
        valid_pairs = 0
        sum_closure_pct = 0.0
        
        for fid in all_file_ids:
            b = baseline.get(fid, None)
            x = diffs.get(fid, None)
            
            if isinstance(b, int) and isinstance(x, int):
                baseline_total += b
                model_total += x
                valid_pairs += 1
                
                # Per-file closure percentage
                if b > 0:
                    sum_closure_pct += ((b - x) / b) * 100
                else:
                    sum_closure_pct += 100.0 if x == 0 else 0.0

        
        diffs_closed = baseline_total - model_total
        
        # Weighted diff closure (based on total diffs)
        if baseline_total > 0:
            weighted_closure = (diffs_closed / baseline_total) * 100
        else:
            weighted_closure = 100.0
        
        # Mean per-file diff closure
        mean_file_closure = sum_closure_pct / valid_pairs if valid_pairs > 0 else 0.0
        
        summary_rows.append({
               "model": m,
               "baseline_total_diffs": baseline_total,
               "remaining_diffs": model_total,
               "diffs_closed": diffs_closed,
               "weighted_diff_closure_pct": round(weighted_closure, 2),
               "mean_file_diff_closure_pct": round(mean_file_closure, 2),
        })


    summary = pd.DataFrame(summary_rows)
    summary = summary.sort_values("weighted_diff_closure_pct", ascending=False)


    print("\n" + "=" * 80)
    print("DIFF CLOSURE (Higher % = Better)")
    print("=" * 80)
    print(summary.to_string(index=False))

    summary.to_csv(outdir / "diff_closure_summary.csv", index=False)

    print(f"\n[OK] Diff closure analysis saved to: {outdir / 'diff_closure_summary.csv'}")


def main():
    # Set fixed seed for reproducibility
    import random
    SEED = 42
    np.random.seed(SEED)
    random.seed(SEED)
    pd.options.mode.copy_on_write = False  # Avoid pandas warning
    
    # Import config for dynamic paths
    import sys
    sys.path.insert(0, str(Path(__file__).parent.parent))
    from config import LLM_MIGRATION_SUBDIR, DATASET_SUBDIR, LLM_EVAL_SUBDIR
    
    # --- paths (adjust if your repo layout differs)
    code_analysis_path = LLM_MIGRATION_SUBDIR / "outputs" / "code_analysis"
    
    # Original benchmark (unmigrated PHP 5.x files analyzed by Rector)
    original_benchmark_dir = DATASET_SUBDIR / "rector_reports_selected_100_files" / "individual_files"

    outdir = LLM_EVAL_SUBDIR / "diff_count_analysis"
    outdir.mkdir(parents=True, exist_ok=True)

    # Directory names must match your folders under code_analysis_path
    model_order = [
        "Original_Benchmark",  # Unmigrated PHP 5.x code baseline (total work needed)
        "Rector_Baseline",     # Rector-migrated files (how well Rector alone performs)
        "claude_sonnet_4_20250514",
        "gemini_2_5_flash",
        "gemini_2_5_pro",
        "gpt_5_codex",
        "meta_llama_llama_3_3_70b_instruct",
    ]

    print("Loading diff counts...")
    all_diff_counts: Dict[str, Dict[str, Optional[int]]] = {}

    for m in model_order:
        if m == "Original_Benchmark":
            # Original unmigrated benchmark files (what Rector would change)
            all_diff_counts[m] = load_diff_counts_baseline(original_benchmark_dir)
        elif m == "Rector_Baseline":
            # Rector-migrated files analyzed by Rector (remaining changes after Rector)
            all_diff_counts[m] = load_diff_counts_model(m, code_analysis_path)
        else:
            # LLM-migrated files analyzed by Rector (remaining changes after LLM)
            all_diff_counts[m] = load_diff_counts_model(m, code_analysis_path)
        print(f"  {m}: {len(all_diff_counts[m])} files")

    # Summary table
    summary_df = make_summary_df(all_diff_counts)
    summary_df.to_csv(outdir / "diff_count_summary.csv", index=False)

    print("\n" + "=" * 80)
    print("DIFF COUNT SUMMARY")
    print("=" * 80)
    print(summary_df.to_string(index=False))

    # Detailed table
    detailed_df = make_detailed_df(all_diff_counts, model_order)
    detailed_df.to_csv(outdir / "diff_count_detailed.csv", index=False)
    print(f"\n[OK] Detailed comparison saved to: {outdir / 'diff_count_detailed.csv'}")

    # Diff closure analysis (most important metric)
    compare_with_baseline(all_diff_counts, baseline_key="Original_Benchmark", model_order=model_order, outdir=outdir)

    print(f"\n[OK] Done. Outputs in: {outdir.resolve()}")


if __name__ == "__main__":
    main()
