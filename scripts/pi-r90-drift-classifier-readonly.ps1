#requires -Version 5.1
<#
PI R90 source drift discriminator. READ ONLY. Reports which file SHA changes
are only CRLF/LF or UTF-8 BOM changes vs different source text; compares
Composer locked package versions and migration source text. No SQL queries,
APP_KEY, .env reads, server changes, migrations or installation.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory=$true)][string]$CandidateRepo,
    [Parameter(Mandatory=$true)][ValidatePattern('^[0-9a-fA-F]{40}$')][string]$ExpectedCommit,
    [string]$AppRoot='D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel',
    [string]$CiFixtureRoot
)
Set-StrictMode -Version 2
$ErrorActionPreference='Stop'
$original='D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel'
if (-not [string]::IsNullOrWhiteSpace($CiFixtureRoot)) {
    if ($env:GITHUB_ACTIONS -ne 'true' -or [string]::IsNullOrWhiteSpace($env:RUNNER_TEMP)) {
        throw 'PI_R90_DRIFT_CI_FIXTURE_NOT_AUTHORIZED'
    }
    $base=[IO.Path]::GetFullPath($env:RUNNER_TEMP).TrimEnd([char]92,[char]47)
    $target=[IO.Path]::GetFullPath($CiFixtureRoot).TrimEnd([char]92,[char]47)
    if (-not $target.StartsWith(($base+[IO.Path]::DirectorySeparatorChar),
        [StringComparison]::OrdinalIgnoreCase)) {throw 'PI_R90_DRIFT_CI_OUTSIDE_RUNNER_TEMP'}
    $AppRoot=Join-Path $target 'laravel'
    Write-Output 'PI_R90_DRIFT_TARGET=ISOLATED_CI'
} elseif ($AppRoot -ne $original) {
    throw 'PI_R90_DRIFT_ORIGINAL_ROOT_MISMATCH'
} else {
    Write-Output 'PI_R90_DRIFT_TARGET=PROTECTED_ORIGINAL'
}
if (-not (Test-Path -LiteralPath (Join-Path $AppRoot 'artisan') -PathType Leaf) -or
    -not (Test-Path -LiteralPath (Join-Path $CandidateRepo 'laravel\artisan') -PathType Leaf)) {
    throw 'PI_R90_DRIFT_MAIN_OR_CANDIDATE_MISSING'
}
$git=(Get-Command git.exe -ErrorAction Stop).Source
$head=([string](& $git -C $CandidateRepo rev-parse HEAD)).Trim()
if ($LASTEXITCODE -ne 0 -or $head -cne $ExpectedCommit.ToLowerInvariant()) {
    throw 'PI_R90_DRIFT_CANDIDATE_HEAD_MISMATCH'
}
$dirty=@(& $git -C $CandidateRepo status --porcelain)
if ($LASTEXITCODE -ne 0 -or $dirty.Count -ne 0) {
    throw 'PI_R90_DRIFT_CANDIDATE_DIRTY'
}
Write-Output ('PI_R90_DRIFT_PIN='+$head)
$strictUtf8=New-Object System.Text.UTF8Encoding($false,$true)
$textExtensions=@('.php','.js','.css','.html','.json','.xml','.svg','.txt','.md','.yml','.yaml','.mjs','.cjs')
function Get-Classification([string]$source,[string]$dest) {
    if (-not (Test-Path -LiteralPath $dest -PathType Leaf)) {return 'MISSING_ON_MAIN'}
    $sHash=(Get-FileHash -LiteralPath $source -Algorithm SHA256).Hash
    $dHash=(Get-FileHash -LiteralPath $dest -Algorithm SHA256).Hash
    if ($sHash -ceq $dHash) {return 'BYTE_IDENTICAL'}
    $ext=[IO.Path]::GetExtension($source).ToLowerInvariant()
    # Apache .htaccess has no extension but is UTF-8 text and must not be
    # mistaken for a binary change solely because of its filename.
    if ($script:textExtensions -notcontains $ext -and
        [IO.Path]::GetFileName($source).ToLowerInvariant() -cne '.htaccess') {
        return 'BINARY_OR_UNSUPPORTED_DIFFERENT'
    }
    $sBytes=[IO.File]::ReadAllBytes($source)
    $dBytes=[IO.File]::ReadAllBytes($dest)
    if ($sBytes.Length -gt 5242880 -or $dBytes.Length -gt 5242880) {
        return 'LARGE_TEXT_DIFFERENT'
    }
    try {
        $sText=$script:strictUtf8.GetString($sBytes)
        $dText=$script:strictUtf8.GetString($dBytes)
    } catch [Text.DecoderFallbackException] {
        return 'NON_UTF8_DIFFERENT'
    }
    $lineSep=[string][char]10
    $sNorm=$sText.Replace(([string][char]13+$lineSep),$lineSep).Replace(([string][char]13),$lineSep)
    $dNorm=$dText.Replace(([string][char]13+$lineSep),$lineSep).Replace(([string][char]13),$lineSep)
    if ($sNorm -ceq $dNorm) {return 'LINE_ENDING_ONLY'}
    $bom=[string][char]0xFEFF
    if ($sNorm.TrimStart([char]0xFEFF) -ceq $dNorm.TrimStart([char]0xFEFF)) {
        return 'BOM_OR_LINE_ENDING_ONLY'
    }
    return 'TEXT_CONTENT_DIFFERENT'
}
$categories=@('BYTE_IDENTICAL','LINE_ENDING_ONLY','BOM_OR_LINE_ENDING_ONLY',
    'TEXT_CONTENT_DIFFERENT','BINARY_OR_UNSUPPORTED_DIFFERENT',
    'LARGE_TEXT_DIFFERENT','NON_UTF8_DIFFERENT','MISSING_ON_MAIN')
