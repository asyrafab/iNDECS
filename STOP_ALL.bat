@echo off
setlocal
cd /d "%~dp0"
title iNDECS - Stop All Engines

echo ============================================================
echo   Menutup semua enjin iNDECS, AIRS dan AMIRA...
echo ============================================================
echo.

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
    "$ports = @(8000, 8765, 8770); " ^
    "foreach ($p in $ports) { " ^
    "  $conns = Get-NetTCPConnection -LocalPort $p -State Listen -ErrorAction SilentlyContinue; " ^
    "  foreach ($c in $conns) { " ^
    "    Write-Host ('Menghentikan proses pada port ' + $p + ' (PID ' + $c.OwningProcess + ')...') -ForegroundColor Yellow; " ^
    "    Stop-Process -Id $c.OwningProcess -Force -ErrorAction SilentlyContinue " ^
    "  } " ^
    "}; " ^
    "$procs = Get-CimInstance Win32_Process -ErrorAction SilentlyContinue | Where-Object { " ^
    "  ($_.Name -eq 'php.exe' -and ($_.CommandLine -match 'artisan\s+(queue:work|schedule:work|serve)' -or $_.CommandLine -match '127\.0\.0\.1:(8000|8765|8770)')) -or " ^
    "  ($_.Name -eq 'powershell.exe' -and $_.CommandLine -match 'Start-(Indecs|Airs)\.ps1') " ^
    "}; " ^
    "foreach ($proc in $procs) { " ^
    "  Write-Host ('Menghentikan pekerja latar (PID ' + $proc.ProcessId + ')...') -ForegroundColor Yellow; " ^
    "  Stop-Process -Id $proc.ProcessId -Force -ErrorAction SilentlyContinue " ^
    "}"

echo.
echo Semua pelayan web dan pekerja giliran (iNDECS, AIRS, AMIRA) telah dihentikan.
timeout /t 3
