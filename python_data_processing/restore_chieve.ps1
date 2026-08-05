<#
.SYNOPSIS
  Restore production sql\Chieve.bak into local SQL Server for Python export.

.DESCRIPTION
  Requires SQL Server Express / Developer / Full with sqlcmd.
  After restore, set MSSQL_CONN in python_data_processing\.env and run export_mssql.py
#>
param(
    [string]$BakPath = (Join-Path $PSScriptRoot "..\production sql\Chieve.bak"),
    [string]$DbName = "Chieve",
    [string]$ServerInstance = ".\SQLEXPRESS",
    [string]$DataDir = "C:\SQLData"
)

$ErrorActionPreference = "Stop"
$BakPath = (Resolve-Path $BakPath).Path
if (-not (Test-Path $BakPath)) { throw "BAK not found: $BakPath" }

$sqlcmd = Get-Command sqlcmd -ErrorAction SilentlyContinue
if (-not $sqlcmd) {
    Write-Host @"
sqlcmd not found. Install SQL Server Express + command-line tools, then re-run.

  winget search SQLServer
  Or download: https://www.microsoft.com/sql-server/sql-server-downloads

After install, open a NEW PowerShell and run this script again.
"@
    exit 1
}

New-Item -ItemType Directory -Force -Path $DataDir | Out-Null
$mdf = Join-Path $DataDir "$DbName.mdf"
$ldf = Join-Path $DataDir "${DbName}_log.ldf"

Write-Host "Listing logical file names in backup…"
& sqlcmd -S $ServerInstance -E -Q "RESTORE FILELISTONLY FROM DISK = N'$BakPath'" -W -s "|"

Write-Host ""
Write-Host "IMPORTANT: Edit LOGICAL names below if FILELISTONLY shows different names, then re-run SQL manually if needed."
Write-Host "Restoring to $DbName on $ServerInstance …"

$restore = @"
IF DB_ID(N'$DbName') IS NOT NULL
BEGIN
  ALTER DATABASE [$DbName] SET SINGLE_USER WITH ROLLBACK IMMEDIATE;
  DROP DATABASE [$DbName];
END
RESTORE DATABASE [$DbName]
FROM DISK = N'$BakPath'
WITH MOVE N'Chieve' TO N'$mdf',
     MOVE N'Chieve_log' TO N'$ldf',
     REPLACE, STATS = 10;
"@

# First try with common logical names; if fails user sees FILELISTONLY output
& sqlcmd -S $ServerInstance -E -Q $restore
if ($LASTEXITCODE -ne 0) {
    Write-Host @"

Restore failed (logical file names often differ).
1) Note LogicalName from FILELISTONLY above
2) Run in SSMS:

RESTORE DATABASE [$DbName]
FROM DISK = N'$BakPath'
WITH MOVE N'<DataLogicalName>' TO N'$mdf',
     MOVE N'<LogLogicalName>' TO N'$ldf',
     REPLACE;

"@
    exit 1
}

Write-Host "Restore OK. Example .env MSSQL_CONN:"
Write-Host "MSSQL_CONN=DRIVER={ODBC Driver 18 for SQL Server};SERVER=$ServerInstance;DATABASE=$DbName;Trusted_Connection=yes;TrustServerCertificate=yes"
Write-Host ""
Write-Host "Next:"
Write-Host "  cd python_data_processing"
Write-Host "  python export_mssql.py"
Write-Host "  python import_mysql.py"
