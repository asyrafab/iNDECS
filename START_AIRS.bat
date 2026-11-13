@echo off
setlocal
cd /d "%~dp0"
title AIRS Engine (Web 8765 + Queue + Scheduler)

set "AIRS_DIR=%~dp0..\Plan Review"
set "OPEN_BROWSER=1"
if /I "%~1"=="--no-browser" set "OPEN_BROWSER=0"

echo ============================================================
echo   AIRS (AI Review Studio) Engine - Port 8765
echo ============================================================
echo.

if not exist "%AIRS_DIR%\artisan" (
    echo [ERROR] Folder AIRS tidak ditemui di "%AIRS_DIR%".
    pause
    exit /b 1
)

if "%OPEN_BROWSER%"=="1" (
    start "" powershell -NoProfile -WindowStyle Hidden -Command "for ($i=0; $i -lt 25; $i++) { try { $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8765/assessment-plan' -UseBasicParsing -TimeoutSec 2; if ($r.StatusCode -eq 200) { Start-Process 'http://127.0.0.1:8765/assessment-plan'; break } } catch {}; Start-Sleep -Seconds 1 }"
)

if exist "%AIRS_DIR%\scripts\Start-Airs.ps1" (
    powershell -NoProfile -ExecutionPolicy Bypass -File "%AIRS_DIR%\scripts\Start-Airs.ps1"
) else if exist "%AIRS_DIR%\START_AIRS.cmd" (
    call "%AIRS_DIR%\START_AIRS.cmd"
) else (
    pushd "%AIRS_DIR%"
    php -S 127.0.0.1:8765 -t public
    popd
)

if errorlevel 1 (
    echo.
    echo [ERROR] AIRS engine exited with an error.
    pause
)
