#!/usr/bin/env python3
"""
Simple LLM Evaluation Report Generator
=====================================

Generate a simple report showing remaining Rector rules after LLM migration.
"""

import json
from pathlib import Path
from collections import defaultdict
from typing import Dict, List, Any
from datetime import datetime
import sys

# Add parent directory to path to import shared config
sys.path.insert(0, str(Path(__file__).parent.parent))
from core.config import LLM_EVALUATION_REPORTS_DIR, LLM_NEW_VERSION_DIR

class SimpleRulesAnalyzer:
    """Simple analyzer to view remaining Rector rules after LLM migration."""
    
    def __init__(self, model_name: str = None):
        if model_name:
            self.reports_dir = LLM_EVALUATION_REPORTS_DIR / model_name
            self.model_name = model_name
        else:
            self.reports_dir = Path("rector_reports")
            self.model_name = None
            
        self.metadata = self.load_metadata()
    
    def load_metadata(self) -> Dict[str, Any]:
        """Load metadata from JSON file."""
        metadata_file = self.reports_dir / "metadata.json"
        
        if not metadata_file.exists():
            if self.model_name:
                raise FileNotFoundError(f"No evaluation found for {self.model_name}. Run: python process_all_files.py {self.model_name}")
            else:
                raise FileNotFoundError(f"No analysis found. Run: python process_all_files.py")
        
        with open(metadata_file, 'r', encoding='utf-8') as f:
            return json.load(f)
    
    def get_file_summaries(self) -> List[Dict[str, Any]]:
        """Get simple summary of each file's remaining rules."""
        summaries = []
        
        for file_data in self.metadata["files"]:
            filename = file_data["filename"]
            changes = file_data["rector_analysis"]["php_version_changes"]
            rules = file_data["rector_analysis"]["rules_triggered"]
            
            # Extract PHP versions and rule names
            php_versions = set()
            rule_names = []
            
            for rule in rules:
                parts = rule.split("\\")
                if len(parts) > 1:
                    php_versions.add(parts[1].replace('Php', 'PHP '))
                if len(parts) > 0:
                    rule_names.append(parts[-1])
            
            summaries.append({
                'filename': filename,
                'total_changes': changes,
                'php_versions': sorted(php_versions),
                'rule_names': rule_names
            })
        
        # Sort by number of changes (most problematic first)
        summaries.sort(key=lambda x: x['total_changes'], reverse=True)
        return summaries
    
    def get_rule_frequency(self) -> List[Dict[str, Any]]:
        """Get frequency of each rule across all files."""
        rule_count = defaultdict(int)
        rule_versions = {}
        
        for file_data in self.metadata["files"]:
            for rule in file_data["rector_analysis"]["rules_triggered"]:
                parts = rule.split("\\")
                rule_name = parts[-1] if parts else rule
                php_version = parts[1].replace('Php', 'PHP ') if len(parts) > 1 else "Unknown"
                
                rule_count[rule_name] += 1
                rule_versions[rule_name] = php_version
        
        # Convert to sorted list
        rules = []
        for rule_name, count in rule_count.items():
            rules.append({
                'rule_name': rule_name,
                'php_version': rule_versions[rule_name],
                'file_count': count
            })
        
        rules.sort(key=lambda x: x['file_count'], reverse=True)
        return rules
    
    def generate_simple_report(self) -> str:
        """Generate a simple, easy-to-read report."""
        file_summaries = self.get_file_summaries()
        rule_frequency = self.get_rule_frequency()
        
        # Header
        if self.model_name:
            title = f"LLM Migration Report - {self.model_name}"
        else:
            title = "PHP Migration Analysis Report"
        
        report = f"""# {title}

*Generated on {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}*

## Summary

"""
        
        # Overall stats
        total_files = len(file_summaries)
        perfect_files = len([f for f in file_summaries if f['total_changes'] == 0])
        total_changes = sum(f['total_changes'] for f in file_summaries)
        
        report += f"- **Files analyzed**: {total_files}\n"
        report += f"- **Perfect migrations** (0 changes needed): {perfect_files}\n"
        report += f"- **Files needing work**: {total_files - perfect_files}\n"
        report += f"- **Total remaining changes**: {total_changes}\n"
        
        if total_files > 0:
            avg_changes = total_changes / total_files
            report += f"- **Average changes per file**: {avg_changes:.1f}\n"
        
        # Migration quality
        if self.model_name:
            report += f"\n### Migration Statistics\n\n"
            report += f"- **Files with no changes**: {perfect_files} ({perfect_files/total_files*100:.1f}%)\n"
            report += f"- **Files with 1-3 changes**: {len([f for f in file_summaries if 1 <= f['total_changes'] <= 3])}\n"
            report += f"- **Files with 4-8 changes**: {len([f for f in file_summaries if 4 <= f['total_changes'] <= 8])}\n"
            report += f"- **Files with 9+ changes**: {len([f for f in file_summaries if f['total_changes'] >= 9])}\n"
        
        # File-by-file results
        report += "\n## File-by-File Results\n\n"
        
        for file_info in file_summaries:
            versions_str = ", ".join(file_info['php_versions']) if file_info['php_versions'] else "None"
            rules_str = ", ".join(file_info['rule_names']) if file_info['rule_names'] else "None"
            
            report += f"### 📄 `{file_info['filename']}`\n\n"
            report += f"- **Changes needed**: {file_info['total_changes']}\n"
            report += f"- **PHP versions affected**: {versions_str}\n"
            report += f"- **Triggered rules**: {rules_str}\n\n"
        
        # Most common rules
        report += "\n## Most Common Rules Triggered\n\n"
        report += "| Rule Name | PHP Version | Files Affected |\n"
        report += "|-----------|-------------|----------------|\n"
        
        for rule_info in rule_frequency[:10]:  # Show top 10 rules
            report += f"| `{rule_info['rule_name']}` | {rule_info['php_version']} | {rule_info['file_count']} |\n"
        
        return report
    
    def save_report(self) -> None:
        """Generate and save the simple report."""
        print("📊 Generating simple migration report...")
        
        report = self.generate_simple_report()
        
        # Save report
        if self.model_name:
            report_file = self.reports_dir / "migration_report.md"
        else:
            report_file = self.reports_dir / "migration_report.md"
            
        with open(report_file, 'w', encoding='utf-8') as f:
            f.write(report)
        
        print(f"✅ Report saved: {report_file}")
        
        # Also print a quick summary to console
        file_summaries = self.get_file_summaries()
        total_files = len(file_summaries)
        perfect_files = len([f for f in file_summaries if f['total_changes'] == 0])
        
        print(f"\n📋 Quick Summary:")
        print(f"   Perfect migrations: {perfect_files}/{total_files}")
        print(f"   Files needing work: {total_files - perfect_files}")
        
        if file_summaries:
            worst_file = file_summaries[0]  # Already sorted by changes desc
            if worst_file['total_changes'] > 0:
                print(f"   Most issues: {worst_file['filename']} ({worst_file['total_changes']} changes)")

