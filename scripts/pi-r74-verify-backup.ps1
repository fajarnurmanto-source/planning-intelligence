#requires -Version 5.1
<#
PI R74 immutable original launcher backup receipt verifier.
Read-only. Does not change backup, original launcher, main Laravel DB,
.env, APP_KEY, shortcut, PHP process or browser.
PS 5.1 explicitly normalizes JSON arrays instead of relying on implicit
ConvertFrom-Json pipeline enumeration.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory=$true)][string] $BackupRoot,
    [hashtable] $ExpectedSha12 = @{
        'BUKA-PI-HIDDEN.vbs'='9FC0BF471E18'
        'BUKA-PI-LARAVEL-HIDDEN.cmd'='D59A6681D3A0'
        'BUKA-PI-LARAVEL.cmd'='A0238A4B33E5'
    }
)
Set-StrictMode -Version 2
$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath $BackupRoot -PathType Container)) {
    throw 'PI_R74_BACKUP_FOLDER_MISSING'
}
$manifestPath = Join-Path $BackupRoot 'ORIGINAL_SHA256.json'
if (-not (Test-Path -LiteralPath $manifestPath -PathType Leaf)) {
    throw 'PI_R74_BACKUP_MANIFEST_MISSING'
}
$raw = Get-Content -LiteralPath $manifestPath -Raw -ErrorAction Stop
try {
    $parsed = ConvertFrom-Json -InputObject $raw -ErrorAction Stop
} catch {
    throw 'PI_R74_BACKUP_MANIFEST_INVALID_JSON'
}
$records = New-Object 'System.Collections.Generic.List[object]'
foreach ($entry in $parsed) {
    if ($entry -is [array]) {
        foreach ($record in $entry) { $records.Add($record) }
    } else {
        $records.Add($entry)
    }
}
Write-Output ('PI_R74_BACKUP_RECEIPT_RECORD_COUNT=' + $records.Count)
if ($records.Count -ne $ExpectedSha12.Count) {
    throw 'PI_R74_BACKUP_MANIFEST_RECORD_COUNT_MISMATCH'
}
$index = @{}
foreach ($entry in $records) {
    if ($null -eq $entry -or
        $null -eq $entry.PSObject.Properties['File'] -or
        $null -eq $entry.PSObject.Properties['SHA256']) {
        throw 'PI_R74_BACKUP_MANIFEST_SCHEMA_BAD'
    }
    $fileName = [string]$entry.File
    $digest = [string]$entry.SHA256
    if (-not $ExpectedSha12.ContainsKey($fileName)) {
        throw 'PI_R74_BACKUP_MANIFEST_UNKNOWN_FILE'
    }
    if ($index.ContainsKey($fileName)) {
        throw ('PI_R74_BACKUP_MANIFEST_DUPLICATE_ENTRY=' + $fileName)
    }
    if ($digest -notmatch '^[0-9A-Fa-f]{64}$') {
        throw ('PI_R74_BACKUP_MANIFEST_BAD_SHA256=' + $fileName)
    }
    $index[$fileName] = $digest.ToUpperInvariant()
}
foreach ($name in $ExpectedSha12.Keys) {
    if (-not $index.ContainsKey($name)) {
        throw ('PI_R74_BACKUP_MANIFEST_MISSING_ENTRY=' + $name)
    }
    $backupFile = Join-Path $BackupRoot $name
    if (-not (Test-Path -LiteralPath $backupFile -PathType Leaf)) {
        throw ('PI_R74_BACKUP_FILE_MISSING=' + $name)
    }
    $actualHash = (Get-FileHash -LiteralPath $backupFile -Algorithm SHA256).Hash.ToUpperInvariant()
    if ($actualHash -cne $index[$name]) {
        throw ('PI_R74_BACKUP_FILE_FULL_HASH_MISMATCH=' + $name)
    }
    if ($actualHash.Substring(0,12) -cne ([string]$ExpectedSha12[$name]).ToUpperInvariant()) {
        throw ('PI_R74_BACKUP_BASELINE_HASH_MISMATCH=' + $name)
    }
    Write-Output ('PI_R74_BACKUP_FILE_VERIFIED=' + $name)
}
Write-Output 'PI_R74_ORIGINAL_BACKUP_MANIFEST=VERIFIED'
