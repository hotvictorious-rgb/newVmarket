# Victorious MARKET - Shared Hosting Deployment Packager (PowerShell)
# Prepares two ready-to-upload directories: 'core' (outside public_html) and 'public_html'

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "   Victorious MARKET - Shared Hosting Packaging Utility   " -ForegroundColor Yellow
Write-Host "==========================================================" -ForegroundColor Cyan

$WorkspaceRoot = Resolve-Path "$PSScriptRoot\.."
$BackendDir = "$WorkspaceRoot\backend"
$OutputDir = "$WorkspaceRoot\dist_shared_hosting"
$CoreDir = "$OutputDir\core"
$PublicHtmlDir = "$OutputDir\public_html"

if (Test-Path $OutputDir) {
    Write-Host "Cleaning previous build in $OutputDir..." -ForegroundColor Gray
    Remove-Item -Recurse -Force $OutputDir
}

New-Item -ItemType Directory -Path $CoreDir -Force | Out-Null
New-Item -ItemType Directory -Path $PublicHtmlDir -Force | Out-Null

Write-Host "1. Copying Core Application Files to core/..." -ForegroundColor Green
$ExcludedItems = @("public", "tests", ".git", ".github", "node_modules", "dist_shared_hosting")

Get-ChildItem -Path $BackendDir | Where-Object { $ExcludedItems -notcontains $_.Name } | ForEach-Object {
    Copy-Item -Path $_.FullName -Destination $CoreDir -Recurse -Force
}

Write-Host "2. Copying Public Assets to public_html/..." -ForegroundColor Green
Copy-Item -Path "$BackendDir\public\*" -Destination $PublicHtmlDir -Recurse -Force

Write-Host "3. Patching public_html/index.php paths for shared hosting..." -ForegroundColor Green
$IndexPhpPath = "$PublicHtmlDir\index.php"
$IndexContent = Get-Content $IndexPhpPath -Raw

# Replace relative paths so public_html references ../core/
$IndexContent = $IndexContent.Replace("__DIR__.'/../vendor/autoload.php'", "__DIR__.'/../core/vendor/autoload.php'")
$IndexContent = $IndexContent.Replace("__DIR__.'/../bootstrap/app.php'", "__DIR__.'/../core/bootstrap/app.php'")
$IndexContent = $IndexContent.Replace("__DIR__.'/../storage/framework/maintenance.php'", "__DIR__.'/../core/storage/framework/maintenance.php'")
Set-Content -Path $IndexPhpPath -Value $IndexContent

Write-Host "4. Creating production .env.production template in core/..." -ForegroundColor Green
$EnvExample = Get-Content "$BackendDir\.env.example" -Raw
$ProductionEnv = $EnvExample.Replace("APP_ENV=local", "APP_ENV=production")
$ProductionEnv = $ProductionEnv.Replace("APP_DEBUG=true", "APP_DEBUG=false")
$ProductionEnv = $ProductionEnv.Replace("DB_CONNECTION=sqlite", "DB_CONNECTION=mysql")
$ProductionEnv = $ProductionEnv.Replace("QUEUE_CONNECTION=sync", "QUEUE_CONNECTION=database")
$ProductionEnv = $ProductionEnv.Replace("CACHE_STORE=file", "CACHE_STORE=database")
$ProductionEnv = $ProductionEnv.Replace("SESSION_DRIVER=file", "SESSION_DRIVER=database")
Set-Content -Path "$CoreDir\.env.production.example" -Value $ProductionEnv

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "[OK] PACKAGING COMPLETE!" -ForegroundColor Green
Write-Host "  Directory: $OutputDir" -ForegroundColor Yellow
Write-Host "  Structure:" -ForegroundColor White
Write-Host "    - core/        -> Upload to /home/your_username/core" -ForegroundColor White
Write-Host "    - public_html/ -> Upload into /home/your_username/public_html" -ForegroundColor White
Write-Host "==========================================================" -ForegroundColor Cyan
