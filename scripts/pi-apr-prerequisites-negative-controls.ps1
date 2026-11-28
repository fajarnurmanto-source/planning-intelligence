#requires -Version 5.1
param([Parameter(Mandatory=$true)][string]$FixtureRoot)
Set-StrictMode -Version 2
$ErrorActionPreference='Stop'
. (Join-Path $PSScriptRoot 'pi-apr-laravel-prerequisites.ps1')
$base=Join-Path ([IO.Path]::GetFullPath($FixtureRoot)) ('prerequisites-'+[guid]::NewGuid().ToString('N'))
$app=Join-Path $base 'laravel'
New-Item -ItemType Directory -Path $app -Force|Out-Null
function Expect-Hold([scriptblock]$Action,[string]$Pattern){
 $reason=''
 try{& $Action|Out-Null}catch{$reason=[string]$_.Exception.Message}
 if($reason -notmatch $Pattern){throw ('PI_APR_RUNTIME_NEGATIVE_CONTROL_FAILED='+$Pattern)}
}
Expect-Hold {Invoke-PiAprLaravelPrerequisites -AppRoot $app -IsolatedRoot $base} 'DIRECTORY_MISSING'
$r=Invoke-PiAprLaravelPrerequisites -AppRoot $app -IsolatedRoot $base -Repair
Assert-PiAprLaravelReceipt $r
$r2=Invoke-PiAprLaravelPrerequisites -AppRoot $app -IsolatedRoot $base -Repair
if(@($r2.Directories|Where-Object {$_.Created}).Count -ne 0){throw 'PI_APR_RUNTIME_IDEMPOTENCE_HOLD'}
Expect-Hold {Assert-PiAprLaravelReceipt ([pscustomobject]@{Schema='wrong';Status='PASS';Directories=@()})} 'RECEIPT_HOLD'
Expect-Hold {Invoke-PiAprLaravelPrerequisites -AppRoot $app -Repair} 'REPAIR_REQUIRES_ISOLATION'
$collision=Join-Path $base 'collision'
New-Item -ItemType Directory -Path (Join-Path $collision 'bootstrap') -Force|Out-Null
[IO.File]::WriteAllText((Join-Path $collision 'bootstrap\cache'),'synthetic collision')
Expect-Hold {Invoke-PiAprLaravelPrerequisites -AppRoot $collision -IsolatedRoot $base -Repair} 'DIRECTORY_COLLISION'
# Real Windows ACL fault confined to this newly created disposable directory.
$denied=Join-Path $app 'bootstrap\cache'
$acl=Get-Acl -LiteralPath $denied
$originalAcl=$acl.GetSecurityDescriptorSddlForm([Security.AccessControl.AccessControlSections]::All)
$sid=[Security.Principal.WindowsIdentity]::GetCurrent().User
$rule=New-Object Security.AccessControl.FileSystemAccessRule($sid,[Security.AccessControl.FileSystemRights]::Write,[Security.AccessControl.AccessControlType]::Deny)
try{
 $acl.AddAccessRule($rule)
 Set-Acl -LiteralPath $denied -AclObject $acl
 Expect-Hold {Invoke-PiAprLaravelPrerequisites -AppRoot $app -IsolatedRoot $base -Repair} 'DIRECTORY_NOT_WRITABLE'
}finally{
 $restored=New-Object Security.AccessControl.DirectorySecurity
 $restored.SetSecurityDescriptorSddlForm($originalAcl)
 Set-Acl -LiteralPath $denied -AclObject $restored
}
Assert-PiAprLaravelReceipt (Invoke-PiAprLaravelPrerequisites -AppRoot $app -IsolatedRoot $base -Repair)
Write-Output 'PI_APR_RUNTIME_MISSING_UNWRITABLE_COLLISION_MALFORMED_IDEMPOTENCE_NEGATIVES=PASS'
