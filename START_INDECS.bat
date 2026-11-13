@echo off
setlocal
cd /d "%~dp0"
title iNDECS Engine (Web 8000 + Queues + Scheduler)

set "OPEN_FLAG=-OpenBrowser"
if /I "%~1"=="--no-browser" set "OPEN_FLAG="

echo ============================================================
echo   iNDECS Core Engine (Web + Queue Workers + Scheduler)
echo ============================================================
echo.

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\Start-Indecs.ps1" %OPEN_FLAG%
if errorlevel 1 (
    echo.
    echo [ERROR] iNDECS engine exited with an error.
    pause
)
