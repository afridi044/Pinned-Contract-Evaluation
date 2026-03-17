#!/usr/bin/env python3
"""
Summarize PHPCompatibility results from PHPCS JSON reports.
Adds:
  - Issue concentration (Top-5 share, non-clean mean, P95)
  - Root-cause breakdown (dominant PHPCS sniff/source + share)
  - Top-5 worst files with (errors/warnings) and full paths
  - Robust 'Found' labeling by keeping last-2 source segments
"""

from __future__ import annotations

import json
import sys
from pathlib import Path
from typing import Dict, Any, List, Tuple
from collections import Counter

import pandas as pd
import numpy as np

# Add parent directory to path for config import
sys.path.insert(0, str(Path(__file__).parent.parent))
from config import PHPCOMPATIBILITY_RESULTS_DIR


# ────────────────
# JSON loading (robust)
# ────────────────
def _load_json_loose(path: Path) -> Dict[str, Any]:
    """
    PHPCS should output JSON, but wrappers sometimes prepend noise.
    Try strict JSON first, then fall back to extracting the first {...} block.
    """
    raw = path.read_text(encoding="utf-8", errors="replace").strip()
    try:
        return json.loads(raw)
    except json.JSONDecodeError:
        start = raw.find("{")
        end = raw.rfind("}")
        if start != -1 and end != -1 and end > start:
            return json.loads(raw[start : end + 1])
        raise


def _find_case_insensitive(results_dir: Path, filename: str) -> Path | None:
    """Find file in directory ignoring case differences (useful across OSes)."""
    target = filename.lower()
    for p in results_dir.iterdir():
        if p.is_file() and p.name.lower() == target:
            return p
    return None


# -----------------------------
# Source shortening + parse detection
# -----------------------------
def short_source(src: str) -> str:
    """
    Turn:
      PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext
    into:
      ForbiddenThisUseContexts.OutsideObjectContext

    Turn:
      PHPCompatibility.Syntax.RemovedCurlyBraceArrayAccess.Found
    into:
      RemovedCurlyBraceArrayAccess.Found

    This avoids collapsing many different rules into 'Found'/'Changed' etc.
    """
    if not isinstance(src, str) or not src.strip():
        return "Unknown"

    src = src.strip()
    src = src.replace("PHPCompatibility.", "")
    parts = src.split(".")
    if len(parts) >= 2:
        return f"{parts[-2]}.{parts[-1]}"
    return parts[-1]


def is_parse_error_message(msg_text: str) -> bool:
    """
    Conservative parse/syntax error detection.
    NOTE: Do NOT treat every PHPCS ERROR as "parse error".
    """
    if not isinstance(msg_text, str) or not msg_text:
        return False
    t = msg_text.lower()
    markers = (
        "parse error",
        "syntax error",
        "unexpected token",
        "unexpected t_",
        "unexpected end of",
        "unexpected eof",
        "fatal error: syntax",
    )
    return any(m in t for m in markers)


