# 🐛 Debug Guide - Split Bill & History Sync

## Masalah yang Sudah Diperbaiki

### ✅ FIX 1: Split Bill Logic
**File**: `frontend/src/views/PosView.vue`
**Masalah**: 
- Modal pembayaran langsung tutup setelah pembayaran pertama
- Tidak ada opsi untuk melanjutkan pembayaran (split)

**Perbaikan**:
- ✅ Menambah debugging log untuk track sisa_tagihan
- ✅ Memisahkan logic untuk LUNAS vs SPLIT PAYMENT
- ✅ Modal tetap terbuka untuk split payment
- ✅ Reset form dengan sisa amount untuk pembayaran berikutnya
- ✅ Toast notification lebih jelas untuk split mode

### ✅ FIX 2: History Auto-Sync
**File**: `frontend/src/stores/posStore.ts`
**Masalah**: 
- History tidak update setelah transaksi
- fetchHistory tidak dipanggil dengan benar

**Perbaikan**:
- ✅ Menghapus cache check di fetchHistory (selalu fresh)
- ✅ Menambah logging untuk track history updates
- ✅ Memastikan fetchHistory(true) dipanggil setelah setiap payment

**File**: `frontend/src/views/HistoryView.vue`
**Perbaikan**:
- ✅ Watch historyList dengan deep: true
- ✅ Log ketika history berubah

### ✅ FIX 3: Payment Modal UX
**File**: `frontend/src/views/PosView.vue`
**Perbaikan**:
- ✅ Total Tagihan Asli selalu terlihat di modal
- ✅ Sisa Pembayaran hanya muncul saat split (sudah ada pembayaran)
- ✅ Info badge untuk Split Payment mode
- ✅ Tombol "Tutup" untuk cancel, "Konfirmasi Bayar" untuk lanjut

---

## 🧪 Cara Test Split Bill (Step by Step)

### Test 1: Normal Checkout (Full Payment)
```
1. Tambah 2-3 items ke cart
2. Click "Proses Tagihan"
3. Modal muncul dengan total di atas
4. Enter amount = total (misalnya 150000)
5. Click "Konfirmasi Bayar"
6. Lihat console (F12) → harus muncul "🔄 Split Payment Mode"? NO
   Seharusnya muncul "✅ Pembayaran LUNAS!"
7. Modal auto-close setelah 2 detik
8. Go to History → transaksi muncul dengan status "PAID" ✅
```

### Test 2: Split Payment (2x pembayaran)
```
1. Tambah 2-3 items ke cart (total 200000)
2. Click "Proses Tagihan"
3. Modal pembayaran muncul
   - Total Tagihan Asli: Rp 200000
   - Sisa Pembayaran: Hidden (belum ada split)
   - Form amount sudah pre-filled: 200000
4. Ubah amount jadi 100000 (50%)
5. Click "Konfirmasi Bayar"
6. Struk print - terlihat "SPLIT KE-1"
7. Check console (F12 → Console):
   - 📊 Payment Response: sisa_tagihan: 100000
   - ⚠️ Pembayaran Parsial sukses! Sisa: Rp 100000
   - Modal TIDAK tutup ✅
8. Modal tetap terbuka untuk pembayaran 2
   - Total Tagihan Asli: Rp 200000
   - Sisa Pembayaran: Rp 100000 (SEKARANG VISIBLE)
   - Form amount auto-fill: 100000
9. Change method ke QRIS (atau Card)
10. Click "Konfirmasi Bayar" lagi
11. Struk print - terlihat "SPLIT KE-2"
12. Check console:
    - 📊 Payment Response: sisa_tagihan: 0
    - ✅ Pembayaran LUNAS!
    - Modal auto-close
13. Go to History → transaksi muncul DENGAN 2 ROWS:
    - Row 1: SPLIT KE-1 (100000, QRIS)
    - Row 2: SPLIT KE-2 (100000, Card)
    - Status keduanya: "PAID (Split 1)" & "PAID (Split 2)" atau "PAID"
```

