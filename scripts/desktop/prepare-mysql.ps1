# Copies a trimmed MySQL server into extras/mysql so it ships inside the desktop app.
# Usage: powershell -ExecutionPolicy Bypass -File scripts/desktop/prepare-mysql.ps1 [-Source D:\laragon\bin\mysql\mysql-8.4.3-winx64]
param(
    [string]$Source = "D:\laragon\bin\mysql\mysql-8.4.3-winx64"
)

$ErrorActionPreference = "Stop"
$root = Resolve-Path (Join-Path $PSScriptRoot "..\..")
$target = Join-Path $root "extras\mysql"

if (-not (Test-Path (Join-Path $Source "bin\mysqld.exe"))) {
    throw "mysqld.exe not found under $Source\bin. Pass -Source <path to an extracted MySQL 8.x Windows zip>."
}

if (Test-Path $target) { Remove-Item -Recurse -Force $target }
New-Item -ItemType Directory -Force (Join-Path $target "bin") | Out-Null

foreach ($exe in @("mysqld.exe", "mysql.exe", "mysqladmin.exe", "mysqldump.exe")) {
    Copy-Item (Join-Path $Source "bin\$exe") (Join-Path $target "bin")
}
Get-ChildItem (Join-Path $Source "bin\*.dll") | Where-Object { $_.Name -notlike "*-debug.dll" } |
    Copy-Item -Destination (Join-Path $target "bin")

Copy-Item -Recurse (Join-Path $Source "share") (Join-Path $target "share")

# Visual C++ runtime next to mysqld, so PCs without the VC++ redistributable still work
foreach ($dll in @("vcruntime140.dll", "vcruntime140_1.dll", "msvcp140.dll")) {
    $sys = Join-Path $env:WINDIR "System32\$dll"
    if (Test-Path $sys) { Copy-Item $sys (Join-Path $target "bin") }
}

$size = (Get-ChildItem -Recurse $target | Measure-Object -Property Length -Sum).Sum / 1MB
Write-Host ("MySQL copied to {0} ({1:N0} MB)" -f $target, $size)
