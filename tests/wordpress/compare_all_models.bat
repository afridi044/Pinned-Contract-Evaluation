@echo off
REM Compare all models - Windows Batch Version

echo.
echo ========================================================================
echo        Comparing ALL Models - Execution-Based Testing
echo ========================================================================
echo.
echo Files: selected_files.txt (35 files)
echo.

FOR %%m IN (claude_sonnet_4_20250514 gemini_2_5_flash gemini_2_5_pro gpt_5_codex meta_llama_llama_3_3_70b_instruct Rector_Baseline) DO (
    echo.
    echo ------------------------------------------------------------------------
    echo Testing Model: %%m
    echo ------------------------------------------------------------------------
    
    php compare_versions.php -m %%m -f selected_files.txt
    
    echo.
)

echo.
echo ========================================================================
echo                    ALL MODELS COMPLETED
echo ========================================================================
echo.
echo Reports saved to: tests/results/
echo.
