# 🧪 POS App Integration Testing Checklist

## Pre-Requisites
- ✅ Backend running di localhost:8000
- ✅ Frontend running (npm run dev)
- ✅ MariaDB/MySQL dengan tabel sudah dibuat
- ✅ API endpoints accessible

---

## Test 1: Tambah Pasien & Lihat di PosView

### Steps:
1. Buka **Customers** page (Data Master Pasien)
2. Klik **+ Registrasi Pasien**
3. Isi form:
   - NIK: 1234567890123456
   - Nama: Budi Santoso
   - Gender: Laki-laki
   - DOB: 1990-05-15
   - Phone: 081234567890
   - Address: Jalan Merdeka No. 123
4. Klik **Simpan Data Medis**
5. Verifikasi:
   - ✅ Toast muncul: "Data pasien berhasil ditambahkan!"
   - ✅ Pasien muncul di tabel Customers
   - ✅ **CRITICAL**: Buka **Kasir (PosView)** → Pasien harus muncul di dropdown!

### Expected Result:
- Data pasien baru langsung tampil di kasir tanpa reload

---

## Test 2: Tambah Item & Lihat di PosView

### Steps:
1. Buka **Produk** page (Manajemen Barang & Layanan)
2. Klik **+ Tambah Item**
3. Isi form:
   - Nama: Perban Elastis
   - Jenis: Barang
   - Harga: 75000
4. Klik **Simpan Baru**
5. Verifikasi:
   - ✅ Toast muncul: "Data produk berhasil ditambahkan!"
   - ✅ Item muncul di tabel Produk
   - ✅ **CRITICAL**: Buka **Kasir (PosView)** → Item harus muncul di grid Farmasi & Jasa!

### Expected Result:
- Item baru langsung tampil di kasir search tanpa reload
- Warna badge sesuai jenis (orange untuk barang, blue untuk jasa)

---

## Test 3: Proses Transaksi Lengkap

### Prerequisite:
- Minimal 1 pasien & 2 items sudah ada di system

### Steps:
1. Buka **Kasir (PosView)**
2. Pilih pasien dari dropdown
3. Cari item "Perban" di search bar
4. Klik item → Masuk keranjang
5. Tambah item lain (misal: Konsultasi Dokter)
6. Set:
   - Pajak PPN: 11%
   - Diskon: 0 (atau bebas)
7. Klik **Proses Tagihan**
8. Di modal pembayaran:
   - Metode: Uang Tunai
   - Nominal: 100000
9. Klik **Konfirmasi**
10. Verifikasi:
    - ✅ Struk tercetak/preview
    - ✅ Keranjang dikosongkan
    - ✅ Toast success
    - ✅ **CRITICAL**: Buka **Riwayat Tagihan (HistoryView)** → Transaksi harus muncul!

### Expected Result:
- Transaksi tercatat di history
- Struk bisa dicetak
- Keranjang siap untuk transaksi berikutnya

---

## Test 4: Split Bill (Pembayaran Bertahap)

### Prerequisite:
- Transaksi dengan nominal besar sudah ada

### Steps:
1. Di modal pembayaran, masukkan nominal KURANG dari sisa tagihan
   - Misal: Total 300,000 → input 100,000
2. Klik **Konfirmasi**
3. Verifikasi:
   - ✅ Struk tercetak (Split Ke-1)
   - ✅ Toast warning: "Pembayaran Parsial sukses. Sisa: Rp 200,000"
   - ✅ Modal tetap terbuka untuk pembayaran berikutnya
4. Masukkan nominal kedua: 150,000
5. Klik **Konfirmasi**
6. Verifikasi:
   - ✅ Struk tercetak (Split Ke-2)
   - ✅ Sisa: Rp 50,000
7. Bayar sisanya: 50,000
8. Verifikasi:
   - ✅ Toast success: "Pembayaran LUNAS!"
   - ✅ Modal tertutup otomatis
   - ✅ **CRITICAL**: HistoryView harus tampilkan 3 baris untuk transaksi ini!

