#requires -Version 5.1
param([Parameter(Mandatory=$true)][string]$ExpectedCommit)
$ErrorActionPreference='Stop'
$repo=(Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
if((& git -C $repo rev-parse HEAD).Trim() -cne $ExpectedCommit -or $LASTEXITCODE -ne 0){throw 'PI_RAI_PIN_HOLD'}
if(@(& git -C $repo status --porcelain).Count -ne 0 -or $LASTEXITCODE -ne 0){throw 'PI_RAI_DIRTY_HOLD'}
$listener=@(Get-NetTCPConnection -LocalPort 8820 -State Listen)
if($listener.Count -ne 1){throw 'PI_RAI_LISTENER_HOLD'}
$php=(Get-CimInstance Win32_Process -Filter "ProcessId=$($listener[0].OwningProcess)").ExecutablePath
if(-not $php -or (Split-Path $php -Leaf) -ne 'php.exe'){throw 'PI_RAI_PHP_HOLD'}
$autoload='D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel\vendor\autoload.php'
if(-not(Test-Path -LiteralPath $autoload)){throw 'PI_RAI_VENDOR_HOLD'}
$root=Join-Path $env:LOCALAPPDATA 'PlanningIntelligence\RAI_PUBLIC_CANDIDATE_REPOSITORY'
$out=Join-Path $env:LOCALAPPDATA ('PlanningIntelligence\RAI_LIVE_BENCHMARK\'+[guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $out|Out-Null
$prior=$env:PI_RDI_COMPOSER_AUTOLOAD
try{
 $env:PI_RDI_COMPOSER_AUTOLOAD=$autoload
 $receipt=Join-Path $out 'benchmark.json'
 $script=Join-Path $repo 'scripts\pi-rai-live-manifestation.php'
 $proc=Start-Process -FilePath $php -ArgumentList @('-d','memory_limit=768M',('"'+$script+'"'),('"'+$root+'"'),('"'+$out+'"')) -NoNewWindow -Wait -PassThru -RedirectStandardOutput $receipt -RedirectStandardError ($receipt+'.stderr.log')
 if($proc.ExitCode -eq 2){Get-Content -LiteralPath ($receipt+'.stderr.log');throw 'PI_RAI_BENCH_EXECUTION_HOLD'}
 Get-Content -LiteralPath $receipt -Raw -Encoding UTF8
 Write-Output ('RAI_BENCH_RECEIPT='+$receipt)
 Write-Output ('RAI_BENCH_RECEIPT_SHA256='+(Get-FileHash -LiteralPath $receipt -Algorithm SHA256).Hash)
 if($proc.ExitCode -ne 0){throw 'PI_RAI_BENCH_PARTIAL_HOLD'}
}finally{$env:PI_RDI_COMPOSER_AUTOLOAD=$prior}
