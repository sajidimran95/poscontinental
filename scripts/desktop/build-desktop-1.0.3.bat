@echo off
rem Builds desktop installer v1.0.3 into dist\ (double-click to run).
cd /d "%~dp0..\.."
echo Setting NATIVEPHP_APP_VERSION=1.0.3 in .env.desktop ...
powershell -NoProfile -ExecutionPolicy Bypass -Command "$f='.env.desktop'; $c=Get-Content $f; if ($c -match '^NATIVEPHP_APP_VERSION=') { $c=$c -replace '^NATIVEPHP_APP_VERSION=.*','NATIVEPHP_APP_VERSION=1.0.3' } else { $c+='NATIVEPHP_APP_VERSION=1.0.3' }; [IO.File]::WriteAllLines((Resolve-Path $f), [string[]]$c, (New-Object Text.UTF8Encoding $false))"
echo Building... this can take 5-15 minutes.
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\desktop\build.ps1 > storage\logs\desktop-build-1.0.3.log 2>&1
if errorlevel 1 (
  echo BUILD FAILED - see storage\logs\desktop-build-1.0.3.log
) else (
  echo BUILD DONE - installer is in the dist folder.
)
pause
