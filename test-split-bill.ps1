# Test Split Bill Payment via Backend API

Write-Host "╔════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║     TEST SPLIT BILL - Direct Backend API Test             ║" -ForegroundColor Cyan
Write-Host "╚════════════════════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""

# Step 1: Create a billing (checkout)
Write-Host "Step 1️⃣  : Creating invoice..." -ForegroundColor Yellow
$createBillingPayload = @{
    patient_id = 1
    subtotal = 200000
    tax = 0
    discount = 0
    total_amount = 200000
    items = @(
        @{ item_id = 1; quantity = 1; price = 100000 },
        @{ item_id = 2; quantity = 1; price = 100000 }
    )
} | ConvertTo-Json

$createResponse = Invoke-WebRequest -Uri "http://localhost:8000/billing/create" -Method POST -ContentType "application/json" -Body $createBillingPayload -UseBasicParsing
$billingData = $createResponse.Content | ConvertFrom-Json

$billingId = $billingData.billing_id
$invoiceNumber = $billingData.invoice_number

Write-Host "✅ Invoice created!" -ForegroundColor Green
Write-Host "   - Billing ID: $billingId" -ForegroundColor Gray
Write-Host "   - Invoice Number: $invoiceNumber" -ForegroundColor Gray
Write-Host "   - Total: Rp 200000" -ForegroundColor Gray
Write-Host ""

# Step 2: First Payment (50%)
Write-Host "Step 2️⃣  : First payment (50% = 100000 by QRIS)..." -ForegroundColor Yellow
$payment1Payload = @{
    patient_billing_id = $billingId
    payment_method_id = 2  # QRIS
    amount_paid = 100000
    change_amount = 0
} | ConvertTo-Json

$payment1Response = Invoke-WebRequest -Uri "http://localhost:8000/billing/payment" -Method POST -ContentType "application/json" -Body $payment1Payload -UseBasicParsing
$payment1Data = $payment1Response.Content | ConvertFrom-Json

Write-Host "✅ First payment processed!" -ForegroundColor Green
Write-Host "   - Amount Paid: Rp 100000" -ForegroundColor Gray
Write-Host "   - Sisa Tagihan: Rp $($payment1Data.sisa_tagihan)" -ForegroundColor Red
Write-Host "   - Payment Status: $($payment1Data.payment_status)" -ForegroundColor Gray
Write-Host ""

# Check if sisa_tagihan is correct
if ($payment1Data.sisa_tagihan -eq 100000) {
    Write-Host "✅ CORRECT! Sisa = 100000 (tinggal 50%)" -ForegroundColor Green
} else {
    Write-Host "❌ WRONG! Sisa seharusnya 100000, tapi dapat: $($payment1Data.sisa_tagihan)" -ForegroundColor Red
}
Write-Host ""

# Step 3: Second Payment (Complete)
Write-Host "Step 3️⃣  : Second payment (50% = 100000 by CASH)..." -ForegroundColor Yellow
$payment2Payload = @{
    patient_billing_id = $billingId
    payment_method_id = 1  # Cash
    amount_paid = 100000
    change_amount = 0
} | ConvertTo-Json

$payment2Response = Invoke-WebRequest -Uri "http://localhost:8000/billing/payment" -Method POST -ContentType "application/json" -Body $payment2Payload -UseBasicParsing
$payment2Data = $payment2Response.Content | ConvertFrom-Json

Write-Host "✅ Second payment processed!" -ForegroundColor Green
Write-Host "   - Amount Paid: Rp 100000" -ForegroundColor Gray
Write-Host "   - Sisa Tagihan: Rp $($payment2Data.sisa_tagihan)" -ForegroundColor Red
Write-Host "   - Payment Status: $($payment2Data.payment_status)" -ForegroundColor Gray
Write-Host ""

# Check if sisa_tagihan is 0
if ($payment2Data.sisa_tagihan -eq 0) {
    Write-Host "✅ CORRECT! Sisa = 0 (LUNAS)" -ForegroundColor Green
} else {
    Write-Host "❌ WRONG! Sisa seharusnya 0, tapi dapat: $($payment2Data.sisa_tagihan)" -ForegroundColor Red
}
Write-Host ""

# Step 4: Check History
Write-Host "Step 4️⃣  : Checking history..." -ForegroundColor Yellow
$historyResponse = Invoke-WebRequest -Uri "http://localhost:8000/billing/history" -UseBasicParsing
$historyData = $historyResponse.Content | ConvertFrom-Json

Write-Host "✅ History retrieved!" -ForegroundColor Green
Write-Host "   - Total records: $($historyData.data.Count)" -ForegroundColor Gray

# Find our invoice in history
$ourInvoices = $historyData.data | Where-Object { $_.invoice_number -like "*$invoiceNumber*" }
Write-Host "   - Our invoice records: $($ourInvoices.Count)" -ForegroundColor Gray

foreach ($inv in $ourInvoices) {
    Write-Host "     * $($inv.invoice_number) | Status: $($inv.payment_status) | Amount: Rp $($inv.total_amount)" -ForegroundColor Gray
}
Write-Host ""

Write-Host "╔════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║                      TEST COMPLETE                         ║" -ForegroundColor Cyan
Write-Host "╚════════════════════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""
Write-Host "📊 SUMMARY:" -ForegroundColor Yellow
Write-Host "   First Payment:  100000 (QRIS)  → Sisa: $($payment1Data.sisa_tagihan) [$($payment1Data.payment_status)]" -ForegroundColor White
Write-Host "   Second Payment: 100000 (CASH)  → Sisa: $($payment2Data.sisa_tagihan) [$($payment2Data.payment_status)]" -ForegroundColor White
Write-Host "   History shows:  $($ourInvoices.Count) rows" -ForegroundColor White
Write-Host ""
