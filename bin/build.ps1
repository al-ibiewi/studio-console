# Builds dist\alibiewi-console-<version>.zip with only the files a WordPress site needs.
# Entries use forward slashes, so the zip unpacks correctly on Linux hosts.
# (Compress-Archive in Windows PowerShell 5.1 writes backslashes, which breaks plugin uploads.)
$ErrorActionPreference = 'Stop'
Set-Location (Join-Path $PSScriptRoot '..')
Add-Type -AssemblyName System.IO.Compression, System.IO.Compression.FileSystem

$line = Select-String -Path 'alibiewi-console.php' -Pattern "define\( 'ALC_VERSION', '([0-9.]+)'" | Select-Object -First 1
$version = $line.Matches[0].Groups[1].Value
$root = (Get-Location).Path
New-Item -ItemType Directory -Force -Path 'dist' | Out-Null
$zip = Join-Path (Resolve-Path 'dist') "alibiewi-console-$version.zip"
if (Test-Path $zip) { Remove-Item $zip }

$files = foreach ($item in 'alibiewi-console.php', 'uninstall.php', 'readme.txt', 'assets', 'includes', 'languages') {
	Get-ChildItem -Path $item -Recurse -File | Where-Object { $_.Name -notin '.DS_Store', 'Thumbs.db' }
}

$archive = [IO.Compression.ZipFile]::Open($zip, [IO.Compression.ZipArchiveMode]::Create)
try {
	foreach ($file in $files) {
		$relative = $file.FullName.Substring($root.Length + 1) -replace '\\', '/'
		[IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $file.FullName, "alibiewi-console/$relative", [IO.Compression.CompressionLevel]::Optimal) | Out-Null
	}
} finally {
	$archive.Dispose()
}
Write-Host "Built dist\alibiewi-console-$version.zip"
