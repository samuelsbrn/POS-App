# 🧪 Quick Integration Test - 5 Menit

## Setup Sebelum Test

### Terminal 1: Backend (PHP Server)
```powershell
# Terminal PowerShell - Backend sudah running di background
# Verify: http://localhost:8000 → Should show "Selamat datang"
```

### Terminal 2: Frontend (Vue Dev Server)  
```powershell
cd "c:\POS App\frontend"
npm run dev
# Browser: http://localhost:5173
```

---

## 🧪 TEST CASE 1: Verifikasi API Connectivity (1 min)

### Step 1.1: Open Browser Console
- Press: `F12` atau `Ctrl+Shift+I`
- Go to: **Console** tab
- Go to: **Network** tab (keep open)

### Step 1.2: Refresh Frontend
- Press: `F5` atau `Ctrl+R`
- **Expected**: 
  - No red errors di Console
  - Network tab shows API calls:
    - GET `/patients` → 200 OK
    - GET `/healthcare-items` → 200 OK
  - No 500 errors

### Step 1.3: Verify Data Loads
- **PosView** (Kasir):
  - ✅ Patient dropdown shows "Budi Santoso"
  - ✅ Product grid shows items (e.g., "Pendaftaran & Administrasi Pasien")
- **HistoryView**:
  - May be empty (no transactions yet)
  
---

## 🧪 TEST CASE 2: Add Patient & Verify Sync (1 min)

### Step 2.1: Go to CustomersView
- Click: **Customers** di top navbar

### Step 2.2: Add New Patient
- Click: **Tambah Pasien** button
- Fill form:
  - Name: `Test Pasien 1`
  - Phone: `081234567890`
  - Address: `Jl Test No. 1`
  - Click: **Simpan**
- **Expected**: 
  - Toast notification: "Pasien berhasil ditambahkan!"
  - Patient appears di list bawah

### Step 2.3: Verify Sync to PosView
- Click: **Kasir** di navbar
- **Expected**: 
  - Dropdown patient shows: `Test Pasien 1`
  - **TIDAK perlu refresh!** (Pinia reactivity bekerja)

---

## 🧪 TEST CASE 3: Add Product & Verify Sync (1 min)

### Step 3.1: Go to ProductsView
- Click: **Products** di navbar

### Step 3.2: Add New Barang (Drug/Supply)
- Click: **Tambah Barang** button
- Fill form:
  - Nama: `Test Obat 1`
  - Harga: `50000`
  - Stock: `10`
  - Click: **Simpan**
- **Expected**: 
  - Toast: "Barang berhasil ditambahkan!"
  - Item appears di list

### Step 3.3: Verify Sync to PosView
- Click: **Kasir**
- **Expected**: 
  - `Test Obat 1` appears di product grid
  - **TIDAK perlu refresh!**

### Step 3.4: Add New Jasa (Service)
- Back to **Products**
- Click: **Tambah Jasa**
- Fill form:
  - Nama: `Test Jasa 1`
  - Harga: `100000`
  - Click: **Simpan**
- **Expected**: 
  - Toast: "Jasa berhasil ditambahkan!"
  - Item appears di list with "JASA" badge

### Step 3.5: Verify in PosView Again
- Click: **Kasir**
- **Expected**: 
  - Both `Test Obat 1` AND `Test Jasa 1` visible
  - Together with sample data items

---

## 🧪 TEST CASE 4: Process Transaction & Verify History (1.5 min)

### Step 4.1: Go to PosView
- Click: **Kasir**

### Step 4.2: Select Patient
- Click dropdown "Pilih Pasien"
- Select: `Test Pasien 1`

### Step 4.3: Add Items to Cart
- Click: `Test Obat 1` (qty 1)
- Click: `Test Jasa 1` (qty 1)
- **Expected**: 
  - Cart shows 2 items
  - Total = 50000 + 100000 = 150000 (before tax/discount)

### Step 4.4: Process Checkout
- Click: **Checkout** button
- **Expected**: 
  - Modal pembayaran muncul
  - Shows total amount
  - Payment methods available (Cash, QRIS, Card)

### Step 4.5: Complete Payment
- Select: **Uang Tunai** (Cash)
- Enter: Amount = `150000` (atau lebih)
- Click: **Bayar** button
- **Expected**: 
  - Receipt modal shows
  - Status shows "PAID" ✅
  - Products grid CLEARS (auto-refresh)

