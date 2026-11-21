#requires -Version 5.1
<#
Runs the exact committed GitHub Bridge Bash source in WSL, not the
Windows-working-tree .sh file (which may have been converted to CRLF).
No repair of .git, no newline rewrites, no database operations.
Default: offline self-test. To stage R84's pinned migration SOURCE (not
execute migration), operator explicitly passes -StageMigrationSource.
#>
[CmdletBinding()]
param(
    [switch]$SelfTest,
    [switch]$StageMigrationSource,
    [switch]$AuditLocal
)
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

if (@($SelfTest, $StageMigrationSource, $AuditLocal | Where-Object { $_ }).Count -ne 1) {
    throw 'PI_BRIDGE_USAGE=Specify exactly one of -SelfTest, -AuditLocal or -StageMigrationSource'
}

$Bridge = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..\..') -ErrorAction Stop).Path
$Branch = 'pilot/pi-laravel-github-bridge-20261108'
$BridgeRelPath = 'tools/pi-bridge/apply-laravel-updates.sh'
$ExpectedR84Blob = '2588a8f6356fd5eafad5a867f2948f77689dc2e8'

if (!(Get-Command git -ErrorAction SilentlyContinue)) {
    throw 'PI_BRIDGE_GIT_NOT_AVAILABLE'
}
# Get the Git blob, rather than reading CRLF-converted Windows working tree.
$lines = @(& git -C $Bridge show "HEAD:$BridgeRelPath")
if ($LASTEXITCODE -ne 0 -or $lines.Count -lt 50) {
    throw 'PI_BRIDGE_COMMITTED_SCRIPT_NOT_AVAILABLE'
}
$source = ($lines -join [char]10) + [char]10
if (!$source.StartsWith('#!/usr/bin/env bash' + [char]10) -or
    !$source.Contains('set -euo pipefail') -or
    !$source.Contains('--stage-r84-migration-source') -or
    !$source.Contains('--audit-r84-local') -or
    !$source.Contains($ExpectedR84Blob)) {
    throw 'PI_BRIDGE_SCRIPT_CONTRACT_NOT_VERIFIED'
}

if ($SelfTest) {
    # Parser/guard self-test; never invokes WSL or accesses any DB.
    if ($source.Contains([char]13) -or
        ('set -euo pipefail' + [char]13 + [char]10).Replace([string][char]13, '') -cne
            ('set -euo pipefail' + [char]10)) {
        throw 'PI_BRIDGE_LF_NORMALIZATION_SELFTEST_FAILED'
    }
    Write-Output 'PI_BRIDGE_COMMITTED_SCRIPT_LF=PASS'
    Write-Output 'PI_R84_PINNED_STAGE_SOURCE_CONTRACT=PASS'
    Write-Output 'PI_R84_ACTIVE_DATABASE_MUTATION=NONE'
    return
}

$current = (& git -C $Bridge branch --show-current).Trim()
if ($LASTEXITCODE -ne 0 -or $current -ne $Branch) {
    throw 'PI_BRIDGE_CANONICAL_BRANCH_MISMATCH'
}
$dirty = @(& git -C $Bridge status --porcelain=v1 --untracked-files=all)
if ($LASTEXITCODE -ne 0 -or
    @($dirty | Where-Object {
        $_ -and $_ -ne '?? .pi_bridge_last_applied.sha'
    }).Count -gt 0) {
    throw 'PI_BRIDGE_LOCAL_CHANGES_PRESERVED'
}

$windowsPath = (Resolve-Path -LiteralPath $Bridge).Path
if ($windowsPath -notmatch '^([A-Za-z]):[\\/](.+)$') {
    throw 'PI_BRIDGE_WSL_MOUNT_PATH_UNSUPPORTED'
}
$wslPath = '/mnt/' + $Matches[1].ToLowerInvariant() + '/' +
    $Matches[2].Replace('\', '/')

if (!(Get-Command wsl.exe -ErrorAction SilentlyContinue)) {
    throw 'PI_BRIDGE_WSL_NOT_AVAILABLE'
}

# The entire pipeline executes inside Linux. No PowerShell pipe may
# reintroduce Windows CRLF. bash -s receives committed Git blob bytes.
$runner = 'set -euo pipefail; git -C "$1" show "HEAD:tools/pi-bridge/apply-laravel-updates.sh" | tr -d ''\r'' | bash -s -- "$2"'

Write-Output '=== PI R84 VERIFIED WSL SOURCE STAGING ==='
& wsl.exe -d Ubuntu-24.04 -e bash -c $runner pi-bridge "$wslPath" '--self-test'
if ($LASTEXITCODE -ne 0) {
    throw 'PI_R84_WSL_COMMITTED_SELFTEST_FAILED'
}
$mode = if ($AuditLocal) { '--audit-r84-local' } else { '--stage-r84-migration-source' }
& wsl.exe -d Ubuntu-24.04 -e bash -c $runner pi-bridge "$wslPath" $mode
if ($LASTEXITCODE -ne 0) {
    throw 'PI_R84_WSL_PINNED_SOURCE_PREFLIGHT_OR_STAGE_FAILED'
}
if ($AuditLocal) {
    Write-Output 'PI_R84_CRLF_SAFE_LOCAL_AUDIT=PASS'
} else {
    Write-Output 'PI_R84_CRLF_SAFE_SOURCE_STAGE=PASS'
}
Write-Output 'PI_R84_ACTIVE_DATABASE_MUTATION=NONE'
