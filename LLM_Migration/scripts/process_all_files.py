#!/usr/bin/env python3
"""
Batch Rector Analysis Processor
==============================

Process all 100 files in the organized dataset with Rector analysis.
Generates individual reports and aggregated metadata.
"""

import json
import os
import csv
from pathlib import Path
from datetime import datetime
from typing import Dict, List, Any

# Add parent directory to path to import shared modules
import sys
sys.path.insert(0, str(Path(__file__).parent.parent))
from rector_analyzer import RectorAnalyzer
from core.config import SELECTED_100_FILES_DIR, LLM_EVALUATION_REPORTS_DIR, LLM_NEW_VERSION_DIR

class BatchRectorProcessor:
    """Process LLM-generated files with Rector for evaluation."""
    
    def __init__(self, model_name: str):
        """Initialize processor for LLM evaluation mode only."""
        # Use shared config paths for LLM evaluation
        self.dataset_dir = LLM_NEW_VERSION_DIR / model_name
        self.reports_dir = LLM_EVALUATION_REPORTS_DIR / model_name
        self.analyzer = RectorAnalyzer(reports_dir=str(self.reports_dir))
        self.model_name = model_name
        self.evaluation_mode = True
        self.selection_metadata, self.selection_categories = self.load_selection_metadata()
    
    def load_selection_metadata(self) -> tuple[Dict[str, str], Dict[int, str]]:
        """Load original paths and categories from selection_summary.csv."""
        # Always load from the shared source location
        selection_file = SELECTED_100_FILES_DIR / "selection_summary.csv"
        metadata = {}
        categories = {}
        
        if selection_file.exists():
            try:
                with open(selection_file, 'r', encoding='utf-8') as f:
                    reader = csv.DictReader(f)
                    for row in reader:
                        file_id = int(row['file_id'])
                        size_category = row['size_category']
                        categories[file_id] = size_category
                        
                print(f"✅ Loaded selection metadata for {len(categories)} files")
            except Exception as e:
                print(f"⚠️  Could not load selection metadata: {e}")
        else:
            print(f"⚠️  Selection metadata not found at: {selection_file}")
        
        return metadata, categories

    def get_original_path(self, filename: str) -> str:
        """Get original path for a filename - in evaluation mode, use LLM-generated path."""
        # In evaluation mode, use the filename as-is since it's from LLM output
        return f"llm_generated/{self.model_name}/{filename}"
    
    def find_all_php_files(self) -> List[Path]:
        """Find all PHP files recursively in the organized dataset."""
        php_files = []
        
        # Recursive search - works for both flat and folder structures
        for php_file in self.dataset_dir.rglob("*.php"):
            php_files.append(php_file)
        
        if not php_files:
            print(f"⚠️  No PHP files found in {self.dataset_dir}")
        
        # Sort by filename for consistent processing order
        php_files.sort(key=lambda x: x.name)
        return php_files
    
    def process_single_file(self, file_path: Path) -> Dict[str, Any]:
        """Process a single file and return results."""
        try:
            print(f"📄 Processing {file_path.name}...")
            
            # Get file metrics
            file_metrics = self.analyzer.get_file_metrics(str(file_path))
            
            # Run Rector analysis
            analysis_result = self.analyzer.analyze_single_file(str(file_path))
            
            if "error" in analysis_result:
                print(f"❌ Error analyzing {file_path.name}: {analysis_result['error']}")
                return None
            
            # Save individual report
            report_path = self.analyzer.save_individual_report(analysis_result, file_path.name)
            
            # Extract file ID from filename (e.g., "001_options-writing.php" -> 1)
            file_id_match = file_path.name.split('_')[0]
            try:
                file_id = int(file_id_match)
            except ValueError:
                file_id = 0
            
            # Create structured result
            result = {
                "file_id": file_id,
                "filename": file_path.name,
                "original_path": self.get_original_path(file_path.name),
                "file_metrics": file_metrics,
                "rector_analysis": analysis_result["rector_analysis"],
                "analysis_metadata": analysis_result["analysis_metadata"],
                "report_path": report_path
            }
            
            print(f"✅ {file_path.name}: {result['rector_analysis']['php_version_changes']} version changes found")
            return result
            
        except Exception as e:
            print(f"❌ Exception processing {file_path.name}: {str(e)}")
            return None
    
    def process_all_files(self) -> List[Dict[str, Any]]:
        """Process all files in the dataset."""
        php_files = self.find_all_php_files()
        print(f"🚀 Found {len(php_files)} PHP files to process")
        print("=" * 60)
        
        results = []
        
        # Process files (using single thread to avoid Rector conflicts)
        for file_path in php_files:
            result = self.process_single_file(file_path)
            if result:
                results.append(result)
        
        print("\n🎉 Batch processing complete!")
        print(f"✅ Successfully processed: {len(results)} files")
        print(f"❌ Failed to process: {len(php_files) - len(results)} files")
        
        return results
    
    def generate_enhanced_metadata(self, results: List[Dict[str, Any]]) -> Dict[str, Any]:
        """Generate enhanced metadata JSON from all results."""
        dataset_info = {
            "version": "4.0_llm_evaluation",
            "total_files": len(results),
            "analysis_method": "rector_php_version_upgrades_llm_evaluation",
            "llm_model": self.model_name,
            "rector_version": self.analyzer.rector_version,
            "analysis_date": datetime.now().isoformat(),
            "evaluation_type": "llm_generated_code_analysis",
            "focus": "php_version_specific_changes_on_llm_migrated_code"
        }
        
        metadata = {
            "dataset_info": dataset_info,
            "files": results
        }
        
        return metadata
    
    def generate_enhanced_csv(self, results: List[Dict[str, Any]]) -> str:
        """Generate enhanced CSV from all results - VERSION SPECIFIC ONLY."""
        csv_lines = [
            "file_id,filename,original_path,lines_of_code,file_size_kb,"
            "php_version_changes,has_version_changes,size_category"
        ]
        
        for result in results:
            rector = result["rector_analysis"]
            metrics = result["file_metrics"]
            file_id = result['file_id']
            
            # Get category from selection data
            size_category = self.selection_categories.get(file_id, 'unknown')
            
            csv_line = (
                f"{result['file_id']},"
                f"{result['filename']},"
                f"{result['original_path']},"
                f"{metrics['lines_of_code']},"
                f"{metrics['file_size_kb']:.1f},"
                f"{rector['php_version_changes']},"
                f"{rector['has_diff']},"
                f"{size_category}"
            )
            csv_lines.append(csv_line)
        
        return "\n".join(csv_lines)
    
    def save_results(self, results: List[Dict[str, Any]]) -> None:
        """Save all results to files."""
        # Create evaluation_reports directory
        self.reports_dir.mkdir(parents=True, exist_ok=True)
        
        # Generate and save enhanced metadata
        enhanced_metadata = self.generate_enhanced_metadata(results)
        metadata_file = self.reports_dir / "metadata.json"
        with open(metadata_file, 'w', encoding='utf-8') as f:
            json.dump(enhanced_metadata, f, indent=2, ensure_ascii=False)
        print(f"📊 Metadata saved: {metadata_file}")
        
        # Generate and save enhanced CSV
        enhanced_csv = self.generate_enhanced_csv(results)
        csv_file = self.reports_dir / "summary.csv"
        with open(csv_file, 'w', encoding='utf-8') as f:
            f.write(enhanced_csv)
        print(f"📈 CSV summary saved: {csv_file}")
        
        # Generate summary statistics
        self.print_summary_statistics(results)
    
    def print_summary_statistics(self, results: List[Dict[str, Any]]) -> None:
        """Print summary statistics."""
        total_files = len(results)
        total_changes = sum(r["rector_analysis"]["php_version_changes"] for r in results)
        files_with_changes = len([r for r in results if r["rector_analysis"]["php_version_changes"] > 0])
        
        print(f"\n📊 LLM EVALUATION - RECTOR ANALYSIS SUMMARY ({self.model_name.upper()})")
        print("=" * 60)
        print(f"LLM Model: {self.model_name}")
        print(f"Total files processed: {total_files}")
        print(f"Files with version changes: {files_with_changes}")
        print(f"Files with no version changes: {total_files - files_with_changes}")
        print(f"Total PHP version changes found: {total_changes}")
        print(f"Average version changes per file: {total_changes / total_files:.1f}")
        
        # Top files by version changes
        top_files = sorted(results, key=lambda x: x["rector_analysis"]["php_version_changes"], reverse=True)[:5]
        print(f"\n🔝 Top 5 files by PHP version changes:")
        for i, file_result in enumerate(top_files, 1):
            print(f"  {i}. {file_result['filename']}: {file_result['rector_analysis']['php_version_changes']} version changes")

