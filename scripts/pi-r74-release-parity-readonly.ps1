#requires -Version 5.1
<#
Planning Intelligence R74 => R88/R89 source parity CENSUS ONLY.
Never deploy, copy, reset, stop, start or edit anything. Reads only Git-tracked
non-secret runtime files from a PINNED QA checkout and the original Laravel root.
No .env, DB, secrets, storage, vendor, logs, credentials, migrations or external HTTP.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory=$true)][string]$CandidateRepo,
    [Parameter(Mandatory=$true)][ValidatePattern('^[0-9a-fA-F]{40}$')][string]$ExpectedCommit,
    [string]$AppRoot='D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel'
)
Set-StrictMode -Version 2
$ErrorActionPreference='Stop'
$original='D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel'
if ($AppRoot -ne $original) {throw 'PI_R74_PARITY_ORIGINAL_SOURCE_ROOT_CHANGED'}
if (-not (Test-Path -LiteralPath (Join-Path $AppRoot 'artisan') -PathType Leaf)) {
    throw 'PI_R74_PARITY_ORIGINAL_LARAVEL_MISSING'
}
if (-not (Test-Path -LiteralPath (Join-Path $CandidateRepo 'laravel\artisan') -PathType Leaf)) {
    throw 'PI_R74_PARITY_CANDIDATE_NOT_LARAVEL_REPO'
}
$git=(Get-Command git.exe -ErrorAction Stop).Source
$actualHead=([string](& $git -C $CandidateRepo rev-parse HEAD)).Trim()
if ($LASTEXITCODE -ne 0 -or
    $actualHead -cne $ExpectedCommit.ToLowerInvariant()) {
    throw 'PI_R74_PARITY_CANDIDATE_HEAD_MISMATCH'
}
$dirty=@(& $git -C $CandidateRepo status --porcelain)
if ($LASTEXITCODE -ne 0 -or $dirty.Count -ne 0) {
    throw 'PI_R74_PARITY_CANDIDATE_UNCOMMITTED_CHANGES'
}
# Only executable Laravel runtime paths, not tests or migrations; do not read
# .env, provider credentials, SQLite, logs, npm caches or generated evidence.
$allowed=@('laravel/app/','laravel/bootstrap/app.php',
    'laravel/routes/','laravel/config/','laravel/public/')
$tracked=@(& $git -C $CandidateRepo ls-files -- laravel/app laravel/routes laravel/config laravel/public laravel/bootstrap/app.php)
if ($LASTEXITCODE -ne 0) {throw 'PI_R74_PARITY_GIT_TRACKED_SCAN_FAILED'}
$totals=@{}
foreach($bucket in @('app','routes','config','public','bootstrap')){
    $totals[$bucket]=@{Same=0; Different=0; Missing=0; Count=0}
}
$examples=New-Object 'System.Collections.Generic.List[string]'
foreach ($relative in $tracked) {
    $name=[string]$relative
    if (-not $name.StartsWith('laravel/',[StringComparison]::Ordinal)) {continue}
    $valid=$false
    foreach ($prefix in $allowed) {
        if ($name.StartsWith($prefix,[StringComparison]::Ordinal)) {
            $valid=$true; break
        }
    }
    if (-not $valid -or $name -match '(?i)(^|/)\.env|(^|/)(database|storage|vendor|node_modules|secrets|private)(/|$)') {continue}
    $parts=$name.Split('/')
    if ($parts.Length -lt 3) {continue}
    $bucket=$parts[1]
    if (-not $totals.ContainsKey($bucket)) {continue}
    $relativeLocal=($parts[2..($parts.Length-1)] -join '\')
    $candidate=Join-Path (Join-Path $CandidateRepo 'laravel') (Join-Path $bucket $relativeLocal)
    $live=Join-Path $AppRoot (Join-Path $bucket $relativeLocal)
    if (-not (Test-Path -LiteralPath $candidate -PathType Leaf)) {
        throw ('PI_R74_PARITY_TRACKED_CANDIDATE_MISSING='+$name)
    }
    $totals[$bucket].Count++
    if (-not (Test-Path -LiteralPath $live -PathType Leaf)) {
        $totals[$bucket].Missing++
        if ($examples.Count -lt 24) {$examples.Add('MISSING:'+($name.Substring(8)))}
    } elseif ((Get-FileHash -LiteralPath $candidate -Algorithm SHA256).Hash -eq
        (Get-FileHash -LiteralPath $live -Algorithm SHA256).Hash) {
        $totals[$bucket].Same++
    } else {
        $totals[$bucket].Different++
        if ($examples.Count -lt 24) {$examples.Add('DIFFERENT:'+($name.Substring(8)))}
    }
}
$matched=0;$diff=0;$missing=0;$count=0
foreach($bucket in @('app','routes','config','public','bootstrap')){
    $m=$totals[$bucket]
    $matched+=$m.Same; $diff+=$m.Different; $missing+=$m.Missing; $count+=$m.Count
    Write-Output ('PI_R74_PARITY_'+$bucket.ToUpperInvariant()+
        '_TRACKED='+$m.Count+',SAME='+$m.Same+
        ',DIFFERENT='+$m.Different+',MISSING='+$m.Missing)
}
Write-Output ('PI_R74_PARITY_PIN='+$actualHead)
Write-Output ('PI_R74_PARITY_TOTAL_TRACKED='+$count)
Write-Output ('PI_R74_PARITY_TOTAL_SAME='+$matched)
Write-Output ('PI_R74_PARITY_TOTAL_DIFFERENT='+$diff)
Write-Output ('PI_R74_PARITY_TOTAL_MISSING='+$missing)
foreach ($item in $examples) {Write-Output ('PI_R74_PARITY_SAMPLE='+$item)}
if ($count -eq 0) {throw 'PI_R74_PARITY_EMPTY_CENSUS'}
if ($diff -eq 0 -and $missing -eq 0) {
    Write-Output 'PI_R74_RUNTIME_SOURCE_PARITY=TRACKED_RUNTIME_FILES_MATCH'
} else {
    Write-Output 'PI_R74_RUNTIME_SOURCE_PARITY=NOT_YET_CONSOLIDATED'
}
Write-Output 'PI_R74_PARITY_DB_ENV_APPKEY_SHORTCUT_PROCESSES=UNTOUCHED'
Write-Output 'PI_R74_PARITY_MODE=READ_ONLY_CENSUS_COMPLETE'
