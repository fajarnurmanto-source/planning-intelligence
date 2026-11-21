#requires -Version 5.1
<#
R74 protected, READ-ONLY credential parity check after R87 recovery.
Prompts via SecureString on the user's own Windows console; no chat, command
line password arguments, temp files, HTTP login/session writes or DB mutation.
#>
[CmdletBinding()]
param(
    [string]$AppRoot='D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel',
    [string]$Username='fajar.nurmanto'
)
Set-StrictMode -Version 2
$ErrorActionPreference = 'Stop'
$protectedRoot='D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel'
if ($AppRoot -ne $protectedRoot -or $Username -ne 'fajar.nurmanto') {
    throw 'PI_R74_CREDENTIAL_PARITY_SOURCE_DRIFT'
}
$db=Join-Path $AppRoot 'database\database.sqlite'
$probe=Join-Path $PSScriptRoot 'pi-r74-verify-entered-password.php'
if (-not (Test-Path -LiteralPath $db -PathType Leaf) -or
    -not (Test-Path -LiteralPath $probe -PathType Leaf)) {
    throw 'PI_R74_CREDENTIAL_PARITY_SOURCE_NOT_FOUND'
}
$php=Get-Command php.exe -ErrorAction Stop
$secure=$null
$bstr=[IntPtr]::Zero
$plain=$null
$originalEncoding=$OutputEncoding
try {
    Write-Output 'PI_R74_CREDENTIAL_PARITY=LOCAL_READ_ONLY'
    Write-Output 'PI_R74_PASSWORD_INPUT=SECURE_CONSOLE_ONLY'
    $secure=Read-Host 'Masukkan password PI yang digunakan pada halaman login' -AsSecureString
    $bstr=[Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
    $plain=[Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr)
    if ([string]::IsNullOrEmpty($plain)) {
        throw 'PI_R74_LOCAL_PASSWORD_INPUT_EMPTY'
    }
    # Preserve non-ASCII passwords when PowerShell 5.1 pipes to php.exe.
    $OutputEncoding=New-Object System.Text.UTF8Encoding($false)
    $result=@($plain | & $php.Source $probe $db $Username)
    if ($LASTEXITCODE -ne 0) {
        throw 'PI_R74_BCRYPT_READONLY_VERIFICATION_FAILED'
    }
    foreach ($item in $result) { Write-Output $item }
    if ($result -contains 'PI_R74_ENTERED_PASSWORD_MATCHES_RECOVERED_ACCOUNT=YES') {
        Write-Output 'PI_R74_NEXT=CHECK_BROWSER_AUTOFILL_AND_LARAVEL_AUTH'
    } elseif ($result -contains 'PI_R74_ENTERED_PASSWORD_MATCHES_RECOVERED_ACCOUNT=NO') {
        Write-Output 'PI_R74_NEXT=PASSWORD_DIFFERS_FROM_R87_RECOVERED_HASH'
    } else {
        throw 'PI_R74_LOCAL_PASSWORD_RESULT_INCOMPLETE'
    }
} finally {
    $OutputEncoding=$originalEncoding
    $plain=$null
    if ($bstr -ne [IntPtr]::Zero) {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr)
    }
    if ($null -ne $secure) { $secure.Dispose() }
}