def get_available_models():
    """Dynamically discover available models from new-version/ directory."""
    models = []
    
    if LLM_NEW_VERSION_DIR.exists():
        for item in LLM_NEW_VERSION_DIR.iterdir():
            if item.is_dir():
                # Check if the directory contains any .php files
                php_files = list(item.glob("*.php"))
                if php_files:
                    models.append(item.name)
    
    return sorted(models)

def main():
    """Main execution function."""
    import sys
    
    # Get available models dynamically
    available_models = get_available_models()
    
    # Check if model name is provided
    if len(sys.argv) < 2:
        print("❌ Error: Model name is required")
        print()
        print("Usage: python process_all_files.py <model_name>")
        print()
        if available_models:
            print(f"Available models: {', '.join(available_models)}")
        else:
            print("⚠️  No models found in new-version/ directory")
        sys.exit(1)
    
    model_name = sys.argv[1]
    
    if model_name not in available_models:
        print(f"❌ Error: Model '{model_name}' not found")
        print()
        if available_models:
            print(f"Available models: {', '.join(available_models)}")
        else:
            print("⚠️  No models found in new-version/ directory")
        sys.exit(1)
    
    print("🔬 LLM Evaluation - Rector Analysis Tool")
    print("=" * 50)
    print(f"Evaluating LLM-generated code from model: {model_name}")
    print(f"Input directory: {LLM_NEW_VERSION_DIR / model_name}")
    print(f"Output directory: {LLM_EVALUATION_REPORTS_DIR / model_name}")
    print()
    
    processor = BatchRectorProcessor(model_name=model_name)
    
    # Process all files
    results = processor.process_all_files()
    
    if results:
        # Save results
        processor.save_results(results)
        
        print("\n🎉 Analysis Complete!")
        print("✅ Enhanced metadata and CSV generated")
        print("✅ Individual file reports saved")
        
        print(f"\n📁 Check {LLM_EVALUATION_REPORTS_DIR / processor.model_name} directory for all outputs")
    else:
        print("❌ No files were successfully processed")

if __name__ == "__main__":
    main()
