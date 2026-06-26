<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { usePosStore } from '../stores/posStore' // <-- 1. Import Store
import { storeToRefs } from 'pinia'

// 2. Inisialisasi Store
const posStore = usePosStore()
// 3. Ambil data dari Store agar reaktif
const { patients, products } = storeToRefs(posStore) 

// Susun daftar dropdown pasien (tambahkan opsi Umum di paling atas)
const patientsList = computed(() => {
  return [{ id: null, mrn: 'UMUM', name: 'Pasien Umum / Walk-in' }, ...patients.value]
})

const selectedPatient = ref(patientsList.value[0])
const cart = ref<any[]>([])

// State Pajak & Diskon
const taxRate = ref(0)
const discountRp = ref(0)

const addToCart = (product: any) => {
  const existing = cart.value.find(item => item.id === product.id)
  if (existing) existing.qty++
  else cart.value.push({ ...product, qty: 1 })
}

const subtotal = computed(() => cart.value.reduce((total, item) => total + (item.price * item.qty), 0))
const taxAmount = computed(() => (subtotal.value * taxRate.value) / 100)
const grandTotal = computed(() => subtotal.value + taxAmount.value - discountRp.value)

// State Modal Pembayaran
const showPaymentModal = ref(false)
const currentBillingId = ref<number | null>(null)
const sisaTagihan = ref(0)
const formPayment = ref({ method_id: 1, amount: 0 })

const kembalian = computed(() => {
  if (formPayment.value.method_id === 1 && formPayment.value.amount > sisaTagihan.value) {
    return formPayment.value.amount - sisaTagihan.value
  }
  return 0
})

const prosesTagihan = async () => {
  if (cart.value.length === 0) return
  
  const payload = {
    patient_id: selectedPatient.value.id,
    subtotal: subtotal.value,
    tax: taxAmount.value,
    discount: discountRp.value,
    total_amount: grandTotal.value,
    items: cart.value.map(item => ({ item_id: item.id, quantity: item.qty, price: item.price }))
  }

  try {
    const response = await axios.post('http://localhost:8000/billing/create', payload)
    currentBillingId.value = response.data.billing_id
    sisaTagihan.value = grandTotal.value
    formPayment.value.amount = sisaTagihan.value 
    showPaymentModal.value = true
  } catch (error) {
    alert('Gagal membuat tagihan. Pastikan Backend Phalcon aktif.')
  }
}

const submitPayment = async () => {
  if (formPayment.value.amount <= 0) return

  const uangDiakui = (formPayment.value.method_id === 1 && formPayment.value.amount > sisaTagihan.value) 
                     ? sisaTagihan.value : formPayment.value.amount

  const payload = {
    patient_billing_id: currentBillingId.value,
    payment_method_id: formPayment.value.method_id,
    amount_paid: uangDiakui,
    change_amount: kembalian.value
  }

  try {
    const response = await axios.post('http://localhost:8000/billing/payment', payload)
    sisaTagihan.value = response.data.sisa_tagihan

    if (sisaTagihan.value <= 0) {
      alert(`🎉 Pembayaran LUNAS!\n\nKembalian Pasien: Rp ${kembalian.value.toLocaleString('id-ID')}`)
      showPaymentModal.value = false
      cart.value = [] 
      taxRate.value = 0
      discountRp.value = 0
      selectedPatient.value = patientsList.value[0] 
    } else {
      alert(`Pembayaran parsial berhasil! Sisa tagihan: Rp ${sisaTagihan.value.toLocaleString('id-ID')}`)
      formPayment.value.amount = sisaTagihan.value 
    }
  } catch (error) {
    alert('Gagal memproses pembayaran.')
  }
}

// 4. Panggil data dari Store saat halaman dimuat
onMounted(async () => {
  await posStore.fetchPatients()
  await posStore.fetchProducts()
  // Pastikan default selectedPatient tersetting ulang setelah data masuk
  selectedPatient.value = patientsList.value[0]
})
</script>

