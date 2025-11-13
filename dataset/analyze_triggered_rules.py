#!/usr/bin/env python3
"""
Rector Triggered Rules Analysis (Basic Version)
===============================================

Comprehensive analysis of Rector rules triggered across the dataset.
"""

import json
from pathlib import Path
from collections import defaultdict, Counter
from typing import Dict, List, Any, Tuple
from datetime import datetime

class TriggeredRulesAnalyzer:
    """Analyze triggered Rector rules across the dataset."""
    
    def __init__(self, reports_dir: str = "rector_reports"):
        self.reports_dir = Path(reports_dir)
        self.metadata = self.load_metadata()
    
    def load_metadata(self) -> Dict[str, Any]:
        """Load metadata from JSON file."""
        metadata_file = self.reports_dir / "metadata.json"
        with open(metadata_file, 'r', encoding='utf-8') as f:
            return json.load(f)
    
    def extract_rule_data(self) -> List[Dict[str, Any]]:
        """Extract rule data into a structured list."""
        rule_data = []
        
        for file_data in self.metadata["files"]:
            file_id = file_data["file_id"]
            filename = file_data["filename"]
            size_category = file_data.get("size_category", "unknown")
            total_changes = file_data["rector_analysis"]["php_version_changes"]
            
            # Extract each rule triggered
            rules = file_data["rector_analysis"]["rules_triggered"]
            
            for rule in rules:
                # Parse rule components
                rule_parts = rule.split("\\")
                php_version = rule_parts[1] if len(rule_parts) > 1 else "Unknown"
                rule_name = rule_parts[-1] if rule_parts else rule
                rule_category = rule_parts[-2] if len(rule_parts) > 2 else "Unknown"
                
                rule_data.append({
                    'file_id': file_id,
                    'filename': filename,
                    'size_category': size_category,
                    'total_file_changes': total_changes,
                    'rule_full_name': rule,
                    'php_version': php_version,
                    'rule_name': rule_name,
                    'rule_category': rule_category,
                })
        
        return rule_data
    
    def analyze_rule_frequency(self, rule_data: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        """Analyze rule frequency across files."""
        rule_stats = defaultdict(lambda: {
            'files_triggered': set(),
            'total_changes': 0,
            'php_version': '',
            'rule_name': '',
            'rule_full_name': ''
        })
        
        for rule in rule_data:
            key = rule['rule_full_name']
            rule_stats[key]['files_triggered'].add(rule['file_id'])
            rule_stats[key]['total_changes'] += rule['total_file_changes']
            rule_stats[key]['php_version'] = rule['php_version']
            rule_stats[key]['rule_name'] = rule['rule_name']
            rule_stats[key]['rule_full_name'] = rule['rule_full_name']
        
        # Convert to list
        result = []
        for rule_name, stats in rule_stats.items():
            files_count = len(stats['files_triggered'])
            result.append({
                'rule_full_name': stats['rule_full_name'],
                'php_version': stats['php_version'],
                'rule_name': stats['rule_name'],
                'files_triggered': files_count,
                'total_changes': stats['total_changes']
            })
        
        # Sort by frequency
        result.sort(key=lambda x: x['files_triggered'], reverse=True)
        return result
    
    def analyze_php_version_distribution(self, rule_data: List[Dict[str, Any]]) -> Dict[str, Dict[str, Any]]:
        """Analyze distribution of rules by PHP version."""
        version_stats = defaultdict(lambda: {
            'files_affected': set(),
            'unique_rules': set(),
            'total_rule_triggers': 0
        })
        
        for rule in rule_data:
            version = rule['php_version']
            version_stats[version]['files_affected'].add(rule['file_id'])
            version_stats[version]['unique_rules'].add(rule['rule_full_name'])
            version_stats[version]['total_rule_triggers'] += 1
        
        # Convert sets to counts
        result = {}
        for version, stats in version_stats.items():
            result[version] = {
                'files_affected': len(stats['files_affected']),
                'unique_rules': len(stats['unique_rules']),
                'total_rule_triggers': stats['total_rule_triggers']
            }
        
        # Sort by total triggers
        result = dict(sorted(result.items(), key=lambda x: x[1]['total_rule_triggers'], reverse=True))
        return result
    
    def analyze_rule_categories(self, rule_data: List[Dict[str, Any]]) -> Dict[str, Dict[str, Any]]:
        """Analyze rule categories (FuncCall, Array_, etc.)."""
        category_stats = defaultdict(lambda: defaultdict(lambda: {
            'files_affected': set(),
            'unique_rules': set()
        }))
        
        for rule in rule_data:
            category = rule['rule_category']
            version = rule['php_version']
            category_stats[category][version]['files_affected'].add(rule['file_id'])
            category_stats[category][version]['unique_rules'].add(rule['rule_full_name'])
        
        # Convert to final format
        result = {}
        for category, versions in category_stats.items():
            result[category] = {}
            for version, stats in versions.items():
                result[category][version] = {
                    'files_affected': len(stats['files_affected']),
                    'unique_rules': len(stats['unique_rules'])
                }
        
        return result
    
    def analyze_file_complexity(self, rule_data: List[Dict[str, Any]]) -> Dict[int, Dict[str, Any]]:
        """Analyze files by size category and rule triggers."""
        file_stats = defaultdict(lambda: {
            'filename': '',
            'size_category': '',
            'unique_rules': set(),
            'php_versions': set()
        })
        
        for rule in rule_data:
            file_id = rule['file_id']
            file_stats[file_id]['filename'] = rule['filename']
            file_stats[file_id]['size_category'] = rule['size_category']
            file_stats[file_id]['unique_rules'].add(rule['rule_full_name'])
            file_stats[file_id]['php_versions'].add(rule['php_version'])
        
        # Convert
        result = {}
        for file_id, stats in file_stats.items():
            # Map size categories to display format
            category_mapping = {
                'small': 'Small (1-200)',
                'medium': 'Medium (201-500)',
                'large': 'Large (501-1000)',
                'extra_large': 'Extra Large (1000+)',
                'unknown': 'Unknown'
            }
            
            display_category = category_mapping.get(stats['size_category'], 'Unknown')
            
            result[file_id] = {
                'filename': stats['filename'],
                'size_category': stats['size_category'],
                'loc_category': display_category,
                'unique_rules_triggered': len(stats['unique_rules']),
                'php_versions_affected': len(stats['php_versions'])
            }
        
        return result
    
    def generate_top_rules_report(self, rule_stats: List[Dict[str, Any]], top_n: int = 20) -> str:
        """Generate a report of top triggered rules."""
        top_rules = rule_stats[:top_n]
        
        report = f"""# Top {top_n} Most Frequently Triggered Rector Rules

## Executive Summary
- **Total Unique Rules**: {len(rule_stats)}
- **Most Common Rule**: {top_rules[0]['rule_name']} (triggered in {top_rules[0]['files_triggered']} files)
- **Analysis Date**: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}

## Top {top_n} Rules by Frequency

| Rank | Rule Name | PHP Version | Files Affected |
|------|-----------|-------------|----------------|"""
        
        for idx, rule in enumerate(top_rules, 1):
            report += f"""
| {idx} | `{rule['rule_name']}` | {rule['php_version']} | {rule['files_triggered']} |"""
        
        return report
    
    def generate_php_version_analysis(self, version_stats: Dict[str, Dict[str, Any]]) -> str:
        """Generate PHP version distribution analysis."""
        report = """

## PHP Version Migration Analysis

### Rule Distribution by PHP Version

| PHP Version | Files Affected | Unique Rules | Total Rule Triggers |
|-------------|----------------|--------------|---------------------|"""
        
        for version, stats in version_stats.items():
            version_display = version.replace('Php', 'PHP ')
            report += f"""
| {version_display} | {stats['files_affected']} | {stats['unique_rules']} | {stats['total_rule_triggers']} |"""
        
        return report
    
    def generate_rule_category_analysis(self, category_stats: Dict[str, Dict[str, Any]]) -> str:
        """Generate rule category analysis."""
        # We need to calculate this from the original rule_data to get accurate unique file counts
        # This function will be called with category_stats, but we need to recalculate from rule_data
        return self._generate_rule_category_analysis_fixed()
    
    def _generate_rule_category_analysis_fixed(self) -> str:
        """Generate rule category analysis with correct unique file counting."""
        # Re-extract rule data to get accurate counts
        rule_data = self.extract_rule_data()
        
        # Calculate unique files and rules per category
        category_stats = defaultdict(lambda: {
            'files_affected': set(),
            'unique_rules': set()
        })
        
        for rule in rule_data:
            category = rule['rule_category']
            category_stats[category]['files_affected'].add(rule['file_id'])
            category_stats[category]['unique_rules'].add(rule['rule_full_name'])
        
        # Convert to final format
        category_summary = {}
        for category, stats in category_stats.items():
            category_summary[category] = {
                'files_affected': len(stats['files_affected']),
                'unique_rules': len(stats['unique_rules'])
            }
        
        # Sort by impact
        sorted_categories = sorted(category_summary.items(), 
                                 key=lambda x: x[1]['files_affected'], reverse=True)
        
        report = """

## Rule Category Analysis

### Most Common Rule Categories

| Category | Files Impacted | Unique Rules | Description |
|----------|-----------------|--------------|-------------|"""
        
        # Add descriptions for common categories
        category_descriptions = {
            'FuncCall': 'Function call transformations and modernizations',
            'Array_': 'Array syntax and function improvements',
            'Assign': 'Assignment operator enhancements', 
            'Ternary': 'Ternary operator improvements',
            'Variable': 'Variable handling updates',
            'ClassMethod': 'Class method signature changes',
            'Property': 'Property declaration improvements',
            'Class_': 'Class structure modifications',
            'If_': 'Conditional statement optimizations',
            'BinaryOp': 'Binary operation improvements',
            'String_': 'String handling enhancements'
        }
        
        for category, stats in sorted_categories:
            desc = category_descriptions.get(category, 'Code structure improvements')
            report += f"""
| `{category}` | {stats['files_affected']} | {stats['unique_rules']} | {desc} |"""
        
        return report
    
    def analyze_migration_patterns(self, rule_data: List[Dict[str, Any]]) -> str:
        """Analyze migration patterns across PHP versions."""
        # Find files that span multiple PHP versions
        file_versions = defaultdict(set)
        
        for rule in rule_data:
            file_versions[rule['file_id']].add(rule['php_version'])
        
        # Analyze version combinations
        version_combos = defaultdict(int)
        single_version_count = 0
        multi_version_count = 0
        
        for file_id, versions in file_versions.items():
            if len(versions) == 1:
                single_version_count += 1
            else:
                multi_version_count += 1
                sorted_versions = tuple(sorted(versions))
                version_combos[sorted_versions] += 1
        
        total_files = len(file_versions)
        
        report = f"""

## Migration Pattern Analysis

### Files Requiring Multi-Version Updates

| Pattern Type | File Count | Percentage | Migration Complexity |
|--------------|------------|------------|---------------------|
| Single PHP Version | {single_version_count} | {single_version_count/total_files*100:.1f}% | 🟢 Simple |
| Multiple PHP Versions | {multi_version_count} | {multi_version_count/total_files*100:.1f}% | 🔴 Complex |

### Most Common Version Combinations

"""
        
        for combo, count in sorted(version_combos.items(), key=lambda x: x[1], reverse=True)[:10]:
            versions_str = " + ".join([v.replace('Php', 'PHP ') for v in combo])
            report += f"- **{versions_str}**: {count} files\n"
        
        return report
    
    def generate_file_complexity_insights(self, file_stats: Dict[int, Dict[str, Any]]) -> str:
        """Generate insights about file size categories vs rule triggers."""
        # Group by size category
        complexity_by_size = defaultdict(lambda: {
            'files': 0,
            'total_rules': 0,
            'total_versions': 0
        })
        
        for file_id, stats in file_stats.items():
            category = stats['loc_category']
            complexity_by_size[category]['files'] += 1
            complexity_by_size[category]['total_rules'] += stats['unique_rules_triggered']
            complexity_by_size[category]['total_versions'] += stats['php_versions_affected']
        
        report = """

## File Size Category vs Migration Opportunities

### Rule Distribution by File Size Category

| File Size Category | Files | Avg Rules per File | Avg PHP Versions |
|--------------------|-------|-------------------|------------------|"""
        
        # Use updated category order matching the new LOC ranges
        category_order = ['Small (1-200)', 'Medium (201-500)', 'Large (501-1000)', 'Extra Large (1000+)']
        
        for category in category_order:
            if category in complexity_by_size:
                stats = complexity_by_size[category]
                files = stats['files']
                avg_rules = stats['total_rules'] / files if files > 0 else 0
                avg_versions = stats['total_versions'] / files if files > 0 else 0
                
                report += f"""
| {category} | {files} | {avg_rules:.1f} | {avg_versions:.1f} |"""
        
        report += """

### Key Insights
- **File size categories** help organize files by complexity
- **Rule distribution** shows migration patterns across different file sizes
- **Version coverage** indicates how many PHP versions each category touches
"""
        
        return report
    
    def save_detailed_analysis(self) -> None:
        """Generate and save comprehensive analysis."""
        print("🔍 Extracting rule data...")
        rule_data = self.extract_rule_data()
        
        print("📊 Analyzing rule frequency...")
        rule_stats = self.analyze_rule_frequency(rule_data)
        
        print("🔢 Analyzing PHP version distribution...")
        version_stats = self.analyze_php_version_distribution(rule_data)
        
        print("📋 Analyzing rule categories...")
        category_stats = self.analyze_rule_categories(rule_data)
        
        print("📏 Analyzing file complexity...")
        file_stats = self.analyze_file_complexity(rule_data)
        
        # Generate comprehensive report
        report = f"""# Rector Rules Analysis Report - Comprehensive

*Generated on {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}*

## Dataset Overview
- **Total Files Analyzed**: {len(self.metadata['files'])}
- **Total Unique Rules Triggered**: {len(rule_stats)}
- **Total Rule Applications**: {len(rule_data)}
- **PHP Versions Covered**: {len(version_stats)}

"""
        
        report += self.generate_top_rules_report(rule_stats, 20)
        report += self.generate_php_version_analysis(version_stats)
        report += self.generate_rule_category_analysis(category_stats)
        report += self.analyze_migration_patterns(rule_data)
        report += self.generate_file_complexity_insights(file_stats)
        
        # Add detailed rule breakdown
        report += """

## Complete Rule Reference

### All Triggered Rules (Alphabetical)

| Rule Name | PHP Version | Files | Full Class Name |
|-----------|-------------|-------|-----------------|"""
        
        sorted_rules = sorted(rule_stats, key=lambda x: x['rule_name'])
        for rule in sorted_rules:
            report += f"""
| `{rule['rule_name']}` | {rule['php_version']} | {rule['files_triggered']} | `{rule['rule_full_name']}` |"""
        
        # Save report
        report_file = self.reports_dir / "triggered_rules_analysis.md"
        with open(report_file, 'w', encoding='utf-8') as f:
            f.write(report)
        print(f"📝 Comprehensive analysis saved: {report_file}")
        
        print("✅ Triggered rules analysis complete!")

def main():
    """Main execution function."""
    import sys
    
    print("🔍 Rector Triggered Rules Analyzer")
    print("=" * 50)
    
    reports_dir = sys.argv[1] if len(sys.argv) > 1 else "rector_reports_organized_dataset_All"
    analyzer = TriggeredRulesAnalyzer(reports_dir=reports_dir)
    analyzer.save_detailed_analysis()

if __name__ == "__main__":
    main()
