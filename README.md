# Pinned-Contract Evaluation for LLM-Assisted PHP Migration

This repository contains the artifact for the paper:

**Pinned-Contract Evaluation: A Policy-Grounded Framework for Evaluating LLM Code Migration**

It provides the full migration and evaluation pipeline used in the study, including dataset preparation, LLM-based migration, Rector-based contract scoring, and cross-oracle quality analyses.

## Pipeline Overview

![Pinned-Contract Evaluation Pipeline](assets/pipeline.png)

The workflow operationalizes migration quality as *obligation discharge* under a pinned static-analysis contract (Rector ruleset), then complements it with secondary diagnostics (syntax, structural preservation, loadability, PHPCompatibility, and correlation analyses).

## What Is Included

- Pinned-toolchain migration contract for PHP 5.x to 8.3 (`rector.php` + Composer lock).
- WordPress-derived 100-file benchmark metadata and selection artifacts.
- LLM migration harness for multiple providers/models.
- Evaluation modules for obligation discharge and robustness diagnostics.
- Reproducibility lock checks (`reproducibility.lock.json`, `verify_reproducibility.ps1`).
- End-to-end paper table generation pipeline (`run_paper_tables.ps1`).

## Repository Layout

- `dataset/`
  - Benchmark data and selection metadata.
- `LLM_Migration/`
  - Prompting, provider clients, chunk/reconstruction pipeline, migration scripts.
- `LLM_eval/`
  - PCE scoring, visualizations, inferential stats, PHPCompatibility and cross-oracle analysis.
- `paper/`
  - LaTeX sources for the manuscript.
- `tests/wordpress/`
  - Runtime/loadability and comparison utilities.
- `rector_analyzer.py`, `rector.php`
  - Baseline and per-file Rector analysis tooling.
- `run_paper_tables.ps1`
  - Main orchestrator for generating reported tables.

## Environment Requirements

- OS: Windows (reference environment in lock file)
- PHP: `8.3.22` (CLI) with extensions listed in `reproducibility.lock.json`
- Composer (for PHP toolchain dependencies)
- Python 3.10+ (recommended)

### Required PHP tools (via Composer)

- `rector/rector` `2.1.0`
- `phpstan/phpstan` `2.1.17`
- `squizlabs/php_codesniffer` `3.13.5`
- `phpcompatibility/php-compatibility` `9.3.5`

### Python packages

At minimum:

- from `LLM_Migration/requirements.txt`: `openai`, `python-dotenv`, `requests`, `pandas`, `numpy`, `anthropic`
- evaluation stack: `matplotlib`, `seaborn`, `scipy`

## Setup

1. Install PHP dependencies from repository root:

```powershell
composer install
```

2. Create Python environment and install dependencies:

```powershell
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r LLM_Migration\requirements.txt
pip install matplotlib seaborn scipy
```

3. Configure API keys for migration providers (if running LLM migration):

```env
OPENROUTER_API_KEY=...
GOOGLE_API_KEY=...
ANTHROPIC_API_KEY=...
```

You can place these in a root `.env` file.

## Quick Start

### 1. Reproducibility preflight

```powershell
.\verify_reproducibility.ps1
```

This validates:
- PHP runtime invariants
- OS/timezone/locale invariants
- Composer lock and installed tool versions
- Snapshot hashes and baseline obligation denominator

### 2. Run full paper pipeline

```powershell
.\run_paper_tables.ps1
```

This orchestrates the full sequence used for paper outputs, including:
- PCE obligation discharge (Panels/Tables A-C)
- Syntax validity
- Structural preservation
- Patch-volume closure
- PHPCompatibility summary
- Loadability tripwire
- Resistant rule analysis
- Cross-oracle correlation

## Running Individual Components

### LLM migration

```powershell
python LLM_Migration\scripts\migrate.py --all-files --model claude-sonnet-4-20250514 --strategy basic
```

### Evaluate one model

```powershell
cd LLM_eval
python run_evaluation.py claude_sonnet_4_20250514
```

### Evaluate all discovered models

```powershell
cd LLM_eval
python run_evaluation.py all
```

### PHPCompatibility analysis

```powershell
cd LLM_eval
python run_phpcompatibility.py
python summarize_phpcs.py
```

## Models Evaluated in the Paper

- `claude_sonnet_4_20250514`
- `gemini_2_5_flash`
- `gemini_2_5_pro`
- `gpt_5_codex`
- `meta_llama_llama_3_3_70b_instruct`
- `Rector_Baseline` (non-LLM baseline)

## Key Results Snapshot

Compact summary from the paper's obligation discharge table (all-files scoring for weighted discharge):

| System | Weighted Discharge (%) | Discharged Obligations | Contract-Clean Files |
|---|---:|---:|---:|
| Rector (reference) | 98.43 | 503 | 89 |
| Gemini 2.5 Pro | 72.80 | 372 | 19 |
| GPT-5 Codex | 58.12 | 297 | 15 |
| Gemini 2.5 Flash | 53.42 | 273 | 13 |
| Claude Sonnet 4 (05-14) | 53.23 | 272 | 6 |
| Llama 3.3 70B Instruct | 30.53 | 156 | 2 |

## Reproducibility Notes

Pinned reproducibility state is tracked in `reproducibility.lock.json`, including:
- runtime and environment invariants
- exact tool versions
- snapshot hashes
- baseline obligation count (`511` `Rector\Php*` file-rule incidences)

For faithful reproduction, keep `composer.lock`, `reproducibility.lock.json`, and benchmark metadata unchanged.

## Citation

If you use this artifact, please cite the paper:

```bibtex
@inproceedings{mahmud2026pce,
  title={Pinned-Contract Evaluation: A Policy-Grounded Framework for Evaluating LLM Code Migration},
  author={Mahmud, Afridi and Khondaker, Abid Hasan and Ahmed, Sadif and Rahman, Md Nafiu and Shahriyar, Rifat},
  year={2026},
  note={Artifact repository}
}
```

## License

No repository license file is currently included. Add a license before public release if you intend to permit reuse.
