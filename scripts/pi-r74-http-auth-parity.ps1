#requires -Version 5.1
<#
R74 one-origin HTTP auth probe. Default GET-only audit. -ExerciseHttpLogin
sends ONE local login with private in-memory cookies, CSRF and a password
entered through secure console input. Writes a normal HTTP session only;
never alters password, user, source, .env, shortcut or launcher. Never prints
passwords, hashes, cookies, CSRF or response bodies.
#>
[CmdletBinding()]
param([switch]$ExerciseHttpLogin)
Set-StrictMode -Version 2
$ErrorActionPreference='Stop'
$AppRoot='D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel'
$Supervisor='D:\Research\PlanningIntelligence\PI_UNIFIED_LAUNCHER_R74_20261111\PI-R74-RUNTIME.ps1'
$Origin='http://127.0.0.1:8820'
$Username='fajar.nurmanto'
if (-not (Test-Path -LiteralPath $Supervisor -PathType Leaf)) { throw 'PI_R74_ORIGINAL_SUPERVISOR_MISSING' }
$verified=@(& $Supervisor -AppRoot $AppRoot -Port 8820 -Mode Audit)
if ($verified -notcontains 'PI_R74_ORIGIN_VERIFIED=True') { throw 'PI_R74_HTTP_ABORT_UNVERIFIED_RUNTIME' }
Write-Output 'PI_R74_HTTP_RUNTIME_IDENTITY=VERIFIED'
$session=New-Object Microsoft.PowerShell.Commands.WebRequestSession
try {
    $response=Invoke-WebRequest -Uri ($Origin+'/api/auth/setup-status') -Method Get -UseBasicParsing -TimeoutSec 8 -WebSession $session -Headers @{Accept='application/json'} -ErrorAction Stop
    $status=ConvertFrom-Json -InputObject ([string]$response.Content) -ErrorAction Stop
} catch { throw 'PI_R74_HTTP_SETUP_STATUS_UNAVAILABLE' }
if ($null -eq $status -or $null -eq $status.PSObject.Properties['needs_setup'] -or $null -eq $status.PSObject.Properties['csrf']) {
    throw 'PI_R74_HTTP_SETUP_STATUS_SCHEMA_INVALID'
}
Write-Output ('PI_R74_HTTP_NEEDS_SETUP=' + [string][bool]$status.needs_setup)
if ([bool]$status.needs_setup) { throw 'PI_R74_HTTP_USER_DATABASE_DIVERGES_FROM_VERIFIED_SQLITE' }
$csrf=[string]$status.csrf
if ([string]::IsNullOrWhiteSpace($csrf)) { throw 'PI_R74_HTTP_CSRF_TOKEN_MISSING' }
Write-Output 'PI_R74_HTTP_CSRF_AND_COOKIE_SESSION=AVAILABLE'
if (-not $ExerciseHttpLogin) {
    Write-Output 'PI_R74_HTTP_AUTH_PROBE=READ_ONLY_SETUP_STATUS_PASS'
    Write-Output 'PI_R74_HTTP_LOGIN_ATTEMPT=NOT_REQUESTED'
    return
}
$secure=$null
$bstr=[IntPtr]::Zero
$plain=$null
$json=$null
try {
    Write-Output 'PI_R74_HTTP_LOGIN_ATTEMPT=ONE_LOCAL_ATTEMPT'
    $secure=Read-Host 'Password PI (uji HTTP lokal saja)' -AsSecureString
    $bstr=[Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
    $plain=[Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr)
    if ([string]::IsNullOrEmpty($plain)) { throw 'PI_R74_HTTP_PASSWORD_EMPTY' }
    $json=@{username=$Username;password=$plain} | ConvertTo-Json -Compress
    $code=0
    try {
        $response=Invoke-WebRequest -Uri ($Origin+'/api/auth/login') -Method Post -UseBasicParsing -TimeoutSec 10 -WebSession $session -ContentType 'application/json' -Headers @{'X-CSRF-TOKEN'=$csrf;Accept='application/json'} -Body $json -ErrorAction Stop
        $code=[int]$response.StatusCode
    } catch [System.Net.WebException] {
        if ($null -eq $_.Exception.Response) { throw 'PI_R74_HTTP_LOGIN_CONNECTION_ERROR' }
        $code=[int]$_.Exception.Response.StatusCode
    }
    if ($code -eq 401) {
        Write-Output 'PI_R74_HTTP_LOGIN_RESULT=INVALID_CREDENTIALS_401'
        Write-Output 'PI_R74_HTTP_NEXT=BACKEND_AUTH_CONTEXT_DIFFERS_FROM_RECOVERED_SQLITE'
        return
    }
    if ($code -eq 419) {
        Write-Output 'PI_R74_HTTP_LOGIN_RESULT=CSRF_SESSION_419'
        return
    }
    if ($code -eq 429) {
        Write-Output 'PI_R74_HTTP_LOGIN_RESULT=THROTTLED_429'
        return
    }
    if ($code -ne 200) {
        Write-Output ('PI_R74_HTTP_LOGIN_RESULT=HTTP_'+$code)
        return
    }
    try {
        $response=Invoke-WebRequest -Uri ($Origin+'/api/auth/session') -Method Get -UseBasicParsing -TimeoutSec 8 -WebSession $session -Headers @{Accept='application/json'} -ErrorAction Stop
        $auth=ConvertFrom-Json -InputObject ([string]$response.Content) -ErrorAction Stop
    } catch { throw 'PI_R74_HTTP_LOGIN_200_BUT_SESSION_GET_FAILED' }
    if ([string]$auth.status -eq 'AUTHENTICATED' -and [string]$auth.username -eq $Username) {
        Write-Output 'PI_R74_HTTP_LOGIN_SESSION=AUTHENTICATED'
        Write-Output 'PI_R74_HTTP_NEXT=EDGE_AUTOFILL_OR_BROWSER_COOKIE_ISOLATION'
    } else { throw 'PI_R74_HTTP_LOGIN_200_BUT_USER_SESSION_DIFFERENT' }
} finally {
    $json=$null
    $plain=$null
    $csrf=$null
    if ($bstr -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr) }
    if ($null -ne $secure) { $secure.Dispose() }
}
