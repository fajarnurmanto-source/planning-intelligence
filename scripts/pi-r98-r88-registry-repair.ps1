#requires -Version 5.1
<#
PI R98: fix live 8820 R88 crosswalk HTTP503 caused by missing TWO
immutable reviewed source registries. Preflight READ_ONLY by default.
-Apply ONLY adds previously MISSING registries from clean pinned Git source.
Existing different registry: HOLD; never overwrite. Existing matching: keep.
No secrets, SQLite, APP_KEY, launcher, asset, PHP process, network or migration.
CI fixture is allowed only under GitHub Actions RUNNER_TEMP.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory=$true)][string]$CandidateRepo,
    [Parameter(Mandatory=$true)][ValidatePattern('^[0-9a-fA-F]{40}$')][string]$ExpectedCommit,
    [switch]$Apply,
    [string]$CiFixtureRoot,
    [switch]$CiFailAfterFirstAdd
)
Set-StrictMode -Version 2
$ErrorActionPreference = 'Stop'
$original = 'D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel'
$launcher = 'D:\Research\PlanningIntelligence\PI_UNIFIED_LAUNCHER_R74_20261111'
$ci = -not [string]::IsNullOrWhiteSpace($CiFixtureRoot)
if($ci) {
    if($env:GITHUB_ACTIONS -ne 'true' -or [string]::IsNullOrWhiteSpace($env:RUNNER_TEMP)) {
        throw 'PI_R98_CI_MODE_NOT_AUTHORIZED'
    }
    $runner = [IO.Path]::GetFullPath($env:RUNNER_TEMP).TrimEnd([char]92,[char]47)
    $fixture = [IO.Path]::GetFullPath($CiFixtureRoot).TrimEnd([char]92,[char]47)
    if(-not $fixture.StartsWith(($runner+[IO.Path]::DirectorySeparatorChar),
      [StringComparison]::OrdinalIgnoreCase)) {
        throw 'PI_R98_CI_OUTSIDE_RUNNER_TEMP'
    }
    $original = Join-Path $fixture 'laravel'
    $launcher = Join-Path $fixture 'launcher'
    Write-Output 'PI_R98_TARGET=CI_SYNTHETIC_ONLY'
} else {
    if($CiFailAfterFirstAdd) {throw 'PI_R98_INJECTION_FORBIDDEN_ON_ORIGINAL'}
    Write-Output 'PI_R98_TARGET=PROTECTED_ORIGINAL_8820'
}
$original = [IO.Path]::GetFullPath($original)
$rootPrefix = $original.TrimEnd([char]92,[char]47)+[IO.Path]::DirectorySeparatorChar

$protected = @(
    'artisan','.env','database\database.sqlite',
    'public\overview-r69c.json','public\uu12-2011-r70a.pdf'
)
foreach($rel in $protected) {
    if(-not (Test-Path -LiteralPath (Join-Path $original $rel) -PathType Leaf)) {
        throw ('PI_R98_PROTECTED_FILE_ABSENT='+$rel)
    }
}
if(-not (Test-Path -LiteralPath (Join-Path $launcher 'BUKA-PI-HIDDEN.vbs') -PathType Leaf)) {
    throw 'PI_R98_ORIGINAL_LAUNCHER_NOT_FOUND'
}
$git = (Get-Command git.exe -ErrorAction Stop).Source
$actual = ([string](& $git -C $CandidateRepo rev-parse HEAD)).Trim()
if($LASTEXITCODE -ne 0 -or $actual -cne $ExpectedCommit.ToLowerInvariant()) {
    throw 'PI_R98_CANDIDATE_PIN_MISMATCH'
}
$dirty = @(& $git -C $CandidateRepo status --porcelain)
if($LASTEXITCODE -ne 0 -or $dirty.Count -gt 0) {throw 'PI_R98_CANDIDATE_DIRTY'}

