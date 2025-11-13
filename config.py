"""
Shared configuration for dataset and LLM_Migration projects.
This file defines paths to shared resources like rector.php and vendor directory.
"""
from pathlib import Path

# Project root directory (level 2)
PROJECT_ROOT = Path(__file__).parent.absolute()

# Shared paths
RECTOR_PHP_PATH = PROJECT_ROOT / "rector.php"
VENDOR_PATH = PROJECT_ROOT / "vendor"
RECTOR_BIN = VENDOR_PATH / "bin" / "rector.bat"
RECTOR_BIN_UNIX = VENDOR_PATH / "bin" / "rector"  # For Unix-like systems
COMPOSER_JSON = PROJECT_ROOT / "composer.json"

# Dataset paths
DATASET_DIR = PROJECT_ROOT / "dataset"
ORGANIZED_DATASET_DIR = DATASET_DIR / "organized_dataset_All"
SELECTED_100_FILES_DIR = DATASET_DIR / "selected_100_files"  # Single source for 100 files
RECTOR_REPORTS_DIR = DATASET_DIR / "rector_reports"

# LLM Migration paths
LLM_MIGRATION_DIR = PROJECT_ROOT / "LLM_Migration"
LLM_NEW_VERSION_DIR = LLM_MIGRATION_DIR / "outputs" / "new-version"
LLM_EVALUATION_REPORTS_DIR = LLM_MIGRATION_DIR / "outputs" / "evaluation_reports"  # Where LLM eval reports are generated

# LLM Evaluation paths (for final analysis/visualization)
LLM_EVAL_DIR = PROJECT_ROOT / "LLM_eval"

def get_rector_command():
    """Get the appropriate rector command based on the OS."""
    import platform
    if platform.system() == "Windows":
        return str(RECTOR_BIN)
    else:
        return str(RECTOR_BIN_UNIX)
