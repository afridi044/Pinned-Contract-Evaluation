param(
    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$Args
)

$repoRootScript = Join-Path (Join-Path $PSScriptRoot "..") "run_paper_tables.ps1"

if (-not (Test-Path $repoRootScript)) {
    Write-Error "Cannot find pipeline script at: $repoRootScript"
    exit 1
}

& $repoRootScript @Args
exit $LASTEXITCODE