# -----------------------------
# Summarization
# -----------------------------
def summarize_phpcs(json_path: str, top_k_files: int = 5, top_k_sources: int = 5) -> Dict[str, Any]:
    """Extract summary metrics + concentration + root-cause breakdown."""
    path = Path(json_path)
    data = _load_json_loose(path)

    files = data.get("files", {}) or {}
    n_files = len(files)

    clean_files = 0
    total_errors = 0
    total_warnings = 0
    parse_error_files = 0

    # Per-file totals: (total, errors, warnings, filepath)
    per_file: List[Tuple[int, int, int, str]] = []

    # Source breakdown by messages
    source_counts: Counter[str] = Counter()

    for filepath, info in files.items():
        errors = int(info.get("errors", 0) or 0)
        warnings = int(info.get("warnings", 0) or 0)
        total = errors + warnings

        total_errors += errors
        total_warnings += warnings
        per_file.append((total, errors, warnings, filepath))

        if total == 0:
            clean_files += 1

        # Parse error detection: once per file
        has_parse = False
        for msg in info.get("messages", []) or []:
            src = short_source(msg.get("source", ""))
            source_counts[src] += 1

            if not has_parse and is_parse_error_message(msg.get("message", "")):
                has_parse = True

        if has_parse:
            parse_error_files += 1

    total_issues = total_errors + total_warnings
    clean_pct = (clean_files / n_files * 100.0) if n_files else 0.0

    issue_counts = [t for (t, _, _, _) in per_file]
    non_clean_issues = [t for t in issue_counts if t > 0]

    median_issues = float(np.median(issue_counts)) if issue_counts else 0.0
    p90_issues = float(np.percentile(issue_counts, 90)) if issue_counts else 0.0
    p95_issues = float(np.percentile(issue_counts, 95)) if issue_counts else 0.0
    non_clean_mean = float(np.mean(non_clean_issues)) if non_clean_issues else 0.0

    # Top-k worst files
    per_file_sorted = sorted(per_file, key=lambda x: x[0], reverse=True)
    top_files = per_file_sorted[:top_k_files]
    top_files_total = sum(t for (t, _, _, _) in top_files)
    top_files_share = (top_files_total / total_issues * 100.0) if total_issues else 0.0

    # Dominant sniff/source
    dominant_source = "—"
    dominant_count = 0
    if source_counts:
        dominant_source, dominant_count = source_counts.most_common(1)[0]
    dominant_share = (dominant_count / total_issues * 100.0) if total_issues else 0.0

    top_sources = source_counts.most_common(top_k_sources)

    return {
        "total_files": n_files,
        "clean_files": clean_files,
        "clean_pct": clean_pct,
        "errors": total_errors,
        "warnings": total_warnings,
        "total_issues": total_issues,
        "parse_error_files": parse_error_files,
        # distribution
        "median_issues_per_file": median_issues,
        "p90_issues_per_file": p90_issues,
        "p95_issues_per_file": p95_issues,
        "non_clean_mean_issues": non_clean_mean,
        # concentration
        "top_k_files": top_files,
        "top_k_issue_share_pct": top_files_share,
        # root cause
        "dominant_source": dominant_source,
        "dominant_source_count": dominant_count,
        "dominant_source_share_pct": dominant_share,
        "top_sources": top_sources,
    }


def resolve_report(results_dir: Path, filename: str) -> Path | None:
    """Resolve filename case-insensitively."""
    p = results_dir / filename
    if p.exists():
        return p
    return _find_case_insensitive(results_dir, filename)


def create_comparison_table(results_dir: Path) -> pd.DataFrame:
    """Create comparison table for all models."""

    model_to_file_candidates = {
        "Original Benchmark": ["phpcs_original_benchmark.json"],
        "Claude Sonnet 4": ["phpcs_claude_sonnet_4.json"],
        "Gemini 2.5 Flash": ["phpcs_gemini_2_5_flash.json"],
        "Gemini 2.5 Pro": ["phpcs_gemini_2_5_pro.json"],
        "GPT-5 Codex": ["phpcs_gpt_5_codex.json"],
        "LLaMA 3.3 70B": ["phpcs_llama_3_3_70b.json"],
        # support both historical file names, but emit one model row.
        "Rector Baseline": ["phpcs_rector_baseline.json", "phpcs_Rector_baseline.json"],
    }

    rows = []
    original_issues = None

    for model_name, candidates in model_to_file_candidates.items():
        json_path = None
        for filename in candidates:
            json_path = resolve_report(results_dir, filename)
            if json_path is not None:
                break
        if json_path is None:
            continue

        metrics = summarize_phpcs(str(json_path))

        if model_name == "Original Benchmark":
            original_issues = metrics["total_issues"]

        reduction_pct = "—"
        if original_issues is not None and model_name != "Original Benchmark" and original_issues > 0:
            reduction_pct = f"{((original_issues - metrics['total_issues']) / original_issues) * 100:.1f}%"

        rows.append({
            "Model": model_name,
            "Total Files": metrics["total_files"],
            "Total Issues": metrics["total_issues"],
            "Errors": metrics["errors"],
            "Warnings": metrics["warnings"],
            "Clean Files": metrics["clean_files"],
            "Clean %": f"{metrics['clean_pct']:.1f}%",
            "Issue Reduction %": reduction_pct,
            "Non-Clean Mean": f"{metrics['non_clean_mean_issues']:.1f}",
            "Median": f"{metrics['median_issues_per_file']:.0f}",
            "P95": f"{metrics['p95_issues_per_file']:.0f}",
            "Top-5 Share %": f"{metrics['top_k_issue_share_pct']:.1f}%",
            "Dominant Sniff": metrics["dominant_source"],
            "Dominant Share %": f"{metrics['dominant_source_share_pct']:.1f}%",
            "Parse-Error Files": metrics["parse_error_files"],
        })

    return pd.DataFrame(rows)


