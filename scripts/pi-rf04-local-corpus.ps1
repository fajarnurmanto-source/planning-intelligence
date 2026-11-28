#requires -Version 5.1
param([Parameter(Mandatory=$true)][string]$ExpectedCommit)
$ErrorActionPreference='Stop'
$repo=(Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
if((& git -C $repo rev-parse HEAD).Trim() -cne $ExpectedCommit -or $LASTEXITCODE -ne 0){throw 'PI_RF04_PIN_HOLD'}
if(@(& git -C $repo status --porcelain).Count -ne 0 -or $LASTEXITCODE -ne 0){throw 'PI_RF04_DIRTY_HOLD'}
$listener=@(Get-NetTCPConnection -LocalPort 8820 -State Listen)
if($listener.Count -ne 1){throw 'PI_RF04_LISTENER_HOLD'}
$php=(Get-CimInstance Win32_Process -Filter "ProcessId=$($listener[0].OwningProcess)").ExecutablePath
if(-not $php -or (Split-Path $php -Leaf) -ne 'php.exe'){throw 'PI_RF04_PHP_HOLD'}
$autoload='D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel\vendor\autoload.php'
if(-not(Test-Path -LiteralPath $autoload)){throw 'PI_RF04_VENDOR_HOLD'}
$q=Get-Content -LiteralPath (Join-Path $repo 'laravel\resources\pi\rf04-primary-acquisition.json') -Raw -Encoding UTF8|ConvertFrom-Json
$out=Join-Path $env:LOCALAPPDATA ('PlanningIntelligence\RF04_CORPUS\'+[guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $out|Out-Null
foreach($d in $q.documents){
 $uri=[uri]$d.pdf_url
 if($uri.Scheme -ne 'https' -or $uri.Host -notin @('peraturan.bpk.go.id','peraturan.go.id','www.peraturan.go.id') -or $uri.UserInfo){throw 'PI_RF04_URL_HOLD'}
 $file=Join-Path $out ($d.id+'.pdf')
 & curl.exe --fail --silent --show-error --proto '=https' --connect-timeout 15 --max-time 180 --max-filesize 83886080 $uri.AbsoluteUri -o $file
 if($LASTEXITCODE -ne 0){if(Test-Path -LiteralPath $file){Remove-Item -LiteralPath $file};Write-Output ('RF04_FETCH_HOLD='+$d.id);continue}
 Write-Output ('RF04_FETCHED='+$d.id)
}
$prior=$env:PI_RDI_COMPOSER_AUTOLOAD
try{
 $env:PI_RDI_COMPOSER_AUTOLOAD=$autoload
 $script=Join-Path $repo 'scripts\pi-rf04-corpus-inventory.php'
 $receipt=Join-Path $out 'inventory.json'
 $proc=Start-Process -FilePath $php -ArgumentList @('-d','memory_limit=1536M',('"'+$script+'"'),('"'+$out+'"')) -NoNewWindow -Wait -PassThru -RedirectStandardOutput $receipt -RedirectStandardError ($receipt+'.stderr.log')
 if($proc.ExitCode -eq 2){Get-Content -LiteralPath ($receipt+'.stderr.log');throw 'PI_RF04_EXECUTION_HOLD'}
 $r=Get-Content -LiteralPath $receipt -Raw -Encoding UTF8|ConvertFrom-Json
 foreach($p in $r.documents.PSObject.Properties){
  $v=$p.Value
  [pscustomobject]@{id=$p.Name;sha=$v.source_sha256;pages=$v.inventory.expected_pages;text_pages=$v.inventory.text_pages;signals=$v.format_profile.counts}|ConvertTo-Json -Depth 8 -Compress|Write-Output
 }
 Write-Output ('RF04_PROCESSED='+$r.processed_documents+'/'+$r.expected_documents)
 Write-Output ('RF04_HOLDS='+($r.holds|ConvertTo-Json -Compress))
 Write-Output ('RF04_RECEIPT='+$receipt)
 Write-Output ('RF04_RECEIPT_SHA256='+(Get-FileHash -LiteralPath $receipt -Algorithm SHA256).Hash)
 if($proc.ExitCode -ne 0 -or $r.processed_documents -ne $r.expected_documents){throw 'PI_RF04_PARTIAL_HOLD'}
 Write-Output 'RF04=PASS_INVENTORY_CANDIDATE_ONLY'
}finally{$env:PI_RDI_COMPOSER_AUTOLOAD=$prior}
