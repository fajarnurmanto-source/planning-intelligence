<#
Read-only benchmark runner for two ALREADY-RUNNING isolated PI R87 source snapshots.
No source, user, session table, or bridge marker is edited by this script.
Login generates ordinary QA sessions in the two test runtimes.
Requires local Node.js + playwright-core + installed Microsoft Edge.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory=$true)][string]$BeforeUrl,
    [Parameter(Mandatory=$true)][string]$AfterUrl,
    [int]$Iterations = 10,
    [string]$Username = 'fajar.nurmanto',
    [switch]$Headed
)
$ErrorActionPreference = 'Stop'
$Kit = $PSScriptRoot
$ResultDir = Join-Path $Kit ('results-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
New-Item -ItemType Directory -Path $ResultDir -Force | Out-Null
$StopFile = Join-Path $ResultDir '.stop-sampling'
$Csv = Join-Path $ResultDir 'r87-php-process-resources.csv'

function Get-LocalHttpUrl([string]$raw, [string]$label) {
    try { $u = [Uri]$raw } catch { throw "$label is not a valid URL." }
    if ($u.Scheme -ne 'http' -or ($u.Host -ne 'localhost' -and $u.Host -ne '127.0.0.1')) {
        throw "$label must be an http://127.0.0.1 or http://localhost URL."
    }
    if (!$u.AbsolutePath.EndsWith('/regulatory-laravel-r70g.html')) { throw "$label must target regulatory-laravel-r70g.html." }
    return $u
}
$Before = Get-LocalHttpUrl $BeforeUrl 'BEFORE'
$After  = Get-LocalHttpUrl $AfterUrl  'AFTER'
if ($Before.Authority -eq $After.Authority) { throw 'BEFORE and AFTER must run from separate ports/origins.' }

$Targets = @()
foreach ($pair in @(@{label='BEFORE';port=$Before.Port},@{label='AFTER';port=$After.Port})) {
    $row = Get-NetTCPConnection -LocalPort $pair.port -State Listen -ErrorAction SilentlyContinue |
        Select-Object -First 1
    if ($null -eq $row) { throw "No server listening on port $($pair.port) ($($pair.label))." }
    $proc = Get-CimInstance Win32_Process -Filter "ProcessId=$($row.OwningProcess)"
    if ($null -eq $proc -or $proc.Name -ne 'php.exe') {
        throw "Server on port $($pair.port) is not a PHP process; investigate before benchmarking."
    }
    $Targets += [PSCustomObject]@{ label=$pair.label; pid=[int]$row.OwningProcess; port=[int]$pair.port; cmd=$proc.CommandLine }
}
if ($Targets[0].pid -eq $Targets[1].pid) {throw 'Before and after have same PHP PID; cannot isolate process metrics.'}
$Targets | Select-Object label,pid,port,cmd | Format-Table -Wrap | Out-String | Write-Host
Write-Host 'Confirm BEFORE/AFTER source trees match two Git snapshots; this script cannot independently attest source provenance.' -ForegroundColor Yellow

if (-not (Get-Command node -ErrorAction SilentlyContinue)) { throw 'Node.js is not installed or on PATH.' }
Push-Location $Kit
$job = $null
$secure = $null
$bstr = [IntPtr]::Zero
$plain = $null
try {
    & node -e "require.resolve('playwright-core')" 2>$null | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Missing playwright-core. In the kit directory run: npm install --no-save playwright-core' }

    # Sampling is passive and only reads PID counters. Source processes are not stopped.
    $jobConfig = [PSCustomObject]@{Targets=$Targets; Csv=$Csv; StopFile=$StopFile}
    $job = Start-Job -ArgumentList $jobConfig -ScriptBlock {
        param($config)
        $Targets = $config.Targets
        $Csv = $config.Csv
        $StopFile = $config.StopFile
        $ErrorActionPreference = 'Continue'
        $prev = @{}
        $cores = [Environment]::ProcessorCount
        while (-not (Test-Path -LiteralPath $StopFile)) {
            $now = Get-Date
            foreach ($t in $Targets) {
                try {
                    $proc = Get-Process -Id $t.pid -ErrorAction Stop
                    $cpu = [double]$proc.CPU
                    $cpuPct = $null
                    if ($prev.ContainsKey($t.label)) {
                        $prevRow = $prev[$t.label]
                        $wallSeconds = ($now - $prevRow.at).TotalSeconds
                        if ($wallSeconds -gt 0) {
                            $cpuPct = [Math]::Round(100 * ($cpu-$prevRow.cpu) / ($wallSeconds * $cores),2)
                        }
                    }
                    $prev[$t.label] = @{ at=$now;cpu=$cpu }
                    $record = [PSCustomObject]@{
                        at = $now.ToUniversalTime().ToString('o')
                        version = $t.label
                        process_id = $t.pid
                        port = $t.port
                        working_set_mb = [Math]::Round($proc.WorkingSet64 / 1MB,2)
                        private_bytes_mb = [Math]::Round($proc.PrivateMemorySize64 / 1MB,2)
                        total_cpu_seconds = [Math]::Round($cpu,3)
                        cpu_percent_total_machine = $cpuPct
                    }
                    $record | Export-Csv -LiteralPath $Csv -Append -NoTypeInformation -Encoding UTF8
                } catch {
                    # Process may have exited. No attempt is made to restart it.
                }
            }
            Start-Sleep -Milliseconds 500
        }
    }

    $secure = Read-Host 'Password QA lokal (tidak disimpan dalam file/log)' -AsSecureString
    $bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
    $plain = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr)

    $nodeArgs = @('r87-browser-benchmark.mjs','--before',$BeforeUrl,'--after',$AfterUrl,
                  '--out',$ResultDir,'--iterations',[string]$Iterations,'--username',$Username)
    if ($Headed) {$nodeArgs += '--headed'}
    $plain | & node @nodeArgs
    $exit = $LASTEXITCODE
    Write-Host "Benchmark exit code: $exit" -ForegroundColor Cyan
    Write-Host "Report folder: $ResultDir" -ForegroundColor Cyan
    if ($exit -ne 0) { Write-Warning 'Benchmark incomplete; examine results. Do not declare R87 acceptance PASS.' }
}
finally {
    $plain = $null
    if ($bstr -ne [IntPtr]::Zero) {[Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr)}
    if ($null -ne $secure) {$secure.Dispose()}
    [System.IO.File]::WriteAllText($StopFile, 'stop')
    if ($null -ne $job) {
        Wait-Job -Job $job -Timeout 8 | Out-Null
        Receive-Job -Job $job -ErrorAction SilentlyContinue | Out-Null
        Remove-Job -Job $job -Force -ErrorAction SilentlyContinue
    }
    Remove-Item -LiteralPath $StopFile -ErrorAction SilentlyContinue
    Pop-Location
}
