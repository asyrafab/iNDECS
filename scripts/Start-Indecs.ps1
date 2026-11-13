param(
    [ValidateRange(0, 3600)]
    [int] $RunSeconds = 0,

    [switch] $OpenBrowser
)

$ErrorActionPreference = 'Stop'

$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$phpCommand = Get-Command php.exe -ErrorAction SilentlyContinue

if (-not $phpCommand) {
    Write-Host 'PHP tidak ditemui. Pastikan PHP telah dipasang dan tersedia dalam PATH.' -ForegroundColor Red
    exit 1
}

$phpPath = $phpCommand.Source
$healthUrl = 'http://127.0.0.1:8000/up'
$browserUrl = 'http://localhost:8000'
$components = [ordered]@{}
$uploadTempDirectory = Join-Path $projectRoot 'storage\app\upload-tmp'
$publicDirectory = Join-Path $projectRoot 'public'
$caBundlePath = Join-Path $projectRoot 'storage\certs\cacert.pem'

New-Item -ItemType Directory -Path $uploadTempDirectory -Force | Out-Null

$caBundleArguments = @()
if (Test-Path $caBundlePath) {
    $caBundleArguments = @(
        '-d', ('curl.cainfo="{0}"' -f $caBundlePath),
        '-d', ('openssl.cafile="{0}"' -f $caBundlePath)
    )
}

# Normalize duplicate Path/PATH environment variables for Start-Process
$pathSources = @(
    [Environment]::GetEnvironmentVariable('Path', 'Process'),
    [Environment]::GetEnvironmentVariable('Path', 'Machine'),
    [Environment]::GetEnvironmentVariable('Path', 'User'),
    (Split-Path $phpPath -Parent)
)
$cleanPath = (($pathSources | Where-Object { $_ }) -join ';').Split(';') |
    Where-Object { $_ } |
    Select-Object -Unique

[Environment]::SetEnvironmentVariable('PATH', $null, 'Process')
[Environment]::SetEnvironmentVariable('Path', ($cleanPath -join ';'), 'Process')
[Environment]::SetEnvironmentVariable('TEMP', $null, 'Process')
[Environment]::SetEnvironmentVariable('TMP', $null, 'Process')
[Environment]::SetEnvironmentVariable('Temp', $uploadTempDirectory, 'Process')
[Environment]::SetEnvironmentVariable('Tmp', $uploadTempDirectory, 'Process')

function Start-IndecsComponent {
    param(
        [Parameter(Mandatory)]
        [string] $Name,

        [Parameter(Mandatory)]
        [string[]] $Arguments,

        [string] $WorkingDirectory = $projectRoot
    )

    Write-Host "Memulakan $Name..." -ForegroundColor Cyan

    $startOptions = @{
        FilePath = $phpPath
        ArgumentList = $Arguments
        WorkingDirectory = $WorkingDirectory
        NoNewWindow = $true
        PassThru = $true
    }

    return Start-Process @startOptions
}

function Stop-IndecsComponent {
    param(
        [System.Diagnostics.Process] $Process
    )

    if ($Process -and -not $Process.HasExited) {
        Stop-Process -Id $Process.Id -Force -ErrorAction SilentlyContinue
    }
}

function Test-IndecsReady {
    try {
        $response = Invoke-WebRequest -Uri $healthUrl -UseBasicParsing -TimeoutSec 6
        return $response.StatusCode -eq 200
    } catch {
        return $false
    }
}

$serverArguments = @(
    '-d', ('upload_tmp_dir="{0}"' -f $uploadTempDirectory),
    '-d', 'post_max_size=128M',
    '-d', 'upload_max_filesize=128M',
    '-d', 'max_file_uploads=100',
    '-d', 'memory_limit=512M'
) + $caBundleArguments + @(
    '-S', '127.0.0.1:8000',
    '..\vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php'
)

$queueDefaultArguments = @(
    '-d', 'memory_limit=512M'
) + $caBundleArguments + @(
    'artisan', 'queue:work', 'database', '--queue=precheck,default',
    '--sleep=1',
    '--tries=3',
    '--timeout=300'
)

$queueAmiraArguments = @(
    '-d', 'memory_limit=512M'
) + $caBundleArguments + @(
    'artisan', 'queue:work', 'amira', '--queue=amira,amira-index',
    '--sleep=1',
    '--tries=1',
    '--timeout=310'
)

$queueAirsArguments = @(
    '-d', 'memory_limit=512M'
) + $caBundleArguments + @(
    'artisan', 'queue:work', 'airs', '--queue=airs',
    '--sleep=1',
    '--tries=1',
    '--timeout=960'
)

$queueGisArguments = @(
    '-d', 'memory_limit=512M'
) + $caBundleArguments + @(
    'artisan', 'queue:work', 'gis', '--queue=gis',
    '--sleep=3',
    '--tries=1',
    '--timeout=3600'
)

$schedulerArguments = @(
    '-d', 'memory_limit=256M'
) + $caBundleArguments + @(
    'artisan', 'schedule:work'
)