def get_available_models():
    """Dynamically discover available models from new-version/ directory."""
    models = []
    
    if LLM_NEW_VERSION_DIR.exists():
        for item in LLM_NEW_VERSION_DIR.iterdir():
            if item.is_dir():
                # Check if evaluation report exists for this model
                eval_report = LLM_EVALUATION_REPORTS_DIR / item.name / "metadata.json"
                if eval_report.exists():
                    models.append(item.name)
    
    return sorted(models)

def main():
    """Main execution function."""
    import sys
    
    # Get available models dynamically
    available_models = get_available_models()
    
    # Check if we're running in evaluation mode
    if len(sys.argv) > 1 and sys.argv[1] in available_models:
        model_name = sys.argv[1]
        print(f"🔍 Simple Migration Report for {model_name}")
        print("=" * 50)
        
        analyzer = SimpleRulesAnalyzer(model_name=model_name)
    else:
        print("🔍 Simple Migration Report Generator")
        print("=" * 40)
        print()
        print("Usage:")
        print("  python analyze_triggered_rules.py <model_name>")
        print()
        if available_models:
            print("Available models:")
            for model in available_models:
                print(f"  - {model}")
        else:
            print("No models found. Run process_all_files.py <model_name> first.")
        print()
        print("Or run without arguments for original dataset analysis.")
        print()
        
        # Try to analyze whatever reports exist
        analyzer = SimpleRulesAnalyzer()
    
    try:
        analyzer.save_report()
    except FileNotFoundError as e:
        print(f"❌ {e}")
        return
    except Exception as e:
        print(f"❌ Unexpected error: {e}")
        return

if __name__ == "__main__":
    main()
