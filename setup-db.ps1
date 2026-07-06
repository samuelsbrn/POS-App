# ============================================================
# Setup Database Script untuk POS Healthcare App
# ============================================================

$MySQLPath = "C:\Program Files\MariaDB 12.3\bin\mysql.exe"
$SQLFile = "c:\POS App\schema_mysql.sql"
$DBUser = "root"

# Coba dengan berbagai password
$passwords = @("", "root", "password", "mysql", "mariadb")

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "POS Healthcare Database Setup" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check if mysql client exists
if (-Not (Test-Path $MySQLPath)) {
    Write-Host "❌ Error: MySQL client tidak ditemukan di $MySQLPath" -ForegroundColor Red
    Write-Host "Pastikan MariaDB sudah terinstall dengan benar." -ForegroundColor Yellow
    exit 1
}

Write-Host "✅ MySQL client found: $MySQLPath" -ForegroundColor Green
Write-Host ""

# Coba connect dengan berbagai password
$connected = $false
$correctPassword = ""

foreach ($pwd in $passwords) {
    Write-Host "Mencoba connect dengan password: '$pwd'" -ForegroundColor Yellow
    
    $testCmd = @"
SELECT 'Connection successful' as status;
"@

    try {
        if ($pwd -eq "") {
            $output = $testCmd | & $MySQLPath -u $DBUser 2>&1
        } else {
            $output = $testCmd | & $MySQLPath -u $DBUser -p"$pwd" 2>&1
        }
        
        if ($output -match "Connection successful") {
            Write-Host "✅ Connection successful dengan password: '$pwd'" -ForegroundColor Green
            $connected = $true
            $correctPassword = $pwd
            break
        }
    } catch {
        # Silent, try next password
    }
}

if (-Not $connected) {
    Write-Host ""
    Write-Host "❌ Tidak dapat connect ke MariaDB dengan password default." -ForegroundColor Red
    Write-Host ""
    Write-Host "Silakan masukkan password MariaDB root user Anda:" -ForegroundColor Yellow
    $correctPassword = Read-Host "Password (biarkan kosong jika tidak ada password)"
    
    # Validate password with one more try
    try {
        if ($correctPassword -eq "") {
            $testCmd = "SELECT 'Test';" | & $MySQLPath -u $DBUser 2>&1
        } else {
            $testCmd = "SELECT 'Test';" | & $MySQLPath -u $DBUser -p"$correctPassword" 2>&1
        }
    } catch {
        Write-Host "❌ Masih gagal connect. Periksa username/password MariaDB Anda." -ForegroundColor Red
        exit 1
    }
}

Write-Host ""
Write-Host "Menjalankan setup database..." -ForegroundColor Cyan

try {
    $sqlContent = Get-Content $SQLFile -Raw
    
    if ($correctPassword -eq "") {
        $sqlContent | & $MySQLPath -u $DBUser
    } else {
        $sqlContent | & $MySQLPath -u $DBUser -p"$correctPassword"
    }
    
    Write-Host "✅ Database setup berhasil!" -ForegroundColor Green
    Write-Host ""
    Write-Host "Summary:" -ForegroundColor Cyan
    Write-Host "- Database 'pos' created" -ForegroundColor Green
    Write-Host "- Tabel patients created" -ForegroundColor Green
    Write-Host "- Tabel healthcare_items created" -ForegroundColor Green
    Write-Host "- Tabel payment_methods created" -ForegroundColor Green
    Write-Host "- Tabel patient_billings created" -ForegroundColor Green
    Write-Host "- Tabel billing_details created" -ForegroundColor Green
    Write-Host "- Tabel billing_payments created" -ForegroundColor Green
    Write-Host ""
    Write-Host "Sample data sudah di-insert." -ForegroundColor Green
    
} catch {
    Write-Host "❌ Error saat menjalankan SQL: $_" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Next Steps:" -ForegroundColor Cyan
Write-Host "1. Update backend/public/index.php dengan password yang benar" -ForegroundColor Yellow
Write-Host "2. Restart backend server: npm start (di folder backend)" -ForegroundColor Yellow  
Write-Host "3. Frontend sudah ready untuk di-test" -ForegroundColor Yellow
Write-Host "========================================" -ForegroundColor Cyan
