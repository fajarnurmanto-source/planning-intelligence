# Read-only RDI corpus execution. Does not alter original application.
param(
 [Parameter(Mandatory=$true)][string]$CandidateRepo,
 [Parameter(Mandatory=$true)][string]$ExpectedCommit,
 [string]$NationalRoot,
 [switch]$Layout,
 [switch]$Multiline
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
 if($Layout){$argsRdi+='--layout'}
 if($Multiline){$argsRdi+='--multiline'}
 if($NationalRoot){$argsRdi+="--national-root=$NationalRoot"}
 # Start-Process avoids Windows PowerShell's lossy native UTF-8 pipe decoding.
 $quoted=@('-d','memory_limit=1024M','-d','display_errors=stderr')+@($argsRdi|ForEach-Object {'"'+$_+'"'})
 $proc=Start-Process -FilePath $php -ArgumentList $quoted -NoNewWindow -Wait -PassThru -RedirectStandardOutput $receipt -RedirectStandardError (Join-Path $out 'stderr.log')
 if($proc.ExitCode -ne 0){Get-Content -LiteralPath (Join-Path $out 'stderr.log')}
 $json=Get-Content -LiteralPath $receipt -Raw -Encoding UTF8|ConvertFrom-Json
 foreach($item in $json.documents.PSObject.Properties){
  $d=$item.Value
  if($Multiline -and $d.multiline_fiscal){$m=$d.multiline_fiscal;Write-Output ('PI_RDI_MULTILINE='+$item.Name+'|FRAGMENTS='+$m.fragment_count+'|CODES='+$m.resolved_code_candidates+'|AMOUNTS='+$m.positional_amount_candidates+'|ROW_SHAPES='+$m.row_shape_candidates+'|CARRY_CANDIDATES='+$m.previous_page_scope_candidates);foreach($reason in $m.hold_reason_counts.PSObject.Properties){Write-Output ('PI_RDI_MULTILINE_HOLD='+$item.Name+'|'+$reason.Name+'|'+$reason.Value)}}
  if($Layout -and $d.layout_candidates){$l=$d.layout_candidates;Write-Output ('PI_RDI_LAYOUT='+$item.Name+'|NODES='+@($l.hierarchy_nodes).Count+'|AMBIGUOUS='+$l.ambiguous_node_count+'|ACCOUNT_FRAGMENTS='+$l.account_fragments)}
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
