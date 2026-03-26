#!/usr/bin/env python3
"""PHP migration visualization tool."""

import sys
from pathlib import Path

# Add parent directory to path to import shared config
sys.path.insert(0, str(Path(__file__).parent.parent))
from config import LLM_CODE_ANALYSIS_DIR, LLM_EVAL_DIR, LLM_EVAL_SUBDIR

import pandas as pd
import numpy as np
import matplotlib.pyplot as plt
import seaborn as sns
import warnings
from scipy.interpolate import make_interp_spline
warnings.filterwarnings('ignore')

plt.style.use('seaborn-v0_8')
sns.set_palette("husl")

class PHPVisualizer:
    """Generate  visualizations for PHP migration evaluation"""
    
    def __init__(self, model_folder: str = None):
        # Use paths from shared config
        self.base_dir = LLM_EVAL_DIR  # LLM_eval folder
        self.migration_reports_dir = LLM_CODE_ANALYSIS_DIR  # LLM_Migration/code_analysis
        
        # Auto-detect model folder if not specified
        if model_folder is None:
            model_folders = [d for d in self.migration_reports_dir.iterdir() 
                           if d.is_dir() and not d.name.startswith('.') and d.name != '__pycache__']
            if model_folders:
                self.model_dir = model_folders[0]
            else:
                raise ValueError("No model folder found in LLM_Migration/evaluation_reports")
        else:
            self.model_dir = self.migration_reports_dir / model_folder
        
        self.model_name = self._sanitize_model_name(self.model_dir.name)
        self.model_folder_name = self.model_dir.name
        
        # Create output directory for this model in LLM_eval
        model_output_dir = LLM_EVAL_SUBDIR / self.model_folder_name
        self.output_dir = model_output_dir / "visualizations"
        self.output_dir.mkdir(parents=True, exist_ok=True)
        
        # Set up plotting style
        plt.rcParams.update({
            'font.size': 12,
            'axes.titlesize': 14,
            'axes.labelsize': 12,
            'figure.dpi': 300,
            'savefig.dpi': 300,
            'savefig.bbox': 'tight'
        })
    
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
        """Load evaluation results"""
        try:
            # Load from model-specific folder
            model_output_dir = LLM_EVAL_SUBDIR / self.model_folder_name
            csv_path = model_output_dir / "evaluation_results.csv"
            
            if not csv_path.exists():
                print(f"Error: CSV file not found at {csv_path}")
                return False
            
            self.df = pd.read_csv(csv_path)
            print(f"Loaded data for {len(self.df)} files")
            
            # Check if required columns exist
            required_columns = ['discharge_rate', 'size_category', 'discharged_obligations', 'original_obligations']
            missing_columns = [col for col in required_columns if col not in self.df.columns]
            if missing_columns:
                print(f"Error: Missing columns in CSV: {missing_columns}")
                print(f"Available columns: {list(self.df.columns)}")
                return False
            
            return True
        except FileNotFoundError as e:
            print(f"Error loading data: {e}")
            return False
        except Exception as e:
            print(f"Error loading CSV: {e}")
            return False
    
    def create_performance_overview(self):
        """Create  overview of model performance"""
        fig, axes = plt.subplots(1, 3, figsize=(18, 6))
        fig.suptitle(f'{self.model_name} Performance Overview', fontsize=16, fontweight='bold')
        
        # 1. Obligation Discharge Rate Distribution
        ax1 = axes[0]
        discharge_rates = self.df['discharge_rate']
        ax1.hist(discharge_rates, bins=15, alpha=0.7, color='skyblue', edgecolor='black')
        ax1.axvline(discharge_rates.mean(), color='red', linestyle='--', 
                   label=f'Mean: {discharge_rates.mean():.1f}%')
        ax1.set_xlabel('Obligation Discharge Rate (%)')
        ax1.set_ylabel('Number of Files')
        ax1.set_title('Distribution of Obligation Discharge Rates (PCE)')
        ax1.legend()
        ax1.grid(True, alpha=0.3)
        
        # 2. Performance by File Size Category
        ax2 = axes[1]
        category_stats = self.df.groupby('size_category').agg({
            'discharged_obligations': 'sum',
            'original_obligations': 'sum'
        })
        
        categories = category_stats.index
        discharge_pct = (category_stats['discharged_obligations'] / category_stats['original_obligations'] * 100)
        
        bars = ax2.bar(categories, discharge_pct, color='lightgreen', alpha=0.8)
        ax2.set_xlabel('File Size Category')
        ax2.set_ylabel('Obligation Discharge Rate (%)')
        ax2.set_title('Obligation Discharge by File Size')
        ax2.tick_params(axis='x', rotation=45)
        ax2.grid(True, alpha=0.3)
        
        # Add value labels
        for bar, value in zip(bars, discharge_pct):
            height = bar.get_height()
            ax2.text(bar.get_x() + bar.get_width()/2., height + 1,
                    f'{value:.1f}%', ha='center', va='bottom')
        
        # 3. Obligation Discharge Success Categories
        ax3 = axes[2]
        success_categories = {
            'Excellent\n(90-100%)': len(self.df[self.df['discharge_rate'] >= 90]),
            'Good\n(70-89%)': len(self.df[(self.df['discharge_rate'] >= 70) & (self.df['discharge_rate'] < 90)]),
            'Fair\n(50-69%)': len(self.df[(self.df['discharge_rate'] >= 50) & (self.df['discharge_rate'] < 70)]),
            'Poor\n(0-49%)': len(self.df[self.df['discharge_rate'] < 50])
        }
        
        colors = ['darkgreen', 'green', 'orange', 'red']
        
        # Filter out categories with zero values to avoid empty slices
        filtered_categories = {k: v for k, v in success_categories.items() if v > 0}
        filtered_colors = [colors[i] for i, (k, v) in enumerate(success_categories.items()) if v > 0]
        
        wedges, texts, autotexts = ax3.pie(filtered_categories.values(), 
                                          labels=filtered_categories.keys(), 
                                          autopct=lambda pct: f'{pct:.1f}%' if pct > 5 else '',
                                          colors=filtered_colors,
                                          startangle=90,
                                          pctdistance=0.85,
                                          labeldistance=1.1,
                                          textprops={'fontsize': 9})
        
        # Improve text formatting
        for autotext in autotexts:
            autotext.set_color('white')
            autotext.set_fontweight('bold')
            autotext.set_fontsize(10)
        
        for text in texts:
            text.set_fontsize(9)
            text.set_fontweight('bold')
        
        ax3.set_title('Distribution of Success Categories', fontsize=12, pad=20)
        
        plt.tight_layout()
        plt.savefig(self.output_dir / 'performance_overview.png')
        plt.close()
        print("Performance overview saved")
    
    def create_complexity_analysis(self):
        """Analyze obligation discharge by file complexity (PCE Framework)"""
        fig, axes = plt.subplots(1, 2, figsize=(12, 5))
        fig.suptitle('Obligation Discharge vs File Complexity', fontsize=16, fontweight='bold')
        
        # 1. Scatter plot: Complexity (original obligations) vs Discharge Rate
        ax1 = axes[0]
        scatter = ax1.scatter(self.df['original_obligations'], self.df['discharge_rate'], 
                             alpha=0.6, s=50, c='steelblue')
        ax1.set_xlabel('Original Obligations (Complexity)')
        ax1.set_ylabel('Obligation Discharge Rate (%)')
        ax1.set_title('Complexity vs Obligation Discharge Rate')
        ax1.grid(True, alpha=0.3)
        
        # Add trend line
        z = np.polyfit(self.df['original_obligations'], self.df['discharge_rate'], 1)
        p = np.poly1d(z)
        ax1.plot(self.df['original_obligations'], p(self.df['original_obligations']), "r--", alpha=0.8, linewidth=2)
        
        # 2. Performance by complexity bins
        ax2 = axes[1]
        # Create complexity bins
        complexity_bins = pd.cut(self.df['original_obligations'], bins=4, labels=['Low', 'Medium', 'High', 'Very High'])
        complexity_performance = self.df.groupby(complexity_bins)['discharge_rate'].mean()
        
        bars = ax2.bar(complexity_performance.index, complexity_performance.values, 
                      color='orange', alpha=0.7)
        ax2.set_xlabel('Complexity Level')
        ax2.set_ylabel('Average Obligation Discharge Rate (%)')
        ax2.set_title('Performance by Complexity Level')
        ax2.grid(True, alpha=0.3)
        
        # Add value labels
        for bar in bars:
            height = bar.get_height()
            ax2.text(bar.get_x() + bar.get_width()/2., height + 1,
                    f'{height:.1f}%', ha='center', va='bottom')
        
        plt.tight_layout()
        plt.savefig(self.output_dir / 'complexity_analysis.png')
        plt.close()
        print("Complexity analysis saved")
    
    def create_summary_statistics(self):
        """Create summary statistics chart (PCE Obligation Discharge Metrics)"""
        fig, ax = plt.subplots(1, 1, figsize=(10, 6))
        
        # Calculate key statistics
        stats = {
            'Perfect Discharges (100%)': len(self.df[self.df['discharge_rate'] == 100]),
            'Excellent (≥90%)': len(self.df[self.df['discharge_rate'] >= 90]),
            'Good (≥70%)': len(self.df[self.df['discharge_rate'] >= 70]),
            'Needs Improvement (<50%)': len(self.df[self.df['discharge_rate'] < 50])
        }
        
        # Create bar chart
        keys = list(stats.keys())
        values = list(stats.values())
        colors = ['darkgreen', 'green', 'lightgreen', 'red']
        total_files = len(self.df)
        
        bars = ax.bar(keys, values, color=colors, alpha=0.8)
        ax.set_ylabel('Number of Files')
        ax.set_title('Migration Performance Summary', fontsize=14, fontweight='bold')
        ax.tick_params(axis='x', rotation=45)
        
        # Add value labels with percentages
        for bar, key in zip(bars, keys):
            height = bar.get_height()
            percentage = height/total_files*100
            ax.text(bar.get_x() + bar.get_width()/2., height + 0.5,
                   f'{int(height)}\n({percentage:.1f}%)', 
                   ha='center', va='bottom', fontweight='bold')
        
        # Add total files info
        ax.text(0.02, 0.98, f'Total Files Analyzed: {total_files}', 
               transform=ax.transAxes, fontsize=12, fontweight='bold',
               verticalalignment='top', bbox=dict(boxstyle='round', facecolor='wheat', alpha=0.8))
        
        ax.grid(True, alpha=0.3)
        plt.tight_layout()
        plt.savefig(self.output_dir / 'summary_statistics.png')
        plt.close()
        print("Summary statistics saved")
    
    def create_research_charts(self):
        plt.rcParams.update({
            'font.family': 'serif',
            'font.size': 11,
            'axes.titlesize': 12,
            'axes.labelsize': 11,
            'figure.dpi': 300,
            'savefig.dpi': 300,
            'savefig.format': 'pdf',
            'savefig.bbox': 'tight'
        })
        
        # Chart 1: Overall Performance Summary
        fig, ax = plt.subplots(1, 1, figsize=(8, 5))
        
        perfect_rate = len(self.df[self.df['discharge_rate'] == 100]) / len(self.df) * 100
        high_performance_rate = len(self.df[self.df['discharge_rate'] >= 90]) / len(self.df) * 100
        avg_discharge = self.df['discharge_rate'].mean()
        
        metrics = ['Perfect\nDischarges', 'Excellent Discharge\n(≥90%)', 'Average\nDischarge']
        values = [perfect_rate, high_performance_rate, avg_discharge]
        colors = ['#228B22', '#32CD32', '#4682B4']
        
        bars = ax.bar(metrics, values, color=colors, alpha=0.8, edgecolor='black', linewidth=0.5)
        ax.set_ylabel('Percentage (%)')
        ax.set_title(f'{self.model_name} Performance Summary')
        ax.set_ylim(0, 100)
        
        # Add value labels
        for bar, value in zip(bars, values):
            height = bar.get_height()
            ax.text(bar.get_x() + bar.get_width()/2., height + 1,
                   f'{value:.1f}%', ha='center', va='bottom', fontweight='bold')
        
        ax.grid(True, alpha=0.3, axis='y')
        plt.tight_layout()
        plt.savefig(self.output_dir / 'performance_summary.pdf')
        plt.close()
        
        # Chart 2: Performance by File Size
        fig, ax = plt.subplots(1, 1, figsize=(8, 5))
        
        size_stats = self.df.groupby('size_category').agg({
            'discharge_rate': 'mean',
            'file_id': 'count'
        }).round(1)
        
        categories = [cat.replace('_', ' ').title() for cat in size_stats.index]
        means = size_stats['discharge_rate']
        counts = size_stats['file_id']
        
        bars = ax.bar(categories, means, color='steelblue', alpha=0.8, edgecolor='black', linewidth=0.5)
        
        ax.set_xlabel('File Size Category')
        ax.set_ylabel('Average Obligation Discharge Rate (%)')
        ax.set_title('Obligation Discharge by File Size Category')
        ax.set_ylim(0, 100)
        
        # Add value and count labels
        for bar, mean, count in zip(bars, means, counts):
            height = bar.get_height()
            ax.text(bar.get_x() + bar.get_width()/2., height + 1,
                   f'{mean:.1f}%\n(n={count})', ha='center', va='bottom', fontweight='bold')
        
        ax.grid(True, alpha=0.3, axis='y')
        plt.tight_layout()
        plt.savefig(self.output_dir / 'performance.pdf')
        plt.close()
        
        print("Research paper charts saved as PDFs")
    
    def create_issues_progression_chart(self):
        """Create a chart showing original vs remaining issues across all files"""
        fig, ax = plt.subplots(1, 1, figsize=(14, 8))
        
        # Sort dataframe by file_id to ensure proper ordering
        df_sorted = self.df.sort_values('file_id')
        
        # Extract file numbers from file_id (assuming format like '001', '002', etc.)
        file_numbers = []
        for file_id in df_sorted['file_id']:
            # Extract numeric part from file_id
            import re
            match = re.search(r'(\d+)', str(file_id))
            if match:
                file_numbers.append(int(match.group(1)))
            else:
                file_numbers.append(len(file_numbers) + 1)
        
        # If file_numbers is empty or doesn't match expected range, create sequential numbers
        if not file_numbers or len(file_numbers) != len(df_sorted):
            file_numbers = list(range(1, len(df_sorted) + 1))
        
        original_obligations = df_sorted['original_obligations'].values
        remaining_obligations = df_sorted['remaining_obligations'].values
        
        # Create smooth curves using spline interpolation
        # Create more points for smoother curves
        file_numbers_smooth = np.linspace(min(file_numbers), max(file_numbers), 300)
        
        # Create spline interpolations for smooth curves
        spl_original = make_interp_spline(file_numbers, original_obligations, k=3)
        spl_remaining = make_interp_spline(file_numbers, remaining_obligations, k=3)
        
        original_smooth = spl_original(file_numbers_smooth)
        remaining_smooth = spl_remaining(file_numbers_smooth)
        
        # Ensure non-negative values (interpolation might create small negative values)
        original_smooth = np.maximum(original_smooth, 0)
        remaining_smooth = np.maximum(remaining_smooth, 0)
        
        # Create the plot with smooth curves
        ax.plot(file_numbers_smooth, original_smooth, 'b-', linewidth=2.5, label='Original Obligations', alpha=0.9)
        ax.plot(file_numbers_smooth, remaining_smooth, 'r-', linewidth=2.5, label='Remaining Obligations', alpha=0.9)
        
        # Optionally, add scatter points to show actual data points
        ax.scatter(file_numbers, original_obligations, color='blue', s=20, alpha=0.6, zorder=5)
        ax.scatter(file_numbers, remaining_obligations, color='red', s=20, alpha=0.6, zorder=5)
        
        # Customize the plot
        ax.set_xlabel('File ID (1-100)', fontsize=12)
        ax.set_ylabel('Number of Issues', fontsize=12)
        ax.set_title(f'{self.model_name} - Original vs Remaining Issues by File', fontsize=14, fontweight='bold')
        ax.legend(fontsize=11)
        ax.grid(True, alpha=0.3)
        
        # Set x-axis to show all files from 1 to 100
        ax.set_xlim(1, 100)
        ax.set_xticks(range(0, 101, 10))  # Show ticks every 10 files
        
        # Add some statistics as text
        total_original = original_obligations.sum()
        total_remaining = remaining_obligations.sum()
        total_discharged = total_original - total_remaining
        discharge_rate = (total_discharged / total_original * 100) if total_original > 0 else 0
        
        stats_text = f'Total Original Obligations: {total_original}\nTotal Discharged: {total_discharged}\nOverall Discharge Rate: {discharge_rate:.1f}%'
        ax.text(0.02, 0.98, stats_text, transform=ax.transAxes, fontsize=10, 
               verticalalignment='top', bbox=dict(boxstyle='round', facecolor='wheat', alpha=0.8))
        
        plt.tight_layout()
        plt.savefig(self.output_dir / 'issues_progression.png')
        plt.close()
        print("Issues progression chart saved")
    
    def generate_all_visualizations(self):
        """Generate all  visualization charts"""
        if not self.load_data():
            return False
        
        print("Generating  visualizations...")
        
        # Create essential charts only
        self.create_performance_overview()
        self.create_complexity_analysis()
        self.create_summary_statistics()
        self.create_research_charts()
        self.create_issues_progression_chart()  # Add the new chart
        
        print(f"\nAll visualizations saved to: {self.output_dir}")
        print("Generated files:")
        for file in self.output_dir.glob("*"):
            print(f"  - {file.name}")
        
        return True

def main():
    """Main execution function"""
    visualizer = PHPVisualizer()
    success = visualizer.generate_all_visualizations()
    
    if success:
        print("\n===  Visualization Generation Complete ===")
        print("Essential charts have been generated for your research paper.")
    else:
        print("\n=== Error ===")
        print("Please run the evaluation script first to generate the required data.")

if __name__ == "__main__":
    main()
