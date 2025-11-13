#!/usr/bin/env python3
"""
PHP Migration Model Evaluation Tool
Analyzes the effectiveness of LLM-based PHP version migration
"""

import sys
from pathlib import Path

# Add parent directory to path to import shared config
sys.path.insert(0, str(Path(__file__).parent.parent))
from config import LLM_EVALUATION_REPORTS_DIR, LLM_EVAL_DIR, SELECTED_100_FILES_DIR

import json
import pandas as pd
import numpy as np
import matplotlib.pyplot as plt
import seaborn as sns
from typing import Dict, List, Tuple, Any
from dataclasses import dataclass
from datetime import datetime
import warnings
warnings.filterwarnings('ignore')

@dataclass
class MigrationResult:
    """Data class for migration analysis results"""
    file_id: int
    filename: str
    original_issues: int
    resolved_issues: int
    remaining_issues: int
    resolution_rate: float
    size_category: str

class PHPMigrationEvaluator:
    """Comprehensive evaluation of PHP migration model performance"""
    
    def __init__(self, model_folder: str = None):
        # Use paths from shared config
        self.base_dir = LLM_EVAL_DIR  # LLM_eval folder for selection data
        self.migration_reports_dir = LLM_EVALUATION_REPORTS_DIR  # LLM_Migration/evaluation_reports
        
        # Auto-detect model folder if not specified
        if model_folder is None:
            model_folders = [d for d in self.migration_reports_dir.iterdir() 
                           if d.is_dir() and not d.name.startswith('.') and d.name != '__pycache__']
            if model_folders:
                # Take the first valid model folder found
                self.model_dir = model_folders[0]
            else:
                raise ValueError("No model folder found in LLM_Migration/evaluation_reports")
        else:
            self.model_dir = self.migration_reports_dir / model_folder
            
        # Extract and sanitize model name from folder
        self.model_name = self._sanitize_model_name(self.model_dir.name)
        self.model_folder_name = self.model_dir.name  # Keep original folder name
        
        # Create output directory for this model in LLM_eval
        self.output_dir = self.base_dir / self.model_folder_name
        self.output_dir.mkdir(exist_ok=True)
        
        self.selection_data = None
        self.model_data = None
        self.individual_files = {}
        self.evaluation_results = []
    
    def _sanitize_model_name(self, folder_name: str) -> str:
        """Convert folder name to readable model name"""
        # Replace underscores with spaces and capitalize words
        sanitized = folder_name.replace('_', ' ')
        
        # Handle special cases for common model naming patterns
        # Handle Meta-Llama specifically
        if 'meta llama' in sanitized.lower():
            # Replace pattern like "meta llama llama 3 3 70b instruct"
            sanitized = sanitized.replace('meta llama llama', 'Meta-Llama')
            sanitized = sanitized.replace('meta llama', 'Meta-Llama')
        
        # Handle other common patterns
        sanitized = sanitized.replace('llama', 'Llama')
        sanitized = sanitized.replace('instruct', 'Instruct')
        sanitized = sanitized.replace('gpt', 'GPT')
        sanitized = sanitized.replace('claude', 'Claude')
        sanitized = sanitized.replace('gemini', 'Gemini')
        
        # Fix version numbers (e.g., "3 3" -> "3.3")
        import re
        # Pattern to match version numbers like "3 3 70b" -> "3.3 70B"
        sanitized = re.sub(r'(\d+)\s+(\d+)\s+(\d+)([a-z])', r'\1.\2 \3\4', sanitized)
        sanitized = re.sub(r'(\d+)([a-z])', r'\1\2', sanitized.upper())
        
        # Capitalize each word properly
        words = sanitized.split()
        capitalized_words = []
        for word in words:
            # Handle version numbers and special formatting
            if 'B' in word.upper() and any(c.isdigit() for c in word):
                capitalized_words.append(word.upper())
            elif word.replace('.', '').isdigit():
                capitalized_words.append(word)
            else:
                capitalized_words.append(word.capitalize())
        
        return ' '.join(capitalized_words)
        
    def load_data(self):
        """Load all necessary data files"""
        print("Loading evaluation data...")
        
        # Load selection summary from dataset/selected_100_files
        selection_file = SELECTED_100_FILES_DIR / "selection_summary.csv"
        self.selection_data = pd.read_csv(selection_file)
        print(f"Loaded {len(self.selection_data)} original files")
        
        # Load detailed selection data for version analysis from dataset/selected_100_files
        selection_json_file = SELECTED_100_FILES_DIR / "selection_data.json"
        with open(selection_json_file, 'r', encoding='utf-8') as f:
            self.selection_data_detailed = json.load(f)
        
        # Load model results
        model_file = self.model_dir / "summary.csv"
        self.model_data = pd.read_csv(model_file)
        print(f"Loaded {len(self.model_data)} model output files")
        
        # Load individual evaluation files
        individual_dir = self.model_dir / "individual_files"
        for json_file in individual_dir.glob("*.json"):
            with open(json_file, 'r', encoding='utf-8') as f:
                data = json.load(f)
                file_id = int(json_file.stem.split('_')[0])
                self.individual_files[file_id] = data
        
        print(f"Loaded {len(self.individual_files)} individual evaluation files")
        
    def analyze_migration_effectiveness(self) -> List[MigrationResult]:
        """Analyze the effectiveness of migration for each file"""
        print("Analyzing migration effectiveness...")
        
        results = []
        
        for _, row in self.selection_data.iterrows():
            file_id = row['file_id']
            
            # Get corresponding model data
            model_row = self.model_data[self.model_data['file_id'] == file_id]
            if model_row.empty:
                continue
                
            model_row = model_row.iloc[0]
            
            # Calculate metrics
            original_issues = row['php_version_changes']
            remaining_issues = model_row['php_version_changes']
            resolved_issues = max(0, original_issues - remaining_issues)
            
            # Calculate resolution rate
            resolution_rate = (resolved_issues / original_issues * 100) if original_issues > 0 else 100
            
            result = MigrationResult(
                file_id=file_id,
                filename=row['filename'],
                original_issues=original_issues,
                resolved_issues=resolved_issues,
                remaining_issues=remaining_issues,
                resolution_rate=resolution_rate,
                size_category=row['size_category']
            )
            
            results.append(result)
        
        self.evaluation_results = results
        return results
    
    def analyze_php_version_patterns(self) -> Dict[str, Any]:
        """Analyze patterns in PHP version changes"""
        print("Analyzing PHP version migration patterns...")
        
        # Initialize version analysis with all PHP versions
        version_analysis = {
            'php_53': {'original': 0, 'remaining': 0, 'resolved': 0},
            'php_54': {'original': 0, 'remaining': 0, 'resolved': 0},
            'php_56': {'original': 0, 'remaining': 0, 'resolved': 0},
            'php_70': {'original': 0, 'remaining': 0, 'resolved': 0},
            'php_71': {'original': 0, 'remaining': 0, 'resolved': 0},
            'php_74': {'original': 0, 'remaining': 0, 'resolved': 0},
            'php_80': {'original': 0, 'remaining': 0, 'resolved': 0},
            'php_81': {'original': 0, 'remaining': 0, 'resolved': 0},
            'php_82': {'original': 0, 'remaining': 0, 'resolved': 0}
        }
        
        rule_effectiveness = {}
        
        # Function to extract PHP version from rule name
        def get_php_version_from_rule(rule_name):
            if 'Php53' in rule_name:
                return 'php_53'
            elif 'Php54' in rule_name:
                return 'php_54'
            elif 'Php56' in rule_name:
                return 'php_56'
            elif 'Php70' in rule_name:
                return 'php_70'
            elif 'Php71' in rule_name:
                return 'php_71'
            elif 'Php74' in rule_name:
                return 'php_74'
            elif 'Php80' in rule_name:
                return 'php_80'
            elif 'Php81' in rule_name:
                return 'php_81'
            elif 'Php82' in rule_name:
                return 'php_82'
            return None
        
        # Process original files to get original version-specific issues
        for file_data in self.selection_data_detailed:
            if 'rules_triggered' in file_data and file_data['rules_triggered']:
                for rule in file_data['rules_triggered']:
                    version = get_php_version_from_rule(rule)
                    if version and version in version_analysis:
                        version_analysis[version]['original'] += 1
        
        # Process post-migration files to get remaining version-specific issues
        for file_id, file_data in self.individual_files.items():
            if 'rector_analysis' in file_data:
                analysis = file_data['rector_analysis']
                
                # Process remaining version-specific changes
                if 'changes_by_php_version' in analysis:
                    for version, count in analysis['changes_by_php_version'].items():
                        if version in version_analysis:
                            version_analysis[version]['remaining'] += count
                
                # Process rule effectiveness
                if 'rules_triggered' in analysis:
                    for rule in analysis['rules_triggered']:
                        if rule not in rule_effectiveness:
                            rule_effectiveness[rule] = {'files_applied': 0, 'total_changes': 0}
                        rule_effectiveness[rule]['files_applied'] += 1
                        rule_effectiveness[rule]['total_changes'] += 1
        
        # Calculate resolved issues for each version
        for version in version_analysis:
            original = version_analysis[version]['original']
            remaining = version_analysis[version]['remaining']
            version_analysis[version]['resolved'] = max(0, original - remaining)
        
        return {
            'version_analysis': version_analysis,
            'rule_effectiveness': rule_effectiveness
        }
    
    def calculate_aggregate_metrics(self) -> Dict[str, float]:
        """Calculate overall model performance metrics"""
        print("Calculating aggregate performance metrics...")
        
        if not self.evaluation_results:
            return {}
        
        total_files = len(self.evaluation_results)
        total_original_issues = sum(r.original_issues for r in self.evaluation_results)
        total_resolved_issues = sum(r.resolved_issues for r in self.evaluation_results)
        total_remaining_issues = sum(r.remaining_issues for r in self.evaluation_results)
        
        # Calculate metrics by size category
        size_metrics = {}
        for category in ['small', 'medium', 'large', 'extra_large']:
            category_results = [r for r in self.evaluation_results if r.size_category == category]
            if category_results:
                size_metrics[category] = {
                    'files': len(category_results),
                    'avg_resolution_rate': np.mean([r.resolution_rate for r in category_results]),
                    'total_issues_resolved': sum(r.resolved_issues for r in category_results),
                    'total_original_issues': sum(r.original_issues for r in category_results)
                }
        
        # Perfect migration files (100% resolution)
        perfect_migrations = len([r for r in self.evaluation_results 
                                if r.resolution_rate == 100])
        
        # Files with poor performance (less than 50% resolution)
        poor_performance_files = len([r for r in self.evaluation_results if r.resolution_rate < 50])
        
        return {
            'total_files_analyzed': total_files,
            'total_original_issues': total_original_issues,
            'total_resolved_issues': total_resolved_issues,
            'total_remaining_issues': total_remaining_issues,
            'average_resolution_rate': np.mean([r.resolution_rate for r in self.evaluation_results]),
            'perfect_migrations': perfect_migrations,
            'perfect_migration_rate': (perfect_migrations / total_files * 100),
            'poor_performance_files': poor_performance_files,
            'poor_performance_rate': (poor_performance_files / total_files * 100),
            'size_category_metrics': size_metrics
        }
    
    def generate_detailed_report(self) -> str:
        """Generate comprehensive markdown report"""
        print("Generating detailed evaluation report...")
        
        metrics = self.calculate_aggregate_metrics()
        version_patterns = self.analyze_php_version_patterns()
        
        report = f"""# PHP Code Migration Model Evaluation Report
## {self.model_name} Model Performance Analysis

**Generated on:** {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}

## Executive Summary

This report presents a comprehensive evaluation of the {self.model_name} model's performance in migrating PHP code to newer versions. The analysis covers {metrics['total_files_analyzed']} PHP files with a total of {metrics['total_original_issues']} version-specific issues.

### Key Performance Indicators

- **Average Resolution Rate:** {metrics['average_resolution_rate']:.2f}%
- **Perfect Migrations:** {metrics['perfect_migrations']} files ({metrics['perfect_migration_rate']:.2f}%)
- **Total Issues Resolved:** {metrics['total_resolved_issues']}/{metrics['total_original_issues']} issues
- **Files with Poor Performance:** {metrics['poor_performance_files']} ({metrics['poor_performance_rate']:.2f}%)

## Detailed Analysis

### 1. Migration Success Distribution

"""
        
        # Add success distribution analysis
        success_ranges = {
            'Excellent (90-100%)': len([r for r in self.evaluation_results if r.resolution_rate >= 90]),
            'Good (70-89%)': len([r for r in self.evaluation_results if 70 <= r.resolution_rate < 90]),
            'Fair (50-69%)': len([r for r in self.evaluation_results if 50 <= r.resolution_rate < 70]),
            'Poor (0-49%)': len([r for r in self.evaluation_results if r.resolution_rate < 50])
        }
        
        for range_name, count in success_ranges.items():
            percentage = (count / len(self.evaluation_results) * 100)
            report += f"- **{range_name}:** {count} files ({percentage:.1f}%)\n"
        
        report += f"""
### 2. Performance by File Size Category

"""
        
        for category, data in metrics['size_category_metrics'].items():
            report += f"""#### {category.replace('_', ' ').title()} Files
- **Files Analyzed:** {data['files']}
- **Average Resolution Rate:** {data['avg_resolution_rate']:.2f}%
- **Issues Resolved:** {data['total_issues_resolved']}/{data['total_original_issues']}
- **Category Success Rate:** {(data['total_issues_resolved']/data['total_original_issues']*100):.2f}%

"""
        
        # Add top performing and challenging files
        top_performers = sorted(self.evaluation_results, key=lambda x: x.resolution_rate, reverse=True)[:10]
        challenging_files = sorted(self.evaluation_results, key=lambda x: x.resolution_rate)[:10]
        
        report += f"""
### 3. Top Performing Files

| File | Resolution Rate | Issues Resolved |
|------|-----------------|-----------------|
"""
        
        for result in top_performers:
            report += f"| {result.filename} | {result.resolution_rate:.1f}% | {result.resolved_issues}/{result.original_issues} |\n"
        
        report += f"""
### 4. Most Challenging Files

| File | Resolution Rate | Issues Remaining | Original Issues |
|------|-----------------|------------------|-----------------|
"""
        
        for result in challenging_files:
            report += f"| {result.filename} | {result.resolution_rate:.1f}% | {result.remaining_issues} | {result.original_issues} |\n"
        
        # Add version-specific analysis
        report += f"""
### 5. PHP Version Migration Analysis

"""
        
        version_data = version_patterns['version_analysis']
        for version, data in version_data.items():
            if data['original'] > 0:
                version_name = version.replace('_', '.').upper()
                resolution_rate = (data['resolved'] / data['original'] * 100) if data['original'] > 0 else 0
                report += f"- **{version_name}:** {data['resolved']}/{data['original']} issues resolved ({resolution_rate:.1f}%)\n"
        
        # Add footer
        report += f"""
---

*This report was generated using automated analysis tools. For production deployments, additional manual verification is recommended.*
"""
        
        return report
    
    def save_detailed_csv(self, filename: str = "evaluation_results.csv"):
        """Save detailed results to CSV for further analysis"""
        print(f"Saving detailed results to {filename}...")
        
        # Create detailed DataFrame
        detailed_data = []
        
        for result in self.evaluation_results:
            # Get additional data from original sources
            orig_row = self.selection_data[self.selection_data['file_id'] == result.file_id].iloc[0]
            model_row = self.model_data[self.model_data['file_id'] == result.file_id].iloc[0]
            
            # Get individual file analysis if available
            individual_data = self.individual_files.get(result.file_id, {})
            rector_analysis = individual_data.get('rector_analysis', {})
            
            row_data = {
                'file_id': result.file_id,
                'filename': result.filename,
                'original_path': orig_row['original_path'],
                'lines_of_code_original': orig_row['lines_of_code'],
                'lines_of_code_generated': model_row['lines_of_code'],
                'size_category': result.size_category,
                'original_issues': result.original_issues,
                'resolved_issues': result.resolved_issues,
                'remaining_issues': result.remaining_issues,
                'resolution_rate': result.resolution_rate,
                'has_diff': rector_analysis.get('has_diff', False)
            }
            
            detailed_data.append(row_data)
        
        # Create DataFrame and save
        df = pd.DataFrame(detailed_data)
        output_path = self.output_dir / filename
        df.to_csv(output_path, index=False)
        print(f"Detailed CSV saved to: {output_path}")
        
        return df
    
    def generate_summary_statistics(self) -> Dict[str, Any]:
        """Generate summary statistics for research paper"""
        metrics = self.calculate_aggregate_metrics()
        version_patterns = self.analyze_php_version_patterns()
        
        # Calculate additional research metrics
        resolution_rates = [r.resolution_rate for r in self.evaluation_results]
        
        # Statistical measures
        stats = {
            'descriptive_statistics': {
                'resolution_rate_mean': np.mean(resolution_rates),
                'resolution_rate_median': np.median(resolution_rates),
                'resolution_rate_std': np.std(resolution_rates),
                'resolution_rate_min': np.min(resolution_rates),
                'resolution_rate_max': np.max(resolution_rates)
            },
            'performance_metrics': metrics,
            'version_analysis': version_patterns,
            'correlation_analysis': {
                'file_size_vs_resolution': np.corrcoef(
                    [r.original_issues for r in self.evaluation_results],
                    [r.resolution_rate for r in self.evaluation_results]
                )[0, 1]
            }
        }
        
        return stats
    
    def run_complete_evaluation(self):
        """Run the complete evaluation pipeline"""
        print("=== PHP Migration Model Evaluation ===")
        print("Starting comprehensive analysis...")
        
        # Load data
        self.load_data()
        
        # Run analysis
        self.analyze_migration_effectiveness()
        
        # Generate outputs
        report = self.generate_detailed_report()
        detailed_df = self.save_detailed_csv()
        summary_stats = self.generate_summary_statistics()
        
        # Save report
        report_path = self.output_dir / "migration_evaluation_report.md"
        with open(report_path, 'w', encoding='utf-8') as f:
            f.write(report)
        print(f"Evaluation report saved to: {report_path}")
        
        # Save summary statistics
        stats_path = self.output_dir / "summary_statistics.json"
        with open(stats_path, 'w', encoding='utf-8') as f:
            json.dump(summary_stats, f, indent=2, default=str)
        print(f"Summary statistics saved to: {stats_path}")
        
        print("\n=== Evaluation Complete ===")
        print(f"Files analyzed: {len(self.evaluation_results)}")
        print(f"Average resolution rate: {summary_stats['performance_metrics']['average_resolution_rate']:.2f}%")
        
        return {
            'report': report,
            'detailed_data': detailed_df,
            'summary_statistics': summary_stats
        }

def main():
    """Main execution function"""
    evaluator = PHPMigrationEvaluator()
    results = evaluator.run_complete_evaluation()
    return results

if __name__ == "__main__":
    main()