def print_issue_distribution_analysis(results_dir: Path, top_sources_to_print: int = 5):
    """Print detailed concentration + root-cause analysis for each model."""

    model_to_file_candidates = {
        "Original Benchmark": ["phpcs_original_benchmark.json"],
        "Claude Sonnet 4": ["phpcs_claude_sonnet_4.json"],
        "Gemini 2.5 Flash": ["phpcs_gemini_2_5_flash.json"],
        "Gemini 2.5 Pro": ["phpcs_gemini_2_5_pro.json"],
        "GPT-5 Codex": ["phpcs_gpt_5_codex.json"],
        "LLaMA 3.3 70B": ["phpcs_llama_3_3_70b.json"],
        "Rector Baseline": ["phpcs_rector_baseline.json", "phpcs_Rector_baseline.json"],
    }

    print("\n" + "=" * 80)
    print("Issue Concentration & Root-Cause Analysis")
    print("=" * 80)

    for model_name, candidates in model_to_file_candidates.items():
        json_path = None
        for fname in candidates:
            json_path = resolve_report(results_dir, fname)
            if json_path is not None:
                break
        if json_path is None:
            continue

        metrics = summarize_phpcs(str(json_path), top_k_files=5, top_k_sources=top_sources_to_print)

        print(f"\n{model_name}:")
        print(f"  Clean Files: {metrics['clean_files']}/{metrics['total_files']} ({metrics['clean_pct']:.1f}%)")
        print(f"  Total Issues: {metrics['total_issues']} (E:{metrics['errors']}, W:{metrics['warnings']})")
        print(f"  Parse-Error Files: {metrics['parse_error_files']}")

        print("\n  Issue Distribution:")
        print(f"    Median issues/file: {metrics['median_issues_per_file']:.0f}")
        print(f"    90th percentile: {metrics['p90_issues_per_file']:.0f}")
        print(f"    95th percentile: {metrics['p95_issues_per_file']:.0f}")
        print(f"    Non-clean mean: {metrics['non_clean_mean_issues']:.1f} issues/file")

        print("\n  Issue Concentration:")
        top_files = metrics["top_k_files"]
        top_total = sum(t for (t, _, _, _) in top_files)
        print(f"    Top-5 total: {top_total} issues ({metrics['top_k_issue_share_pct']:.1f}% of all issues)")
        print("    Top-5 worst files:")
        for (t, e, w, fp) in top_files:
            if t == 0:
                continue
            print(f"      - {t:4d} (E{e:3d},W{w:2d}) {fp}")

        print("\n  Root Cause (PHPCS source/sniff):")
        print(f"    Dominant: {metrics['dominant_source']} "
              f"({metrics['dominant_source_count']}/{metrics['total_issues']} = {metrics['dominant_source_share_pct']:.1f}%)")
        print("    Top sources:")
        for src, cnt in metrics["top_sources"]:
            share = (cnt / metrics["total_issues"] * 100.0) if metrics["total_issues"] else 0.0
            print(f"      - {cnt:4d} ({share:5.1f}%) {src}")

        # Divergence alert
        if metrics["clean_pct"] > 80 and metrics["total_issues"] > 100:
            non_clean_files = metrics["total_files"] - metrics["clean_files"]
            print("\n  ⚠ Divergence Alert:")
            print(f"    High clean rate ({metrics['clean_pct']:.1f}%) but issues remain large ({metrics['total_issues']})")
            print(f"    → Issues concentrated in {non_clean_files} files")
            print(f"    → Top-5 files contain {metrics['top_k_issue_share_pct']:.1f}% of all issues")


def print_summary_report(results_dir: Path):
    """Print summary table + save CSV + print detailed analysis."""

    print("\n" + "=" * 80)
    print("PHPCompatibility Cross-Oracle Validation Summary")
    print("=" * 80)
    print()

    df = create_comparison_table(results_dir)
    if df.empty:
        print("No results found!")
        return

    print(df.to_string(index=False))

    csv_path = results_dir / "phpcompatibility_comparison.csv"
    df.to_csv(csv_path, index=False)
    print(f"\n✓ Results saved to: {csv_path}")

    print_issue_distribution_analysis(results_dir, top_sources_to_print=5)


def main():
    """
    Run:
      python summarize_phpcs.py
    Expects:
      ./phpcompatibility_results/phpcs_*.json
    """
    results_dir = PHPCOMPATIBILITY_RESULTS_DIR
    if not results_dir.exists():
        print(f"Results directory not found: {results_dir}")
        print("Run run_phpcompatibility.py first!")
        return

    print_summary_report(results_dir)


if __name__ == "__main__":
    main()
