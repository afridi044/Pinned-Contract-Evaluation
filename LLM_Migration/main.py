"""
LLM-Based PHP Code Migration Tool
Entry point for migration orchestration and evaluation.
"""

import os
import sys
import json
from pathlib import Path
from datetime import datetime
import warnings
warnings.filterwarnings('ignore')
import numpy as np
import random

# ============================================================================
# FIXED SEED FOR REPRODUCIBILITY
# ============================================================================
SEED = 42
np.random.seed(SEED)
random.seed(SEED)

# Add parent directory to sys.path for config import
sys.path.append(str(Path(__file__).resolve().parent.parent))

from config import SELECTED_100_FILES_DIR
from core.config import config
from core.llm_client import MultiProviderClient
from core.processor import MigrationManager
from core.parser import OutputParser, FileReconstructor
from core.utils import load_test_files


def setup_migration_environment():
    """Initialize the migration environment and load test files."""
    # Get properly initialized providers
    providers = config.get_providers()
    
    # Initialize multi-provider client
    multi_client = MultiProviderClient(providers)
    
    # Load test files from selected dataset
    test_files = load_test_files(str(SELECTED_100_FILES_DIR))
    
    return multi_client, test_files


def migrate_contract_obligation_space(multi_client: MultiProviderClient, test_files: dict, 
                                      model_name: str, strategy: str = 'basic'):
    """
    Execute migration contract on file benchmark.
    Assesses obligation discharge through orchestrated LLM migration.
    """
    migration_manager = MigrationManager(multi_client, test_files)
    
    # Migrate files under pinned contract policy
    results = migration_manager.batch_migrate(
        list(test_files.keys()),
        model=model_name,
        strategy=strategy
    )
    
    return results


def reconstruct_migrated_artifacts(test_files: dict):
    """Reconstruct chunked files and prepare for evaluation."""
    parser = OutputParser()
    reconstructor = FileReconstructor(parser)
    
    # Process all model responses
    parser.process_all_responses()
    
    # Reconstruct chunked files
    reconstructor.reconstruct_all_files()


if __name__ == "__main__":
    # Setup migration environment
    multi_client, test_files = setup_migration_environment()
    
    if not test_files:
        sys.exit(1)
    
    # Run migration under contract
    migrate_contract_obligation_space(multi_client, test_files, 'claude-sonnet-4-20250514')
    
    # Reconstruct artifacts
    reconstruct_migrated_artifacts(test_files)

# Mixed provider batch (you can mix and match in sequence):
# migration_manager.batch_migrate(list(test_files.keys())[:5], model='gemini-2.5-pro', strategy='basic')
migration_manager.migrate_file('012_module.audio-video.riff.php', 'claude-sonnet-4-20250514', 'basic')

print("\n🔄 Processing responses and reconstructing files...")
parser.process_all_responses()
reconstructor.reconstruct_all_files()

print("✅ Done!")