### Test 3: Verify History Auto-Sync
```
1. Open 2 browser tabs (atau split screen):
   - Tab 1: Kasir (PosView)
   - Tab 2: History (HistoryView)
2. Di Tab 1: Process transaction & bayar
3. Di Tab 2: History harus UPDATE OTOMATIS
   - TANPA click "Refresh Data"
   - Transaksi baru muncul di atas
4. Check console (F12 → Console):
   - 📊 History Fetched: X transactions (jumlah harus bertambah)
```

---

## 📊 Console Logging Reference

Ketika testing, perhatikan log ini di browser console (F12):

```javascript
// SAAT CREATE CHECKOUT
✅ Checkout Response: {billing_id: 123, invoice_number: "ZC-POS/2026/01/01/5678"}

// SAAT FIRST PAYMENT (PARTIAL)
💳 Processing Payment: {patient_billing_id: 123, payment_method_id: 2, amount_paid: 100000, ...}
✅ Payment Response: {status: "success", sisa_tagihan: 100000, payment_status: "Partially Paid", ...}
📊 Payment Response: {sisa_tagihan: 100000, payment_status: "Partially Paid", amount_paid: 100000, ...}
⚠️ Pembayaran Parsial sukses! Sisa: Rp 100000
🔄 Split Payment Mode - Tunggu pembayaran berikutnya
📊 History Fetched: 1 transactions

// SAAT SECOND PAYMENT (COMPLETE)
💳 Processing Payment: {patient_billing_id: 123, payment_method_id: 1, amount_paid: 100000, ...}
✅ Payment Response: {status: "success", sisa_tagihan: 0, payment_status: "Paid", ...}
📊 Payment Response: {sisa_tagihan: 0, payment_status: "Paid", amount_paid: 100000, ...}
✅ Pembayaran LUNAS!
📊 History Fetched: 1 transactions (tetap 1 karena masih invoice yang sama)
```

---

## 🔍 Troubleshooting

### Problem: Modal tetap closed setelah split payment pertama
**Solusi**: 
- Cek console untuk error message
- Pastikan backend mengembalikan `sisa_tagihan > 0`
- Verify browser console untuk "Payment Response" log

### Problem: History tidak update setelah pembayaran
**Solusi**:
- Hard refresh History page: `Ctrl+Shift+R`
- Cek console apakah ada error API
- Verify backend `/billing/history` endpoint ada

### Problem: Payment modal content tidak match
**Solusi**:
- Cache clear: `Ctrl+Shift+Delete`
- Restart frontend: `npm run dev`
- Hard refresh: `F5` atau `Ctrl+R`

### Problem: Struk tidak print
**Solusi**:
- Check browser popup blocker
- Check console untuk JavaScript error
- Verify browser allow print dialog

---

## 📋 Test Checklist

Sebelum approve, pastikan✅ semua ini:

- [ ] Test 1 (Normal Checkout) ✅ PASS
  - [ ] Modal muncul
  - [ ] Payment diproses
  - [ ] Modal tutup after payment
  - [ ] History auto-update

- [ ] Test 2 (Split Payment) ✅ PASS
  - [ ] First payment partial 50%
  - [ ] Modal TIDAK tutup
  - [ ] Form reset dengan sisa amount
  - [ ] Second payment complete 100%
  - [ ] Modal tutup
  - [ ] Struk terpisah untuk tiap payment

- [ ] Test 3 (History Sync) ✅ PASS
  - [ ] History update tanpa refresh
  - [ ] Multiple payment rows visible
  - [ ] Status correct untuk tiap row

- [ ] Console logging clear (no red errors)

---

## 🚀 Next Steps After Fixes

1. **Start Frontend**
   ```powershell
   cd "c:\POS App\frontend"
   npm run dev
   ```

2. **Run Tests**
   - Follow Test 1, 2, 3 above

3. **If All Pass**: ✅ Integration complete!

4. **If Any Fail**: Report specific test + console error

---

**Key Changes Summary**:
1. Split bill modal stays open for partial payments ✅
2. History auto-fetches after every payment ✅
3. Console logging for debugging ✅
4. Better UX for split payment workflow ✅

**Expected Behavior**:
- Add 2 items → Checkout → Pay 50% → Modal open → Pay 50% → Modal close → History shows 2 rows ✅
