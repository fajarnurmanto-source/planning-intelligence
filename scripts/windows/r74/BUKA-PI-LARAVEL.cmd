@echo off
setlocal EnableExtensions
rem Manual diagnostic uses the SAME runtime and SAME port as existing VBS icon.
call "%~dp0BUKA-PI-LARAVEL-HIDDEN.cmd"
set "PI_RESULT=%ERRORLEVEL%"
if not "%PI_RESULT%"=="0" (
  echo PI_R74_LAUNCH_FAILED_CODE=%PI_RESULT%
  echo See "%~dp0logs\launcher-last.log" for sanitized startup diagnostics.
  pause
)
exit /b %PI_RESULT%
