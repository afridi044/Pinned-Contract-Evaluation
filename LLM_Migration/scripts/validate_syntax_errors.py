"""
Syntax Error Validation (Secondary Robustness Diagnostic)

Part of the PCE (Pinned Contract Evaluation) framework's validity diagnostics.
Compares PHP syntax errors between original and LLM-migrated files (php -l).

This is a SECONDARY metric (Table 9, §Robustness Diagnostics), NOT the primary
PCE obligation discharge metric. Demonstrates that higher obligation discharge
does not guarantee fewer syntax errors—a key anti-gaming diagnostic.

Outcome categories align with paper Table 9:
  - BOTH_VALID: Both original and migrated syntax valid
  - FIXED_ERROR: Original had error, migrated fixed it
  - NEW_ERROR: Original valid, migrated introduced syntax error (hard regression)
  - BOTH_ERROR: Both have syntax errors
  - MISSING_MIGRATED: Migrated file not found

Usage:
    python scripts/validate_syntax_errors.py <model_name>
    python scripts/validate_syntax_errors.py gpt_5_codex
    python scripts/validate_syntax_errors.py all
"""

import os
import sys
import subprocess
import json
from pathlib import Path
from typing import Dict, List, Tuple, Optional
from datetime import datetime
import csv

# Add parent directories to path for config import
sys.path.insert(0, str(Path(__file__).parent.parent.parent))
from config import SELECTED_100_FILES_DIR, LLM_NEW_VERSION_DIR, LLM_MIGRATION_SUBDIR, DATASET_NAME


