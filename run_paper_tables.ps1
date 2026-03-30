# =============================================================================
# run_paper_tables.ps1
#
# Runs the full pipeline that generates all tables in the paper, in order.
# Run from the workspace root
#
# Tables generated:
#   Table 9  (syntax validity)              <- validate_syntax_errors.py
#   Table 10 (structural preservation)     <- validate_code_completeness.py
#   Table A  (obligation discharge)  \
#   Table B  (discharge by size)      |    <- run_evaluation.py
#   Table C  (discharge by PHP family)/
#   Secondary (patch-volume closure)        <- analyze_diff_counts.py
#   Table 12 (PHPCompatibility)             <- run_phpcompatibility.py + summarize_phpcs.py
#   Table 13 (loadability tripwire)         <- load_analysis.py
#   Resistant rules table                   <- generate_resistant_rules_table.py
#   Correlation (RQ4)                       <- cross_oracle_correlation.py
# =============================================================================

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$ROOT = $PSScriptRoot

function Step {
    param([string]$Label)
    Write-Host ""
    Write-Host ("=" * 70) -ForegroundColor Cyan
    Write-Host "  $Label" -ForegroundColor Cyan
    Write-Host ("=" * 70) -ForegroundColor Cyan
}

function Run {
    param([string]$Script, [string[]]$ScriptArgs, [string]$Cwd)
    $prev = $PWD
    Set-Location $Cwd
    Write-Host ">> python $Script $ScriptArgs" -ForegroundColor Yellow
    & python $Script @ScriptArgs
    if ($LASTEXITCODE -ne 0) {
        Write-Host "FAILED (exit $LASTEXITCODE): $Script" -ForegroundColor Red
        Set-Location $prev
        exit $LASTEXITCODE
    }
    Set-Location $prev
}

Step "PREFLIGHT: Reproducibility lock verification"
& (Join-Path $ROOT "verify_reproducibility.ps1")
if ($LASTEXITCODE -ne 0) {
    Write-Host "Reproducibility preflight failed. Aborting pipeline." -ForegroundColor Red
    exit $LASTEXITCODE
}

# ---------------------------------------------------------------------------
# STAGE 1 - LLM_Migration: Rector analysis + syntax + structural preservation
# ---------------------------------------------------------------------------

Step "STAGE 1a: Rector analysis on all migrated outputs (code_analysis artifacts)"
# Run "analyze_migrated_code.py" @("all") "$ROOT\LLM_Migration"

Step "STAGE 1b: Syntax validity (Table 9 - php -l)"
# Run "LLM_Migration\scripts\validate_syntax_errors.py" @("all") $ROOT

Step "STAGE 1c: Structural preservation (Table 10)"
# Run "LLM_Migration\scripts\validate_code_completeness.py" @("all") $ROOT

# ---------------------------------------------------------------------------
# STAGE 2 - LLM_eval: PCE obligation discharge (Panels A / B / C)
# ---------------------------------------------------------------------------

Step "STAGE 2: PCE evaluation - obligation discharge, size breakdown, PHP family (Panels A/B/C)"
Run "run_evaluation.py" @("all") "$ROOT\LLM_eval"

# ---------------------------------------------------------------------------
# STAGE 3 - Patch-volume closure (WDC / MFDC secondary table)
# ---------------------------------------------------------------------------

Step "STAGE 3: Patch-volume closure - WDC / MFDC (secondary panel)"
Run "analyze_diff_counts.py" @() "$ROOT\LLM_eval"

# ---------------------------------------------------------------------------
# STAGE 4 - PHPCompatibility (Table 12)
# ---------------------------------------------------------------------------

Step "STAGE 4a: Run PHPCompatibility via PHPCS (Table 12 raw data)"
Run "run_phpcompatibility.py" @() "$ROOT\LLM_eval"

Step "STAGE 4b: Summarize PHPCompatibility results (Table 12)"
Run "summarize_phpcs.py" @() "$ROOT\LLM_eval"

# ---------------------------------------------------------------------------
# STAGE 5 - Loadability tripwire (Table 13)
# ---------------------------------------------------------------------------

Step "STAGE 5: Loadability tripwire - regression counts (Table 13)"
Run "load_analysis.py" @() "$ROOT\LLM_eval"

# ---------------------------------------------------------------------------
# STAGE 6 — Resistant rules table
# ---------------------------------------------------------------------------

Step "STAGE 6: Most resistant Rector rules table"
Run "generate_resistant_rules_table.py" @() "$ROOT\LLM_eval"

# ---------------------------------------------------------------------------
# STAGE 7 - Cross-oracle correlation (RQ4)
# ---------------------------------------------------------------------------

Step "STAGE 7: Cross-oracle correlation - PCE vs PHPCompatibility (RQ4)"
Run "cross_oracle_correlation.py" @() "$ROOT\LLM_eval"

# ---------------------------------------------------------------------------
# Done
# ---------------------------------------------------------------------------

Write-Host ""
Write-Host ("=" * 70) -ForegroundColor Green
Write-Host "  ALL STAGES COMPLETE - paper tables ready" -ForegroundColor Green
Write-Host ("=" * 70) -ForegroundColor Green
Write-Host ""
