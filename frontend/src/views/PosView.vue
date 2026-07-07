<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { usePosStore } from '../stores/posStore'
import { storeToRefs } from 'pinia'

// 1. HUBUNGKAN KE STORE
const posStore = usePosStore()
const { patients, products } = storeToRefs(posStore) 

const patientsList = computed(() => [{ id: null, mrn: 'UMUM', name: 'Pasien Umum / Walk-in' }, ...patients.value])
const selectedPatient = ref(patientsList.value[0])
const cart = ref<any[]>([])

const taxRate = ref(0)
const discountRp = ref(0)

// SEARCH BAR
const searchQuery = ref('')
const filteredProducts = computed(() => {
  if (!searchQuery.value) return products.value
  return products.value.filter(p => 
    p.name.toLowerCase().includes(searchQuery.value.toLowerCase()) || 
    (p.category && p.category.toLowerCase().includes(searchQuery.value.toLowerCase()))
  )
})

const addToCart = (product: any) => {
  const existing = cart.value.find(item => item.id === product.id)
  if (existing) existing.qty++
  else cart.value.push({ ...product, qty: 1 })
  searchQuery.value = ''
}

// ==========================================
// FITUR BARU: KONTROL KUANTITAS KERANJANG
// ==========================================
const increaseQuantity = (item: any) => {
  item.qty++
}

const decreaseQuantity = (item: any) => {
  if (item.qty > 1) {
    item.qty--
  } else {
    // Hapus barang jika kuantitas dikurangi saat jumlahnya 1
    cart.value = cart.value.filter(i => i.id !== item.id)
  }
}

const updateQuantity = (item: any) => {
  if (item.qty <= 0 || !item.qty) {
    // Hapus barang jika user mengetik angka 0 atau kosong
    cart.value = cart.value.filter(i => i.id !== item.id)
  }
}
// ==========================================

const subtotal = computed(() => cart.value.reduce((total, item) => total + (item.price * item.qty), 0))
const taxAmount = computed(() => (subtotal.value * taxRate.value) / 100)
const grandTotal = computed(() => subtotal.value + taxAmount.value - discountRp.value)

// STATE PEMBAYARAN & LOKASI
const showPaymentModal = ref(false)
const currentBillingId = ref<number | null>(null)
const currentInvoiceNumber = ref<string>('')
const sisaTagihan = ref(0)
const formPayment = ref({ method_id: 1, amount: 0 })
const currentLocation = ref({ lat: '-', lng: '-' })

const kembalian = computed(() => {
  if (formPayment.value.method_id === 1 && formPayment.value.amount > sisaTagihan.value) {
    return formPayment.value.amount - sisaTagihan.value
  }
  return 0
})

// TOAST NOTIFICATION
const toast = ref({ show: false, message: '', type: 'success' })
let toastTimeout: any = null

const showToast = (message: string, type: 'success' | 'error' | 'warning' = 'success') => {
  toast.value = { show: true, message, type }
  if (toastTimeout) clearTimeout(toastTimeout)
  toastTimeout = setTimeout(() => { toast.value.show = false }, 4000) 
}

// LOKASI DIAM-DIAM: Ambil lokasi di background, jika gagal biarkan saja (tanpa error)
const fetchLocation = () => {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      (position) => {
        currentLocation.value.lat = position.coords.latitude.toString()
        currentLocation.value.lng = position.coords.longitude.toString()
      },
      (error) => {
        console.warn("Info: Akses lokasi untuk struk tidak tersedia.", error.message)
      }
    )
  }
}

