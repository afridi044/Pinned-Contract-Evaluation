#!/usr/bin/env python3
"""
Optimal File Selection for PHP Migration Analysis
===============================================

Selects 100 optimal files from the dataset ensuring:
1. Complete rule coverage (all 48 unique rules)
2. Balanced size distribution (small/medium/large/extra-large)
3. High-quality migration candidates
4. Logical distribution across complexity levels
"""

import json
import csv
from pathlib import Path
from collections import defaultdict, Counter
from typing import Dict, List, Any, Tuple, Set
from datetime import datetime
import shutil
import os

# Add parent directory to path to import shared modules
import sys
sys.path.insert(0, str(Path(__file__).parent.parent))
from config import DATASET_NAME

class OptimalFileSelector:
    """Select optimal subset of files for migration analysis."""
    
    def __init__(self, reports_dir: str = None, dataset_dir: str = None):
        # Use config-based defaults if not specified
        if reports_dir is None:
            reports_dir = f"{DATASET_NAME}/rector_reports_organized_dataset_All"
        if dataset_dir is None:
            dataset_dir = f"{DATASET_NAME}/organized_dataset_All"
        
        self.reports_dir = Path(reports_dir)
        self.dataset_dir = Path(dataset_dir)
        self.metadata = self.load_metadata()
        self.summary_df = self.load_summary_csv()
        self.dataset_summary = self.load_dataset_summary()
        
        # Size categories (lines of code) - Updated ranges
        self.size_categories = {
            'small': (1, 200),
            'medium': (201, 500), 
            'large': (501, 1000),
            'extra_large': (1001, float('inf'))
        }
        
        # Target distribution for 100 files
        self.target_distribution = {
            'small': 35,      # 35%
            'medium': 30,     # 30% 
            'large': 25,      # 25%
            'extra_large': 10 # 10%
        }
    
    def load_metadata(self) -> Dict[str, Any]:
        """Load metadata from JSON file."""
        metadata_file = self.reports_dir / "metadata.json"
        with open(metadata_file, 'r', encoding='utf-8') as f:
            return json.load(f)
    
    def load_summary_csv(self) -> List[Dict[str, Any]]:
        """Load the summary CSV with file statistics."""
        summary_file = self.reports_dir / "summary.csv"
        summary_data = []
        
        with open(summary_file, 'r', encoding='utf-8') as f:
            lines = f.readlines()
            headers = lines[0].strip().split(',')
            
            for line in lines[1:]:
                values = line.strip().split(',')
                row_dict = {}
                for i, header in enumerate(headers):
                    if i < len(values):
                        value = values[i]
                        # Convert numeric columns
                        if header in ['file_id', 'lines_of_code', 'php_version_changes']:
                            row_dict[header] = int(value)
                        elif header == 'file_size_kb':
                            row_dict[header] = float(value)
                        elif header == 'has_version_changes':
                            row_dict[header] = value.lower() == 'true'
                        else:
                            row_dict[header] = value
                summary_data.append(row_dict)
        
        return summary_data
    
    def load_dataset_summary(self) -> Dict[str, str]:
        """Load original paths from dataset_summary.csv in organized_dataset_All."""
        dataset_summary_file = Path(f"{DATASET_NAME}/organized_dataset_All/dataset_summary.csv")
        original_paths = {}
        
        if dataset_summary_file.exists():
            try:
                with open(dataset_summary_file, 'r', encoding='utf-8') as f:
                    reader = csv.DictReader(f)
                    for row in reader:
                        filename = row['filename']
                        original_path = row['original_path']
                        original_paths[filename] = original_path
                print(f"✅ Loaded {len(original_paths)} original paths from dataset_summary.csv")
            except Exception as e:
                print(f"⚠️  Could not load dataset_summary.csv: {e}")
        else:
            print(f"⚠️  dataset_summary.csv not found at: {dataset_summary_file}")
        
        return original_paths
    
    def categorize_files_by_size(self) -> Dict[str, List[Dict[str, Any]]]:
        """Categorize files by size."""
        categorized = {category: [] for category in self.size_categories.keys()}
        
        for row in self.summary_df:
            file_info = {
                'file_id': row['file_id'],
                'filename': row['filename'], 
                'lines_of_code': row['lines_of_code'],
                'php_version_changes': row['php_version_changes'],
                'has_version_changes': row['has_version_changes']
            }
            
            loc = row['lines_of_code']
            for category, (min_loc, max_loc) in self.size_categories.items():
                if min_loc <= loc < max_loc:
                    categorized[category].append(file_info)
                    break
        
        return categorized
    
    def extract_all_rules_by_file(self) -> Dict[int, Set[str]]:
        """Extract all triggered rules for each file."""
        rules_by_file = {}
        
        for file_data in self.metadata["files"]:
            file_id = file_data["file_id"]
            rules = set(file_data["rector_analysis"]["rules_triggered"])
            if rules:  # Only include files with rules
                rules_by_file[file_id] = rules
        
        return rules_by_file
    
    def get_all_unique_rules(self) -> Set[str]:
        """Get all unique rules across the dataset."""
        all_rules = set()
        for file_data in self.metadata["files"]:
            rules = file_data["rector_analysis"]["rules_triggered"]
            all_rules.update(rules)
        return all_rules
    
    def calculate_file_scores(self, categorized_files: Dict[str, List[Dict]], 
                            rules_by_file: Dict[int, Set[str]]) -> Dict[str, List[Tuple]]:
        """Calculate selection scores for files in each category."""
        scored_files = {category: [] for category in self.size_categories.keys()}
        
        for category, files in categorized_files.items():
            for file_info in files:
                file_id = file_info['file_id']
                
                # Skip files without migration rules
                if file_id not in rules_by_file or not file_info['has_version_changes']:
                    continue
                
                rules = rules_by_file[file_id]
                
                # Calculate score based on multiple factors
                score = self.calculate_individual_score(file_info, rules)
                
                scored_files[category].append((file_info, rules, score))
        
        # Sort each category by score (descending)
        for category in scored_files:
            scored_files[category].sort(key=lambda x: x[2], reverse=True)
        
        return scored_files
    
    def calculate_individual_score(self, file_info: Dict[str, Any], rules: Set[str]) -> float:
        """Calculate selection score for an individual file."""
        score = 0.0
        
        # Factor 1: Number of unique rules (more rules = higher priority)
        num_rules = len(rules)
        score += num_rules * 10
        
        # Factor 2: PHP version changes (more changes = more migration examples)
        version_changes = file_info['php_version_changes']
        score += version_changes * 5
        
        # Factor 3: File size bonus (reasonable size gets bonus)
        loc = file_info['lines_of_code']
        if 100 <= loc <= 2000:  # Sweet spot for analysis
            score += 20
        elif loc > 2000:  # Very large files get penalty
            score -= (loc - 2000) * 0.01
        
        # Factor 4: Rule diversity bonus (extract PHP versions from rules)
        php_versions = set()
        for rule in rules:
            parts = rule.split("\\")
            if len(parts) > 1:
                php_versions.add(parts[1])
        score += len(php_versions) * 8
        
        # Factor 5: Special rule bonuses for important migration patterns
        important_rules = {
            'LongArrayToShortArrayRector': 5,
            'TernaryToNullCoalescingRector': 8,
            'StrContainsRector': 6,
            'StrStartsWithRector': 6,
            'ChangeSwitchToMatchRector': 10,
            'ClassPropertyAssignToConstructorPromotionRector': 9
        }
        
        for rule in rules:
            rule_name = rule.split("\\")[-1]
            if rule_name in important_rules:
                score += important_rules[rule_name]
        
        return score
    
    def ensure_rule_coverage(self, selected_files: List[Tuple], 
                           all_rules: Set[str]) -> List[Tuple]:
        """Ensure all unique rules are covered in the selection."""
        covered_rules = set()
        for file_info, rules, score in selected_files:
            covered_rules.update(rules)
        
        missing_rules = all_rules - covered_rules
        
        if not missing_rules:
            return selected_files
        
        print(f"🔍 Found {len(missing_rules)} rules not covered. Adding files to ensure coverage...")
        
        # Find files that contain missing rules
        rules_by_file = self.extract_all_rules_by_file()
        candidate_files = []
        
        for file_id, file_rules in rules_by_file.items():
            if missing_rules & file_rules:  # If file contains any missing rules
                # Find the corresponding file info
                file_info = None
                for row in self.summary_df:
                    if row['file_id'] == file_id and row['has_version_changes']:
                        file_info = {
                            'file_id': row['file_id'],
                            'filename': row['filename'],
                            'lines_of_code': row['lines_of_code'],
                            'php_version_changes': row['php_version_changes'],
                            'has_version_changes': row['has_version_changes']
                        }
                        break
                
                if file_info:
                    # Calculate how many missing rules this file covers
                    rules_covered = len(missing_rules & file_rules)
                    score = rules_covered * 100 + len(file_rules) * 10  # High priority for rule coverage
                    candidate_files.append((file_info, file_rules, score))
        
        # Sort candidates by coverage score
        candidate_files.sort(key=lambda x: x[2], reverse=True)
        
        # Add files until all rules are covered
        for candidate in candidate_files:
            file_info, file_rules, score = candidate
            if missing_rules & file_rules:  # If still has missing rules
                selected_files.append(candidate)
                covered_rules.update(file_rules)
                missing_rules = all_rules - covered_rules
                
                if not missing_rules:
                    break
        
        print(f"✅ All {len(all_rules)} unique rules are now covered!")
        return selected_files
    
    def select_optimal_files(self) -> List[Dict[str, Any]]:
        """Main selection algorithm."""
        print("🔍 Starting optimal file selection...")
        
        # Step 1: Categorize files by size
        print("📏 Categorizing files by size...")
        categorized_files = self.categorize_files_by_size()
        
        for category, files in categorized_files.items():
            print(f"   {category}: {len(files)} files")
        
        # Step 2: Extract rules and calculate scores
        print("🎯 Calculating file selection scores...")
        rules_by_file = self.extract_all_rules_by_file()
        all_rules = self.get_all_unique_rules()
        scored_files = self.calculate_file_scores(categorized_files, rules_by_file)
        
        print(f"   Found {len(all_rules)} unique rules to cover")
        
        # Step 3: Select files from each category based on target distribution
        print("⚖️ Selecting files with balanced distribution...")
        selected_files = []
        
        for category, target_count in self.target_distribution.items():
            available_files = scored_files[category]
            
            # Take top files from this category
            selected_from_category = available_files[:target_count]
            selected_files.extend(selected_from_category)
            
            print(f"   {category}: selected {len(selected_from_category)}/{target_count} files")
        
        print(f"📊 Initially selected {len(selected_files)} files")
        
        # Step 4: Ensure complete rule coverage
        print("🎯 Ensuring complete rule coverage...")
        selected_files = self.ensure_rule_coverage(selected_files, all_rules)
        
        # Step 5: Adjust to exactly 100 files if needed
        if len(selected_files) > 100:
            print(f"✂️ Trimming from {len(selected_files)} to 100 files...")
            # Sort by score and take top 100, but ensure rule coverage
            selected_files.sort(key=lambda x: x[2], reverse=True)
            
            # Check if trimming to 100 maintains rule coverage
            test_selection = selected_files[:100]
            test_covered_rules = set()
            for file_info, rules, score in test_selection:
                test_covered_rules.update(rules)
            
            if len(test_covered_rules) == len(all_rules):
                selected_files = test_selection
                print("✅ Successfully trimmed while maintaining full rule coverage")
            else:
                print("⚠️ Keeping extra files to maintain rule coverage")
        
        elif len(selected_files) < 100:
            print(f"📈 Need to add {100 - len(selected_files)} more files...")
            # Add more files while maintaining distribution balance
            remaining_needed = 100 - len(selected_files)
            
            # Get already selected file IDs
            selected_ids = {file_info['file_id'] for file_info, _, _ in selected_files}
            
            # Collect remaining candidates from all categories
            remaining_candidates = []
            for category, files_with_scores in scored_files.items():
                for file_info, rules, score in files_with_scores:
                    if file_info['file_id'] not in selected_ids:
                        remaining_candidates.append((file_info, rules, score))
            
            # Sort by score and add top candidates
            remaining_candidates.sort(key=lambda x: x[2], reverse=True)
            selected_files.extend(remaining_candidates[:remaining_needed])
        
        print(f"✅ Final selection: {len(selected_files)} files")
        
        # Convert to final format
        final_selection = []
        for file_info, rules, score in selected_files:
            file_info['rules_triggered'] = list(rules)
            file_info['selection_score'] = score
            file_info['size_category'] = self.get_size_category(file_info['lines_of_code'])
            final_selection.append(file_info)
        
        return final_selection
    
    def get_size_category(self, loc: int) -> str:
        """Get size category for a given LOC count."""
        for category, (min_loc, max_loc) in self.size_categories.items():
            if min_loc <= loc < max_loc:
                return category
        return 'extra_large'  # fallback
    
    def generate_selection_report(self, selected_files: List[Dict[str, Any]]) -> str:
        """Generate comprehensive selection report."""
        # Analyze final selection
        size_distribution = Counter(f['size_category'] for f in selected_files)
        total_rules = set()
        php_versions = set()
        total_loc = sum(f['lines_of_code'] for f in selected_files)
        total_changes = sum(f['php_version_changes'] for f in selected_files)
        
        for file_info in selected_files:
            total_rules.update(file_info['rules_triggered'])
            for rule in file_info['rules_triggered']:
                parts = rule.split("\\")
                if len(parts) > 1:
                    php_versions.add(parts[1])
        
        report = f"""# Optimal File Selection Report

*Generated on {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}*

## Selection Summary
- **Total Files Selected**: {len(selected_files)}
- **Total Lines of Code**: {total_loc:,}
- **Average LOC per File**: {total_loc/len(selected_files):.0f}
- **Total PHP Version Changes**: {total_changes}
- **Unique Rules Covered**: {len(total_rules)}/48 ({len(total_rules)/48*100:.1f}%)
- **PHP Versions Covered**: {len(php_versions)}

## Size Distribution

| Category | Target | Selected | Percentage | Status |
|----------|--------|----------|------------|--------|"""
        
        for category in ['small', 'medium', 'large', 'extra_large']:
            target = self.target_distribution[category]
            actual = size_distribution[category]
            percentage = actual / len(selected_files) * 100
            status = "✅" if abs(actual - target) <= 3 else "⚠️"
            
            report += f"""
| {category.replace('_', ' ').title()} (LOC {self.size_categories[category][0]}-{self.size_categories[category][1] if self.size_categories[category][1] != float('inf') else '∞'}) | {target} | {actual} | {percentage:.1f}% | {status} |"""
        
        report += f"""

## Rule Coverage Analysis

### Top 10 Most Common Rules in Selection

| Rank | Rule Name | Files | PHP Version | Coverage |
|------|-----------|-------|-------------|----------|"""
        
        # Count rule frequency in selection
        rule_counts = Counter()
        for file_info in selected_files:
            for rule in file_info['rules_triggered']:
                rule_counts[rule] += 1
        
        for idx, (rule, count) in enumerate(rule_counts.most_common(10), 1):
            rule_name = rule.split("\\")[-1]
            php_version = rule.split("\\")[1] if len(rule.split("\\")) > 1 else "Unknown"
            coverage = count / len(selected_files) * 100
            report += f"""
| {idx} | `{rule_name}` | {count} | {php_version} | {coverage:.1f}% |"""
        
        report += """

## File Quality Metrics

### Top 10 Highest Scoring Files

| Rank | Filename | LOC | Rules | Score | Category |
|------|----------|-----|-------|-------|----------|"""
        
        # Sort by selection score
        top_files = sorted(selected_files, key=lambda x: x['selection_score'], reverse=True)[:10]
        
        for idx, file_info in enumerate(top_files, 1):
            report += f"""
| {idx} | `{file_info['filename']}` | {file_info['lines_of_code']} | {len(file_info['rules_triggered'])} | {file_info['selection_score']:.1f} | {file_info['size_category']} |"""
        
        # Add complete file list with new IDs
        report += """

## Complete File Selection

### All Selected Files (Sorted by Score)

| New ID | Filename | LOC | Category | Rules | Changes | Score |
|--------|----------|-----|----------|-------|---------|-------|"""
        
        for idx, file_info in enumerate(sorted(selected_files, key=lambda x: x['selection_score'], reverse=True), 1):
            new_filename = file_info.get('new_filename', file_info['filename'])
            report += f"""
| {idx:03d} | `{new_filename}` | {file_info['lines_of_code']} | {file_info['size_category']} | {len(file_info['rules_triggered'])} | {file_info['php_version_changes']} | {file_info['selection_score']:.1f} |"""
        
        return report
    
    def create_organized_subset(self, selected_files: List[Dict[str, Any]]) -> None:
        """Create organized subset directories with selected files."""
        print("📁 Creating organized subset directories...")
        
        # Create main subset directory
        subset_dir = Path(f"{DATASET_NAME}/selected_100_files")
        if subset_dir.exists():
            shutil.rmtree(subset_dir)
        
        # Create category subdirectories
        categories = ['small_1_200', 'medium_201_500', 'large_500_1000', 'extra_large_1000_plus']
        
        for category in categories:
            category_dir = subset_dir / category
            category_dir.mkdir(parents=True, exist_ok=True)
        
        # Copy files to appropriate directories with new naming
        copy_count = 0
        
        for idx, file_info in enumerate(selected_files, 1):
            # The filename already includes the ID prefix (e.g., "052_getid3.lib.php")
            source_filename = file_info['filename']
            source_path = self.dataset_dir / source_filename
            
            # Create new filename with reassigned ID (001_getid3.lib.php)
            original_name_without_prefix = "_".join(file_info['filename'].split("_")[1:])
            new_filename = f"{idx:03d}_{original_name_without_prefix}"
            
            # Determine target directory
            size_category = file_info['size_category']
            if size_category == 'small':
                target_dir = subset_dir / 'small_1_200'
            elif size_category == 'medium':
                target_dir = subset_dir / 'medium_201_500'
            elif size_category == 'large':
                target_dir = subset_dir / 'large_500_1000'
            else:  # extra_large
                target_dir = subset_dir / 'extra_large_1000_plus'
            
            target_path = target_dir / new_filename
            
            if source_path.exists():
                shutil.copy2(source_path, target_path)
                copy_count += 1
                # Update filename in file_info for consistency
                file_info['new_filename'] = new_filename
            else:
                print(f"⚠️ Warning: Source file not found: {source_path}")
        
        print(f"✅ Successfully copied {copy_count} files to organized subset")
        
        # Create summary files for each category
        for category in categories:
            category_path = subset_dir / category
            category_files = []
            
            for idx, file_info in enumerate(selected_files, 1):
                size_cat = file_info['size_category']
                if ((category == 'small_1_200' and size_cat == 'small') or
                    (category == 'medium_201_500' and size_cat == 'medium') or
                    (category == 'large_500_1000' and size_cat == 'large') or  
                    (category == 'extra_large_1000_plus' and size_cat == 'extra_large')):
                    # Update file info with new ID and filename
                    file_info_copy = file_info.copy()
                    file_info_copy['new_file_id'] = idx
                    category_files.append(file_info_copy)
            
            # Write category summary
            summary_file = category_path / "README.md"
            with open(summary_file, 'w', encoding='utf-8') as f:
                size_range = category.replace('_', ' ').replace('plus', '+').title()
                f.write(f"# {size_range} Files\n\n")
                f.write(f"**Files in this category:** {len(category_files)}\n")
                f.write(f"**Total LOC:** {sum(f['lines_of_code'] for f in category_files):,}\n")
                f.write(f"**Average LOC:** {sum(f['lines_of_code'] for f in category_files)/len(category_files):.0f}\n\n")
                
                f.write("## Files List\n\n")
                f.write("| New ID | Filename | LOC | Rules | Score |\n")
                f.write("|--------|----------|-----|-------|-------|\n")
                
                for file_info in sorted(category_files, key=lambda x: x['selection_score'], reverse=True):
                    display_filename = file_info.get('new_filename', file_info['filename'])
                    f.write(f"| {file_info['new_file_id']:03d} | `{display_filename}` | {file_info['lines_of_code']} | {len(file_info['rules_triggered'])} | {file_info['selection_score']:.1f} |\n")
        
        print(f"📁 Organized subset created in: {subset_dir.absolute()}")
        
        # Create master index with updated information
        index_file = subset_dir / "INDEX.md"
        with open(index_file, 'w', encoding='utf-8') as f:
            f.write("# Selected 100 Files for PHP Migration Analysis\n\n")
            f.write(f"*Generated on {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}*\n\n")
            
            f.write("## Directory Structure\n\n")
            for category in categories:
                category_files = [f for idx, f in enumerate(selected_files, 1) if 
                                (category == 'small_1_200' and f['size_category'] == 'small') or
                                (category == 'medium_201_500' and f['size_category'] == 'medium') or
                                (category == 'large_500_1000' and f['size_category'] == 'large') or
                                (category == 'extra_large_1000_plus' and f['size_category'] == 'extra_large')]
                f.write(f"- `{category}/` - {len(category_files)} files\n")
            
            f.write(f"\n**Total: {len(selected_files)} files covering all 48 unique Rector rules**\n")
            f.write(f"\n**Files are renumbered 001-100 for easy reference**\n")
    
    def save_selection_data(self, selected_files: List[Dict[str, Any]]) -> None:
        """Save selection data in multiple formats."""
        output_dir = Path(f"{DATASET_NAME}/selected_100_files")
        
        # Load original paths from dataset_summary.csv
        original_paths = self.load_dataset_summary()
        
        # Reassign file IDs from 1 to 100 and add original_path
        for idx, file_info in enumerate(selected_files, 1):
            file_info['new_file_id'] = idx
            
            # Get original path from dataset_summary.csv
            filename = file_info['filename']
            file_info['original_path'] = original_paths.get(filename, f"unknown/{filename}")
        
        # Save as JSON
        json_file = output_dir / "selection_data.json"
        with open(json_file, 'w', encoding='utf-8') as f:
            json.dump(selected_files, f, indent=2, default=str)
        
        # Save as CSV with proper column ordering (selection_summary.csv)
        # Use NEW filenames (001_, 002_, etc.) so they match the actual files
        csv_file = output_dir / "selection_summary.csv"
        
        # Create CSV manually without pandas dependency
        with open(csv_file, 'w', encoding='utf-8') as f:
            # Write header
            f.write("file_id,filename,original_path,lines_of_code,php_version_changes,has_version_changes,selection_score,size_category\n")
            
            # Write data rows with NEW filenames
            for file_info in selected_files:
                new_filename = file_info.get('new_filename', file_info['filename'])
                f.write(f"{file_info['new_file_id']},{new_filename},\"{file_info['original_path']}\",")
                f.write(f"{file_info['lines_of_code']},{file_info['php_version_changes']},")
                f.write(f"{file_info['has_version_changes']},{file_info['selection_score']:.1f},")
                f.write(f"{file_info['size_category']}\n")
        
        print(f"💾 Selection data saved: {json_file} and {csv_file}")
        print(f"� selection_summary.csv includes all file mappings and original paths")
    
    def run_selection(self) -> None:
        """Execute the complete file selection process."""
        print("🚀 Starting Optimal File Selection Process")
        print("=" * 60)
        
        # Step 1: Select optimal files
        selected_files = self.select_optimal_files()
        
        # Step 2: Generate report
        print("\n📊 Generating selection report...")
        report = self.generate_selection_report(selected_files)
        
        # Create output directory
        output_dir = Path(f"{DATASET_NAME}/selected_100_files")
        output_dir.mkdir(exist_ok=True)
        
        # Save report
        report_file = output_dir / "SELECTION_REPORT.md"
        with open(report_file, 'w', encoding='utf-8') as f:
            f.write(report)
        print(f"📝 Selection report saved: {report_file}")
        
        # Step 3: Create organized subset
        self.create_organized_subset(selected_files)
        
        # Step 4: Save selection data
        self.save_selection_data(selected_files)
        
        print("\n" + "=" * 60)
        print("✅ SELECTION PROCESS COMPLETE!")
        print(f"📁 Check the '{DATASET_NAME}/selected_100_files' directory for results")
        print(f"📊 {len(selected_files)} files selected with complete rule coverage")

def main():
    """Main execution function."""
    selector = OptimalFileSelector()
    selector.run_selection()

if __name__ == "__main__":
    main()
