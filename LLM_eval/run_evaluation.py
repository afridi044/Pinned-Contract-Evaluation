#!/usr/bin/env python3
"""
Evaluation Runner
Runs evaluation and generates visualizations for a specific model
"""

import sys
from pathlib import Path

# Add parent directory to path to import shared config
sys.path.insert(0, str(Path(__file__).parent.parent))
from config import LLM_EVALUATION_REPORTS_DIR

from php_migration_evaluator import PHPMigrationEvaluator
from visualizer import PHPVisualizer

def get_available_models():
    """Get list of available model folders"""
    if not LLM_EVALUATION_REPORTS_DIR.exists():
        return []
    
    models = [d.name for d in LLM_EVALUATION_REPORTS_DIR.iterdir() 
              if d.is_dir() and not d.name.startswith('.') and d.name != '__pycache__']
    return sorted(models)

def main():
    """Run evaluation pipeline"""
    # Parse command line arguments
    if len(sys.argv) < 2:
        print("⚠️  Usage: python run_evaluation.py <model_folder>")
        print("\nAvailable models:")
        available_models = get_available_models()
        if available_models:
            for model in available_models:
                print(f"  - {model}")
        else:
            print("  No models found in LLM_Migration/evaluation_reports/")
        print("\nExample: python run_evaluation.py claude_sonnet_4_20250514")
        sys.exit(1)
    
    model_folder = sys.argv[1]
    
    print("=== PHP Migration Evaluation ===")
    print(f"Model: {model_folder}\n")
    
    # 1. Run evaluation
    print("1. Running evaluation...")
    evaluator = PHPMigrationEvaluator(model_folder=model_folder)
    evaluation_results = evaluator.run_complete_evaluation()
    
    if evaluation_results is None:
        print("❌ Error: Evaluation failed!")
        sys.exit(1)
    
    # 2. Generate visualizations
    print("\n2. Generating visualizations...")
    visualizer = PHPVisualizer(model_folder=model_folder)
    viz_success = visualizer.generate_all_visualizations()
    
    if not viz_success:
        print("❌ Error: Visualization generation failed!")
        sys.exit(1)
    
    print("\n✅ Evaluation Complete!")
    print(f"\nGenerated files in LLM_eval/{model_folder}/:")
    print(f"  - evaluation_results.csv")
    print(f"  - migration_evaluation_report.md")
    print(f"  - summary_statistics.json")
    print(f"  - visualizations/performance_overview.png")
    print(f"  - visualizations/complexity_analysis.png")
    print(f"  - visualizations/summary_statistics.png")
    print(f"  - visualizations/performance_summary.pdf")
    print(f"  - visualizations/size_performance.pdf")
    

if __name__ == "__main__":
    main()
