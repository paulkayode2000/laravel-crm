<#
.SYNOPSIS
    Stops what start-local.ps1 started: the PHP dev server and the project-owned MySQL instance.

.DESCRIPTION
    Only touches processes that belong to this project: 'php artisan serve' listening on the given port
    and the mysqld started from .tools\mysql\my.ini (located through its pid-file).

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File .\stop-local.ps1
#>
[CmdletBinding()]
param(
    [int]$Port = 8000,
    [int]$MysqlPort = 3307,
    [string]$MysqlBin
)

$ErrorActionPreference = 'Continue'
$project = $PSScriptRoot
$configFile = Join-Path $project '.tools\mysql\my.ini'
$pidFile = Join-Path $project '.tools\mysql\mysqld.pid'

function Get-ProcessCommandLine {
    param([string]$Name)

    return Get-CimInstance Win32_Process -Filter ("Name = '{0}'" -f $Name) -ErrorAction SilentlyContinue
}

function Test-TcpPort {
    param([Parameter(Mandatory)][int]$Port)

    return [bool](Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue)
}

# 1. PHP built-in server for this project's port: the 'artisan serve' parent and the
#    'php -S host:port .../resources/server.php' child it spawns (the child outlives the parent).
$serveProcesses = Get-ProcessCommandLine -Name 'php.exe' | Where-Object {
    if (-not $_.CommandLine) {
        return $false
    }

    $isArtisan = $_.CommandLine -match 'artisan\s+serve' -and $_.CommandLine -match ('--port={0}\b' -f $Port)
    $isBuiltInServer = $_.CommandLine -match ('-S\s+\S+:{0}\b' -f $Port) -and $_.CommandLine.Contains($project)

    return ($isArtisan -or $isBuiltInServer)
}

if ($serveProcesses) {
    $serveProcesses | ForEach-Object {
        Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue
        Write-Host ("Stopped PHP dev server (pid {0})." -f $_.ProcessId)
    }
} else {
    Write-Host ("No 'php artisan serve' process found for port {0}." -f $Port)
}

# 2. Project-owned MySQL instance: identified by its --defaults-file, never by port alone,
#    so the machine-wide MySQL service on 3306 is never touched even when the pid-file is stale.
$localMysqld = @(Get-ProcessCommandLine -Name 'mysqld.exe' | Where-Object {
    $_.CommandLine -and $_.CommandLine.Contains($configFile)
})

if (-not $localMysqld) {
    Write-Host 'No project-owned MySQL instance is running.'
    Remove-Item -LiteralPath $pidFile -ErrorAction SilentlyContinue
    return
}

# 2a. Graceful shutdown first (works when the local root account is passwordless, see LOCAL-SETUP.md).
$binDir = $MysqlBin

if (-not $binDir) {
    $binCandidates = @(
        (Join-Path $project '.tools\mysql\bin'),
        (Join-Path $env:ProgramFiles 'MySQL\MySQL Server 8.4\bin'),
        (Join-Path $env:ProgramFiles 'MySQL\MySQL Server 8.0\bin')
    )

    foreach ($candidate in $binCandidates) {
        if ($candidate -and (Test-Path -LiteralPath (Join-Path $candidate 'mysqladmin.exe'))) {
            $binDir = $candidate

            break
        }
    }
}

if ($binDir) {
    $mysqladmin = Join-Path $binDir 'mysqladmin.exe'

    Write-Host ("Shutting down MySQL on 127.0.0.1:{0} gracefully..." -f $MysqlPort)
    & $mysqladmin --host=127.0.0.1 --port=$MysqlPort --user=root shutdown 2>&1 | Out-Null

    for ($i = 0; $i -lt 30; $i++) {
        Start-Sleep -Seconds 1

        if (-not (Test-TcpPort -Port $MysqlPort)) {
            break
        }
    }
}

# 2b. Force-stop anything from this project that is still alive (no password, hung, or a duplicate copy).
foreach ($mysqld in $localMysqld) {
    $process = Get-Process -Id $mysqld.ProcessId -ErrorAction SilentlyContinue

    if ($process -and $process.ProcessName -eq 'mysqld') {
        Stop-Process -Id $mysqld.ProcessId -Force -ErrorAction SilentlyContinue
        Write-Host ("Stopped MySQL (pid {0})." -f $mysqld.ProcessId)
    }
}

Remove-Item -LiteralPath $pidFile -ErrorAction SilentlyContinue

if (Test-TcpPort -Port $MysqlPort) {
    Write-Warning ("Port {0} is still in use by a process that does not belong to this project; it was left running." -f $MysqlPort)
} else {
    Write-Host ("MySQL on port {0} is stopped." -f $MysqlPort)
}
