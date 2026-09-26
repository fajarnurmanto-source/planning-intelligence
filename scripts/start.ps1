$ErrorActionPreference='Stop'
$root=Split-Path -Parent $PSScriptRoot
Set-Location $root
Write-Host "Menyalakan aplikasi..." -ForegroundColor Cyan
docker compose up -d --build
Write-Host "Menunggu service..."; Start-Sleep -Seconds 6
docker compose ps
Write-Host "Buka browser: http://localhost:8080" -ForegroundColor Green
Start-Process "http://localhost:8080"
