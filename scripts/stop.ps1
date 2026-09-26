$root=Split-Path -Parent $PSScriptRoot
Set-Location $root
docker compose stop
Write-Host "Aplikasi berhenti. Data tetap tersimpan di folder data." -ForegroundColor Green
