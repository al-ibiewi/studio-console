# Builds dist\alibiewi-console-<version>.zip with only the files a WordPress site needs.
$ErrorActionPreference = 'Stop'
Set-Location (Join-Path $PSScriptRoot '..')
$line = Select-String -Path 'alibiewi-console.php' -Pattern "define\( 'ALC_VERSION', '([0-9.]+)'" | Select-Object -First 1
$version = $line.Matches[0].Groups[1].Value
$stage = Join-Path ([IO.Path]::GetTempPath()) ("alc-" + [guid]::NewGuid())
$dest = Join-Path $stage 'alibiewi-console'
New-Item -ItemType Directory -Force -Path $dest, 'dist' | Out-Null
Copy-Item -Recurse 'alibiewi-console.php','uninstall.php','readme.txt','assets','includes','languages' $dest
$zip = Join-Path (Resolve-Path 'dist') "alibiewi-console-$version.zip"
if (Test-Path $zip) { Remove-Item $zip }
Compress-Archive -Path $dest -DestinationPath $zip
Remove-Item -Recurse -Force $stage
Write-Host "Built dist\alibiewi-console-$version.zip"
