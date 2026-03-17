Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$ROOT = $PSScriptRoot
$LOCK_PATH = Join-Path $ROOT "reproducibility.lock.json"

if (-not (Test-Path $LOCK_PATH)) {
    Write-Error "Missing reproducibility lock file: $LOCK_PATH"
    exit 1
}

$lock = Get-Content $LOCK_PATH -Raw | ConvertFrom-Json

function Fail {
    param([string]$Message)
    Write-Host "[REPRO CHECK FAILED] $Message" -ForegroundColor Red
    exit 1
}

function Pass {
    param([string]$Message)
    Write-Host "[REPRO CHECK] $Message" -ForegroundColor Green
}

# 1) PHP runtime invariants must match exactly
$phpVersion = (& php -r "echo PHP_VERSION;")
if ($phpVersion -ne $lock.php.version) {
    Fail "PHP version mismatch. Expected $($lock.php.version), got $phpVersion"
}
Pass "PHP version = $phpVersion"

$phpSapi = (& php -r "echo PHP_SAPI;")
if ($phpSapi -ne $lock.php.sapi) {
    Fail "PHP SAPI mismatch. Expected $($lock.php.sapi), got $phpSapi"
}
Pass "PHP SAPI = $phpSapi"

$phpExtensions = @{}
foreach ($line in (& php -m)) {
    $ext = $line.Trim()
    if ($ext -and -not $ext.StartsWith("[")) {
        $phpExtensions[$ext.ToLowerInvariant()] = $true
    }
}

foreach ($requiredExt in $lock.php.required_extensions) {
    $key = $requiredExt.ToLowerInvariant()
    if (-not $phpExtensions.ContainsKey($key)) {
        Fail "Missing required PHP extension: $requiredExt"
    }
}
Pass "Required PHP extensions are available"

# 2) Host-level invariants that can affect outputs
if ($env:OS -ne $lock.system.os) {
    Fail "OS mismatch. Expected $($lock.system.os), got $env:OS"
}
Pass "OS = $env:OS"

$timezone = (Get-TimeZone).Id
if ($timezone -ne $lock.system.timezone) {
    Fail "Timezone mismatch. Expected $($lock.system.timezone), got $timezone"
}
Pass "Timezone = $timezone"

$locale = [System.Globalization.CultureInfo]::CurrentCulture.Name
if ($locale -ne $lock.system.locale) {
    Fail "Locale mismatch. Expected $($lock.system.locale), got $locale"
}
Pass "Locale = $locale"

# 3) Composer lockfile and tool versions must match exactly
$composerLockPath = Join-Path $ROOT $lock.composer.lock_file
if (-not (Test-Path $composerLockPath)) {
    Fail "Missing Composer lock file: $composerLockPath"
}

$composerLock = Get-Content $composerLockPath -Raw | ConvertFrom-Json
$composerLockPackages = @{}
foreach ($p in @($composerLock.packages) + @($composerLock.'packages-dev')) {
    $composerLockPackages[$p.name] = $p.version
}

$toolNames = $lock.tools.PSObject.Properties.Name
foreach ($tool in $toolNames) {
    $expected = $lock.tools.$tool
    if (-not $composerLockPackages.ContainsKey($tool)) {
        Fail "Missing required package in composer.lock: $tool"
    }
    $actual = $composerLockPackages[$tool]
    if ($actual -ne $expected) {
        Fail "composer.lock mismatch for $tool. Expected $expected, got $actual"
    }
    Pass "composer.lock $tool = $actual"
}

# 4) Composer-installed tool versions must match exactly
$installedPath = Join-Path $ROOT "vendor/composer/installed.json"
if (-not (Test-Path $installedPath)) {
    Fail "Missing installed package metadata: $installedPath"
}

$installedJson = Get-Content $installedPath -Raw | ConvertFrom-Json
$packages = @{}
foreach ($p in @($installedJson.packages)) {
    $packages[$p.name] = $p.version
}

foreach ($tool in $toolNames) {
    $expected = $lock.tools.$tool
    if (-not $packages.ContainsKey($tool)) {
        Fail "Missing required package: $tool"
    }
    $actual = $packages[$tool]
    if ($actual -ne $expected) {
        Fail "Package version mismatch for $tool. Expected $expected, got $actual"
    }
    Pass "$tool = $actual"
}

# 5) Snapshot files must match SHA-256 hashes
foreach ($snapName in @("selection_data", "baseline_metadata")) {
    $snap = $lock.snapshots.$snapName
    $absPath = Join-Path $ROOT $snap.path
    if (-not (Test-Path $absPath)) {
        Fail "Missing snapshot file: $($snap.path)"
    }

    $hash = (Get-FileHash $absPath -Algorithm SHA256).Hash
    if ($hash -ne $snap.sha256) {
        Fail "Snapshot hash mismatch for $($snap.path). Expected $($snap.sha256), got $hash"
    }
    Pass "SHA256 OK: $($snap.path)"
}

# 6) Baseline obligation denominator must match lock
$selectionPath = Join-Path $ROOT $lock.snapshots.selection_data.path
$selectionData = Get-Content $selectionPath -Raw | ConvertFrom-Json
$obligations = 0
foreach ($f in $selectionData) {
    foreach ($r in $f.rules_triggered) {
        if ($r -like "Rector\Php*") {
            $obligations++
        }
    }
}
if ($obligations -ne [int]$lock.snapshots.selection_data.baseline_php_obligations) {
    Fail "Baseline obligation count mismatch. Expected $($lock.snapshots.selection_data.baseline_php_obligations), got $obligations"
}
Pass "Baseline Rector\\Php* obligations = $obligations"

# 7) Baseline metadata Rector version must match lock
$baselineMetadataPath = Join-Path $ROOT $lock.snapshots.baseline_metadata.path
$baselineMetadata = Get-Content $baselineMetadataPath -Raw | ConvertFrom-Json
$baselineRector = $baselineMetadata.dataset_info.rector_version
if ($baselineRector -ne $lock.snapshots.baseline_metadata.rector_version) {
    Fail "Baseline metadata rector_version mismatch. Expected $($lock.snapshots.baseline_metadata.rector_version), got $baselineRector"
}
Pass "Baseline metadata rector_version = $baselineRector"

Write-Host "[REPRO CHECK] All reproducibility checks passed." -ForegroundColor Cyan
exit 0
