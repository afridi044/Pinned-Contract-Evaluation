"""Run PHPCompatibility analysis on code samples."""

import os
import subprocess
import json
import platform
import sys
from pathlib import Path

# Add parent directory to path for config import
sys.path.insert(0, str(Path(__file__).parent.parent))
from config import PHPCOMPATIBILITY_RESULTS_DIR, LLM_NEW_VERSION_DIR, SELECTED_100_FILES_DIR

def run_phpcs(source_dir, output_json, testVersion="8.3"):
    base_dir = Path(__file__).parent
    vendor_dir = base_dir.parent / "vendor" / "bin"
    
    if platform.system() == "Windows":
        phpcs_cmd = str(vendor_dir / "phpcs.bat")
    else:
        phpcs_cmd = str(vendor_dir / "phpcs")
    
    cmd = [
        phpcs_cmd,
        "--standard=PHPCompatibility",
        f"--runtime-set", "testVersion", testVersion,
        "--report=json",
        str(Path(source_dir).resolve())  # Use absolute path
    ]
    
    print(f"Running PHPCS on {source_dir}...")
    print(f"Command: {' '.join(cmd)}")
    
    try:
        is_windows = platform.system() == "Windows"
        
        if is_windows:
            cmd_str = ' '.join(cmd)
            result = subprocess.run(
                cmd_str,
                capture_output=True,
                text=True,
                shell=True,
                cwd=os.path.dirname(__file__)
            )
        else:
            result = subprocess.run(
                cmd,
                capture_output=True,
                text=True,
                cwd=os.path.dirname(__file__)
            )
        
        output = result.stdout if result.stdout else result.stderr
        
        if not output or output.strip() == "":
            print(f"⚠ Warning: No output received from PHPCS")
            print(f"  stdout: {result.stdout[:200] if result.stdout else 'empty'}")
            print(f"  stderr: {result.stderr[:200] if result.stderr else 'empty'}")
            print(f"  exit code: {result.returncode}")
        
        with open(output_json, 'w', encoding='utf-8') as f:
            f.write(output)
        
        print(f"✓ Results saved to {output_json}")
        return True
        
    except Exception as e:
        print(f"✗ Error running PHPCS: {e}")
        import traceback
        traceback.print_exc()
        return False

def main():
    """Run PHPCompatibility on benchmark and all model outputs."""
    
    base_dir = Path(__file__).parent
    results_dir = PHPCOMPATIBILITY_RESULTS_DIR
    results_dir.mkdir(parents=True, exist_ok=True)
    
    # Define all datasets to analyze
    # Original benchmark has PHP files in subdirectories
    # Migrated files are in LLM_Migration/{DATASET_NAME}/outputs/new-version/
    datasets = {
        "original_benchmark": str(SELECTED_100_FILES_DIR),
        "Rector_Baseline": str(LLM_NEW_VERSION_DIR / "Rector_Baseline"),
        "claude_sonnet_4": str(LLM_NEW_VERSION_DIR / "claude_sonnet_4_20250514"),
        "gemini_2_5_flash": str(LLM_NEW_VERSION_DIR / "gemini_2_5_flash"),
        "gemini_2_5_pro": str(LLM_NEW_VERSION_DIR / "gemini_2_5_pro"),
        "gpt_5_codex": str(LLM_NEW_VERSION_DIR / "gpt_5_codex"),
        "llama_3_3_70b": str(LLM_NEW_VERSION_DIR / "meta_llama_llama_3_3_70b_instruct")

    }
    
    print("=" * 60)
    print("PHPCompatibility Cross-Oracle Validation")
    print("=" * 60)
    print()
    
    for name, path in datasets.items():
        full_path = str(path)  # Already absolute from config
        output_file = str(results_dir / f"phpcs_{name}.json")
        
        if not os.path.exists(full_path):
            print(f"⚠ Skipping {name}: directory not found at {full_path}")
            continue
        
        run_phpcs(full_path, output_file, testVersion="8.3")
        print()
    
    print("=" * 60)
    print("Analysis complete! Results in:", results_dir)
    print("=" * 60)

if __name__ == "__main__":
    main()