class SyntaxValidator:
    """
    PHP Syntax Validation under the PCE Framework.
    
    Secondary diagnostic that validates syntax correctness (php -l) for original vs migrated files.
    Outputs categorized results matching Table 9: Both Valid, Fixed, New Error, Both Error.
    
    Purpose: Establish that obligation discharge (primary PCE metric) does not guarantee 
    syntactic correctness—essential anti-gaming diagnostic showing models can discharge 
    obligations while introducing syntax errors.
    """
    
    def __init__(self, model_name: str):
        self.model_name = model_name
        self.original_base = SELECTED_100_FILES_DIR
        self.migrated_base = LLM_NEW_VERSION_DIR / model_name
        # Create model-specific subdirectory in dataset-aware location
        self.output_dir = LLM_MIGRATION_SUBDIR / "outputs" / "validation_reports" / model_name
        self.output_dir.mkdir(parents=True, exist_ok=True)
        
        # Categories for the 100 selected files
        self.categories = [
            "extra_large_1000_plus",
            "large_500_1000",
            "medium_201_500",
            "small_1_200"
        ]
        
        self.results = {
            "model": model_name,
            "timestamp": datetime.now().isoformat(),
            "summary": {
                "total_files": 0,
                "original_syntax_errors": 0,
                "migrated_syntax_errors": 0,
                "fixed_errors": 0,  # Had error in original, fixed in migrated
                "new_errors": 0,    # No error in original, error in migrated
                "both_valid": 0,    # Both original and migrated are valid
                "missing_files": 0,  # Migrated file doesn't exist
                "accuracy_rate": 0.0,  # Percentage of migrated files without syntax errors
                "error_fix_rate": 0.0,  # Percentage of original errors fixed
                "error_introduction_rate": 0.0  # Percentage of valid files that got errors
            },
            "files": []
        }
    
    def check_php_syntax(self, file_path: Path) -> Tuple[bool, Optional[str]]:
        """
        Check PHP file syntax using php -l command.
        
        Returns:
            Tuple of (is_valid, error_message)
        """
        if not file_path.exists():
            return False, "File does not exist"
        
        try:
            result = subprocess.run(
                ["php", "-l", str(file_path)],
                capture_output=True,
                text=True,
                timeout=10
            )
            
            if result.returncode == 0:
                return True, None
            else:
                # Extract error message
                error_lines = result.stderr.strip() or result.stdout.strip()
                return False, error_lines
                
        except subprocess.TimeoutExpired:
            return False, "Timeout: Syntax check took too long"
        except FileNotFoundError:
            return False, "PHP interpreter not found. Install PHP and add to PATH."
        except Exception as e:
            return False, f"Exception: {str(e)}"
    
    def get_all_original_files(self) -> List[Tuple[str, Path]]:
        """
        Get all original PHP files from selected_100_files directory.
        
        Returns:
            List of tuples (category, file_path)
        """
        files = []
        
        # Check if original_base exists
        if not self.original_base.exists():
            print(f"Error: Original files directory not found: {self.original_base}")
            return files
        
        # Categories for file size classification
        categories = [
            "extra_large_1000_plus",
            "large_500_1000",
            "medium_201_500",
            "small_1_200",
        ]
        
        # Look for PHP files in category subdirectories
        # Only include numbered files (001-100 benchmark files), exclude stubs like admin.php
        for category in categories:
            category_path = self.original_base / category
            if category_path.exists():
                for file_path in sorted(category_path.glob("*.php")):
                    # Only include files that start with digits (001_*, 002_*, etc)
                    if file_path.name[0].isdigit():
                        files.append((category, file_path))
        
        return files
    
    def find_migrated_file(self, original_filename: str) -> Optional[Path]:
        """
        Find the corresponding migrated file.
        
        Args:
            original_filename: Name of the original file (e.g., "001_getid3.lib.php")
        
        Returns:
            Path to migrated file if exists, None otherwise
        """
        migrated_path = self.migrated_base / original_filename
        return migrated_path if migrated_path.exists() else None
    
    def validate_file_pair(self, category: str, original_path: Path) -> Dict:
        """
        Validate a pair of original and migrated files.
        
        Returns:
            Dictionary with validation results
        """
        filename = original_path.name
        migrated_path = self.find_migrated_file(filename)
        
        result = {
            "filename": filename,
            "category": category,
            "original_path": str(original_path),
            "migrated_path": str(migrated_path) if migrated_path else None,
            "original_valid": False,
            "original_error": None,
            "migrated_valid": False,
            "migrated_error": None,
            "status": None
        }
        
        # Check original file
        orig_valid, orig_error = self.check_php_syntax(original_path)
        result["original_valid"] = orig_valid
        result["original_error"] = orig_error
        
        # Check migrated file
        if migrated_path:
            mig_valid, mig_error = self.check_php_syntax(migrated_path)
            result["migrated_valid"] = mig_valid
            result["migrated_error"] = mig_error
            
            # Determine status
            if orig_valid and mig_valid:
                result["status"] = "BOTH_VALID"
                self.results["summary"]["both_valid"] += 1
            elif not orig_valid and mig_valid:
                result["status"] = "FIXED_ERROR"
                self.results["summary"]["fixed_errors"] += 1
            elif orig_valid and not mig_valid:
                result["status"] = "NEW_ERROR"
                self.results["summary"]["new_errors"] += 1
            else:  # Both have errors
                result["status"] = "BOTH_ERROR"
            
            # Count errors (only once per file)
            if not orig_valid:
                self.results["summary"]["original_syntax_errors"] += 1
            if not mig_valid:
                self.results["summary"]["migrated_syntax_errors"] += 1
        else:
            result["status"] = "MISSING_MIGRATED"
            result["migrated_error"] = "Migrated file not found"
            self.results["summary"]["missing_files"] += 1
            if not orig_valid:
                self.results["summary"]["original_syntax_errors"] += 1
        
        return result
    
    def validate_all(self):
        """Validate all original vs migrated file pairs."""
        # Check if migrated directory exists
        if not self.migrated_base.exists():
            print(f"Error: Migrated files directory not found: {self.migrated_base}")
            print(f"Please ensure LLM migration has been run for model: {self.model_name}")
            return
        
        original_files = self.get_all_original_files()
        if not original_files:
            print(f"Error: No original PHP files found in: {self.original_base}")
            return
        
        self.results["summary"]["total_files"] = len(original_files)
        
        print(f"Processing {len(original_files)} files...")
        print(f"Original base: {self.original_base}")
        print(f"Migrated base: {self.migrated_base}")
        print(f"{'File':<50} {'Category':<25} {'Status':<20}")
        print("-" * 95)
        
        for category, original_path in original_files:
            result = self.validate_file_pair(category, original_path)
            self.results["files"].append(result)
            status_display = result["status"].replace("_", " ")
            print(f"{result['filename']:<50} {category:<25} {status_display:<20}")
        
        self.calculate_rates()
        self.print_summary()
        self.save_results()
    
    def calculate_rates(self):
        """Calculate accuracy and error rates."""
        summary = self.results["summary"]
        total = summary["total_files"]
        migrated_available = total - summary["missing_files"]
        
        if migrated_available > 0:
            # Accuracy rate: percentage of migrated files without syntax errors
            valid_migrated = summary["both_valid"] + summary["fixed_errors"]
            summary["accuracy_rate"] = (valid_migrated / migrated_available) * 100
        
        # Error fix rate: of files with original errors, how many were fixed
        original_errors = summary["original_syntax_errors"]
        if original_errors > 0:
            summary["error_fix_rate"] = (summary["fixed_errors"] / original_errors) * 100
        
        # Error introduction rate: of valid original files, how many got errors
        # Count files that were valid in original
        original_valid_count = 0
        for file_result in self.results["files"]:
            if file_result["original_valid"] and file_result["status"] != "MISSING_MIGRATED":
                original_valid_count += 1
        
        if original_valid_count > 0:
            summary["error_introduction_rate"] = (summary["new_errors"] / original_valid_count) * 100
    
    def print_summary(self):
        """Print validation summary."""
        summary = self.results["summary"]
        
        print(f"\nValidation Summary")
        print(f"{'-'*50}")
        print(f"Total files:          {summary['total_files']}")
        print(f"Both valid:           {summary['both_valid']}")
        print(f"Fixed errors:         {summary['fixed_errors']}")
        print(f"New errors:           {summary['new_errors']}")
        print(f"Accuracy:             {summary['accuracy_rate']:.1f}%")
    
    def save_results(self):
        """Save results to JSON and CSV files."""
        
        # Save JSON
        json_path = self.output_dir / f"syntax_validation_{self.model_name}.json"
        with open(json_path, 'w', encoding='utf-8') as f:
            json.dump(self.results, f, indent=2)
        print(f"\nDetailed JSON report: {json_path}")
        
        # Save CSV
        csv_path = self.output_dir / f"syntax_validation_{self.model_name}.csv"
        with open(csv_path, 'w', newline='', encoding='utf-8') as f:
            writer = csv.DictWriter(f, fieldnames=[
                'filename', 'category', 'status', 
                'original_valid', 'original_error',
                'migrated_valid', 'migrated_error'
            ])
            writer.writeheader()
            for file_result in self.results["files"]:
                writer.writerow({
                    'filename': file_result['filename'],
                    'category': file_result['category'],
                    'status': file_result['status'],
                    'original_valid': file_result['original_valid'],
                    'original_error': file_result['original_error'] or '',
                    'migrated_valid': file_result['migrated_valid'],
                    'migrated_error': file_result['migrated_error'] or ''
                })
        print(f"CSV report: {csv_path}")
        
        # Save summary only
        summary_path = self.output_dir / f"syntax_summary_{self.model_name}.json"
        with open(summary_path, 'w', encoding='utf-8') as f:
            json.dump({
                "model": self.model_name,
                "last_updated": self.results["timestamp"],
                "summary": self.results["summary"]
            }, f, indent=2)
        print(f"Summary report: {summary_path}")


