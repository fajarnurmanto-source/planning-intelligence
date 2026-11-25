#requires -Version 5.1
param([Parameter(Mandatory=$true)][string]$ExpectedCommit)
$ErrorActionPreference='Stop'
$repo=(Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$head=(& git -C $repo rev-parse HEAD).Trim()
if($LASTEXITCODE -ne 0 -or $head -cne $ExpectedCommit){throw 'PI_RDI03B_PIN_HOLD'}
$dirty=@(& git -C $repo status --porcelain)
if($LASTEXITCODE -ne 0 -or $dirty.Count -ne 0){throw 'PI_RDI03B_DIRTY_HOLD'}
$src=Join-Path $env:LOCALAPPDATA 'PlanningIntelligence\RDI02_PLANNING_CORPUS\ce8f78449f024d7a97d5c86f7edf28d4'
$listener=@(Get-NetTCPConnection -LocalPort 8820 -State Listen)
if($listener.Count -ne 1){throw 'PI_RDI03B_LISTENER_HOLD'}
$php=(Get-CimInstance Win32_Process -Filter "ProcessId=$($listener[0].OwningProcess)").ExecutablePath
if(-not $php -or (Split-Path $php -Leaf) -ne 'php.exe'){throw 'PI_RDI03B_PHP_HOLD'}
$autoload='D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel\vendor\autoload.php'
if(-not (Test-Path -LiteralPath $autoload)){throw 'PI_RDI03B_VENDOR_HOLD'}
$pins=@(
 @('YOG-RENSTRA-PD-2025-2029','10FF7F1CF7353B3CF6BDABB9AA3C1BCE62639720DA894877601C2C988010F224','5A8E7AF524D5866A1F70337E656402A9A7B804D6D14A39985BBCC5A16636EC9F','5FE3760DB227E796E3B3B49EA1388BCF81876592A8F658B3A55176B1D8F99BD1'),
 @('YOG-RENJA-PD-2026','6594EF5EDC6B439DAC4A5D19735F9B34E911980C5AA33C6B2F5EC7BE85E5938F','6D13BFE400EBE66AED18AFA41D74ADD6957E67FABA6EA1967A8F9EC824601534','A9E8FF6E1D834A542AF1D4E08ECD229CE3CAF7FBFC6F2301470D61401144D660')
)
$out=Join-Path $env:LOCALAPPDATA ('PlanningIntelligence\RDI03B_ADAPTIVE\'+[guid]::NewGuid().ToString('N'))
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
   $old=Join-Path $env:LOCALAPPDATA ('PlanningIntelligence\RDI02C_EVIDENCE\7e2d0e4b79cd435e82370d872a15bb2d\'+$id+'.evidence.json')
   $receipt=Join-Path $out ($id+'.adaptive.json')
   $script=Join-Path $repo 'scripts\pi-rdi03b-adaptive-table.php'
   $proc=Start-Process -FilePath $php -ArgumentList @('-d','memory_limit=768M',('"'+$script+'"'),$id,('"'+$file+'"'),$sha,('"'+$baseline+'"'),$profileSha,('"'+$old+'"'),$pin[3]) -NoNewWindow -Wait -PassThru -RedirectStandardOutput $receipt -RedirectStandardError ($receipt+'.stderr.log')
   if($proc.ExitCode -ne 0){Get-Content -LiteralPath ($receipt+'.stderr.log');throw 'PI_RDI03B_EVIDENCE_HOLD'}
   $r=Get-Content -LiteralPath $receipt -Raw -Encoding UTF8|ConvertFrom-Json
   if($r.source_sha256 -cne $sha -or $r.baseline_sha256 -cne $profileSha -or $r.production_admission -ne 'DENIED'){throw 'PI_RDI03B_RECEIPT_HOLD'}
   if($r.input_evidence_sha256 -cne $pin[3] -or $r.numeric_admission -ne 0){throw 'PI_RDI03B_RECEIPT_HOLD'}
   Write-Output ('PI_RDI03B_DOCUMENT='+$id+'|OCCURRENCES='+$r.occurrences)
   $r.counts|ConvertTo-Json -Compress|Write-Output
   $r.samples.PSObject.Properties|ForEach-Object {
      $s=$_.Value
      [pscustomobject]@{shape=$_.Name;page=$s.page;line=$s.line;code=$s.code;boundary=$s.boundary;token_rows=$s.token_rows;header_signals=$s.header_signals;agency_heading_signals=$s.agency_heading_signals}|ConvertTo-Json -Depth 10 -Compress|Write-Output
   }
   Write-Output ('PI_RDI03B_SHA256='+(Get-FileHash -LiteralPath $receipt -Algorithm SHA256).Hash)
   $ok++
  }catch{Write-Output ('PI_RDI03B_HOLD='+$pin[0]+'|'+$_.Exception.Message)}
 }
 Write-Output ('PI_RDI03B_OUTPUT='+$out)
 Write-Output ('PI_RDI03B_PROCESSED='+$ok+'/2')
 if($ok -ne 2){throw 'PI_RDI03B_PARTIAL_HOLD'}
 Write-Output 'PI_RDI03B_CORPUS=PASS_CANDIDATE_ONLY'
}finally{$env:PI_RDI_COMPOSER_AUTOLOAD=$prior}
