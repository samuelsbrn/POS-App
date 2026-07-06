# ============================================================
# Start PHP Development Server untuk Backend
# ============================================================

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Starting POS App Backend Server" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

$backendDir = "c:\POS App\backend"
$publicDir = "$backendDir\public"

# Kill existing PHP processes
Write-Host "Stopping previous PHP instances..." -ForegroundColor Yellow
Get-Process php -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue

Start-Sleep -Milliseconds 500

# Change to backend directory
Set-Location $backendDir

Write-Host "✅ Starting PHP server on http://localhost:8000" -ForegroundColor Green
Write-Host ""
Write-Host "Backend Info:" -ForegroundColor Cyan
Write-Host "- Location: $publicDir" -ForegroundColor Gray
Write-Host "- Database: pos" -ForegroundColor Gray
Write-Host "- DB User: root" -ForegroundColor Gray
Write-Host "- DB Password: mariadb" -ForegroundColor Gray
Write-Host ""
Write-Host "Press Ctrl+C to stop the server" -ForegroundColor Yellow
Write-Host ""

# Start PHP development server
php -S localhost:8000 -t $publicDir

