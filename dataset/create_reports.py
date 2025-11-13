"""
Complete Rector Pipeline
===========================================

Orchestrates the complete process:
1. Analyze all files with Rector
2. Generate enhanced metadata and CSV
3. Create comprehensive documentation
4. Analyze triggered rules across dataset
5. Validate results and generate summary
"""

import sys
import time
from pathlib import Path
from datetime import datetime

# Add parent directory to path to import shared config
sys.path.insert(0, str(Path(__file__).parent.parent))
from config import RECTOR_PHP_PATH, VENDOR_PATH, RECTOR_BIN
from rector_analyzer import RectorAnalyzer

# Import our custom modules from current directory
from process_all_files import BatchRectorProcessor
from generate_documentation import RectorDocumentationGenerator
from analyze_triggered_rules import TriggeredRulesAnalyzer

# ============================================================================
# DATASET CONFIGURATION
# ============================================================================
# Dataset will be specified via command line argument or default to selected_100_files
# ============================================================================

class RectorDatasetAnalyzer:
    """Complete dataset enhancement pipeline."""
    
    def __init__(self, dataset_folder: str, reports_folder: str):
        self.start_time = time.time()
        self.dataset_folder = dataset_folder
        self.reports_folder = reports_folder
        
    def print_header(self):
        """Print pipeline header."""
        print("🔬 RECTOR DATASET ENHANCEMENT PIPELINE")
        print("=" * 60)
        print("Transforming WordPress 4.0 dataset with professional Rector analysis")
        print(f"Started: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
        print()
    
    def validate_prerequisites(self) -> bool:
        """Validate that all prerequisites are met."""
        print("🔍 Validating prerequisites...")
        
        # Check if dataset directory exists
        dataset_dir = Path(self.dataset_folder)
        if not dataset_dir.exists():
            print(f"❌ {self.dataset_folder} directory not found")
            return False
        
        # Check if rector is installed (using shared config)
        if not RECTOR_BIN.exists():
            print("❌ Rector not found. Please run 'composer install' first")
            return False
        
        # Check if rector.php config exists (using shared config)
        if not RECTOR_PHP_PATH.exists():
            print("❌ rector.php configuration not found")
            return False
        
        # Count PHP files recursively (works for both flat and folder structures)
        php_files = list(dataset_dir.rglob("*.php"))
        
        print(f"✅ Found {len(php_files)} PHP files to process")
        print("✅ Rector installation verified")
        print("✅ Configuration files present")
        print()
        
        return len(php_files) > 0
    
    def run_complete_analysis(self) -> bool:
        """Run the complete analysis pipeline."""
        try:
            print("🚀 PHASE 1: Rector Analysis")
            print(f"   Dataset: {self.dataset_folder}")
            print(f"   Reports: {self.reports_folder}")
            print("-" * 30)
            
            # Initialize processor with configured dataset directory and reports directory
            processor = BatchRectorProcessor(dataset_dir=self.dataset_folder, reports_dir=self.reports_folder)
            
            # Process all files
            results = processor.process_all_files()
            
            if not results:
                print("❌ No files were successfully processed")
                return False
            
            print(f"✅ Successfully analyzed {len(results)} files")
            
            # Save results
            processor.save_results(results)
            print("✅ Enhanced metadata and CSV generated")
            print()
            
            return True
            
        except Exception as e:
            print(f"❌ Error during analysis: {str(e)}")
            return False
    
    def generate_documentation(self) -> bool:
        """Generate comprehensive documentation."""
        try:
            print("📚 PHASE 2: Documentation Generation")
            print("-" * 30)
            
            generator = RectorDocumentationGenerator(reports_dir=self.reports_folder, dataset_dir=self.dataset_folder)
            generator.save_documentation()
            
            print("✅ Documentation generated successfully")
            print()
            
            return True
            
        except Exception as e:
            print(f"❌ Error generating documentation: {str(e)}")
            return False
    
    def analyze_triggered_rules(self) -> bool:
        """Analyze triggered rules across the dataset."""
        try:
            print("📊 PHASE 3: Triggered Rules Analysis")
            print("-" * 30)
            
            analyzer = TriggeredRulesAnalyzer(reports_dir=self.reports_folder)
            analyzer.save_detailed_analysis()
            
            print("✅ Triggered rules analysis complete")
            print()
            
            return True
            
        except Exception as e:
            print(f"❌ Error analyzing triggered rules: {str(e)}")
            return False
    
    def print_completion_summary(self):
        """Print completion summary."""
        elapsed_time = time.time() - self.start_time
        minutes = int(elapsed_time // 60)
        seconds = int(elapsed_time % 60)
        
        print("🎉 PIPELINE COMPLETE!")
        print("=" * 60)
        print(f"⏱️  Total execution time: {minutes}m {seconds}s")
        print()
        print("📁 Generated Files:")
        print(f"   📊 {self.reports_folder}/metadata.json                      - Complete analysis results")
        print(f"   📈 {self.reports_folder}/summary.csv                        - Statistical summary")
        print(f"    {self.reports_folder}/rector_analysis_report.md          - Detailed analysis report")
        print(f"   📊 {self.reports_folder}/triggered_rules_analysis.md        - Rules analysis report")
        print(f"   📂 {self.reports_folder}/individual_files/                  - Individual file reports")
    
    def run_pipeline(self) -> bool:
        """Run the complete enhancement pipeline."""
        self.print_header()
        
        # Validate prerequisites
        if not self.validate_prerequisites():
            print("❌ Prerequisites not met. Please fix issues and try again.")
            return False
        
        # Run analysis
        if not self.run_complete_analysis():
            print("❌ Analysis phase failed.")
            return False
        
        # Generate documentation
        if not self.generate_documentation():
            print("❌ Documentation phase failed.")
            return False
        
        # Analyze triggered rules
        if not self.analyze_triggered_rules():
            print("❌ Triggered rules analysis phase failed.")
            return False
        
        # Print summary
        self.print_completion_summary()
        return True

def main():
    """Main execution function."""
    # Parse command line arguments
    if len(sys.argv) < 2:
        print("⚠️  Usage: python enhance_dataset.py <dataset_folder>")
        print("\nAvailable datasets:")
        print("  organized_dataset_All - Analyze all 482 files")
        print("  selected_100_files    - Analyze selected 100 files")
        print("\nExample: python enhance_dataset.py organized_dataset_All")
        sys.exit(1)
    
    dataset_folder = sys.argv[1]
    reports_folder = f"rector_reports_{dataset_folder}"
    
    print(f"📁 Dataset: {dataset_folder}")
    print(f"📂 Reports: {reports_folder}\n")
    
    enhancer = RectorDatasetAnalyzer(dataset_folder, reports_folder)
    
    try:
        success = enhancer.run_pipeline()
        sys.exit(0 if success else 1)
    except KeyboardInterrupt:
        print("\n\n⚠️  Pipeline interrupted by user")
        sys.exit(1)
    except Exception as e:
        print(f"\n\n❌ Unexpected error: {str(e)}")
        sys.exit(1)

if __name__ == "__main__":
    main()