<template>
  <main class="min-h-screen bg-gray-100 p-8 relative">
    
    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-6 flex items-center gap-4">
      <label class="font-bold text-gray-700 whitespace-nowrap">Melayani Pasien:</label>
      <select v-model="selectedPatient" class="w-full max-w-md border-2 border-blue-200 bg-blue-50 text-blue-800 font-bold rounded-lg p-2 focus:outline-none">
        <option v-for="p in patientsList" :key="p.id || 0" :value="p">
          {{ p.mrn }} - {{ p.name }}
        </option>
      </select>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Daftar Layanan & Obat</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
          <div v-for="item in products" :key="item.id" @click="addToCart(item)" class="border-2 border-gray-100 p-4 rounded-xl cursor-pointer hover:border-blue-500 hover:shadow-md hover:bg-blue-50 active:scale-95 transition-all">
            <div class="font-semibold text-gray-700 leading-tight">{{ item.name }}</div>
            <div class="text-blue-600 font-bold mt-2">Rp {{ item.price.toLocaleString('id-ID') }}</div>
          </div>
        </div>
      </div>

      <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 flex flex-col h-[70vh]">
        <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-4">Keranjang Belanja</h2>
        
        <div class="flex-1 overflow-y-auto mb-4 space-y-3">
          <div v-if="cart.length === 0" class="text-center text-gray-400 mt-10">Keranjang kosong</div>
          <div v-for="(item, index) in cart" :key="index" class="flex justify-between items-center bg-gray-50 p-3 rounded-lg border">
            <div>
              <div class="font-bold text-gray-800">{{ item.name }}</div>
              <div class="text-sm text-gray-500">{{ item.qty }}x @ Rp {{ item.price.toLocaleString('id-ID') }}</div>
            </div>
            <div class="font-bold text-blue-700">Rp {{ (item.qty * item.price).toLocaleString('id-ID') }}</div>
          </div>
        </div>

        <div class="border-t pt-4 space-y-2 text-sm font-bold text-gray-600">
          <div class="flex justify-between"><span>Subtotal:</span><span>Rp {{ subtotal.toLocaleString('id-ID') }}</span></div>
          <div class="flex justify-between items-center">
            <span>Pajak (%):</span>
            <select v-model="taxRate" class="border rounded p-1 w-20 text-right"><option :value="0">0%</option><option :value="11">11%</option></select>
          </div>
          <div class="flex justify-between items-center">
            <span>Diskon (Rp):</span>
            <input type="number" v-model="discountRp" class="border rounded p-1 w-24 text-right">
          </div>
        </div>

        <div class="border-t border-gray-200 pt-4 mt-4">
          <div class="flex justify-between items-center mb-4">
            <span class="text-lg font-bold text-gray-500">GRAND TOTAL</span>
            <span class="text-3xl font-black text-blue-600">Rp {{ grandTotal.toLocaleString('id-ID') }}</span>
          </div>
          <button @click="prosesTagihan" :disabled="cart.length === 0" class="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 text-white font-bold py-4 rounded-xl shadow-lg transition-all">
            Buat Tagihan & Bayar
          </button>
        </div>
      </div>
    </div>

    <div v-if="showPaymentModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
      <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden">
        <div class="bg-blue-600 p-4 flex justify-between items-center">
          <h3 class="text-white font-bold">Penyelesaian Pembayaran</h3>
          <button @click="showPaymentModal = false" class="text-white font-bold text-xl">&times;</button>
        </div>

        <div class="p-6 space-y-4">
          <div class="text-center bg-blue-50 p-4 rounded-xl border border-blue-100 mb-4">
            <div class="text-sm text-gray-500 font-medium">SISA TAGIHAN</div>
            <div class="text-4xl font-black text-blue-700">Rp {{ sisaTagihan.toLocaleString('id-ID') }}</div>
          </div>

          <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">Metode Pembayaran</label>
            <select v-model="formPayment.method_id" class="w-full border-2 rounded-lg p-3">
              <option :value="1">💵 Tunai (Cash)</option>
              <option :value="2">📱 QRIS</option>
              <option :value="3">💳 Asuransi / Transfer</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">Jumlah Uang Diterima (Rp)</label>
            <input type="number" v-model="formPayment.amount" class="w-full border-2 rounded-lg p-3 text-xl font-bold">
          </div>

          <div v-if="kembalian > 0" class="bg-green-100 text-green-800 p-3 rounded-lg border border-green-300 flex justify-between font-bold">
            <span>Kembalian:</span>
            <span>Rp {{ kembalian.toLocaleString('id-ID') }}</span>
          </div>
        </div>

        <div class="p-4 border-t flex gap-3">
          <button @click="showPaymentModal = false" class="w-1/3 py-3 font-bold border rounded-xl bg-white text-gray-600">Batal</button>
          <button @click="submitPayment" class="w-2/3 py-3 font-bold text-white bg-green-500 hover:bg-green-600 rounded-xl">Bayar Sekarang</button>
        </div>
      </div>
    </div>
  </main>
</template>