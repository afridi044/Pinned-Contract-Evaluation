"""
Main Migration Script
Command-line interface and orchestration for the LLM migration tool.
"""

import argparse
import sys
from pathlib import Path
from typing import List, Optional

# Import our modules
from core.config import config, DEFAULT_CHUNK_SIZE
from core.llm_client import MultiProviderClient
from core.processor import MigrationManager
from core.parser import OutputParser, FileReconstructor
from core.utils import load_test_files, analyze_file_sizes

# Import config for dynamic paths
import sys
from pathlib import Path
sys.path.insert(0, str(Path(__file__).parent.parent.parent))
from config import DATASET_NAME, SELECTED_100_FILES_DIR


def create_migration_system(test_files_path: str = None):
    """Initialize the complete migration system."""
    # Load test files
    if not test_files_path:
        test_files_path = str(SELECTED_100_FILES_DIR / 'extra_large_1000_plus')
    
    test_files = load_test_files(test_files_path)
    
    if not test_files:
        print("❌ No test files loaded. Cannot proceed.")
        return None, None, None, None
    
    # Initialize multi-provider client
    multi_client = MultiProviderClient(config.get_providers())
    
    # Initialize components
    migration_manager = MigrationManager(multi_client, test_files)
    output_parser = OutputParser()
    file_reconstructor = FileReconstructor(output_parser)
    
    return migration_manager, output_parser, file_reconstructor, test_files


def main():
    """Main command-line interface."""
    parser = argparse.ArgumentParser(description="LLM PHP Migration Tool")
    
    parser.add_argument('--files', type=str, nargs='*', help='Specific files to migrate')
    parser.add_argument('--files-dir', type=str, default=str(SELECTED_100_FILES_DIR / 'extra_large_1000_plus'),
                        help='Directory containing PHP files to migrate')
    parser.add_argument('--all-files', action='store_true', help='Migrate all loaded files')
    parser.add_argument('--limit', type=int, help='Limit number of files to migrate')
    
    parser.add_argument('--model', type=str, default='gemini-1.5-pro',
                        help='Model to use for migration')
    parser.add_argument('--strategy', type=str, default='basic', 
                        choices=['basic', 'comprehensive'],
                        help='Migration strategy to use')
    
    parser.add_argument('--chunk-size', type=int, default=DEFAULT_CHUNK_SIZE,
                        help='Chunk size for large files')
    parser.add_argument('--no-auto-chunk', action='store_true',
                        help='Disable automatic chunking')
    
    parser.add_argument('--analyze', action='store_true',
                        help='Analyze file sizes only')
    parser.add_argument('--migrate', action='store_true', default=True,
                        help='Perform migration (default)')
    parser.add_argument('--parse', action='store_true',
                        help='Parse existing responses')
    parser.add_argument('--reconstruct', action='store_true',
                        help='Reconstruct files from chunks')
    
    parser.add_argument('--test', action='store_true',
                        help='Test provider detection only')
    
    args = parser.parse_args()
    
    migration_manager, output_parser, file_reconstructor, test_files = create_migration_system(args.files_dir)
    
    if not migration_manager:
        sys.exit(1)
    
    if args.test:
        migration_manager.multi_client.test_provider_detection()
        return
    
    if args.analyze:
        analyze_file_sizes(test_files, args.chunk_size)
        return
    
    # Parse existing responses
    if args.parse:
        output_parser.process_all_responses()
        return
    
    # Reconstruct files from chunks
    if args.reconstruct:
        file_reconstructor.reconstruct_all_files()
        return
    
    # Migration workflow
    if args.migrate:
        files_to_migrate = determine_files_to_migrate(args, test_files)
        
        if not files_to_migrate:
            print("No files selected for migration")
            return
        
        print(f"Starting migration of {len(files_to_migrate)} files...")
        
        results = migration_manager.batch_migrate(
            files_to_migrate,
            model=args.model,
            strategy=args.strategy,
            chunk_size=args.chunk_size,
            auto_chunk=not args.no_auto_chunk
        )
        
        print("Migration completed")
        output_parser.process_all_responses()
        
        print("\n🔧 Post-processing: Reconstructing chunked files...")
        file_reconstructor.reconstruct_all_files()
        
        print("\n🎉 Full migration pipeline completed!")


def determine_files_to_migrate(args, test_files) -> List[str]:
    """Determine which files to migrate based on arguments."""
    if args.files:
        # Specific files requested
        available_files = set(test_files.keys())
        requested_files = set(args.files)
        valid_files = list(requested_files & available_files)
        
        if not valid_files:
            print(f"❌ None of the requested files found: {args.files}")
            print(f"💡 Available files: {sorted(available_files)}")
            return []
        
        invalid_files = requested_files - available_files
        if invalid_files:
            print(f"⚠️  Invalid files ignored: {sorted(invalid_files)}")
        
        return valid_files
    
    elif args.all_files:
        # All files
        files = list(test_files.keys())
        if args.limit:
            files = files[:args.limit]
        return files
    
    else:
        # Default: first 3 files for testing
        files = list(test_files.keys())[:3]
        print(f"💡 No specific files selected. Using first 3 files for testing: {files}")
        return files


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        print("\n\n⚠️  Migration interrupted by user")
        sys.exit(1)
    except Exception as e:
        print(f"Error: {e}")
        import traceback
        traceback.print_exc()
        sys.exit(1)
