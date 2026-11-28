#requires -Version 5.1
<#
PI R101 owner one-origin R100 preflight and optionally guarded Apply.
Default READ_ONLY. The R100 installer is the only writer, and protects
original account, .env, APP_KEY, SQLite, evidence, R88, R95 and launcher.
Exactly 8 file changes and 3 raw UTF-8 baseline checks are required.
Never interpret failed/empty preflight as a valid Compare-Object input.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory=$true)][string]$CandidateRepo,
    [Parameter(Mandatory=$true)][ValidatePattern('^[a-fA-F0-9]{40}$')][string]$ExpectedCommit,
    [switch]$Apply,
    [string]$CiFixtureRoot
)
Set-StrictMode -Version 2
$ErrorActionPreference='Stop'
$installer=Join-Path $CandidateRepo 'scripts\pi-r100-official-source-cutover.ps1'
if(-not (Test-Path -LiteralPath $installer -PathType Leaf)){
    throw 'PI_R101_PINNED_INSTALLER_MISSING'
}
$expected=@(
    'PI_R100_CHANGE=config\pi.php:REPLACE',
    'PI_R100_CHANGE=routes\web.php:REPLACE',
    'PI_R100_CHANGE=public\development-case-r85.html:REPLACE',
    'PI_R100_CHANGE=app\Http\Controllers\Api\OfficialSourceMetadataController.php:ADD',
    'PI_R100_CHANGE=app\Services\Pi\Development\OfficialSourceMetadataCatalogue.php:ADD',
    'PI_R100_CHANGE=resources\pi\official-source-metadata-r100.json:ADD',
    'PI_R100_CHANGE=public\official-sources-r100.css:ADD',
    'PI_R100_CHANGE=public\official-sources-r100.js:ADD'
)
$baselines=@(
    'PI_R100_BASELINE_UTF8_VERIFIED=config\pi.php',
    'PI_R100_BASELINE_UTF8_VERIFIED=routes\web.php',
    'PI_R100_BASELINE_UTF8_VERIFIED=public\development-case-r85.html'
)
$arguments=@{
    CandidateRepo=$CandidateRepo
    ExpectedCommit=$ExpectedCommit
}
if(-not [string]::IsNullOrWhiteSpace($CiFixtureRoot)){
    $arguments.CiFixtureRoot=$CiFixtureRoot
}
$pre=@()
try {
    $pre=@(& $installer @arguments)
} catch {
    Write-Output ('PI_R101_PREFLIGHT=HOLD_'+[string]$_.Exception.Message)
    throw
}
$pre | ForEach-Object {Write-Output $_}
if($pre -notcontains 'PI_R100_READ_ONLY_PREFLIGHT=PASS' -or
   $pre -notcontains 'PI_R100_ALLOWLIST_FILES=8' -or
   $pre -notcontains 'PI_R100_FILES_TO_CHANGE=8' -or
   $pre -notcontains 'PI_R100_OFFICIAL_METADATA_RECORDS=18' -or
   $pre -notcontains 'PI_R100_SOURCE_BYTES_VERIFIED=0' -or
   $pre -notcontains 'PI_R100_SUBACTIVITY_NUMBERS_ADMITTED=0') {
    throw 'PI_R101_PREFLIGHT=HOLD_COUNTS_OR_GATES_MISMATCH'
}
foreach($line in $baselines){
    if($pre -cnotcontains $line) {
        throw ('PI_R101_PREFLIGHT=HOLD_BASELINE_MISMATCH_'+$line)
    }
}
$actual=@($pre | Where-Object {$_ -clike 'PI_R100_CHANGE=*'})
if($actual.Count -ne 8) {
    throw ('PI_R101_PREFLIGHT=HOLD_EXPECTED_EIGHT_ACTUAL_'+$actual.Count)
}
foreach($needed in $expected){
    if($actual -cnotcontains $needed) {
        throw ('PI_R101_PREFLIGHT=HOLD_UNEXPECTED_MANIFEST_'+$needed)
    }
}
Write-Output 'PI_R101_EXACT_EIGHT_FILE_UTF8_PREFLIGHT=PASS'
if(-not $Apply) {
    Write-Output 'PI_R101_ACTION=READ_ONLY_NO_INSTALLATION'
    return
}
# A SECOND preflight within the installer revalidates source and target
# immediately before its guarded replacement, then rollback on failure.
$result=@()
try {
    $result=@(& $installer @arguments -Apply)
} catch {
    Write-Output ('PI_R101_RELEASE=HOLD_'+[string]$_.Exception.Message)
    throw
}
$result | ForEach-Object {Write-Output $_}
if($result -notcontains 'PI_R100_OFFICIAL_SOURCE_CUTOVER=APPLIED_VERIFIED' -or
   $result -notcontains 'PI_R100_SQLITE_ENV_APPKEY_LAUNCHER_R88=UNCHANGED') {
    throw 'PI_R101_RELEASE=HOLD_CODE_OR_PROTECTED_GATES'
}
if([string]::IsNullOrWhiteSpace($CiFixtureRoot)){
    if($result -notcontains 'PI_R100_HTTP_ANONYMOUS_API_401_AND_ASSET_SHA=PASS') {
        throw 'PI_R101_RELEASE=HOLD_HTTP_GATE'
    }
    if(@($result | Where-Object {$_ -like 'PI_R100_ORIGINAL_PHP_PID_PRESERVED=*'}).Count -ne 1){
        throw 'PI_R101_RELEASE=HOLD_PHP_PID'
    }
}
Write-Output 'PI_R101_R100_ONE_ORIGIN_CUTOVER=APPLIED_VERIFIED'
