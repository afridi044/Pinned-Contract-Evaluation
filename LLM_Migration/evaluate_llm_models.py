#!/usr/bin/env python3
"""
LLM Evaluation Runner
====================

Easy script to run evaluation on specific LLM models.
"""

import subprocess
import sys
from pathlib import Path

def run_evaluation(model_name: str):
    """Run evaluation for a specific model."""
    print(f"🚀 Starting evaluation for {model_name}")
    print("=" * 60)
    
    # Check if model directory exists
    model_dir = Path(f"outputs/new-version/{model_name}")
    if not model_dir.exists():
        print(f"❌ Model directory not found: {model_dir}")
        return False
    
    # Step 1: Run Rector analysis
    print("\n📋 Step 1: Running Rector analysis...")
    try:
        result = subprocess.run([sys.executable, "scripts/process_all_files.py", model_name], 
                              capture_output=False, text=True)
        if result.returncode != 0:
            print(f"❌ Rector analysis failed with return code {result.returncode}")
            return False
    except Exception as e:
        print(f"❌ Error running Rector analysis: {e}")
        return False
    
    # Step 2: Run triggered rules analysis
    print("\n📊 Step 2: Running triggered rules analysis...")
    try:
        result = subprocess.run([sys.executable, "scripts/analyze_triggered_rules.py", model_name], 
                              capture_output=False, text=True)
        if result.returncode != 0:
            print(f"❌ Triggered rules analysis failed with return code {result.returncode}")
            return False
    except Exception as e:
        print(f"❌ Error running triggered rules analysis: {e}")
        return False
    
    print(f"\n✅ Evaluation complete for {model_name}!")
    print(f"📁 Results saved in: evaluation_reports/{model_name}/")
    return True

def get_available_models():
    """Dynamically discover available models from outputs/new-version/ directory."""
    from pathlib import Path
    
    new_version_dir = Path("outputs/new-version")
    models = []
    
    if new_version_dir.exists():
        for item in new_version_dir.iterdir():
            if item.is_dir():
                # Check if the directory contains any .php files
                php_files = list(item.glob("*.php"))
                if php_files:
                    models.append(item.name)
    
    return sorted(models)

def main():
    """Main function."""
    available_models = get_available_models()
    
    if len(sys.argv) > 1:
        model_name = sys.argv[1]
        if model_name not in available_models:
            print(f"❌ Unknown model: {model_name}")
            print(f"Available models: {', '.join(available_models)}")
            return
        
        # Run evaluation for specific model
        success = run_evaluation(model_name)
        if not success:
            sys.exit(1)
    
    elif len(sys.argv) == 1:
        # Show usage
        print("🔬 LLM Evaluation Runner")
        print("=" * 30)
        print()
        print("Usage:")
        print(f"  python {sys.argv[0]} <model_name>")
        print()
        if available_models:
            print("Available models:")
            for model in available_models:
                model_dir = Path(f"outputs/new-version/{model}")
                status = "✅" if model_dir.exists() else "❌"
                print(f"  {status} {model}")
        else:
            print("No models found in outputs/new-version/ directory")
        print()
        print("Examples:")
        if available_models:
            for model in available_models[:2]:  # Show first 2 as examples
                print(f"  python {sys.argv[0]} {model}")
        print()
        print("Or run individual steps:")
        print("  python scripts/process_all_files.py <model_name>     # Step 1: Rector analysis")
        print("  python scripts/analyze_triggered_rules.py <model_name>  # Step 2: Rules analysis")

if __name__ == "__main__":
    main()
