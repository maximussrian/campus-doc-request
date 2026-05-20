# Creates campus-doc-request-hostinger.zip for upload (excludes secrets & dev files)
$root = Split-Path -Parent $PSScriptRoot
$out  = Join-Path $root "campus-doc-request-hostinger.zip"

$excludeDirs  = @('.git', 'vendor', 'node_modules', '.idea', '.vscode', 'database')
$excludeFiles = @('.env', '.env.local', 'composer.phar', 'composer-setup.php', 'Dockerfile', 'render.yaml')

if (Test-Path $out) { Remove-Item $out -Force }

$temp = Join-Path $env:TEMP "hostinger-deploy-$(Get-Random)"
New-Item -ItemType Directory -Path $temp -Force | Out-Null

Get-ChildItem -Path $root -Force | ForEach-Object {
    if ($_.PSIsContainer) {
        if ($excludeDirs -contains $_.Name) { return }
        Copy-Item -Path $_.FullName -Destination (Join-Path $temp $_.Name) -Recurse -Force
    } else {
        if ($excludeFiles -contains $_.Name) { return }
        if ($_.Extension -eq '.sql') { return }
        Copy-Item -Path $_.FullName -Destination (Join-Path $temp $_.Name) -Force
    }
}

Compress-Archive -Path "$temp\*" -DestinationPath $out -Force
Remove-Item -Path $temp -Recurse -Force

Write-Host "Created: $out"
Write-Host "Upload this ZIP to Hostinger public_html, then extract."