// CETAK STRUK SPLIT BILL (REAL-TIME DI STRUK & STRUK TERPISAH BERDASARKAN URUTAN SPLIT)
const printReceipt = (paymentData: any) => {
  const printTime = new Date().toLocaleString('id-ID'); // Waktu Real-time saat struk dicetak

  let cartHtml = cart.value.map(item => `
    <tr>
      <td>${item.name}<br><small>${item.qty}x @ Rp ${item.price.toLocaleString('id-ID')}</small></td>
      <td style="text-align: right;">Rp ${(item.qty * item.price).toLocaleString('id-ID')}</td>
    </tr>
  `).join('')

  const html = `
    <html>
    <head>
      <title>Struk Pembayaran - ${paymentData.receipt_number}</title>
      <style>
        body { font-family: monospace; padding: 10px; font-size: 12px; color: #000; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        hr { border-top: 1px dashed #000; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 5px 0; }
        td { padding: 4px 0; vertical-align: top; }
        .box { border: 1px solid #000; padding: 10px; margin: 10px 0; border-radius: 4px; }
      </style>
    </head>
    <body>
      <h2 class="text-center" style="margin-bottom:5px;">KLINIK / APOTEK</h2>
      <div class="text-center">
        Waktu: ${printTime}<br>
        Lokasi (Lat,Lng): ${currentLocation.value.lat}, ${currentLocation.value.lng}<br>
        No. Nota: <strong>${paymentData.receipt_number}</strong>
      </div>
      <hr>
      <table>${cartHtml}</table>
      <hr>
      <table>
        <tr><td><strong>Total Keseluruhan</strong></td><td class="text-right"><strong>Rp ${paymentData.total_tagihan.toLocaleString('id-ID')}</strong></td></tr>
      </table>
      
      <!-- INI YANG MEMBUAT STRUK TERLIHAT BEDA UNTUK SETIAP SPLIT BILL -->
      <div class="box">
        <h3 class="text-center" style="margin: 0 0 10px 0; font-size: 13px;">BUKTI PEMBAYARAN (SPLIT KE-${paymentData.sequence})</h3>
        <table>
          <tr><td>Metode Bayar:</td><td class="text-right"><strong>${paymentData.method_name}</strong></td></tr>
          <tr><td>Nominal Masuk:</td><td class="text-right">Rp ${paymentData.amount_paid.toLocaleString('id-ID')}</td></tr>
          <tr><td>Kembalian:</td><td class="text-right">Rp ${paymentData.change_amount.toLocaleString('id-ID')}</td></tr>
        </table>
        <hr>
        <table>
          <tr><td style="font-size: 14px;"><strong>SISA TAGIHAN:</strong></td><td class="text-right" style="font-size: 14px;"><strong>Rp ${paymentData.sisa_tagihan.toLocaleString('id-ID')}</strong></td></tr>
        </table>
      </div>
      
      <p class="text-center">Terima Kasih</p>
    </body>
    </html>
  `

  const iframe = document.createElement('iframe')
  iframe.style.display = 'none'
  iframe.id = 'print-iframe-' + Date.now(); // Pastikan ID iframe unik untuk setiap kali print
  document.body.appendChild(iframe)

  const doc = iframe.contentWindow?.document || iframe.contentDocument
  if (doc) {
    doc.open()
    doc.write(html)
    doc.close()
  }

  setTimeout(() => {
    if (iframe.contentWindow) {
      iframe.contentWindow.focus()
      iframe.contentWindow.print()
    }
    // Hapus iframe setelah beberapa detik agar tidak menumpuk di memori
    setTimeout(() => { document.body.removeChild(iframe) }, 2000)
  }, 500)
}

const prosesTagihan = async () => {
  if (cart.value.length === 0) return
  fetchLocation() // Coba perbarui lokasi saat checkout
  
  const payload = {
    patient_id: selectedPatient.value.id,
    subtotal: subtotal.value,
    tax: taxAmount.value,
    discount: discountRp.value,
    total_amount: grandTotal.value,
    items: cart.value.map(item => ({ item_id: item.id, quantity: item.qty, price: item.price }))
  }

  try {
    const dataNota = await posStore.prosesCheckoutKasir(payload)
    currentBillingId.value = dataNota.billing_id || 999 
    currentInvoiceNumber.value = dataNota.invoice_number || ''
    sisaTagihan.value = grandTotal.value
    formPayment.value.amount = sisaTagihan.value 
    showPaymentModal.value = true
  } catch (error) {
    showToast('Gagal membuat tagihan. Periksa koneksi server.', 'error')
  }
}

