"""Generate documentation for dataset analysis."""

import json
import sys
from pathlib import Path
from datetime import datetime
from typing import Dict, List, Any

class RectorDocumentationGenerator:
    def __init__(self, reports_dir: str, dataset_dir: str):
        self.reports_dir = Path(reports_dir)
        self.dataset_dir = Path(dataset_dir)
        
        # Validate paths
        if not self.reports_dir.exists():
            raise FileNotFoundError(f"Reports directory not found: {reports_dir}")
        if not self.dataset_dir.exists():
            raise FileNotFoundError(f"Dataset directory not found: {dataset_dir}")
        
    def load_enhanced_metadata(self) -> Dict[str, Any]:
        metadata_file = self.reports_dir / "metadata.json"
        if not metadata_file.exists():
            raise FileNotFoundError(f"Metadata not found: {metadata_file}")
        
        with open(metadata_file, 'r', encoding='utf-8') as f:
            return json.load(f)
    
    def generate_enhanced_readme(self, metadata: Dict[str, Any]) -> str:
        dataset_info = metadata["dataset_info"]
        files = metadata["files"]
        
        # Calculate statistics - VERSION SPECIFIC ONLY
        total_files = len(files)
        files_with_changes = len([f for f in files if f["rector_analysis"]["php_version_changes"] > 0])
        total_changes = sum(f["rector_analysis"]["php_version_changes"] for f in files)
        
        # File size distribution based on size categories from the data
        size_category_counts = {}
        size_category_changes = {}
        size_category_loc_totals = {}
        
        # Count files by size category and calculate totals
        for file_data in files:
            category = file_data.get("size_category", "unknown")
            changes = file_data["rector_analysis"]["php_version_changes"]
            loc = file_data["file_metrics"]["lines_of_code"]
            
            if category not in size_category_counts:
                size_category_counts[category] = 0
                size_category_changes[category] = 0
                size_category_loc_totals[category] = 0
            
            size_category_counts[category] += 1
            size_category_changes[category] += changes
            size_category_loc_totals[category] += loc
        
        # Calculate averages for each category
        def get_avg_changes(category):
            return size_category_changes.get(category, 0) / max(size_category_counts.get(category, 1), 1)
        
        def get_avg_loc(category):
            return size_category_loc_totals.get(category, 0) / max(size_category_counts.get(category, 1), 1)
        
        # Totals - VERSION SPECIFIC FOCUS
        total_lines = sum(f["file_metrics"]["lines_of_code"] for f in files)
        avg_lines_per_file = total_lines / total_files
        
        # Build README content - VERSION SPECIFIC ONLY
        readme_content = f"""# PHP Version-Specific Migration Dataset

## Overview

This dataset contains 100 PHP files from WordPress 4.0, professionally analyzed using **Rector {dataset_info['rector_version']}** for **PHP version-specific migration opportunities only**.

## Professional Tool Analysis - Version Specific Focus

- **Analysis Method**: Rector PHP Version Upgrades Only
- **Rector Version**: {dataset_info['rector_version']}
- **Analysis Date**: {dataset_info['analysis_date'][:10]}
- **Target PHP Version**: 8.3
- **WordPress Version**: {dataset_info['wordpress_version']}
- **Focus**: PHP version-specific changes only (no code quality improvements)

## Dataset Statistics

### PHP Version Migration Overview
| Metric | Count | Percentage |
|--------|-------|------------|
| Total Files | {total_files} | 100% |
| Files with Version Changes | {files_with_changes} | {files_with_changes/total_files*100:.1f}% |
| Files Already Modern | {total_files - files_with_changes} | {(total_files - files_with_changes)/total_files*100:.1f}% |
| **Total PHP Version Changes** | **{total_changes}** | - |
| Average Changes per File | {total_changes/total_files:.1f} | - |

### PHP Version Evolution Analysis
This dataset focuses exclusively on transformations that move code from older PHP versions to PHP 8.3, including:

- **PHP 5.4+**: Array syntax modernization (`array()` → `[]`)
- **PHP 7.0+**: Null coalescing (`??`), spaceship operator (`<=>`), scalar type declarations
- **PHP 7.1+**: Nullable types, void return types
- **PHP 7.4+**: Arrow functions, property types, null coalescing assignment
- **PHP 8.0+**: Constructor property promotion, match expressions, named arguments
- **PHP 8.1+**: Enums, readonly properties, new initializers
- **PHP 8.2+**: Readonly classes, DNF types
- **PHP 8.3+**: Typed class constants, readonly amendments

## File Size Distribution

### Lines of Code Analysis
| Category | File Count | Line Count Range | Purpose | Avg Changes per File |
|----------|------------|------------|---------|---------------------|
| Small | {size_category_counts.get('small', 0)} | 1-200 | Simple migrations, focused patterns | {get_avg_changes('small'):.1f} |
| Medium | {size_category_counts.get('medium', 0)} | 201-500 | Balanced complexity, moderate context | {get_avg_changes('medium'):.1f} |
| Large | {size_category_counts.get('large', 0)} | 501-1000 | Complex migrations, substantial context | {get_avg_changes('large'):.1f} |
| Extra Large | {size_category_counts.get('extra_large', 0)} | 1000+ | Most complex, maximum context usage | {get_avg_changes('extra_large'):.1f} |

### Total Dataset Analysis
| Metric | Value | Average per File |
|--------|-------|------------------|
| **Total Files Analyzed** | **{total_files}** | - |
| **Total Lines** | **{total_lines:,}** | {avg_lines_per_file:.1f} |
| **Total PHP Version Changes** | **{total_changes:,}** | {total_changes/total_files:.1f} |
| **Files Requiring Migration** | **{files_with_changes}** ({files_with_changes/total_files*100:.1f}%) | - |
| **Files Already Modern** | **{total_files - files_with_changes}** ({(total_files - files_with_changes)/total_files*100:.1f}%) | - |

### Change Distribution Analysis
- **Change Density**: {total_changes/total_lines*1000:.2f} version changes per 1000 LOC
- **Migration Coverage**: 100% focus on PHP version upgrades only

## File Organization

```
rector_reports/
├── metadata.json              # Complete analysis results
├── summary.csv                # Statistical summary
├── rector_analysis_report.md  # Detailed analysis report
└── individual_files/          # Per-file reports
    └── <file_id>_<filename>_rector.json
```

## Data Files

### metadata.json
- File-level metrics and PHP version upgrade analysis
- PHP version upgrade opportunities by specific version
- Version-specific rule categorization

### summary.csv
- CSV format for analysis
- PHP version upgrade opportunity quantification
- Research-friendly data structure

### individual_files/*.json
- Detailed per-file Rector analysis
- Specific PHP version rules triggered
- Complete diff information

---

*Generated on {datetime.now().strftime('%Y-%m-%d %H:%M:%S')} using Rector {dataset_info['rector_version']}*
"""
        
        return readme_content
    
    def generate_analysis_report(self, metadata: Dict[str, Any]) -> str:
        """Generate detailed analysis report."""
        files = metadata["files"]
        
        # Detailed statistics by PHP version
        php_version_stats = {}
        for file_data in files:
            for version, count in file_data["rector_analysis"]["changes_by_php_version"].items():
                if count > 0:
                    if version not in php_version_stats:
                        php_version_stats[version] = {"files": 0, "total_changes": 0}
                    php_version_stats[version]["files"] += 1
                    php_version_stats[version]["total_changes"] += count
        
        report_content = f"""# Rector Professional Analysis Report

## Executive Summary

This report provides detailed analysis of 100 WordPress 4.0 PHP files using Rector professional migration tool.

## PHP Version Migration Analysis

### Version-Specific Upgrade Opportunities
"""
        
        for version, stats in sorted(php_version_stats.items()):
            version_name = version.replace('_', '.').upper()
            report_content += f"""
**{version_name} Features**
- Files ready for upgrade: {stats['files']}
- Total upgrade opportunities: {stats['total_changes']}
- Average per file: {stats['total_changes']/stats['files']:.1f}
"""
        
        # File size categorization analysis using size categories from metadata
        size_category_counts = {}
        size_category_changes = {}
        size_category_loc_totals = {}
        
        # Count files by size category and calculate totals
        for file_data in files:
            category = file_data.get("size_category", "unknown")
            changes = file_data["rector_analysis"]["php_version_changes"]
            loc = file_data["file_metrics"]["lines_of_code"]
            
            if category not in size_category_counts:
                size_category_counts[category] = 0
                size_category_changes[category] = 0
                size_category_loc_totals[category] = 0
            
            size_category_counts[category] += 1
            size_category_changes[category] += changes
            size_category_loc_totals[category] += loc
        
        # Calculate averages for each category - VERSION SPECIFIC FOCUS
        def get_avg_changes(category):
            return size_category_changes.get(category, 0) / max(size_category_counts.get(category, 1), 1)
        
        def get_avg_loc(category):
            return size_category_loc_totals.get(category, 0) / max(size_category_counts.get(category, 1), 1)
        
        def get_change_density(category):
            avg_changes = get_avg_changes(category)
            avg_loc = get_avg_loc(category)
            return (avg_changes / avg_loc * 100) if avg_loc > 0 else 0
        
        report_content += f"""

## File Size Distribution Analysis

### Dataset Categorization by Lines of Code

| Category | LOC Range | File Count | Percentage | Avg LOC | Avg Changes | Change Density |
|----------|-----------|------------|------------|---------|-------------|----------------|
| **Small** | 1-200 | {size_category_counts.get('small', 0)} | {size_category_counts.get('small', 0)/len(files)*100:.1f}% | {get_avg_loc('small'):.0f} | {get_avg_changes('small'):.1f} | {get_change_density('small'):.2f}% |
| **Medium** | 201-500 | {size_category_counts.get('medium', 0)} | {size_category_counts.get('medium', 0)/len(files)*100:.1f}% | {get_avg_loc('medium'):.0f} | {get_avg_changes('medium'):.1f} | {get_change_density('medium'):.2f}% |
| **Large** | 501-1000 | {size_category_counts.get('large', 0)} | {size_category_counts.get('large', 0)/len(files)*100:.1f}% | {get_avg_loc('large'):.0f} | {get_avg_changes('large'):.1f} | {get_change_density('large'):.2f}% |
| **Extra Large** | 1000+ | {size_category_counts.get('extra_large', 0)} | {size_category_counts.get('extra_large', 0)/len(files)*100:.1f}% | {get_avg_loc('extra_large'):.0f} | {get_avg_changes('extra_large'):.1f} | {get_change_density('extra_large'):.2f}% |

### Category Analysis Insights

**Small Files (1-200 LOC)**
- Count: {size_category_counts.get('small', 0)} files
- Purpose: Utility functions, simple configurations, focused components
- Migration Pattern: Quick fixes, minimal context required
- Research Value: Testing LLM accuracy on simple, well-defined tasks

**Medium Files (201-500 LOC)**
- Count: {size_category_counts.get('medium', 0)} files
- Purpose: Standard WordPress components, moderate complexity
- Migration Pattern: Balanced mix of upgrades and quality improvements
- Research Value: Optimal for comparing LLM vs professional tool performance

**Large Files (501-1000 LOC)**
- Count: {size_category_counts.get('large', 0)} files
- Purpose: Core WordPress functionality, complex business logic
- Migration Pattern: Higher change density, multiple migration opportunities
- Research Value: Testing LLM capability with substantial context requirements

**Extra Large Files (1000+ LOC)**
- Count: {size_category_counts.get('extra_large', 0)} files
- Purpose: Major WordPress core files, comprehensive functionality
- Migration Pattern: Highest absolute change counts, complex migrations
- Research Value: Maximum context window testing, real-world complexity assessment"""
        
        report_content += f"""

## File-by-File Analysis Summary - Version Specific

| File ID | Filename | LOC | PHP Version Changes |
|---------|----------|-----|-------------------|"""
        
        # Calculate totals for summary - VERSION SPECIFIC ONLY
        total_files = len(files)
        total_lines = sum(f["file_metrics"]["lines_of_code"] for f in files)
        total_changes = sum(f["rector_analysis"]["php_version_changes"] for f in files)
        files_with_no_changes = 0
        files_with_10plus_changes = 0
        
        for file_data in sorted(files, key=lambda x: x["file_id"]):
            rector = file_data["rector_analysis"]
            file_loc = file_data["file_metrics"]["lines_of_code"]
            php_version_changes = rector["php_version_changes"]
            
            # Count files with no changes and 10+ changes (version-specific threshold)
            if rector['php_version_changes'] == 0:
                files_with_no_changes += 1
            if rector['php_version_changes'] >= 10:
                files_with_10plus_changes += 1
            
            report_content += f"""
| {file_data['file_id']:03d} | {file_data['filename']} | {file_loc} | {php_version_changes} |"""
        
        # Add total analysis summary - VERSION SPECIFIC FOCUS
        avg_loc_per_file = total_lines / total_files if total_files > 0 else 0
        avg_changes_per_file = total_changes / total_files if total_files > 0 else 0
        change_density = (total_changes / total_lines * 100) if total_lines > 0 else 0
        
        report_content += f"""

## Total Analysis Summary - Version Specific Focus

### Dataset Overview
- **Total Files**: {total_files}
- **Total Lines of Code**: {total_lines:,}
- **Total PHP Version Changes**: {total_changes:,}

### Statistical Insights - Version Migration Focus
- **Average LOC per file**: {avg_loc_per_file:.0f} lines
- **Average version changes per file**: {avg_changes_per_file:.1f}
- **Change density**: {change_density:.2f}% (version changes per LOC)
- **Files with no changes**: {files_with_no_changes} ({files_with_no_changes/total_files*100:.0f}%)
- **Files with 10+ changes**: {files_with_10plus_changes} ({files_with_10plus_changes/total_files*100:.0f}%)

### Version Migration Impact
- **Pure PHP version upgrade focus**: 100% of changes are version-specific
- **Files most impacted by version changes**: Large core files (2000+ LOC)
- **Change-to-code ratio**: Higher version change density in smaller utility files"""
        
        report_content += f"""

## Research Validation - Version Migration Focus

### Professional Tool Credibility
- **Industry Standard**: Rector is widely used in professional PHP development
- **Version-Specific Rules**: Each change backed by specific PHP version transformation rules
- **Version Accuracy**: Targets exact PHP version features and syntax improvements
- **Community Validated**: Open source with extensive community testing and version-specific rule sets

### Data Integrity - Version Focus
- Complete version migration diff information preserved for verification
- Individual file reports enable detailed version analysis
- Categorization based on Rector's internal PHP version rule organization
- No subjective assessments or code quality estimations included
- Pure focus on PHP version evolution (5.x → 8.3)

---

*Version-specific analysis completed on {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}*
"""
        
        return report_content
    
    def save_documentation(self) -> None:
        """Generate and save all documentation files."""
        try:
            # Load metadata
            metadata = self.load_enhanced_metadata()
            
            # Skip README generation - data is already in metadata.json and summary.csv
            # readme_content = self.generate_enhanced_readme(metadata)
            # readme_file = self.dataset_dir / "README_rector.md"
            # with open(readme_file, 'w', encoding='utf-8') as f:
            #     f.write(readme_content)
            # print(f"📝 README saved: {readme_file}")
            
            # Generate analysis report
            report_content = self.generate_analysis_report(metadata)
            report_file = self.reports_dir / "rector_analysis_report.md"
            with open(report_file, 'w', encoding='utf-8') as f:
                f.write(report_content)
            print(f"📊 Analysis report saved: {report_file}")
            
            print("\n✅ Documentation generation complete!")
            
        except Exception as e:
            print(f"❌ Error generating documentation: {str(e)}")

