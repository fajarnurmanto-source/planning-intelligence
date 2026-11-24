# Read-only RDI corpus execution. Does not alter original application.
param(
 [Parameter(Mandatory=$true)][string]$CandidateRepo,
 [Parameter(Mandatory=$true)][string]$ExpectedCommit,
 [string]$NationalRoot
)
$ErrorActionPreference='Stop'
$repo=(Resolve-Path -LiteralPath $CandidateRepo).Path
$head=(& git -C $repo rev-parse HEAD).Trim()
if($LASTEXITCODE -ne 0 -or $head -cne $ExpectedCommit){throw 'PI_RDI_QA_PIN_HOLD'}
$dirty=@(& git -C $repo status --porcelain)
if($LASTEXITCODE -ne 0 -or $dirty.Count -ne 0){throw 'PI_RDI_QA_DIRTY_HOLD'}
$root='D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel'
$listen=@(Get-NetTCPConnection -LocalPort 8820 -State Listen)
if($listen.Count -ne 1){throw 'PI_RDI_ORIGINAL_LISTENER_HOLD'}
$php=(Get-CimInstance Win32_Process -Filter "ProcessId=$($listen[0].OwningProcess)").ExecutablePath
if(-not $php -or (Split-Path $php -Leaf) -ne 'php.exe'){throw 'PI_RDI_ORIGINAL_PHP_HOLD'}
$autoload=Join-Path $root 'vendor\autoload.php'
if(-not (Test-Path -LiteralPath $autoload)){throw 'PI_RDI_ORIGINAL_VENDOR_HOLD'}
if(-not $NationalRoot){
 $candidates=@(
  'D:\Research\PlanningIntelligence\08_RUNTIME\R81_NATIONAL_PDF_SNAPSHOTS',
  (Join-Path (Split-Path $repo -Parent) '08_RUNTIME\R81_NATIONAL_PDF_SNAPSHOTS')
 )|Select-Object -Unique|Where-Object {Test-Path -LiteralPath (Join-Path $_ 'observed-manifest.json')}
 if(@($candidates).Count -eq 1){$NationalRoot=@($candidates)[0]}
}
$out=Join-Path $env:LOCALAPPDATA ('PlanningIntelligence\RDI_RECEIPTS\'+[guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $out|Out-Null
$receipt=Join-Path $out 'corpus.json'
$prior=$env:PI_RDI_COMPOSER_AUTOLOAD
try{
 $env:PI_RDI_COMPOSER_AUTOLOAD=$autoload
 $argsRdi=@((Join-Path $repo 'scripts\pi-rdi-01a-inventory.php'))
 if($NationalRoot){$argsRdi+="--national-root=$NationalRoot"}
 # Start-Process avoids Windows PowerShell's lossy native UTF-8 pipe decoding.
 $quoted=@($argsRdi|ForEach-Object {'"'+$_+'"'})
 $proc=Start-Process -FilePath $php -ArgumentList $quoted -NoNewWindow -Wait -PassThru -RedirectStandardOutput $receipt -RedirectStandardError (Join-Path $out 'stderr.log')
 $json=Get-Content -LiteralPath $receipt -Raw -Encoding UTF8|ConvertFrom-Json
 foreach($item in $json.documents.PSObject.Properties){
  $d=$item.Value
  Write-Output ('PI_RDI_DOCUMENT='+$item.Name+'|PAGES='+$d.inventoried_pages+'|TEXT='+$d.text_pages+'|NO_TEXT='+$d.no_text_pages+'|UNCLASSIFIED='+$d.unclassified_text_pages)
 }
 Write-Output ('PI_RDI_RECEIPT='+$receipt)
 Write-Output ('PI_RDI_RECEIPT_SHA256='+(Get-FileHash -LiteralPath $receipt -Algorithm SHA256).Hash)
 Write-Output ('PI_RDI_PROCESSED='+$json.processed_documents+'/4')
 if($json.holds -is [System.Management.Automation.PSCustomObject]){
  foreach($hold in $json.holds.PSObject.Properties){Write-Output ('PI_RDI_HOLD='+$hold.Name+'|'+$hold.Value)}
 }elseif($null -ne $json.holds -and @($json.holds).Count -ne 0){throw 'PI_RDI_HOLD_SCHEMA_INVALID'}
 if($proc.ExitCode -ne 0){throw 'PI_RDI_PARTIAL_CORPUS_HOLD'}
 Write-Output 'PI_RDI_CORPUS_INVENTORY=PASS_CANDIDATE_ONLY'
}finally{$env:PI_RDI_COMPOSER_AUTOLOAD=$prior}
