#!/usr/bin/env python3
"""
Enhanced Rector PHP Analysis Script
==================================

File-by-file analysis using Rector professional tool.
Generates pure Rector data without research estimations.
"""

import json
import subprocess
import os
import re
import sys
from pathlib import Path
from datetime import datetime
from typing import Dict, List, Any, Optional

# Import shared config (now in same directory)
from config import RECTOR_PHP_PATH, VENDOR_PATH, get_rector_command, PROJECT_ROOT

class RectorAnalyzer:
    """Analyze individual PHP files using Rector professional tool."""
    
    def __init__(self, project_dir: str = None, reports_dir: str = "rector_reports"):
        # Always use PROJECT_ROOT where rector.php is located
        self.project_dir = PROJECT_ROOT if project_dir is None else Path(project_dir)
        self.reports_dir = Path(reports_dir) if Path(reports_dir).is_absolute() else Path.cwd() / reports_dir
        self.individual_reports_dir = self.reports_dir / "individual_files"
        
        # Create directory structure with parents=True to handle nested paths
        self.reports_dir.mkdir(parents=True, exist_ok=True)
        self.individual_reports_dir.mkdir(parents=True, exist_ok=True)
        
        # Get Rector version
        self.rector_version = self._get_rector_version()
    
    def _get_rector_version(self) -> str:
        """Get Rector version for metadata."""
        try:
            rector_cmd = get_rector_command()
            
            result = subprocess.run([rector_cmd, "--version"], 
                                  capture_output=True, text=True)
            if result.returncode == 0:
                # Extract version from output like "Rector 2.1.0"
                version_match = re.search(r'Rector (\d+\.\d+\.\d+)', result.stdout)
                return version_match.group(1) if version_match else "unknown"
        except Exception:
            pass
        return "unknown"
    
    def analyze_single_file(self, file_path: str) -> Dict[str, Any]:
        """Analyze a single PHP file with Rector."""
        file_path = Path(file_path)
        
        if not file_path.exists():
            return {"error": f"File not found: {file_path}"}
        
        print(f"🔍 Analyzing {file_path.name}...")
        
        # Use shared rector command
        rector_cmd = get_rector_command()
        
        try:
            # Run Rector with direct file path (no environment variable needed)
            result = subprocess.run([
                rector_cmd, 
                "process", 
                str(file_path.absolute()),  # Pass file path directly
                "--dry-run",
                "--output-format", "json"
            ], capture_output=True, text=True, cwd=self.project_dir, timeout=60)
            
            # Rector can return different codes: 0 (no changes), 1 (changes found), 2 (with output)
            if result.returncode in [0, 1, 2] and result.stdout.strip():
                try:
                    rector_output = json.loads(result.stdout)
                    return self._process_rector_output(rector_output, file_path)
                except json.JSONDecodeError:
                    return {"error": "Invalid JSON output", "raw": result.stdout}
            else:
                # No changes found or error
                if result.returncode == 0:
                    return self._create_empty_result(file_path)
                else:
                    return {"error": result.stderr, "returncode": result.returncode}
                
        except subprocess.TimeoutExpired:
            return {"error": "Analysis timed out"}
        except Exception as e:
            return {"error": str(e)}
    
    def _create_empty_result(self, file_path: Path) -> Dict[str, Any]:
        """Create result structure for files with no Rector changes."""
        return {
            "file_path": str(file_path),
            "rector_analysis": {
                "php_version_changes": 0,  # Single consolidated metric
                "rules_triggered": [],
                "changes_by_php_version": {},
                "has_diff": False,
                "diff_line_count": 0
            },
            "analysis_metadata": {
                "rector_version": self.rector_version,
                "analysis_date": datetime.now().isoformat(),
                "file_size_kb": round(file_path.stat().st_size / 1024, 1) if file_path.exists() else 0,
                "analysis_type": "version_specific_only"
            }
        }
    
    def _process_rector_output(self, rector_output: Dict[str, Any], file_path: Path) -> Dict[str, Any]:
        """Process Rector output and extract structured data."""
        # Rector can return structured JSON errors with return code 1.
        # These must be surfaced as failures, not interpreted as 0-change success.
        if rector_output.get("totals", {}).get("errors", 0) > 0 or rector_output.get("errors"):
            first_error = rector_output.get("errors", [{}])[0]
            message = first_error.get("message", "Rector reported errors")
            line = first_error.get("line")
            file_with_error = first_error.get("file", str(file_path))
            line_suffix = f" (line {line})" if line is not None else ""
            return {
                "error": f"{message}{line_suffix}",
                "error_file": file_with_error,
                "raw_rector_output": rector_output,
            }

        if "file_diffs" not in rector_output:
            return self._create_empty_result(file_path)
        
        # Initialize counters - VERSION SPECIFIC ONLY
        php_version_counts = {}
        
        all_rules = []
        total_diff_lines = 0
        
        # Regex patterns for VERSION-SPECIFIC categorization only
        php_version_pattern = re.compile(r'Rector\\Php(\d+)\\')
        level_set_pattern = re.compile(r'Rector\\(Php\d+|Transform|Set)\\')  # Version-specific patterns
        
        # Process each file diff
        for file_diff in rector_output["file_diffs"]:
            # Count diff lines
            if "diff" in file_diff:
                total_diff_lines += len(file_diff["diff"].split('\n'))
            
            # Process applied rules
            for rule in file_diff.get("applied_rectors", []):
                all_rules.append(rule)
                
                # Categorize by PHP version ONLY
                php_match = php_version_pattern.search(rule)
                if php_match:
                    version_number = php_match.group(1)
                    version_key = f"php_{version_number}"
                    php_version_counts[version_key] = php_version_counts.get(version_key, 0) + 1
        
        return {
            "file_path": str(file_path),
            "rector_analysis": {
                "php_version_changes": len(all_rules),  # Single consolidated metric
                "rules_triggered": all_rules,
                "changes_by_php_version": php_version_counts,
                "has_diff": len(all_rules) > 0,
                "diff_line_count": total_diff_lines
            },
            "analysis_metadata": {
                "rector_version": self.rector_version,
                "analysis_date": datetime.now().isoformat(),
                "file_size_kb": round(file_path.stat().st_size / 1024, 1) if file_path.exists() else 0,
                "analysis_type": "version_specific_only"
            },
            "raw_rector_output": rector_output
        }
    
    def save_individual_report(self, analysis_result: Dict[str, Any], filename: str) -> str:
        """Save individual file analysis report."""
        # Generate report filename
        base_name = Path(filename).stem
        report_file = self.individual_reports_dir / f"{base_name}_rector.json"
        
        with open(report_file, 'w', encoding='utf-8') as f:
            json.dump(analysis_result, f, indent=2, ensure_ascii=False)
        
        return str(report_file)
    
    def count_total_lines(self, file_path):
        """Count all lines in the file - simple line counting"""
        try:
            with open(file_path, 'r', encoding='utf-8', errors='ignore') as f:
                lines = f.readlines()
            
            # Simply count all lines
            return len(lines)
        except Exception as e:
            print(f"Error counting lines in {file_path}: {e}")
            return 0

    def get_file_metrics(self, file_path: str) -> Dict[str, Any]:
        """Get file metrics with total line count."""
        file_path = Path(file_path)
        
        if not file_path.exists():
            return {"lines_of_code": 0, "file_size_kb": 0}
        
        try:
            # Use simple line counting method
            total_lines = self.count_total_lines(file_path)
            
            return {
                "lines_of_code": total_lines,
                "file_size_kb": round(file_path.stat().st_size / 1024, 1)
            }
        except Exception:
            return {"lines_of_code": 0, "file_size_kb": 0}

def main():
    """Test single file analysis."""
    analyzer = RectorAnalyzer()
    
    # Test with first file
    test_file = "organized_dataset/batch_01/001_options-writing.php"
    result = analyzer.analyze_single_file(test_file)
    
    if "error" not in result:
        print("✅ Analysis successful!")
        print(f"Changes found: {result['rector_analysis']['total_changes_found']}")
        print(f"Rules triggered: {len(result['rector_analysis']['rules_triggered'])}")
        
        # Save report
        report_path = analyzer.save_individual_report(result, test_file)
        print(f"Report saved: {report_path}")
    else:
        print(f"❌ Analysis failed: {result['error']}")

if __name__ == "__main__":
    main()