### Expected Result:
- Split bill berfungsi dengan baik
- Setiap split menghasilkan struk terpisah
- History menampilkan semua split sebagai row terpisah

---

## Test 5: Lihat Detail Struk & Print

### Steps:
1. Buka **Riwayat Tagihan (HistoryView)**
2. Klik tombol **Lihat Struk** pada salah satu transaksi
3. Verifikasi:
   - ✅ Modal tampil dengan detail lengkap
   - ✅ Invoice number, tanggal, nama pasien terlihat
   - ✅ Items & harga terlihat
   - ✅ Total & metode pembayaran terlihat
4. Klik **Print** (Ctrl+P atau dari browser)
5. Verifikasi:
   - ✅ Format struk rapi
   - ✅ Tulisan jelas & terbaca

### Expected Result:
- Detail struk lengkap dan terformat baik
- Bisa di-print langsung

---

## Test 6: Data Consistency Check

### Steps:
1. Buka **Customers** → Catat jumlah pasien (misal: 5)
2. Buka **Produk** → Catat jumlah item (misal: 8)
3. Buka **Kasir** → Verifikasi kedua angka ini
4. Kembali ke **Customers** dan refresh (F5)
5. Verifikasi:
   - ✅ Data masih sama
   - ✅ Tidak ada duplikasi
6. Lakukan transaksi di Kasir
7. Buka **Riwayat Tagihan** → Verifikasi transaksi baru muncul
8. Refresh page (F5) → Data masih ada

### Expected Result:
- Semua data konsisten antar page
- Data persist setelah refresh
- Tidak ada data yang hilang

---

## Test 7: Error Handling

### Test 7a: API Offline
1. Stop backend server
2. Coba refresh ProductsView
3. Verifikasi:
   - ✅ Fallback data tampil (data lokal temporary)
   - ✅ Tidak crash
4. Buka Kasir
5. Verifikasi:
   - ✅ App masih berfungsi dengan fallback data

### Test 7b: Invalid Input
1. Di ProductsView, coba tambah item tanpa nama
2. Klik Simpan
3. Verifikasi:
   - ✅ Toast error: "Nama dan harga wajib diisi dengan benar!"
   - ✅ Modal tetap terbuka
4. Coba tambah dengan harga 0
5. Verifikasi:
   - ✅ Toast error muncul

### Expected Result:
- App robust terhadap error
- Error message jelas & membantu

---

## Test 8: Refresh Data Functionality

### Steps:
1. Buka **Customers** page
2. Klik tombol **Refresh**
3. Verifikasi:
   - ✅ Loading indicator muncul
   - ✅ Data di-fetch dari server
   - ✅ Toast: "Data pasien berhasil diperbarui dari server!"
4. Lakukan hal sama di **Produk** & **Riwayat Tagihan**

### Expected Result:
- Refresh berfungsi dengan baik
- Toast notification memberikan feedback

---

## ✅ Acceptance Criteria (SEMUA HARUS PASSING)

- [ ] Test 1: Pasien langsung muncul di Kasir tanpa reload
- [ ] Test 2: Item langsung muncul di Kasir tanpa reload
- [ ] Test 3: Transaksi tercatat & muncul di History
- [ ] Test 4: Split bill berfungsi & history menampilkan semua split
- [ ] Test 5: Detail struk lengkap & bisa di-print
- [ ] Test 6: Data konsisten antar page & persist
- [ ] Test 7: Error handling robust & user-friendly
- [ ] Test 8: Refresh data berfungsi di semua page

---

## 📝 Notes

- Jika ada yang gagal, cek console browser (F12 → Console tab)
- Cek Network tab untuk verifikasi API calls
- Pastikan backend response format sesuai yang diharapkan
- Jika ada bug, catat error message dan stack trace

---

## 🎉 Integration Complete!

Jika semua test passing, maka POS App sudah fully integrated dengan:
✅ Real-time data sync
✅ Split bill support
✅ Complete workflow
✅ Error handling
✅ User feedback (toast notifications)
