#!/usr/bin/env python3
"""
Evaluation Runner
Orchestrates PCE evaluation pipeline and generates result summaries.
"""

import sys
from pathlib import Path
import numpy as np
import random

# ============================================================================
# FIXED SEED FOR REPRODUCIBILITY
# ============================================================================
SEED = 42
np.random.seed(SEED)
random.seed(SEED)

# Add parent directory to path to import shared config
sys.path.insert(0, str(Path(__file__).parent.parent))
from config import LLM_CODE_ANALYSIS_DIR

from php_migration_evaluator import PHPMigrationEvaluator
from visualizer import PHPVisualizer

def get_available_models():
    """Get list of available model folders."""
    if not LLM_CODE_ANALYSIS_DIR.exists():
        return []
    
    models = [d.name for d in LLM_CODE_ANALYSIS_DIR.iterdir() 
              if d.is_dir() and not d.name.startswith('.') and d.name != '__pycache__']
    return sorted(models)

def run_model_evaluation(model_folder: str) -> bool:
    """Run evaluation and visualization for a single model."""
    # Run evaluation
    evaluator = PHPMigrationEvaluator(model_folder=model_folder)
    evaluation_results = evaluator.run_complete_evaluation()
    
    if evaluation_results is None:
        return False
    
    # Generate visualizations
    visualizer = PHPVisualizer(model_folder=model_folder)
    viz_success = visualizer.generate_all_visualizations()
    
    return viz_success

def main():
    """Execute PCE evaluation and visualization pipeline."""
    # Parse command line arguments
    if len(sys.argv) < 2:
        print("Usage: python run_evaluation.py <model_folder|all>")
        print("\nAvailable models:")
        available_models = get_available_models()
        if available_models:
            for model in available_models:
                print(f"  {model}")
        print("\nExamples:")
        print("  python run_evaluation.py claude_sonnet_4_20250514")
        print("  python run_evaluation.py all")
        sys.exit(1)
    
    model_folder = sys.argv[1]
    
    if model_folder.lower() == "all":
        available_models = get_available_models()
        if not available_models:
            print("Error: No available models found")
            sys.exit(1)
        
        print(f"Running evaluation for all {len(available_models)} models...")
        failed_models = []
        
        for model in available_models:
            print(f"\n{'='*50}")
            print(f"Processing: {model}")
            print(f"{'='*50}")
            success = run_model_evaluation(model)
            if not success:
                failed_models.append(model)
        
        if failed_models:
            print(f"\nFailed models: {', '.join(failed_models)}")
            sys.exit(1)
        else:
            print(f"\nSuccessfully evaluated all {len(available_models)} models")
    else:
        success = run_model_evaluation(model_folder)
        if not success:
            sys.exit(1)

if __name__ == "__main__":
    main()