$items = @(
  [pscustomobject]@{
    Relative='resources\pi\affairs-nomenclature-seed-r87.json'
    Schema='PI_AFFAIR_FIRST_NOMENCLATURE_SEED_V1'
  },
  [pscustomobject]@{
    Relative='resources\pi\operational-definitions-r88.json'
    Schema='PI_R88_OPERATIONAL_DEFINITIONS_V1'
  }
)
$records=New-Object 'System.Collections.Generic.List[object]'
foreach($item in $items) {
    $candidate=[IO.Path]::GetFullPath((Join-Path (Join-Path $CandidateRepo 'laravel') $item.Relative))
    $target=[IO.Path]::GetFullPath((Join-Path $original $item.Relative))
    if(-not $target.StartsWith($rootPrefix,[StringComparison]::OrdinalIgnoreCase) -or
      -not (Test-Path -LiteralPath $candidate -PathType Leaf)) {
        throw ('PI_R98_PINNED_SOURCE_NOT_FOUND='+$item.Relative)
    }
    $json=Get-Content -LiteralPath $candidate -Raw -Encoding UTF8 | ConvertFrom-Json
    if($json.schema -cne $item.Schema) {throw ('PI_R98_REGISTRY_SCHEMA_INVALID='+$item.Relative)}
    if($item.Schema -eq 'PI_AFFAIR_FIRST_NOMENCLATURE_SEED_V1') {
        if(@($json.affairs).Count -ne 32 -or @($json.nomenclature).Count -lt 4 -or
           $json.gates.production_admission -cne 'DENIED') {
            throw 'PI_R98_AFFAIRS_REGISTRY_GATES_INVALID'
        }
    } else {
        if(@($json.definitions).Count -lt 1) {throw 'PI_R98_DEFINITION_REGISTRY_EMPTY'}
        foreach($def in @($json.definitions)) {
            if($def.relation -cne 'RELATED_CANDIDATE_NOT_IDENTICAL' -or
              $def.status -cne 'CANDIDATE_REVIEW_REQUIRED') {
                throw 'PI_R98_DEFINITION_REVIEW_GUARD_INVALID'
            }
        }
    }
    $expected=(Get-FileHash -LiteralPath $candidate -Algorithm SHA256).Hash
    $state='MISSING'
    if(Test-Path -LiteralPath $target) {
        $object=Get-Item -LiteralPath $target -Force
        if($object.PSIsContainer -or
          ($object.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
            throw ('PI_R98_UNSAFE_EXISTING_SOURCE='+$item.Relative)
        }
        $actualHash=(Get-FileHash -LiteralPath $target -Algorithm SHA256).Hash
        if($actualHash -cne $expected) {
            $state='DIFFERENT_HOLD'
        } else {$state='MATCH'}
    }
    foreach($folder in @((Split-Path $target -Parent),(Split-Path $candidate -Parent))) {
        if(Test-Path -LiteralPath $folder -PathType Container) {
            $obj=Get-Item -LiteralPath $folder -Force
            if(($obj.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
                throw ('PI_R98_REPARSE_DIRECTORY_FORBIDDEN='+$item.Relative)
            }
        }
    }
    $records.Add([pscustomobject]@{Relative=$item.Relative;Source=$candidate;Target=$target;Hash=$expected;State=$state})
    Write-Output ('PI_R98_REGISTRY='+$item.Relative+':'+$state)
}
$missing=@($records | Where-Object {$_.State -eq 'MISSING'})
$different=@($records | Where-Object {$_.State -eq 'DIFFERENT_HOLD'})
Write-Output ('PI_R98_MISSING_COUNT='+$missing.Count)
Write-Output ('PI_R98_DIFFERENT_COUNT='+$different.Count)
Write-Output ('PI_R98_CANDIDATE_PIN='+$actual)
Write-Output 'PI_R98_SQLITE_ENV_ACCOUNT_LAUNCHER=NOT_TOUCHED'
Write-Output 'PI_R98_CLOUD_PROVIDER_REQUESTS=NONE'
if($different.Count -gt 0) {
    throw 'PI_R98_EXISTING_RESOURCE_DRIFT_HOLD_NO_OVERWRITE'
}
if(-not $Apply) {
    Write-Output 'PI_R98_REGISTRY_PREFLIGHT=COMPLETE_READ_ONLY'
    return
}
if($missing.Count -eq 0) {
    Write-Output 'PI_R98_RESOURCE_FIX=NO_CHANGE_ALL_MATCH'
    return
}
# No server restart: validate current single PHP listener only.
$serverPid=0
if(-not $ci) {
    $listeners=@(Get-NetTCPConnection -LocalPort 8820 -State Listen -ErrorAction SilentlyContinue)
    if($listeners.Count -ne 1) {throw 'PI_R98_SINGLE_ORIGINAL_8820_LISTENER_REQUIRED'}
    $serverPid=[int]$listeners[0].OwningProcess
    $proc=Get-CimInstance Win32_Process -Filter "ProcessId=$serverPid" -ErrorAction Stop
    if($null -eq $proc -or $proc.Name -notmatch '^(php|php-cgi)\.exe$'){
        throw 'PI_R98_ORIGINAL_8820_MUST_BE_PHP'
    }
}
$stamp=Get-Date -Format 'yyyyMMdd-HHmmss-fff'
$backup=Join-Path $launcher ('backups\R98_R88_REGISTRIES_'+$stamp+'-'+[guid]::NewGuid().ToString('N').Substring(0,8))
$stages=New-Object 'System.Collections.Generic.List[string]'
$added=New-Object 'System.Collections.Generic.List[string]'
try {
    New-Item -ItemType Directory -Path $backup -Force | Out-Null
    $records | Select-Object Relative,Hash,State | ConvertTo-Json -Depth 3 |
        Out-File -LiteralPath (Join-Path $backup 'REGISTRY_MANIFEST.json') -Encoding UTF8
    foreach($r in $missing) {
        $directory=Split-Path $r.Target -Parent
        if(-not (Test-Path -LiteralPath $directory)) {
            New-Item -ItemType Directory -Path $directory -Force | Out-Null
        }
        $stage=$r.Target+'.r98-stage-'+[guid]::NewGuid().ToString('N')
        Copy-Item -LiteralPath $r.Source -Destination $stage -ErrorAction Stop
        if((Get-FileHash $stage -Algorithm SHA256).Hash -cne $r.Hash) {
            throw ('PI_R98_STAGED_SOURCE_HASH_MISMATCH='+$r.Relative)
        }
        $stages.Add($stage)
        if(Test-Path -LiteralPath $r.Target) {
            throw ('PI_R98_ORIGINAL_RESOURCE_COLLISION='+$r.Relative)
        }
        Move-Item -LiteralPath $stage -Destination $r.Target -ErrorAction Stop
        $added.Add($r.Target)
        if((Get-FileHash $r.Target -Algorithm SHA256).Hash -cne $r.Hash) {
            throw ('PI_R98_APPLIED_SOURCE_HASH_MISMATCH='+$r.Relative)
        }
        if($CiFailAfterFirstAdd -and $added.Count -eq 1) {
            throw 'PI_R98_CI_INJECTED_FIRST_ADD_FAILURE'
        }
    }
    if(-not $ci) {
        $after=@(Get-NetTCPConnection -LocalPort 8820 -State Listen -ErrorAction SilentlyContinue)
        if($after.Count -ne 1 -or [int]$after[0].OwningProcess -ne $serverPid) {
            throw 'PI_R98_ORIGINAL_PHP_PID_CHANGED'
        }
        Write-Output ('PI_R98_ORIGINAL_PHP_PID_PRESERVED='+$serverPid)
    }
    Write-Output ('PI_R98_REGISTRY_FILES_ADDED='+$added.Count)
    Write-Output ('PI_R98_REGISTRY_MANIFEST='+$backup)
    Write-Output 'PI_R98_R88_REGISTRY_FIX=APPLIED_VERIFIED'
} catch {
    $fail=$_.Exception.Message
    foreach($target in $added) {
        # Exact, just-added file only; no existing registry can be removed.
        Remove-Item -LiteralPath $target -Force -ErrorAction Stop
    }
    Write-Output 'PI_R98_ADDED_REGISTRY_ROLLBACK=VERIFIED'
    throw ('PI_R98_REGISTRY_APPLY_ABORTED='+$fail)
} finally {
    foreach($stage in $stages) {
        if(Test-Path -LiteralPath $stage -PathType Leaf){
            Remove-Item -LiteralPath $stage -Force -ErrorAction SilentlyContinue
        }
    }
}