$counts=@{}
$migrationCounts=@{}
foreach($c in $categories){$counts[$c]=0;$migrationCounts[$c]=0}
$tracked=@(& $git -C $CandidateRepo ls-files -- laravel/app laravel/routes laravel/config laravel/public laravel/bootstrap/app.php laravel/database/migrations)
if ($LASTEXITCODE -ne 0) {throw 'PI_R90_DRIFT_TRACKED_SCAN_FAILED'}
$seen=0;$migrationSeen=0;$examples=0
foreach($entry in $tracked) {
    $p=[string]$entry
    if (-not $p.StartsWith('laravel/',[StringComparison]::Ordinal)) {continue}
    $relative=$p.Substring(8)
    if ($relative -match '(?i)(^|/)(storage|vendor|node_modules|secrets|private)(/|$)') {continue}
    $isMigration=$relative.StartsWith('database/migrations/',[StringComparison]::Ordinal)
    if (-not $isMigration -and $relative -match '(?i)(^|/)database(/|$)') {continue}
    $source=Join-Path (Join-Path $CandidateRepo 'laravel') ($relative.Replace([char]47,[char]92))
    $dest=Join-Path $AppRoot ($relative.Replace([char]47,[char]92))
    if (-not (Test-Path -LiteralPath $source -PathType Leaf)) {
        throw ('PI_R90_DRIFT_CANDIDATE_FILE_MISSING='+$relative)
    }
    $class=Get-Classification $source $dest
    if ($relative -ceq 'public/.htaccess') {
        Write-Output ('PI_R90_HTACCESS_CLASSIFICATION='+$class)
    }
    if ($isMigration) {
        $migrationSeen++
        $migrationCounts[$class]++
        Write-Output ('PI_R90_MIGRATION_CLASS='+$relative+':'+$class)
    } else {
        $seen++
        $counts[$class]++
        # Emit all actionable code/binary differences even if 20 early CRLF
        # examples have consumed the bounded sample budget.
        if ($class -in @('TEXT_CONTENT_DIFFERENT',
            'BINARY_OR_UNSUPPORTED_DIFFERENT','LARGE_TEXT_DIFFERENT',
            'NON_UTF8_DIFFERENT')) {
            Write-Output ('PI_R90_ACTIONABLE_RUNTIME_DIFF='+$relative+':'+$class)
        }
        if ($examples -lt 20 -and $class -notin @('BYTE_IDENTICAL','MISSING_ON_MAIN')) {
            Write-Output ('PI_R90_DRIFT_SAMPLE='+$relative+':'+$class)
            $examples++
        }
    }
}
Write-Output ('PI_R90_DRIFT_RUNTIME_FILES_ANALYZED='+$seen)
foreach($c in $categories){Write-Output ('PI_R90_DRIFT_RUNTIME_'+$c+'='+$counts[$c])}
Write-Output ('PI_R90_DRIFT_MIGRATION_FILES_ANALYZED='+$migrationSeen)
foreach($c in $categories){Write-Output ('PI_R90_DRIFT_MIGRATION_'+$c+'='+$migrationCounts[$c])}
if ($seen -eq 0 -or $migrationSeen -eq 0) {throw 'PI_R90_DRIFT_NO_FILES_ANALYZED'}
$origLock=Join-Path $AppRoot 'composer.lock'
$targetLock=Join-Path $CandidateRepo 'laravel\composer.lock'
if (-not (Test-Path -LiteralPath $origLock -PathType Leaf) -or
    -not (Test-Path -LiteralPath $targetLock -PathType Leaf)) {
    throw 'PI_R90_DRIFT_COMPOSER_LOCK_UNAVAILABLE'
}
$old=Get-Content -LiteralPath $origLock -Raw | ConvertFrom-Json
$new=Get-Content -LiteralPath $targetLock -Raw | ConvertFrom-Json
function Get-Packages($lock) {
    $map=@{}
    foreach($bucket in @('packages','packages-dev')) {
        if ($null -eq $lock.PSObject.Properties[$bucket]) {continue}
        foreach($pkg in @($lock.$bucket)) {
            if ($null -eq $pkg) {continue}
            $name=[string]$pkg.name
            if ([string]::IsNullOrWhiteSpace($name) -or $map.ContainsKey($name)) {
                throw 'PI_R90_DRIFT_LOCK_INVALID_PACKAGE_NAME'
            }
            $ref=''
            if ($null -ne $pkg.source -and $null -ne $pkg.source.reference) {
                $ref=[string]$pkg.source.reference
            } elseif ($null -ne $pkg.dist -and $null -ne $pkg.dist.reference) {
                $ref=[string]$pkg.dist.reference
            }
            $map[$name]=[pscustomobject]@{
                Version=[string]$pkg.version
                Ref=$ref
                Bucket=$bucket
            }
        }
    }
    return ,$map
}
$a=Get-Packages $old
$b=Get-Packages $new
$added=0;$removed=0;$versions=0;$refs=0;$relocated=0
foreach($name in @($b.Keys | Sort-Object)) {
    if (-not $a.ContainsKey($name)) {
        $added++
        Write-Output ('PI_R90_LOCK_ADDED_PACKAGE='+$name)
    } elseif ($a[$name].Version -cne $b[$name].Version) {
        $versions++
        Write-Output ('PI_R90_LOCK_VERSION_CHANGED='+$name+':'+$a[$name].Version+'->'+$b[$name].Version)
    } elseif ($a[$name].Ref -cne $b[$name].Ref) {
        $refs++
        Write-Output ('PI_R90_LOCK_SOURCE_REFERENCE_CHANGED='+$name)
    } elseif ($a[$name].Bucket -cne $b[$name].Bucket) {
        $relocated++
        Write-Output ('PI_R90_LOCK_DEV_SCOPE_CHANGED='+$name)
    }
}
foreach($name in @($a.Keys | Sort-Object)) {
    if (-not $b.ContainsKey($name)) {
        $removed++
        Write-Output ('PI_R90_LOCK_REMOVED_PACKAGE='+$name)
    }
}
Write-Output ('PI_R90_LOCK_MAIN_PACKAGE_COUNT='+$a.Count)
Write-Output ('PI_R90_LOCK_CANDIDATE_PACKAGE_COUNT='+$b.Count)
Write-Output ('PI_R90_LOCK_ADDED='+$added)
Write-Output ('PI_R90_LOCK_REMOVED='+$removed)
Write-Output ('PI_R90_LOCK_VERSION_CHANGED_COUNT='+$versions)
Write-Output ('PI_R90_LOCK_SOURCE_REF_CHANGED_COUNT='+$refs)
Write-Output ('PI_R90_LOCK_DEV_SCOPE_CHANGED_COUNT='+$relocated)
if ((Get-FileHash $origLock -Algorithm SHA256).Hash -cne
    (Get-FileHash $targetLock -Algorithm SHA256).Hash -and
    $added -eq 0 -and $removed -eq 0 -and $versions -eq 0 -and
    $refs -eq 0 -and $relocated -eq 0) {
    Write-Output 'PI_R90_LOCK_BYTE_DRIFT_WITH_SAME_PACKAGE_IDENTITIES=YES'
}
# Output counts not actual compliance. Actual SQLite schema and applied-migration
# ledger remain independent gates, and no code copy/migration is authorized.
Write-Output 'PI_R90_MIGRATION_SOURCE_CLASSIFICATION_NOT_SCHEMA_PROOF=YES'
Write-Output 'PI_R90_SQLITE_SCHEMA_AND_APPLIED_LEDGER=NOT_ASSESSED'
Write-Output 'PI_R90_CODE_CUTOVER=HOLD_PENDING_ISOLATED_ACCEPTANCE'
Write-Output 'PI_R90_DRIFT_CLASSIFIER_READ_ONLY=COMPLETE'
