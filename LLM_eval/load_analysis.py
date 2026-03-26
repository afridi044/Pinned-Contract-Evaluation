"""Loadability analysis for robustness diagnostics."""

import json
import pandas as pd
from pathlib import Path
from collections import defaultdict

TESTS_RESULTS_DIR = Path(__file__).parent.parent / "tests" / "wordpress" / "results"
EVAL_DIR = Path(__file__).parent

MODELS = [
    ("claude_sonnet_4_20250514", "Claude Sonnet 4"),
    ("gemini_2_5_flash", "Gemini 2.5 Flash"),
    ("gemini_2_5_pro", "Gemini 2.5 Pro"),
    ("gpt_5_codex", "GPT-5 Codex"),
    ("meta_llama_llama_3_3_70b_instruct", "LLaMA 3.3 70B"),
    ("Rector_Baseline", "Rector Baseline"),
]


def load_data():
    """Load loadability test and PCE obligation discharge data."""
    results = []
    
    for folder, name in MODELS:
        # Loadability test results
        lookup = "rector_baseline" if folder == "Rector_Baseline" else folder
        loadability_path = TESTS_RESULTS_DIR / f"comparison_{lookup}.json"
        
        # Obligation discharge metrics - files are in wordpress subdirectory
        rector_path = EVAL_DIR / "wordpress" / folder / "evaluation_results.csv"
        
        if not loadability_path.exists():
            print(f"Warning: {loadability_path} not found, skipping {name}")
            continue
            
        with open(loadability_path, 'r', encoding='utf-8') as f:
            loadability = json.load(f)
        
        discharge_rates = {}
        if rector_path.exists():
            df = pd.read_csv(rector_path)
            discharge_rates = {row['filename']: row['discharge_rate'] for _, row in df.iterrows()}
        
        # Get avg discharge rate for 55 files
        avg_discharge = sum(discharge_rates.get(r['file'], 0) for r in loadability['results']) / 55
        
        both_fail = loadability.get('both_fail', 0)
        results.append({
            'Model': name,
            'Discharge_%': round(avg_discharge, 1),
            'Pass': loadability['both_pass'] + loadability['improved'],
            'Pass_%': round((loadability['both_pass'] + loadability['improved']) / 55 * 100, 1),
            'Regress': loadability['regressions'],
            'Regress_%': round(loadability['regressions'] / 55 * 100, 1),
            'Other': both_fail,
            'Other_%': round(both_fail / 55 * 100, 1),
        })
    
    return pd.DataFrame(results)


def main():
    df = load_data().sort_values('Regress')
    
    print("\n" + "=" * 70)
    print("Loadability Tripwire vs PCE Obligation Discharge (55 Files)")
    print("=" * 70)
    
    print(f"\n{'Model':<22} {'Discharge':>12} {'Pass':>10} {'Regress':>12} {'Other':>10}")
    print("-" * 70)
    for _, row in df.iterrows():
        print(f"{row['Model']:<22} {row['Discharge_%']:>11.1f}% {row['Pass_%']:>9.1f}% {row['Regress_%']:>11.1f}% {row['Other_%']:>9.1f}%")
    
    # Save CSV
    output_csv = TESTS_RESULTS_DIR / 'loadability_vs_discharge_55.csv'
    df.to_csv(output_csv, index=False)
    print(f"\nSaved: {output_csv}")


if __name__ == "__main__":
    main()
