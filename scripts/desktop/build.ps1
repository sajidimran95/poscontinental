# Builds the Windows desktop installer (NativePHP / Electron) into dist/.
# Usage: powershell -ExecutionPolicy Bypass -File scripts/desktop/build.ps1 -PhpExe C:\path\to\static\php.exe
# Needs: PHP 8.3+, Composer, Node.js 20+, .env.desktop (from .env.desktop.example).
#
# -PhpExe: a single-file (static) PHP 8.4 php.exe WITH pdo_mysql, mysqli, xmlreader, xmlwriter.
# NativePHP's default PHP only has SQLite, so the app could not reach its MySQL without this.
# e.g. the "spc-max" Windows CLI build from https://dl.static-php.dev/static-php-cli/windows/spc-max/
param(
    [Parameter(Mandatory = $true)][string]$PhpExe
)

$ErrorActionPreference = "Stop"
$root = Resolve-Path (Join-Path $PSScriptRoot "..\..")
Set-Location $root

# check the PHP binary has what the POS needs
$modules = & $PhpExe -m
foreach ($ext in @("pdo_mysql", "mysqli", "xmlreader", "xmlwriter", "gd", "zip", "bcmath", "mbstring", "openssl", "curl")) {
    if (-not ($modules -contains $ext)) { throw "$PhpExe is missing the PHP extension: $ext" }
}
# intl is optional: only the English-Arabic invoice layout needs it (the app checks extension_loaded)
if (-not ($modules -contains "intl")) { Write-Warning "$PhpExe has no intl: the English-Arabic invoice layout will not be available." }
$phpMinor = (& $PhpExe -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")

# NativePHP expects <dir>/bin/win/x64/php-<minor>.zip containing only php.exe
$customDir = Join-Path $root "nativephp\custom-php\bin\win\x64"
New-Item -ItemType Directory -Force $customDir | Out-Null
$zip = Join-Path $customDir "php-$phpMinor.zip"
if (Test-Path $zip) { Remove-Item $zip -Force }
Compress-Archive -Path $PhpExe -DestinationPath $zip
$env:NATIVEPHP_BIN_SOURCE = "local"
$env:NATIVEPHP_PHP_BINARY_PATH = "nativephp/custom-php/"
$env:NATIVEPHP_PHP_BINARY_VERSION = $phpMinor

if (-not (Test-Path ".env.desktop")) {
    throw "Create .env.desktop first (copy .env.desktop.example, set APP_KEY and DB_PASSWORD)."
}
if ((Get-Content ".env.desktop" | Select-String -Pattern "^APP_KEY=\s*$")) {
    throw "Set APP_KEY in .env.desktop (php artisan key:generate --show)."
}
if (-not (Test-Path "extras\mysql\bin\mysqld.exe")) {
    & (Join-Path $PSScriptRoot "prepare-mysql.ps1")
}

# Copy (not junction) the composer path packages in the build, junctions break the next build's cleanup.
$env:COMPOSER_MIRROR_PATH_REPOS = "1"
$staleBuild = Join-Path $root "vendor\nativephp\desktop\resources\build"
if (Test-Path $staleBuild) { cmd /c "rmdir /s /q `"$staleBuild`"" }

# The build bundles .env, so swap in the desktop one for the duration of the build.
$backup = ".env.build-backup"
if (Test-Path ".env") { Copy-Item ".env" $backup -Force }
try {
    Copy-Item ".env.desktop" ".env" -Force
    php artisan config:clear
    php artisan native:build win x64
    if ($LASTEXITCODE -ne 0) { throw "native:build failed" }
}
finally {
    if (Test-Path $backup) { Move-Item $backup ".env" -Force }
    php artisan config:clear | Out-Null
}

# native:build can exit 0 after a failed npm / electron step, so check the result
$installer = Get-ChildItem (Join-Path $root "nativephp\electron\dist") -Filter "*-setup.exe" -ErrorAction SilentlyContinue |
    Sort-Object LastWriteTime -Descending | Select-Object -First 1
if (-not $installer) { throw "No installer in nativephp/electron/dist - see the build output above (often a network error, just run again)." }
New-Item -ItemType Directory -Force (Join-Path $root "dist") | Out-Null
Copy-Item $installer.FullName (Join-Path $root "dist") -Force
$installer = Get-Item (Join-Path (Join-Path $root "dist") $installer.Name)
Write-Host "Installer created: $($installer.FullName)"
