@echo off
setlocal
cd /d "%~dp0"
title iNDECS - Master System Launcher

echo ============================================================
echo   iNDECS Family - Master System Launcher
echo   - PostgreSQL 18          (127.0.0.1:5434)
echo   - Mailpit (Optional)     (http://localhost:8025)
echo   - LM Studio + AMIRA      (http://127.0.0.1:8770)
echo   - AIRS Review Studio     (http://127.0.0.1:8765)
echo   - iNDECS Main Portal     (http://localhost:8000)
echo ============================================================
echo.

:: 1. Verify PostgreSQL 18 service
echo [1/5] Memeriksa perkhidmatan PostgreSQL (postgresql-x64-18)...
sc query postgresql-x64-18 | findstr /I "RUNNING" >nul 2>nul
if errorlevel 1 (
    echo       PostgreSQL belum aktif. Cuba memulakan postgresql-x64-18...
    net start postgresql-x64-18 >nul 2>nul
    if errorlevel 1 (
        echo [WARN] Gagal memulakan postgresql-x64-18 secara automatik ^(mungkin memerlukan Administrator^).
    ) else (
        echo       PostgreSQL berjaya dimulakan.
    )
) else (
    echo       PostgreSQL ^(127.0.0.1:5434^) sedang berjalan.
)

:: 2. Optional Mailpit check (Docker or standalone binary)
echo [2/5] Memeriksa Mailpit ^(SMTP 1025 / Web 8025^)...
powershell -NoProfile -Command "if (Get-NetTCPConnection -LocalPort 8025 -State Listen -ErrorAction SilentlyContinue) { exit 0 } else { exit 1 }"
if errorlevel 1 (
    where mailpit >nul 2>nul
    if not errorlevel 1 (
        echo       Memulakan Mailpit tempatan...
        start "Mailpit (8025)" /min mailpit
    ) else (
        docker info >nul 2>nul
        if not errorlevel 1 (
            echo       Memulakan kontena Mailpit melalui Docker Compose...
            docker compose up -d mailpit >nul 2>nul
        ) else (
            echo       Mailpit/Docker tidak aktif ^(pilihan untuk e-mel tempatan^). Dilangkau.
        )
    )
) else (
    echo       Mailpit ^(http://localhost:8025^) sedang berjalan.
)

:: 3. Start AMIRA Backend + LM Studio (Port 8770)
echo [3/5] Memulakan enjin AMIRA ^(LM Studio :1234 + AMIRA :8770^)...
powershell -NoProfile -Command "if (Get-NetTCPConnection -LocalPort 8770 -State Listen -ErrorAction SilentlyContinue) { exit 0 } else { exit 1 }"
if errorlevel 1 (
    start "AMIRA Engine (8770)" cmd /k "call "%~dp0START_AMIRA.bat""
) else (
    echo       AMIRA ^(127.0.0.1:8770^) sudah berjalan.
)

:: 4. Start AIRS Review Engine (Port 8765)
echo [4/5] Memulakan enjin AIRS ^(Web :8765 + Queue + Scheduler^)...
powershell -NoProfile -Command "if (Get-NetTCPConnection -LocalPort 8765 -State Listen -ErrorAction SilentlyContinue) { exit 0 } else { exit 1 }"
if errorlevel 1 (
    if exist "%~dp0..\Plan Review\artisan" (
        start "AIRS Engine (8765)" cmd /k "call "%~dp0START_AIRS.bat" --no-browser"
    ) else (
        echo [WARN] Folder AIRS ^(..\Plan Review^) tidak ditemui. Dilangkau.
    )
) else (
    echo       AIRS ^(127.0.0.1:8765^) sudah berjalan.
)

:: 5. Start iNDECS Core Engine (Port 8000)
echo [5/5] Memulakan enjin utama iNDECS ^(Web :8000 + 4 Queues + Scheduler^)...
powershell -NoProfile -Command "if (Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue) { exit 0 } else { exit 1 }"
if errorlevel 1 (
    start "iNDECS Engine (8000)" cmd /k "call "%~dp0START_INDECS.bat" --no-browser"
) else (
    echo       iNDECS ^(localhost:8000^) sudah berjalan.
)

echo.
echo Menunggu pelayan web sedia sebelum membuka pelayar...
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
    "$indecsReady = $false; $airsReady = $false; " ^
    "for ($i = 0; $i -lt 30; $i++) { " ^
    "  if (-not $indecsReady) { try { $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/up' -UseBasicParsing -TimeoutSec 5; if ($r.StatusCode -eq 200) { $indecsReady = $true } } catch {} } " ^
    "  if (-not $airsReady) { try { $r2 = Invoke-WebRequest -Uri 'http://127.0.0.1:8765/assessment-plan' -UseBasicParsing -TimeoutSec 5; if ($r2.StatusCode -eq 200) { $airsReady = $true } } catch {} } " ^
    "  if ($indecsReady -and $airsReady) { break } " ^
    "  Start-Sleep -Seconds 1 " ^
    "}; " ^
    "Write-Host 'Membuka laman web dalam pelayar lalai...' -ForegroundColor Green; " ^
    "Start-Process 'http://localhost:8000'; " ^
    "if ($airsReady) { Start-Process 'http://127.0.0.1:8765/assessment-plan' }; " ^
    "if (Get-NetTCPConnection -LocalPort 8025 -State Listen -ErrorAction SilentlyContinue) { Start-Process 'http://localhost:8025' }"

echo.
echo ============================================================
echo   Semua enjin telah dilancarkan!
echo   - Portal iNDECS : http://localhost:8000
echo   - Studio AIRS   : http://127.0.0.1:8765/assessment-plan
echo   - AMIRA Health  : http://127.0.0.1:8770/health
echo   - Mailpit       : http://localhost:8025 (jika aktif)
echo.
echo   Untuk menutup semua enjin, jalankan STOP_ALL.bat
echo ============================================================
timeout /t 5
