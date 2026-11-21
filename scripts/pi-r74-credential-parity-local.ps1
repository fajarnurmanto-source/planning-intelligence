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
try {
    Write-Output 'PI_R74_CREDENTIAL_PARITY=LOCAL_READ_ONLY'
    Write-Output 'PI_R74_PASSWORD_INPUT=SECURE_CONSOLE_ONLY'
    $secure=Read-Host 'Masukkan password PI yang digunakan pada halaman login' -AsSecureString
    $bstr=[Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
    $plain=[Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr)
    if ([string]::IsNullOrEmpty($plain)) {
        throw 'PI_R74_LOCAL_PASSWORD_INPUT_EMPTY'
    }
    # Send UTF-8 bytes to PHP through a private redirected STDIN stream.
    # PowerShell 5.1 native-command pipelines may add line endings or
    # transcode input; neither is acceptable in password comparison.
    $start = New-Object System.Diagnostics.ProcessStartInfo
    $start.FileName = $php.Source
    $start.Arguments = ('"{0}" "{1}" "{2}"' -f $probe,$db,$Username)
    $start.UseShellExecute = $false
    $start.CreateNoWindow = $true
    $start.RedirectStandardInput = $true
    $start.RedirectStandardOutput = $true
    $start.RedirectStandardError = $true
    $start.StandardInputEncoding = [System.Text.UTF8Encoding]::new($false)
    $process = New-Object System.Diagnostics.Process
    $process.StartInfo = $start
    try {
        if (-not $process.Start()) { throw 'PI_R74_PHP_STDIN_PROCESS_START_FAILED' }
        # No WriteLine: the submitted password is not suffixed with CR/LF.
        $process.StandardInput.Write($plain)
        $process.StandardInput.Close()
        $stdout = $process.StandardOutput.ReadToEnd()
        $null = $process.StandardError.ReadToEnd()
        $process.WaitForExit()
        if ($process.ExitCode -ne 0) {
            throw 'PI_R74_BCRYPT_READONLY_VERIFICATION_FAILED'
        }
        $result = @($stdout -split '\r?\n' | Where-Object { $_ -ne '' })
    } finally {
        $process.Dispose()
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
    $plain=$null
    if ($bstr -ne [IntPtr]::Zero) {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr)
    }
    if ($null -ne $secure) { $secure.Dispose() }
}