try {
    $existingListener = Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue

    if ($existingListener) {
        throw 'Port 8000 sedang digunakan. Tutup pelayan iNDECS lama sebelum memulakan semula.'
    }

    Push-Location $projectRoot

    & $phpPath artisan config:clear --quiet
    if ($LASTEXITCODE -ne 0) {
        throw 'Tetapan aplikasi iNDECS tidak dapat disediakan.'
    }

    & $phpPath artisan migrate --force --quiet
    if ($LASTEXITCODE -ne 0) {
        throw 'Migrasi pangkalan data iNDECS tidak dapat diselesaikan.'
    }

    $components.Server = Start-IndecsComponent -Name 'pelayan web iNDECS (8000)' -Arguments $serverArguments -WorkingDirectory $publicDirectory
    $components.QueueDefault = Start-IndecsComponent -Name 'pekerja giliran (precheck,default)' -Arguments $queueDefaultArguments
    $components.QueueAmira = Start-IndecsComponent -Name 'pekerja giliran AMIRA (amira,amira-index)' -Arguments $queueAmiraArguments
    $components.QueueAirs = Start-IndecsComponent -Name 'pekerja giliran AIRS (airs)' -Arguments $queueAirsArguments
    $components.QueueGis = Start-IndecsComponent -Name 'pekerja giliran GIS (gis)' -Arguments $queueGisArguments
    $components.Scheduler = Start-IndecsComponent -Name 'penjadual iNDECS (schedule:work)' -Arguments $schedulerArguments

    $ready = $false
    for ($attempt = 1; $attempt -le 20; $attempt++) {
        if ($components.Server.HasExited) {
            throw 'Pelayan iNDECS terhenti semasa dimulakan.'
        }

        if (Test-IndecsReady) {
            $ready = $true
            break
        }

        Start-Sleep -Seconds 1
    }

    if (-not $ready) {
        throw 'Pelayan iNDECS tidak memberi respons selepas 20 saat.'
    }

    Write-Host ''
    Write-Host 'iNDECS kini aktif.' -ForegroundColor Green
    Write-Host "Buka: $browserUrl" -ForegroundColor White
    Write-Host 'Biarkan tetingkap ini terbuka. Tekan Ctrl+C untuk menutup sistem.' -ForegroundColor Yellow
    Write-Host ''

    if ($OpenBrowser) {
        Start-Process $browserUrl | Out-Null
    }

    $startedAt = Get-Date

    while ($true) {
        Start-Sleep -Seconds 2

        if ($RunSeconds -gt 0 -and ((Get-Date) - $startedAt).TotalSeconds -ge $RunSeconds) {
            break
        }

        if ($components.Server.HasExited) {
            Write-Host 'Pelayan iNDECS terhenti. Memulakan semula...' -ForegroundColor Yellow
            Start-Sleep -Seconds 2
            $components.Server = Start-IndecsComponent -Name 'pelayan web iNDECS (8000)' -Arguments $serverArguments -WorkingDirectory $publicDirectory
        }

        if ($components.QueueDefault.HasExited) {
            Write-Host 'Pekerja giliran (precheck,default) terhenti. Memulakan semula...' -ForegroundColor Yellow
            Start-Sleep -Seconds 2
            $components.QueueDefault = Start-IndecsComponent -Name 'pekerja giliran (precheck,default)' -Arguments $queueDefaultArguments
        }

        if ($components.QueueAmira.HasExited) {
            Write-Host 'Pekerja giliran AMIRA terhenti. Memulakan semula...' -ForegroundColor Yellow
            Start-Sleep -Seconds 2
            $components.QueueAmira = Start-IndecsComponent -Name 'pekerja giliran AMIRA (amira,amira-index)' -Arguments $queueAmiraArguments
        }

        if ($components.QueueAirs.HasExited) {
            Write-Host 'Pekerja giliran AIRS terhenti. Memulakan semula...' -ForegroundColor Yellow
            Start-Sleep -Seconds 2
            $components.QueueAirs = Start-IndecsComponent -Name 'pekerja giliran AIRS (airs)' -Arguments $queueAirsArguments
        }

        if ($components.QueueGis.HasExited) {
            Write-Host 'Pekerja giliran GIS terhenti. Memulakan semula...' -ForegroundColor Yellow
            Start-Sleep -Seconds 2
            $components.QueueGis = Start-IndecsComponent -Name 'pekerja giliran GIS (gis)' -Arguments $queueGisArguments
        }

        if ($components.Scheduler.HasExited) {
            Write-Host 'Penjadual iNDECS terhenti. Memulakan semula...' -ForegroundColor Yellow
            Start-Sleep -Seconds 2
            $components.Scheduler = Start-IndecsComponent -Name 'penjadual iNDECS (schedule:work)' -Arguments $schedulerArguments
        }
    }
} catch {
    Write-Host ''
    Write-Host "iNDECS tidak dapat dimulakan: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
} finally {
    Write-Host ''
    Write-Host 'Menutup iNDECS...' -ForegroundColor Cyan

    Stop-IndecsComponent -Process $components.Scheduler
    Stop-IndecsComponent -Process $components.QueueGis
    Stop-IndecsComponent -Process $components.QueueAirs
    Stop-IndecsComponent -Process $components.QueueAmira
    Stop-IndecsComponent -Process $components.QueueDefault
    Stop-IndecsComponent -Process $components.Server

    Pop-Location -ErrorAction SilentlyContinue
    Write-Host 'iNDECS telah ditutup.' -ForegroundColor Green
}
