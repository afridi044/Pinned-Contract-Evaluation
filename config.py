"""
Shared configuration for dataset and LLM_Migration projects.
This file defines paths to shared resources like rector.php and vendor directory.

CONFIGURATION:
    Change DATASET_NAME to switch between different codebases (wordpress, laravel, drupal, etc.)
    
HOW TO ADD A NEW DATASET:
    1. Change DATASET_NAME below (e.g., from "wordpress" to "laravel")
    2. Create the folder structure:
       - dataset/laravel/
       - LLM_Migration/laravel/
       - LLM_eval/laravel/
    3. Run your scripts - all paths will automatically point to the new dataset!
    
EXAMPLE:
    # For WordPress (default):
    DATASET_NAME = "wordpress"
    
    # For Laravel:
    DATASET_NAME = "laravel"
    
    # For Drupal:
    DATASET_NAME = "drupal"
"""
from pathlib import Path
import numpy as np
import random

SEED = 42
np.random.seed(SEED)
random.seed(SEED)

# Project root directory (level 2)
PROJECT_ROOT = Path(__file__).parent.absolute()

DATASET_NAME = "wordpress"

# Shared paths
RECTOR_PHP_PATH = PROJECT_ROOT / "rector.php"
VENDOR_PATH = PROJECT_ROOT / "vendor"
RECTOR_BIN = VENDOR_PATH / "bin" / "rector.bat"
RECTOR_BIN_UNIX = VENDOR_PATH / "bin" / "rector"
COMPOSER_JSON = PROJECT_ROOT / "composer.json"

# Dataset paths (dynamically constructed from DATASET_NAME)
DATASET_DIR = PROJECT_ROOT / "dataset"
DATASET_SUBDIR = DATASET_DIR / DATASET_NAME
ORGANIZED_DATASET_DIR = DATASET_SUBDIR / "organized_dataset_All"
SELECTED_100_FILES_DIR = DATASET_SUBDIR / "selected_100_files"  # Single source for 100 files
RECTOR_REPORTS_DIR = DATASET_SUBDIR / "rector_reports"

# LLM Migration paths (dynamically constructed from DATASET_NAME)
LLM_MIGRATION_DIR = PROJECT_ROOT / "LLM_Migration"
LLM_MIGRATION_SUBDIR = LLM_MIGRATION_DIR / DATASET_NAME
LLM_NEW_VERSION_DIR = LLM_MIGRATION_SUBDIR / "outputs" / "new-version"
LLM_CODE_ANALYSIS_DIR = LLM_MIGRATION_SUBDIR / "outputs" / "code_analysis"  # Where Rector analysis reports are generated
MODEL_OUTPUT_DIR = LLM_MIGRATION_SUBDIR / "model_output"
CHUNKED_MODEL_OUTPUT_DIR = LLM_MIGRATION_SUBDIR / "chunked_model_output"

# LLM Evaluation paths (dynamically constructed from DATASET_NAME)
LLM_EVAL_DIR = PROJECT_ROOT / "LLM_eval"
LLM_EVAL_SUBDIR = LLM_EVAL_DIR / DATASET_NAME
PHPCOMPATIBILITY_RESULTS_DIR = LLM_EVAL_SUBDIR / "phpcompatibility_results"

def get_rector_command():
    """Get the appropriate rector command based on the OS."""
    import platform
    if platform.system() == "Windows":
        return str(RECTOR_BIN)
    else:
        return str(RECTOR_BIN_UNIX)


# ============================================================================
# PATH SUMMARY (for current DATASET_NAME = "wordpress")
# ============================================================================
# All paths are automatically generated based on DATASET_NAME above.
# No need to change anything else in this file!
#
# Current paths will be:
#   Dataset:      dataset/wordpress/organized_dataset_All/
#   Selected:     dataset/wordpress/selected_100_files/
#   Reports:      dataset/wordpress/rector_reports/
#   Model Output: LLM_Migration/wordpress/model_output/
#   New Version:  LLM_Migration/wordpress/outputs/new-version/
#   Evaluation:   LLM_eval/wordpress/phpcompatibility_results/
# ============================================================================