### Step 4.6: Verify in HistoryView
- Click: **History** di navbar
- **Expected**: 
  - New transaction shows
  - Patient: `Test Pasien 1`
  - Items: `Test Obat 1`, `Test Jasa 1`
  - Status: `PAID` (Lunas)
  - Amount: `150000`

---

## 🧪 TEST CASE 5: Split Bill Feature (1.5 min)

### Step 5.1: Create Another Transaction
- Back to **Kasir**
- Select patient: `Budi Santoso` (sample data)

### Step 5.2: Add Multiple Items
- Add 2-3 different items
- Total ≈ 200000

### Step 5.3: First Payment (Partial)
- Click: **Checkout**
- Payment method: **QRIS / E-Wallet**
- Enter amount: `100000` (50% only!)
- Click: **Bayar**
- **Expected**: 
  - Modal shows: "PEMBAYARAN BERHASIL (Sebagian)"
  - Status: `PARTIALLY PAID` ⚠️
  - Receipt shows remaining due: `100000`

### Step 5.4: Second Payment (Complete)
- Modal akan prompt untuk lanjut bayar
- Click: **Lanjut Pembayaran** button
- Payment method: **Kartu Debit / Kredit**
- Enter amount: `100000` (sisa nya)
- Click: **Bayar**
- **Expected**: 
  - Status changes: `PARTIALLY PAID` → `PAID` ✅
  - Final receipt shows total paid

### Step 5.5: Verify in History
- Click: **History**
- **Expected**: 
  - Transaction shows with 2 payment records
  - First payment: `100000` (QRIS)
  - Second payment: `100000` (Card)
  - Total: `200000`
  - Status: `PAID`

---

## ✅ Checklist - Semua Test Passed?

- [ ] API calls working (Network tab shows 200 OK)
- [ ] Patient dropdown auto-sync (Kasir updated after add pasien)
- [ ] Product grid auto-sync (Kasir updated after add item)
- [ ] Transaction created & shows in History
- [ ] Split bill working (Partial payment → Full payment)
- [ ] Status updates correctly (Unpaid → Partially Paid → Paid)
- [ ] No manual refresh needed (All reactive via Pinia)
- [ ] Toast notifications showing
- [ ] Receipt modal displaying correctly

---

## 🐛 Troubleshooting

### API Returns 500 Error
**Check**: 
1. Backend running? `http://localhost:8000`
2. Database connected? `backend/public/index.php` credentials
3. Tables exist? Open PhpMyAdmin & check `pos` database

**Fix**:
```powershell
# Restart backend
Stop-Job -Name 'pos-backend' | Remove-Job
cd "c:\POS App\backend"
php -S localhost:8000 -t public
```

### Frontend shows empty dropdown/grid
**Check**: 
1. API response - Network tab di browser
2. Console errors - `F12` → Console tab
3. Backend connectivity

**Fix**:
- Hard refresh: `Ctrl+Shift+R`
- Clear cache: `Ctrl+Shift+Delete`

### "Patient tidak muncul di Kasir"
**Check**: 
1. Pinia store connected? Check `posStore.ts`
2. `storeToRefs()` used? Check `PosView.vue`

**Fix**:
- Restart frontend: `npm run dev`

### Split bill not working
**Check**: 
1. First payment < total amount?
2. Second payment = remaining amount?

**Fix**: 
- Check `prosesCheckoutKasir()` in `posStore.ts`
- Verify status update logic

---

## 📊 Expected Results Summary

| Component | Feature | Status |
|-----------|---------|--------|
| Backend | API endpoints | ✅ 200 OK |
| Frontend | Data loading | ✅ Automatic |
| Patients | CRUD & sync | ✅ Real-time |
| Products | CRUD & sync | ✅ Real-time |
| Kasir | Selection | ✅ Dropdown populated |
| Checkout | Payment | ✅ Modal working |
| Split Bill | Multi-payment | ✅ Sequential payment |
| History | Record display | ✅ Auto-updated |

---

## ✨ Success Indicator

**Semua integration berhasil jika:**
1. ✅ Bisa add pasien & muncul di Kasir WITHOUT refresh
2. ✅ Bisa add item & muncul di Kasir WITHOUT refresh  
3. ✅ Bisa checkout & data saved ke database
4. ✅ Transaksi muncul di History automatically
5. ✅ Split bill creates multiple payment records
6. ✅ NO manual refresh needed anywhere

**If all tests pass: 🎉 FULLY INTEGRATED!**
