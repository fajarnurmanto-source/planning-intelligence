@echo off
setlocal
cd /d "%~dp0"
echo.
echo Planning Intelligence V0.1A.2 - Apply and Test
echo.
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\v0_1a_apply_and_test.ps1"
echo.
pause
