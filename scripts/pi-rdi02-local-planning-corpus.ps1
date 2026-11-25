#requires -Version 5.1
param([Parameter(Mandatory=$true)][string]$ExpectedCommit)
$ErrorActionPreference='Stop'
$repo=(Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$head=(& git -C $repo rev-parse HEAD).Trim()
if($LASTEXITCODE -ne 0 -or $head -cne $ExpectedCommit){throw 'PI_RDI02_PIN_HOLD'}
$dirty=@(& git -C $repo status --porcelain)
if($LASTEXITCODE -ne 0 -or $dirty.Count -ne 0){throw 'PI_RDI02_DIRTY_HOLD'}
$listener=@(Get-NetTCPConnection -LocalPort 8820 -State Listen)
if($listener.Count -ne 1){throw 'PI_RDI02_LISTENER_HOLD'}
$php=(Get-CimInstance Win32_Process -Filter "ProcessId=$($listener[0].OwningProcess)").ExecutablePath
if(-not $php -or (Split-Path $php -Leaf) -ne 'php.exe'){throw 'PI_RDI02_PHP_HOLD'}
$autoload='D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel\vendor\autoload.php'
if(-not (Test-Path -LiteralPath $autoload)){throw 'PI_RDI02_VENDOR_HOLD'}
$curl=(Get-Command curl.exe -ErrorAction Stop).Source
$queue=Get-Content -LiteralPath (Join-Path $repo 'laravel\resources\pi\rdi02-planning-sources.json') -Raw -Encoding UTF8|ConvertFrom-Json
if($queue.schema -ne 'PI_RDI02_OFFICIAL_PLANNING_SOURCE_QUEUE_V1' -or @($queue.documents).Count -ne 5){throw 'PI_RDI02_QUEUE_HOLD'}
$out=Join-Path $env:LOCALAPPDATA ('PlanningIntelligence\RDI02_PLANNING_CORPUS\'+[guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $out|Out-Null
$prior=$env:PI_RDI_COMPOSER_AUTOLOAD
$ok=0
try{
 $env:PI_RDI_COMPOSER_AUTOLOAD=$autoload
 foreach($d in $queue.documents){
  try{
   if($d.id -notmatch '^YOG-[A-Z0-9-]+$' -or $d.pdf -notmatch '^https://peraturan\.bpk\.go\.id/Download/[0-9]+/[A-Za-z0-9%._-]+\.pdf$'){throw 'PI_RDI02_URL_HOLD'}
   $part=Join-Path $out ($d.id+'.part')
   & $curl --fail --silent --show-error --proto '=https' --max-redirs 0 --connect-timeout 20 --max-time 300 --max-filesize 134217728 --output $part $d.pdf
   if($LASTEXITCODE -ne 0){throw 'PI_RDI02_DOWNLOAD_HOLD'}
   $at=(Get-Date).ToString('o')
   $size=(Get-Item -LiteralPath $part).Length
   if($size -lt 128 -or $size -gt 134217728){throw 'PI_RDI02_SIZE_HOLD'}
   $sha=(Get-FileHash -LiteralPath $part -Algorithm SHA256).Hash
   $file=Join-Path $out ($d.id+'-'+$sha+'.pdf')
   Move-Item -LiteralPath $part -Destination $file
   $receipt=Join-Path $out ($d.id+'.profile.json')
   $script=Join-Path $repo 'scripts\pi-rdi02-planning-profile.php'
   $proc=Start-Process -FilePath $php -ArgumentList @('-d','memory_limit=512M',('"'+$script+'"'),$d.id,('"'+$file+'"'),$sha) -NoNewWindow -Wait -PassThru -RedirectStandardOutput $receipt -RedirectStandardError ($receipt+'.stderr.log')
   if($proc.ExitCode -ne 0){Get-Content -LiteralPath ($receipt+'.stderr.log');throw 'PI_RDI02_PROFILE_HOLD'}
   $r=Get-Content -LiteralPath $receipt -Raw -Encoding UTF8|ConvertFrom-Json
   if($r.source_sha256 -cne $sha -or $r.production_admission -ne 'DENIED' -or @($r.pages).Count -ne $r.physical_pages){throw 'PI_RDI02_RECEIPT_HOLD'}
   Write-Output ('PI_RDI02_DOCUMENT='+$d.id+'|PAGES='+$r.physical_pages+'|TEXT='+$r.text_pages+'|IDENTITY_SIGNAL='+$r.identity_heading_signal+'|BYTES='+$size+'|SHA256='+$sha+'|CAPTURED='+$at)
   $r.signal_counts|ConvertTo-Json -Compress|Write-Output
   $r.samples|ConvertTo-Json -Depth 8 -Compress|Write-Output
   Write-Output ('PI_RDI02_PROFILE_SHA256='+(Get-FileHash -LiteralPath $receipt -Algorithm SHA256).Hash)
   $ok++
  }catch{Write-Output ('PI_RDI02_HOLD='+$d.id+'|'+$_.Exception.Message)}
 }
 Write-Output ('PI_RDI02_OUTPUT='+$out)
 Write-Output ('PI_RDI02_PROCESSED='+$ok+'/5')
 if($ok -ne 5){throw 'PI_RDI02_PARTIAL_CORPUS_HOLD'}
 Write-Output 'PI_RDI02_CORPUS=PASS_CANDIDATE_ONLY'
}finally{$env:PI_RDI_COMPOSER_AUTOLOAD=$prior}
