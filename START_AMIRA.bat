@echo off
setlocal
cd /d "%~dp0"
title AMIRA Engine (LM Studio 1234 + AMIRA Backend 8770)

set "AMIRA_DIR=%~dp0..\AMIRA"
if not exist "%AMIRA_DIR%\public\router.php" (
    if exist "%~dp0output\amira-extraction\AMIRA\public\router.php" (
        set "AMIRA_DIR=%~dp0output\amira-extraction\AMIRA"
    )
)

echo ============================================================
echo   AMIRA AI Assistant Engine (LM Studio + Backend :8770)
echo ============================================================
echo.

where lms >nul 2>nul
if %errorlevel%==0 (
    echo [1/2] Memeriksa pelayan LM Studio ^(Port 1234 / qwen/qwen3.5-9b^)...
    call lms server start --port 1234 >nul 2>nul
    call lms ps | findstr /I "qwen3.5-9b" >nul 2>nul
    if errorlevel 1 (
        echo       Memuatkan model qwen/qwen3.5-9b ^(context 8192^)...
        call lms load qwen/qwen3.5-9b --context-length 8192
    ) else (
        echo       Model qwen/qwen3.5-9b sudah aktif.
    )
) else (
    echo [WARN] CLI 'lms' ^(LM Studio^) tidak ditemui dalam PATH.
    echo        AMIRA akan menggunakan panduan asas tempatan jika model tidak aktif.
)

echo.
echo [2/2] Memulakan pelayan AMIRA Backend pada http://127.0.0.1:8770 ...
if not exist "%AMIRA_DIR%\public\router.php" (
    echo [ERROR] Folder AMIRA tidak ditemui di "%AMIRA_DIR%".
    pause
    exit /b 1
)

echo       Direktori: %AMIRA_DIR%
echo       Biarkan tetingkap ini terbuka. Tekan Ctrl+C untuk menutup AMIRA.
echo.
pushd "%AMIRA_DIR%"
php -S 127.0.0.1:8770 -t public public/router.php
popd
