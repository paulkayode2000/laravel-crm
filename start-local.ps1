<#
.SYNOPSIS
    Starts the Krayin CRM demo locally: the project-owned MySQL instance (when present) and the PHP dev server.

.DESCRIPTION
    Committed to the repository so a fresh clone can be started with one command.
    No binaries are committed: the local MySQL server, its data directory and the machine-specific
    php.ini live in .tools/ (see LOCAL-SETUP.md). Any database reachable through .env works instead,
    in which case use -SkipDatabase or -MysqlConfig/-MysqlBin.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File .\start-local.ps1

.EXAMPLE
    .\start-local.ps1 -Port 8080 -SkipDatabase
#>
[CmdletBinding()]
param(
    [string]$BindHost = '127.0.0.1',
    [int]$Port = 8000,
    [int]$MysqlPort = 3307,
    [string]$MysqlBin,
    [string]$MysqlConfig,
    [switch]$SkipDatabase
)

$ErrorActionPreference = 'Stop'
$project = $PSScriptRoot

function Test-TcpPort {
    param([Parameter(Mandatory)][int]$Port)

    return [bool](Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue)
}

function Resolve-Existing {
    param([string[]]$Candidates)

    foreach ($candidate in $Candidates) {
        if ($candidate -and (Test-Path -LiteralPath $candidate)) {
            return (Resolve-Path -LiteralPath $candidate).Path
        }
    }

    return $null
}

# 1. PHP. The project php.ini enables intl and raises memory/upload limits without touching the machine config.
$php = Get-Command php -ErrorAction SilentlyContinue

if (-not $php) {
    throw 'php was not found on PATH. Install PHP 8.3+ with pdo_mysql, intl, gd, zip, openssl and mbstring enabled.'
}

$phpIni = Join-Path $project 'php.ini'

if (Test-Path -LiteralPath $phpIni) {
    $env:PHPRC = $phpIni
}

if (-not (Test-Path -LiteralPath (Join-Path $project '.env'))) {
    throw 'Missing .env. Run: Copy-Item .env.example .env; php artisan key:generate'
}

# 2. MySQL. Optional: any server reachable through the .env credentials is fine.
if (-not $SkipDatabase) {
    if (Test-TcpPort -Port $MysqlPort) {
        Write-Host ("MySQL already listening on {0}:{1}." -f $BindHost, $MysqlPort)
    } else {
        $config = $MysqlConfig

        if (-not $config) {
            $config = Resolve-Existing @((Join-Path $project '.tools\mysql\my.ini'))
        }

        $binDir = $MysqlBin

        if (-not $binDir) {
            $binDir = Resolve-Existing @(
                (Join-Path $project '.tools\mysql\bin'),
                (Join-Path $env:ProgramFiles 'MySQL\MySQL Server 8.4\bin'),
                (Join-Path $env:ProgramFiles 'MySQL\MySQL Server 8.0\bin')
            )
        }

        if ($config -and $binDir) {
            $mysqld = Join-Path $binDir 'mysqld.exe'
            $defaults = if ($config -match '\s') { '--defaults-file="{0}"' -f $config } else { '--defaults-file={0}' -f $config }

            Write-Host ("Starting local MySQL: {0} {1}" -f $mysqld, $defaults)
            Start-Process -FilePath $mysqld -ArgumentList $defaults -WindowStyle Hidden

            $ready = $false

            for ($i = 0; $i -lt 60; $i++) {
                Start-Sleep -Seconds 1

                if (Test-TcpPort -Port $MysqlPort) {
                    $ready = $true

                    break
                }
            }

            if (-not $ready) {
                throw ("MySQL did not start on port {0}. Check the log-error path configured in {1}." -f $MysqlPort, $config)
            }

            Write-Host ("MySQL is up on {0}:{1}." -f $BindHost, $MysqlPort)
        } else {
            Write-Warning 'No local MySQL configuration found; expecting the database configured in .env to be reachable. Pass -SkipDatabase to silence this.'
        }
    }
}

# 3. Application.
if (Test-TcpPort -Port $Port) {
    Write-Host ("The CRM is already served on http://{0}:{1}/admin/login" -f $BindHost, $Port)
    exit 0
}

Write-Host ("Serving the CRM on http://{0}:{1} (Ctrl+C to stop)" -f $BindHost, $Port)

Push-Location $project

try {
    & $php.Source artisan serve --host=$BindHost --port=$Port
} finally {
    Pop-Location
}

