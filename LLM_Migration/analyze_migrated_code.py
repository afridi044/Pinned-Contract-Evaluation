#!/usr/bin/env python3
"""
LLM Evaluation Runner
Orchestrates the evaluation pipeline for a single model.
"""

import subprocess
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent.parent))
from config import LLM_NEW_VERSION_DIR, LLM_MIGRATION_SUBDIR, DATASET_NAME

def run_evaluation(model_name: str):
    """Run evaluation pipeline for a specific model."""
    print(f"Evaluating {model_name}...")
    
    model_dir = LLM_NEW_VERSION_DIR / model_name
    if not model_dir.exists():
        print(f"Error: Model directory not found: {model_dir}")
        return False
    
    print("Step 1: Rector analysis...")
    try:
        result = subprocess.run([sys.executable, "scripts/process_all_files.py", model_name], 
                              capture_output=False, text=True)
        if result.returncode != 0:
            print(f"Error: Rector analysis failed (code {result.returncode})")
            return False
    except Exception as e:
        print(f"Error: {e}")
        return False
    
    print("Step 2: Triggered rules analysis...")
    try:
        result = subprocess.run([sys.executable, "scripts/analyze_triggered_rules.py", model_name], 
                              capture_output=False, text=True)
        if result.returncode != 0:
            print(f"Error: Triggered rules analysis failed (code {result.returncode})")
            return False
    except Exception as e:
        print(f"Error: {e}")
        return False
    
    print(f"Evaluation complete for {model_name}")
    return True

def get_available_models():
    """Dynamically discover available models from outputs/new-version/ directory."""
    new_version_dir = LLM_NEW_VERSION_DIR
    models = []
    
    if new_version_dir.exists():
        for item in new_version_dir.iterdir():
            if item.is_dir() and list(item.glob("*.php")):
                models.append(item.name)
    
    return sorted(models)

def main():
    """Main function."""
    available_models = get_available_models()
    
    if len(sys.argv) > 1:
        model_name = sys.argv[1]
        
        if model_name.lower() == "all":
            if not available_models:
                print("Error: No available models found")
                return
            print(f"Running evaluation for all {len(available_models)} models...")
            failed_models = []
            for model in available_models:
                print(f"\n{'='*50}")
                print(f"Processing: {model}")
                print(f"{'='*50}")
                success = run_evaluation(model)
                if not success:
                    failed_models.append(model)
            
            if failed_models:
                print(f"\nFailed models: {', '.join(failed_models)}")
                sys.exit(1)
            else:
                print(f"\nSuccessfully evaluated all {len(available_models)} models")
        else:
            if model_name not in available_models:
                print(f"Error: Unknown model '{model_name}'")
                if available_models:
                    print(f"Available: {', '.join(available_models)}")
                return
            
            success = run_evaluation(model_name)
            if not success:
                sys.exit(1)
    
    else:
        print("Usage: python analyze_migrated_code.py <model_name|all>")
        if available_models:
            print(f"Available models: {', '.join(available_models)}")
        print()
        print("Examples:")
        if available_models:
            print(f"  python analyze_migrated_code.py {available_models[0]}")
        print(f"  python analyze_migrated_code.py all")

if __name__ == "__main__":
    main()
