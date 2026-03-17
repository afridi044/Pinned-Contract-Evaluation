"""
Calculate correlation between Rector obligation discharge rates (PCE) and PHPCompatibility metrics.
Provides statistical validation for the paper's dual-oracle evaluation approach.
"""

import json
import pandas as pd
from pathlib import Path
from scipy.stats import pearsonr, spearmanr
import matplotlib.pyplot as plt
import seaborn as sns

def load_rector_results(eval_dir: Path) -> pd.DataFrame:
    """Load Rector evaluation results from model directories."""
    
    models = [
        ("claude_sonnet_4_20250514", "Claude Sonnet 4"),
        ("gemini_2_5_flash", "Gemini 2.5 Flash"),
        ("gemini_2_5_pro", "Gemini 2.5 Pro"),
        ("gpt_5_codex", "GPT-5 Codex"),
        ("meta_llama_llama_3_3_70b_instruct", "LLaMA 3.3 70B")
    ]
    
    rows = []
    
    for folder_name, model_name in models:
        # Files are in wordpress subdirectory
        summary_path = eval_dir / "wordpress" / folder_name / "summary_statistics.json"
        
        if summary_path.exists():
            with open(summary_path, 'r', encoding='utf-8') as f:
                data = json.load(f)
                
            # Extract weighted discharge rate (PCE metric)
            performance_metrics = data.get("performance_metrics", {})
            discharge_rate = performance_metrics.get("weighted_discharge_rate", 0.0)
            
            rows.append({
                "Model": model_name,
                "Rector_Discharge_%": discharge_rate
            })
        else:
            print(f"Warning: {summary_path} not found")
    
    return pd.DataFrame(rows)

def load_phpcs_results(results_dir: Path) -> pd.DataFrame:
    """Load PHPCompatibility results."""
    
    models = [
        ("phpcs_claude_sonnet_4.json", "Claude Sonnet 4"),
        ("phpcs_gemini_2_5_flash.json", "Gemini 2.5 Flash"),
        ("phpcs_gemini_2_5_pro.json", "Gemini 2.5 Pro"),
        ("phpcs_gpt_5_codex.json", "GPT-5 Codex"),
        ("phpcs_llama_3_3_70b.json", "LLaMA 3.3 70B")
    ]
    
    # Get original baseline
    original_path = results_dir / "phpcs_original_benchmark.json"
    if not original_path.exists():
        print(f"Warning: {original_path} not found")
        return pd.DataFrame()
    
    with open(original_path, 'r', encoding='utf-8') as f:
        original_data = json.load(f)
    
    original_issues = sum(
        f.get("errors", 0) + f.get("warnings", 0) 
        for f in original_data.get("files", {}).values()
    )
    
    rows = []
    
    for filename, model_name in models:
        json_path = results_dir / filename
        
        if not json_path.exists():
            print(f"Warning: {json_path} not found")
            continue
        
        with open(json_path, 'r', encoding='utf-8') as f:
            data = json.load(f)
        
        migrated_issues = sum(
            f.get("errors", 0) + f.get("warnings", 0) 
            for f in data.get("files", {}).values()
        )
        
        reduction_pct = ((original_issues - migrated_issues) / original_issues * 100) if original_issues > 0 else 0
        
        rows.append({
            "Model": model_name,
            "PHPCS_Reduction_%": reduction_pct,
            "Original_Issues": original_issues,
            "Migrated_Issues": migrated_issues
        })
    
    return pd.DataFrame(rows)

