# Simple main.py - Direct execution of notebook commands
# Just hardcode and run - exactly like the notebook

# Import everything we need (same as notebook)
import os
import json
import pandas as pd
import numpy as np
from pathlib import Path
from datetime import datetime
import warnings
warnings.filterwarnings('ignore')

# Import configuration
from core.config import config

# Get properly initialized providers
PROVIDERS = config.get_providers()

# Import all the notebook functions
from core.llm_client import MultiProviderClient
from core.processor import MigrationManager
from core.parser import OutputParser, FileReconstructor
from core.utils import load_test_files

# Initialize multi-provider client
multi_client = MultiProviderClient(PROVIDERS)

# Load test files (same as notebook)
test_files = {}
old_version_path = Path('selected_100_files/extra_large_1000_plus')

if old_version_path.exists():
    for php_file in old_version_path.rglob('*.php'):
        try:
            with open(php_file, 'r', encoding='utf-8', errors='ignore') as f:
                content = f.read()
                if content.strip():
                    test_files[php_file.name] = content
        except Exception as e:
            print(f"⚠️  Could not load {php_file.name}: {e}")
    
    print(f"📁 Loaded {len(test_files)} PHP files")
else:
    print("❌ selected_100_files directory not found")

# Create migration manager 
migration_manager = MigrationManager(multi_client, test_files)

# Create parsers (same as notebook)
parser = OutputParser()
reconstructor = FileReconstructor(parser)

# EXACTLY THE SAME COMMANDS AS THE NOTEBOOK:
print("\n🚀 Starting batch migration...")

# UNCOMMENT THESE LINES FOR BATCH MIGRATION WITH DIFFERENT PROVIDERS:

# Google AI batch migration:
# migration_manager.batch_migrate(list(test_files.keys())[:3], model='gemini-1.5-pro', strategy='basic')

# OpenRouter batch migration:
# migration_manager.batch_migrate(list(test_files.keys())[:3], model='mistralai/mistral-small-3.2-24b-instruct:free', strategy='basic')

# Mixed provider batch (you can mix and match in sequence):
# migration_manager.batch_migrate(list(test_files.keys())[:12], model='meta-llama/llama-3.3-70b-instruct:free', strategy='basic')
migration_manager.migrate_file('012_module.audio-video.riff.php', 'meta-llama/llama-3.3-70b-instruct:free', 'basic')

print("\n🔄 Processing responses and reconstructing files...")
parser.process_all_responses()
reconstructor.reconstruct_all_files()

print("✅ Done!")
