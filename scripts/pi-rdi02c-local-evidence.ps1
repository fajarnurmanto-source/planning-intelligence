#requires -Version 5.1
param([Parameter(Mandatory=$true)][string]$ExpectedCommit)
$ErrorActionPreference='Stop'
$repo=(Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$head=(& git -C $repo rev-parse HEAD).Trim()
if($LASTEXITCODE -ne 0 -or $head -cne $ExpectedCommit){throw 'PI_RDI02C_PIN_HOLD'}
$dirty=@(& git -C $repo status --porcelain)
if($LASTEXITCODE -ne 0 -or $dirty.Count -ne 0){throw 'PI_RDI02C_DIRTY_HOLD'}
$src=Join-Path $env:LOCALAPPDATA 'PlanningIntelligence\RDI02_PLANNING_CORPUS\ce8f78449f024d7a97d5c86f7edf28d4'
$listener=@(Get-NetTCPConnection -LocalPort 8820 -State Listen)
if($listener.Count -ne 1){throw 'PI_RDI02C_LISTENER_HOLD'}
$php=(Get-CimInstance Win32_Process -Filter "ProcessId=$($listener[0].OwningProcess)").ExecutablePath
if(-not $php -or (Split-Path $php -Leaf) -ne 'php.exe'){throw 'PI_RDI02C_PHP_HOLD'}
$autoload='D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel\vendor\autoload.php'
if(-not (Test-Path -LiteralPath $autoload)){throw 'PI_RDI02C_VENDOR_HOLD'}
$pins=@(
 @('YOG-RPJPD-2025-2045','9C3052743E381BE65CEC9A4BAC539D950AA4967D30D5401C96602FBA962B30B2','456BB357D243AD18CD8AE8464CF8BD0428CAC8BB9DF92B6E92F92B83D1020C39'),
 @('YOG-RPJMD-2025-2029','04999C70B891D985C23149227E5D90C244981CA8C5A145133F785F55CC03962E','B0A6CF3513F1D02C29672297D3D4254F0A3F201B570CF8DC8E8BDF5EB4C7A567'),
 @('YOG-RKPD-2026','56397218C338B0C44915141D58E5733B653B466BC5F54C78C06372C1D5C68E90','560FBB2F67C168E7C9CE8FB85B50467F7D1F19EBDEE7C4B84E22B2F593526673'),
 @('YOG-RENSTRA-PD-2025-2029','10FF7F1CF7353B3CF6BDABB9AA3C1BCE62639720DA894877601C2C988010F224','5A8E7AF524D5866A1F70337E656402A9A7B804D6D14A39985BBCC5A16636EC9F'),
 @('YOG-RENJA-PD-2026','6594EF5EDC6B439DAC4A5D19735F9B34E911980C5AA33C6B2F5EC7BE85E5938F','6D13BFE400EBE66AED18AFA41D74ADD6957E67FABA6EA1967A8F9EC824601534')
)
$out=Join-Path $env:LOCALAPPDATA ('PlanningIntelligence\RDI02C_EVIDENCE\'+[guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $out|Out-Null
$prior=$env:PI_RDI_COMPOSER_AUTOLOAD
$ok=0
try{
 $env:PI_RDI_COMPOSER_AUTOLOAD=$autoload
 foreach($pin in $pins){
  try{
   $id=$pin[0];$sha=$pin[1];$profileSha=$pin[2]
   $file=Join-Path $src ($id+'-'+$sha+'.pdf')
   $baseline=Join-Path $src ($id+'.profile.json')
   $receipt=Join-Path $out ($id+'.evidence.json')
   $script=Join-Path $repo 'scripts\pi-rdi02c-planning-evidence.php'
   $proc=Start-Process -FilePath $php -ArgumentList @('-d','memory_limit=768M',('"'+$script+'"'),$id,('"'+$file+'"'),$sha,('"'+$baseline+'"'),$profileSha) -NoNewWindow -Wait -PassThru -RedirectStandardOutput $receipt -RedirectStandardError ($receipt+'.stderr.log')
   if($proc.ExitCode -ne 0){Get-Content -LiteralPath ($receipt+'.stderr.log');throw 'PI_RDI02C_EVIDENCE_HOLD'}
   $r=Get-Content -LiteralPath $receipt -Raw -Encoding UTF8|ConvertFrom-Json
   if($r.source_sha256 -cne $sha -or $r.baseline_sha256 -cne $profileSha -or $r.production_admission -ne 'DENIED'){throw 'PI_RDI02C_RECEIPT_HOLD'}
   $codes=@($r.code_occurrences|Where-Object {$_.state -eq 'CODE_ROW_CANDIDATE'}|Select-Object -ExpandProperty code -Unique)
   Write-Output ('PI_RDI02C_DOCUMENT='+$id+'|OCCURRENCES='+@($r.code_occurrences).Count+'|UNIQUE_CODES='+$codes.Count+'|NO_TEXT='+@($r.no_text_pages).Count)
   $r.evidence_counts|ConvertTo-Json -Compress|Write-Output
   $r.evidence_samples|ConvertTo-Json -Depth 10 -Compress|Write-Output
   if(-not $r.identity_heading_signal){$r.identity_heading_evidence|Select-Object -First 20|ConvertTo-Json -Depth 5 -Compress|Write-Output}
   Write-Output ('PI_RDI02C_SHA256='+(Get-FileHash -LiteralPath $receipt -Algorithm SHA256).Hash)
   $ok++
  }catch{Write-Output ('PI_RDI02C_HOLD='+$pin[0]+'|'+$_.Exception.Message)}
 }
 Write-Output ('PI_RDI02C_OUTPUT='+$out)
 Write-Output ('PI_RDI02C_PROCESSED='+$ok+'/5')
 if($ok -ne 5){throw 'PI_RDI02C_PARTIAL_HOLD'}
 Write-Output 'PI_RDI02C_CORPUS=PASS_CANDIDATE_ONLY'
}finally{$env:PI_RDI_COMPOSER_AUTOLOAD=$prior}