def main():
    """Main execution function."""
    print("📚 Rector Documentation Generator")
    print("=" * 40)
    
    # Parse command line arguments
    if len(sys.argv) < 2:
        print("\n⚠️  Usage: python generate_documentation.py <profile>")
        print("\nAvailable profiles:")
        print("  all      - Process organized_dataset_All")
        print("  selected - Process selected_100_files")
        print("\nExample: python generate_documentation.py all")
        sys.exit(1)
    
    profile = sys.argv[1].lower()
    
    # Configure paths based on profile
    if profile == "all":
        reports_dir = "rector_reports_organized_dataset_All"
        dataset_dir = "organized_dataset_All"
        print(f"📁 Profile: ALL FILES (482 files)")
    elif profile == "selected":
        reports_dir = "rector_reports_selected_100_files"
        dataset_dir = "selected_100_files"
        print(f"📁 Profile: SELECTED 100 FILES")
    else:
        print(f"❌ Unknown profile: {profile}")
        print("   Use 'all' or 'selected'")
        sys.exit(1)
    
    print(f"📂 Reports: {reports_dir}")
    print(f"📂 Dataset: {dataset_dir}")
    print()
    
    try:
        generator = RectorDocumentationGenerator(reports_dir, dataset_dir)
        generator.save_documentation()
    except FileNotFoundError as e:
        print(f"❌ Error: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()
