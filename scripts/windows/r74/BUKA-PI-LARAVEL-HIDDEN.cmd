@echo off
setlocal EnableExtensions
rem ORIGINAL R74 HIDDEN CMD - same .lnk and VBS, same audited main port 8820.
rem Runtime supervisor is a helper under this existing launcher directory, not a second server app.
set "PI_APP=D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel"
set "PI_RUNTIME=%~dp0PI-R74-RUNTIME.ps1"
set "PI_LOGDIR=%~dp0logs"
if not exist "%PI_LOGDIR%" mkdir "%PI_LOGDIR%" >nul 2>&1
if not exist "%PI_RUNTIME%" exit /b 10
powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -File "%PI_RUNTIME%" -AppRoot "%PI_APP%" -Port 8820 -Mode Ensure >"%PI_LOGDIR%\launcher-last.log" 2>&1
if errorlevel 1 exit /b 11
start "" "http://127.0.0.1:8820/"
exit /b 0