def calculate_correlation(eval_dir: Path, phpcs_dir: Path):
    """Calculate and visualize correlation between Rector and PHPCompatibility."""
    
    print("\n" + "=" * 80)
    print("Cross-Oracle Correlation Analysis")
    print("=" * 80)
    print()
    
    # Load both datasets
    rector_df = load_rector_results(eval_dir)
    phpcs_df = load_phpcs_results(phpcs_dir)
    
    if rector_df.empty:
        print("⚠ No Rector data found - run evaluation first")
        return
    
    if phpcs_df.empty:
        print("⚠ No PHPCompatibility data found - run run_phpcompatibility.py first")
        return
    
    # Merge on model name
    merged_df = pd.merge(rector_df, phpcs_df, on="Model", how="inner")
    
    if len(merged_df) < 3:
        print("⚠ Not enough data points for correlation analysis")
        print(f"  Rector models: {list(rector_df['Model'].values)}")
        print(f"  PHPCS models: {list(phpcs_df['Model'].values)}")
        return
    
    print("Combined Data:")
    print(merged_df.to_string(index=False))
    print()
    
    # Calculate correlations
    rector_scores = merged_df["Rector_Discharge_%"].values
    phpcs_scores = merged_df["PHPCS_Reduction_%"].values
    
    pearson_r, pearson_p = pearsonr(rector_scores, phpcs_scores)
    spearman_r, spearman_p = spearmanr(rector_scores, phpcs_scores)
    
    print("-" * 80)
    print("Correlation Results:")
    print("-" * 80)
    print(f"Pearson correlation:  r = {pearson_r:.3f}, p = {pearson_p:.4f}")
    print(f"Spearman correlation: ρ = {spearman_r:.3f}, p = {spearman_p:.4f}")
    print()
    
    if pearson_p < 0.05:
        print("✓ Statistically significant correlation (p < 0.05)")
    else:
        print("⚠ Correlation not statistically significant (p ≥ 0.05)")
    
    
    # Save merged results
    output_path = phpcs_dir / "cross_oracle_correlation.csv"
    merged_df.to_csv(output_path, index=False)
    print(f"✓ Results saved to: {output_path}")
    
    # Create visualization
    create_correlation_plot(merged_df, phpcs_dir, pearson_r, pearson_p)

def create_correlation_plot(df: pd.DataFrame, output_dir: Path, r: float, p: float):
    """Create scatter plot showing correlation."""
    
    plt.figure(figsize=(10, 6))
    
    sns.scatterplot(
        data=df,
        x="Rector_Discharge_%",
        y="PHPCS_Reduction_%",
        s=100
    )
    
    # Add model labels
    for idx, row in df.iterrows():
        plt.annotate(
            row["Model"],
            (row["Rector_Discharge_%"], row["PHPCS_Reduction_%"]),
            xytext=(5, 5),
            textcoords="offset points",
            fontsize=9
        )
    
    # Add trend line
    z = np.polyfit(df["Rector_Discharge_%"], df["PHPCS_Reduction_%"], 1)
    p_line = np.poly1d(z)
    x_line = np.linspace(df["Rector_Discharge_%"].min(), df["Rector_Discharge_%"].max(), 100)
    plt.plot(x_line, p_line(x_line), "r--", alpha=0.5, label=f"r = {r:.3f}, p = {p:.3f}")
    
    plt.xlabel("Rector Obligation Discharge Rate (%) [PCE]", fontsize=12)
    plt.ylabel("PHPCompatibility Issue Reduction (%)", fontsize=12)
    plt.title("Cross-Oracle Validation: PCE Discharge vs PHPCompatibility", fontsize=14, fontweight='bold')
    plt.legend()
    plt.grid(True, alpha=0.3)
    plt.tight_layout()
    
    plot_path = output_dir / "cross_oracle_correlation.png"
    plt.savefig(plot_path, dpi=300, bbox_inches='tight')
    print(f"✓ Plot saved to: {plot_path}")
    plt.close()

if __name__ == "__main__":
    import numpy as np  # Import here for the plot
    import sys
    from pathlib import Path
    
    # Import config for dynamic paths
    sys.path.insert(0, str(Path(__file__).parent.parent))
    from config import PHPCOMPATIBILITY_RESULTS_DIR, LLM_EVAL_SUBDIR
    
    base_dir = Path(__file__).parent
    eval_dir = base_dir
    phpcs_dir = PHPCOMPATIBILITY_RESULTS_DIR
    
    if not phpcs_dir.exists():
        print("Error: Run run_phpcompatibility.py first!")
    else:
        calculate_correlation(eval_dir, phpcs_dir)
