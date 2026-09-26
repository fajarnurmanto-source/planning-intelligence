@echo off
setlocal
cd /d "%~dp0"
echo.
echo Planning Intelligence V0.0 - Isolated Restore Test
echo This test will NOT restore over the working database.
echo.
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\isolated_restore_test.ps1"
echo.
pause
