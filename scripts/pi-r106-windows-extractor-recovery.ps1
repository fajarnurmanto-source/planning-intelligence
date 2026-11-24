#requires -Version 5.1
<#
R106 owner PDF engine recovery.
The default is offline read-only review. -Repair explicitly permits
WinGet Poppler installation ONLY for confirmed engine absence.
No launcher, database, original PI 8820, original PDF or receipt mutation.
#>
[CmdletBinding()]
param(
  [Parameter(Mandatory=$true)][ValidatePattern('^[0-9a-fA-F]{40}$')]
  [string]$ExpectedCommit,
  [switch]$Repair,
  [string]$PdfToTextPath=''
)
Set-StrictMode -Version 2
$ErrorActionPreference='Stop'
$qa=(Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$git=(Get-Command git.exe -ErrorAction Stop).Source
$actual=([string](& $git -C $qa rev-parse HEAD)).Trim().ToLowerInvariant()
if($LASTEXITCODE -ne 0 -or $actual -cne $ExpectedCommit.ToLowerInvariant()){
  throw 'PI_R106_QA_PINNED_COMMIT_MISMATCH'
}
$dirty=@(& $git -C $qa status --porcelain)
if($LASTEXITCODE -ne 0 -or $dirty.Count -gt 0){
  throw 'PI_R106_QA_WORKTREE_DIRTY'
}
$php=(Get-Command php.exe -ErrorAction Stop).Source
$cli=Join-Path $qa 'scripts\pi-r106-native-annex-review.php'
if(-not (Test-Path -LiteralPath $cli -PathType Leaf)){
  throw 'PI_R106_QA_OFFLINE_CLI_MISSING'
}
if($PdfToTextPath -ne ''){
  if(-not (Test-Path -LiteralPath $PdfToTextPath -PathType Leaf)){
    throw 'PI_R106_SPECIFIED_EXTRACTOR_NOT_FOUND'
  }
}
Write-Output 'PI_R106_QA_PIN=PASS'
function Invoke-R106Offline {
  param([string]$PathToEngine)
  $previous=$ErrorActionPreference
  $ErrorActionPreference='Continue'
  try {
    if($PathToEngine -eq ''){
      $lines=@(& $php $cli 2>&1)
    }else{
      $lines=@(& $php $cli ('--pdftotext='+$PathToEngine) 2>&1)
    }
    $status=$LASTEXITCODE
  } finally {
    $ErrorActionPreference=$previous
  }
  $message=[string]::Join([Environment]::NewLine,
    @($lines | ForEach-Object { [string]$_ }))
  return [pscustomobject]@{ExitCode=$status;Text=$message}
}
$first=Invoke-R106Offline $PdfToTextPath
if(-not [string]::IsNullOrEmpty($first.Text)){Write-Output $first.Text}
if($first.ExitCode -eq 0){
  Write-Output 'PI_R106_ENGINE_RECOVERY=ALREADY_AVAILABLE'
  Write-Output 'PI_R106_OWNER_QA=PASS'
  Write-Output 'PI_R106_ORIGINAL_PI_8820=UNTOUCHED'
  return
}
if($PdfToTextPath -ne ''){
  throw 'PI_R106_EXPLICIT_ENGINE_FAILED_NO_PACKAGE_INSTALL'
}
if($first.Text -notmatch 'PI_R106_PDF_ENGINE_HOLD:'){
  throw 'PI_R106_SOURCE_OR_PARSER_FAILURE_NOT_ENGINE_INSTALL'
}
if($first.Text -match 'INVALID_TEXT_LAYER|_PROCESS_ERROR'){
  throw 'PI_R106_TEXT_OR_PROCESS_FAILURE_NOT_SAFE_TO_INSTALL'
}
if(-not $Repair){
  Write-Output 'PI_R106_ENGINE=NOT_AVAILABLE'
  Write-Output 'PI_R106_REPAIR=EXPLICIT_-Repair_REQUIRED'
  throw 'PI_R106_MISSING_EXTRACTOR_NO_INSTALL'
}
$winget=Get-Command winget.exe -ErrorAction SilentlyContinue
if($null -eq $winget){throw 'PI_R106_WINGET_UNAVAILABLE'}
Write-Output 'PI_R106_REPAIR_PACKAGE=oschwartz10612.Poppler'
Write-Output 'PI_R106_REPAIR_MODE=EXPLICIT_OWNER_OPT_IN'
& $winget.Source install --exact --id 'oschwartz10612.Poppler' --source winget --accept-package-agreements --accept-source-agreements
if($LASTEXITCODE -ne 0){throw 'PI_R106_WINGET_INSTALL_FAILED'}
# The PHP resolver checks WinGet package directories without changing PATH.
$second=Invoke-R106Offline ''
if(-not [string]::IsNullOrEmpty($second.Text)){Write-Output $second.Text}
if($second.ExitCode -ne 0){
  throw 'PI_R106_ENGINE_INSTALLED_BUT_REVIEW_STILL_HOLD'
}
Write-Output 'PI_R106_ENGINE_RECOVERY=PASS'
Write-Output 'PI_R106_OWNER_QA=PASS'
Write-Output 'PI_R106_ORIGINAL_PI_8820=UNTOUCHED'