const submitPayment = async () => {
  if (formPayment.value.amount <= 0) {
    showToast('Nominal pembayaran harus > 0', 'error')
    return
  }

  // Jika method Cash dan amount > sisa tagihan, gunakan sisa tagihan saja
  const uangDiakui = (formPayment.value.method_id === 1 && formPayment.value.amount > sisaTagihan.value) 
                     ? sisaTagihan.value : formPayment.value.amount

  // Validasi: amount pembayaran tidak boleh lebih besar dari sisa tagihan
  if (uangDiakui > sisaTagihan.value && formPayment.value.method_id !== 1) {
    showToast('Nominal pembayaran melebihi sisa tagihan!', 'error')
    return
  }

  const payload = {
    patient_billing_id: currentBillingId.value,
    payment_method_id: formPayment.value.method_id,
    amount_paid: uangDiakui,
    change_amount: kembalian.value
  }

  try {
    const dataBayar = await posStore.prosesPembayaran(payload)
    const sisaBaru = dataBayar.sisa_tagihan || 0
    sisaTagihan.value = sisaBaru

    // DEBUG LOG
    console.log('📊 Payment Response:', {
      sisa_tagihan: sisaBaru,
      payment_status: dataBayar.payment_status,
      amount_paid: uangDiakui,
      total_tagihan: dataBayar.receipt?.total_tagihan
    })

    if (dataBayar.receipt) {
      printReceipt(dataBayar.receipt)
    }

    // CEK APAKAH SUDAH LUNAS ATAU MASIH SPLIT
    if (sisaBaru <= 0) {
      // TRANSAKSI SELESAI - LUNAS ✅
      showToast(kembalian.value > 0 ? `✅ Pembayaran LUNAS! Kembalian: Rp ${kembalian.value.toLocaleString('id-ID')}` : '✅ Pembayaran LUNAS!', 'success')
      
      // Tunggu 2 detik biar struk sempat di-print
      setTimeout(() => {
        showPaymentModal.value = false
        cart.value = [] 
        taxRate.value = 0
        discountRp.value = 0
        selectedPatient.value = patientsList.value[0]
        formPayment.value = { method_id: 1, amount: 0 }
      }, 2000)
    } else {
      // MASIH ADA SISA - SPLIT PAYMENT 🔄
      showToast(`⚠️ Pembayaran Parsial sukses! Sisa: Rp ${sisaBaru.toLocaleString('id-ID')}`, 'warning')
      
      // Reset form untuk pembayaran berikutnya
      formPayment.value.method_id = 1
      formPayment.value.amount = sisaBaru
      
      // Beri opsi lanjut bayar vs tutup
      console.log('🔄 Split Payment Mode - Tunggu pembayaran berikutnya')
    }
  } catch (error) {
    console.error('Payment Error:', error)
    showToast('❌ Gagal memproses pembayaran. Cek server backend.', 'error') 
  }
}

const handleKeydown = (e: KeyboardEvent) => {
  if (e.key === 'Escape' && showPaymentModal.value) {
    showPaymentModal.value = false
  }
}