def validate_model(model_name: str):
    """Validate a single model."""
    validator = SyntaxValidator(model_name)
    validator.validate_all()


def validate_all_models():
    """Validate all available models."""
    migrated_base = LLM_NEW_VERSION_DIR  # Already points to new-version directory
    
    if not migrated_base.exists():
        print(f"Error: Migrated files directory not found: {migrated_base}")
        return
    
    models = [d.name for d in migrated_base.iterdir() if d.is_dir()]
    
    if not models:
        print("No model directories found!")
        return
    
    print(f"Found {len(models)} models: {', '.join(models)}\n")
    
    for model in models:
        validate_model(model)
        print("\n" + "="*80 + "\n")


def main():
    """Main entry point."""
    if len(sys.argv) < 2:
        print("Usage: python validate_syntax_errors.py <model_name|all>")
        print("\nAvailable models:")
        migrated_base = LLM_NEW_VERSION_DIR  # Already points to new-version directory
        if migrated_base.exists():
            models = [d.name for d in migrated_base.iterdir() if d.is_dir()]
            for model in models:
                print(f"  - {model}")
        print("\nOr use 'all' to validate all models")
        sys.exit(1)
    
    model_name = sys.argv[1]
    
    if model_name.lower() == "all":
        validate_all_models()
    else:
        validate_model(model_name)


if __name__ == "__main__":
    main()