onMounted(async () => {
  await posStore.fetchPatients()
  await posStore.fetchProducts()
  selectedPatient.value = patientsList.value[0]
  fetchLocation() 
  window.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <main class="min-h-screen bg-slate-50 p-3 sm:p-4 lg:p-8 relative font-sans flex flex-col">
    <!-- TOAST NOTIFICATION -->
    <Transition name="toast">
      <div v-if="toast.show" 
           class="fixed top-4 sm:top-6 left-1/2 transform -translate-x-1/2 z-[100] px-4 sm:px-6 py-2 sm:py-3 rounded-full shadow-2xl font-bold flex items-center gap-2 sm:gap-3 text-xs sm:text-sm tracking-wide w-11/12 sm:w-auto justify-center"
           :class="{ 'bg-teal-600 text-white': toast.type === 'success', 'bg-red-600 text-white': toast.type === 'error', 'bg-yellow-500 text-white': toast.type === 'warning' }">
        <svg v-if="toast.type === 'success'" class="w-4 h-4 sm:w-5 sm:h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
        <svg v-else-if="toast.type === 'error'" class="w-4 h-4 sm:w-5 sm:h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <svg v-else class="w-4 h-4 sm:w-5 sm:h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        <span class="truncate">{{ toast.message }}</span>
      </div>
    </Transition>

    <!-- PILIH PASIEN -->
    <div class="bg-white p-3 sm:p-5 rounded-2xl shadow-sm border border-slate-200 mb-4 lg:mb-6 flex items-center gap-3 sm:gap-5 flex-shrink-0">
      <div class="bg-teal-100 p-2 sm:p-3 rounded-full text-teal-600 flex-shrink-0">
        <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
      </div>
      <div class="w-full max-w-lg">
        <label class="block text-[10px] sm:text-xs font-bold text-slate-500 uppercase tracking-widest mb-1">Registrasi Pasien</label>
        <select v-model="selectedPatient" class="w-full border-b-2 border-slate-200 bg-transparent text-slate-800 font-bold text-base sm:text-lg p-1 focus:border-teal-500 focus:outline-none transition-colors cursor-pointer truncate">
          <option v-for="p in patientsList" :key="p.id || 0" :value="p">{{ p.mrn }} - {{ p.name }}</option>
        </select>
      </div>
    </div>

    <!-- GRID UTAMA RESPONSIF -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-8 flex-1">
      <!-- KIRI: PRODUK & SEARCH BAR -->
      <div class="lg:col-span-2 bg-white p-4 sm:p-6 rounded-2xl sm:rounded-3xl shadow-sm border border-slate-200 flex flex-col h-[55vh] lg:h-[75vh]">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 sm:mb-6 gap-3 sm:gap-4 border-b border-slate-100 pb-3 sm:pb-4 flex-shrink-0">
          <h2 class="text-lg sm:text-xl font-extrabold text-slate-800 flex items-center gap-2 whitespace-nowrap">
            <span class="w-1.5 sm:w-2 h-5 sm:h-6 bg-teal-500 rounded-full"></span> Farmasi & Jasa
          </h2>
          <div class="relative w-full sm:max-w-sm">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <svg class="h-4 w-4 sm:h-5 sm:w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
            <input v-model="searchQuery" type="text" placeholder="Cari nama / barcode..." autofocus class="w-full pl-9 sm:pl-10 pr-3 sm:pr-4 py-2 border-2 border-slate-200 rounded-lg sm:rounded-xl font-bold text-xs sm:text-sm focus:border-teal-500 focus:ring-0 transition-colors bg-slate-50 focus:bg-white text-slate-700">
          </div>
        </div>
        <!-- Daftar Produk -->
        <div class="flex-1 overflow-y-auto custom-scrollbar pr-1 sm:pr-2">
          <div v-if="filteredProducts.length === 0" class="flex flex-col items-center justify-center h-full text-slate-400 py-10 text-xs sm:text-sm">
            <span class="font-bold">Item tidak ditemukan</span>
          </div>
          <div v-else class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-2 sm:gap-4 pb-4">
            <div v-for="item in filteredProducts" :key="item.id" @click="addToCart(item)" class="group border border-slate-200 p-3 sm:p-4 rounded-xl sm:rounded-2xl cursor-pointer hover:border-teal-500 hover:shadow-lg hover:bg-teal-50 active:scale-95 transition-all flex flex-col justify-between min-h-[100px] sm:min-h-[120px]">
              <div>
                <span :class="item.category === 'obat' ? 'bg-orange-100 text-orange-700' : 'bg-blue-100 text-blue-700'" class="text-[8px] sm:text-[10px] px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-md font-bold uppercase tracking-wider line-clamp-1">{{ item.category }}</span>
                <div class="font-bold text-slate-700 text-xs sm:text-sm mt-1.5 sm:mt-2 leading-tight group-hover:text-teal-800 line-clamp-2">{{ item.name }}</div>
              </div>
              <div class="text-teal-600 font-black text-sm sm:text-base mt-2 sm:mt-3">Rp {{ item.price.toLocaleString('id-ID') }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- KANAN: KERANJANG -->
      <div class="bg-white p-4 sm:p-6 rounded-2xl sm:rounded-3xl shadow-sm border border-slate-200 flex flex-col h-[60vh] lg:h-[75vh]">
        <h2 class="text-lg sm:text-xl font-extrabold text-slate-800 mb-3 sm:mb-4 border-b border-slate-100 pb-3 sm:pb-4 flex-shrink-0">Rincian Tagihan</h2>
        <div class="flex-1 overflow-y-auto mb-3 sm:mb-4 space-y-2 sm:space-y-3 pr-1 sm:pr-2 custom-scrollbar">
          <div v-if="cart.length === 0" class="flex flex-col items-center justify-center h-full text-slate-400">
            <svg class="w-10 h-10 sm:w-16 sm:h-16 mb-2 sm:mb-4 text-slate-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
            <span class="font-medium text-xs sm:text-sm text-center">Keranjang masih kosong</span>
          </div>
          <div v-for="(item, index) in cart" :key="index" class="flex justify-between items-center bg-slate-50 p-2 sm:p-3 rounded-lg sm:rounded-xl border border-slate-100">
            
            <!-- PERUBAHAN TAMPILAN KERANJANG DI SINI -->
            <div class="flex-1 min-w-0 pr-2">
              <div class="font-bold text-slate-700 text-xs sm:text-sm truncate">{{ item.name }}</div>
              <div class="flex items-center gap-2 mt-1.5">
                <button @click="decreaseQuantity(item)" class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 font-bold text-white transition-colors bg-red-500 rounded hover:bg-red-600 focus:outline-none">-</button>
                <input type="number" v-model.number="item.qty" @change="updateQuantity(item)" class="w-12 h-6 sm:h-7 text-center bg-gray-100 border border-gray-300 rounded focus:outline-none focus:border-teal-500 text-xs sm:text-sm font-semibold" min="1">
                <button @click="increaseQuantity(item)" class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 font-bold text-white transition-colors bg-teal-500 rounded hover:bg-teal-600 focus:outline-none">+</button>
                <span class="text-[10px] sm:text-xs font-semibold text-slate-500 ml-1">@ Rp {{ item.price.toLocaleString('id-ID') }}</span>
              </div>
            </div>
            <!-- AKHIR PERUBAHAN -->

            <div class="font-black text-slate-800 text-sm sm:text-base whitespace-nowrap">Rp {{ (item.qty * item.price).toLocaleString('id-ID') }}</div>
          </div>
        </div>

        <!-- KALKULASI -->
        <div class="border-t border-slate-200 pt-3 sm:pt-5 space-y-2 sm:space-y-3 text-xs sm:text-sm font-bold text-slate-500 flex-shrink-0">
          <div class="flex justify-between items-center"><span>Subtotal</span><span class="text-slate-800">Rp {{ subtotal.toLocaleString('id-ID') }}</span></div>
          <div class="flex justify-between items-center">
            <span>Pajak PPN</span>
            <select v-model="taxRate" class="border border-slate-300 rounded-md sm:rounded-lg p-1 sm:p-1.5 w-20 sm:w-24 text-right bg-slate-50 focus:ring-2 focus:ring-teal-500"><option :value="0">0%</option><option :value="11">11%</option></select>
          </div>
          <div class="flex justify-between items-center">
            <span>Diskon (Rp)</span>
            <input type="number" v-model="discountRp" class="border border-slate-300 rounded-md sm:rounded-lg p-1 sm:p-1.5 w-24 sm:w-32 text-right bg-slate-50 focus:ring-2 focus:ring-teal-500">
          </div>
        </div>

        <div class="border-t border-slate-200 pt-3 sm:pt-5 mt-3 sm:mt-5 flex-shrink-0">
          <div class="flex justify-between items-end mb-4 sm:mb-6">
            <span class="text-[10px] sm:text-xs font-black text-slate-400 uppercase tracking-widest pb-1">Grand Total</span>
            <span class="text-2xl sm:text-3xl lg:text-4xl font-black text-teal-600 leading-none truncate pl-2">Rp {{ grandTotal.toLocaleString('id-ID') }}</span>
          </div>
          <button @click="prosesTagihan" :disabled="cart.length === 0" class="w-full bg-teal-600 hover:bg-teal-700 active:bg-teal-800 disabled:bg-slate-300 text-white font-black tracking-widest uppercase py-3 sm:py-4 rounded-xl sm:rounded-2xl shadow-lg transition-all text-xs sm:text-sm">
            Proses Tagihan
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL PEMBAYARAN -->
    <div v-if="showPaymentModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-end sm:items-center justify-center z-50 p-0 sm:p-4">
      <div class="bg-white w-full max-w-md rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden border border-slate-100 max-h-[90vh] flex flex-col">
        <div class="bg-teal-600 p-4 sm:p-5 text-center relative flex-shrink-0">
          <h3 class="text-white font-black tracking-widest uppercase text-xs sm:text-sm">Penyelesaian Pembayaran</h3>
          <button @click="showPaymentModal = false" class="absolute top-3 sm:top-4 right-4 sm:right-5 text-teal-200 hover:text-white font-black text-lg sm:text-xl">&times;</button>
        </div>

        <div class="p-5 sm:p-8 space-y-4 sm:space-y-6 overflow-y-auto">
          <!-- TOTAL TAGIHAN ASLI -->
          <div class="text-center bg-slate-50 p-4 rounded-xl border border-slate-200">
            <div class="text-[10px] sm:text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Total Tagihan Asli</div>
            <div class="text-2xl sm:text-3xl font-black text-slate-700">Rp {{ sisaTagihan.toLocaleString('id-ID') }}</div>
          </div>

          <!-- SISA PEMBAYARAN (HIGHLIGHT) - Hanya muncul jika sudah ada pembayaran partial -->
          <div v-if="sisaTagihan < grandTotal" class="text-center bg-gradient-to-r from-orange-50 to-red-50 p-5 rounded-2xl border-2 border-orange-300">
            <div class="text-[10px] sm:text-xs font-bold text-orange-600 uppercase tracking-widest mb-2">⚠️ Sisa Pembayaran</div>
            <div class="text-4xl sm:text-5xl font-black text-orange-700">Rp {{ sisaTagihan.toLocaleString('id-ID') }}</div>
          </div>

          <div class="space-y-4 sm:space-y-5">
            <div>
              <label class="block text-[10px] sm:text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5 sm:mb-2">Metode Bayar</label>
              <!-- HANYA CASH & QRIS/DEBIT -->
              <select v-model="formPayment.method_id" class="w-full border-2 border-slate-200 rounded-lg sm:rounded-xl p-2.5 sm:p-3 font-bold text-slate-700 focus:border-teal-500 focus:ring-0 text-sm sm:text-base">
                <option :value="1">💵 Uang Tunai (Cash)</option>
                <option :value="2">📱 QRIS / E-Wallet</option>
                <option :value="3">💳 Kartu Debit / Kredit</option>
              </select>
            </div>
            <div>
              <label class="block text-[10px] sm:text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5 sm:mb-2">Nominal Diterima (Rp)</label>
              <input type="number" v-model="formPayment.amount" class="w-full border-2 border-slate-200 rounded-lg sm:rounded-xl p-3 sm:p-4 text-xl sm:text-2xl font-black text-slate-800 focus:border-teal-500 text-center" @keyup.enter="submitPayment">
            </div>
            <div v-if="kembalian > 0" class="bg-orange-50 text-orange-700 p-3 sm:p-4 rounded-lg sm:rounded-xl border border-orange-200 flex justify-between items-center transition-all">
              <span class="font-bold text-xs sm:text-sm uppercase tracking-widest">Kembalian</span>
              <span class="font-black text-lg sm:text-xl">Rp {{ kembalian.toLocaleString('id-ID') }}</span>
            </div>
            
            <!-- INFO SPLIT PAYMENT JIKA BERLANGSUNG -->
            <div v-if="sisaTagihan < (cart.reduce((sum, item) => sum + (item.price * item.qty), 0))" class="bg-blue-50 p-3 sm:p-4 rounded-xl border border-blue-200">
              <div class="flex items-start gap-2">
                <span class="text-lg">🔄</span>
                <div class="text-xs sm:text-sm text-blue-700 font-bold">
                  <div>Mode Split Payment Aktif</div>
                  <div class="text-[10px] mt-1 opacity-75">Anda bisa melanjutkan pembayaran dengan metode berbeda</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="p-4 sm:p-5 border-t border-slate-100 bg-slate-50 flex gap-3 sm:gap-4 flex-shrink-0">
          <button @click="showPaymentModal = false" class="w-1/3 py-3 sm:py-4 font-bold border-2 border-slate-200 rounded-xl sm:rounded-2xl bg-white text-slate-500 hover:bg-slate-50 uppercase tracking-wider text-[10px] sm:text-xs transition-colors">Tutup</button>
          <button @click="submitPayment" class="w-2/3 py-3 sm:py-4 font-black text-white bg-teal-500 hover:bg-teal-600 rounded-xl sm:rounded-2xl shadow-lg uppercase tracking-wider text-xs sm:text-sm transition-all transform active:scale-95">Konfirmasi Bayar</button>
        </div>
      </div>
    </div>
  </main>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
@media (min-width: 640px) { .custom-scrollbar::-webkit-scrollbar { width: 6px; } }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
.toast-enter-active, .toast-leave-active { transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
.toast-enter-from { opacity: 0; transform: translate(-50%, -20px); }
.toast-leave-to { opacity: 0; transform: translate(-50%, -20px); }
</style>