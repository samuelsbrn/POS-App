<script setup lang="ts">
import { ref, computed } from 'vue'
import axios from 'axios'

// --- STATE DATA ---
const products = ref([
  { id: 1, name: 'Konsultasi Dokter Umum', price: 100000 },
  { id: 2, name: 'Cek Darah Lengkap', price: 250000 },
  { id: 3, name: 'Vitamin C 1000mg', price: 50000 },
  { id: 4, name: 'Suntik Vitamin', price: 150000 },
  { id: 5, name: 'Obat Paracetamol', price: 15000 },
  { id: 6, name: 'Jasa Pemasangan Perban', price: 75000 }
])

const cart = ref<any[]>([])

// --- STATE PEMBAYARAN ---
const showPaymentModal = ref(false)
const currentBillingId = ref<number | null>(null)
const sisaTagihan = ref(0)
const formPayment = ref({
  method_id: 1,
  amount: 0
})

const addToCart = (product: any) => {
  const existing = cart.value.find(item => item.id === product.id)
  if (existing) {
    existing.qty++
  } else {
    cart.value.push({ ...product, qty: 1 })
  }
}

const totalAmount = computed(() => {
  return cart.value.reduce((total, item) => total + (item.price * item.qty), 0)
})

// --- FUNGSI 1: BUAT INVOICE ---
const prosesTagihan = async () => {
  if (cart.value.length === 0) return

  const payload = {
    patient_id: 1,
    subtotal: totalAmount.value,
    tax: 0,
    total_amount: totalAmount.value,
    items: cart.value.map(item => ({
      item_id: item.id,
      quantity: item.qty,
      price: item.price
    }))
  }

  try {
    const response = await axios.post('http://localhost:8000/billing/create', payload)
    
    currentBillingId.value = response.data.billing_id
    sisaTagihan.value = totalAmount.value
    formPayment.value.amount = sisaTagihan.value 
    
    showPaymentModal.value = true
    
  } catch (error: any) {
    console.error(error)
    alert(' Gagal membuat tagihan.')
  }
}

// --- FUNGSI 2: CATAT UANG MASUK (SPLIT PAYMENT) ---
const submitPayment = async () => {
  if (!currentBillingId.value || formPayment.value.amount <= 0) return

  const payload = {
    patient_billing_id: currentBillingId.value,
    payment_method_id: formPayment.value.method_id,
    amount_paid: formPayment.value.amount
  }

  try {
    const response = await axios.post('http://localhost:8000/billing/payment', payload)
    
    sisaTagihan.value = response.data.sisa_tagihan

    if (response.data.payment_status === 'Paid') {
      alert(' Pembayaran LUNAS!')
      showPaymentModal.value = false
      cart.value = [] 
    } else {
      alert(`Pembayaran parsial berhasil! Sisa tagihan: Rp ${sisaTagihan.value.toLocaleString('id-ID')}`)
      formPayment.value.amount = sisaTagihan.value 
    }
    
  } catch (error: any) {
    console.error(error)
    alert(' Gagal memproses pembayaran.')
  }
}
</script>

<template>
  <main class="min-h-screen bg-gray-100 p-8 relative">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      
      <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
        <h2 class="text-xl font-bold text-gray-800 mb-6">Daftar Layanan & Obat</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
          <div 
            v-for="item in products" :key="item.id"
            @click="addToCart(item)"
            class="border-2 border-gray-100 p-4 rounded-xl cursor-pointer hover:border-blue-500 hover:shadow-md hover:bg-blue-50 active:scale-95 transition-all duration-200 group flex flex-col justify-between h-32"
          >
            <div class="font-semibold text-gray-700 group-hover:text-blue-800 leading-tight">{{ item.name }}</div>
            <div class="text-blue-600 font-bold mt-2">Rp {{ item.price.toLocaleString('id-ID') }}</div>
          </div>
        </div>
      </div>

      <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 flex flex-col h-[70vh]">
        <h2 class="text-xl font-bold text-gray-800 mb-4 border-b border-gray-100 pb-4">Keranjang Belanja</h2>
        
        <div class="flex-1 overflow-y-auto mb-4 pr-2 space-y-3 custom-scrollbar">
          <div v-if="cart.length === 0" class="flex flex-col items-center justify-center h-full text-gray-400">
            <span>Keranjang masih kosong</span>
          </div>
          
          <div v-else v-for="(item, index) in cart" :key="index" class="flex justify-between items-center bg-gray-50 p-3 rounded-lg border border-gray-100 shadow-sm">
            <div>
              <div class="font-bold text-gray-800">{{ item.name }}</div>
              <div class="text-sm font-medium text-gray-500">{{ item.qty }}x @ Rp {{ item.price.toLocaleString('id-ID') }}</div>
            </div>
            <div class="font-bold text-blue-700">
              Rp {{ (item.qty * item.price).toLocaleString('id-ID') }}
            </div>
          </div>
        </div>

        <div class="border-t border-gray-200 pt-6 mt-auto">
          <div class="flex justify-between items-center mb-6">
            <span class="text-lg font-bold text-gray-500 uppercase tracking-wide">Total Tagihan</span>
            <span class="text-3xl font-black text-blue-600">Rp {{ totalAmount.toLocaleString('id-ID') }}</span>
          </div>
          <button 
            @click="prosesTagihan"
            :disabled="cart.length === 0"
            class="w-full bg-blue-600 hover:bg-blue-700 active:bg-blue-800 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-bold py-4 px-4 rounded-xl shadow-lg hover:shadow-xl transition-all"
          >
            Buat Tagihan & Bayar
          </button>
        </div>
      </div>

    </div>

    <div v-if="showPaymentModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
      <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden animate-fade-in-up">
        
        <div class="bg-blue-600 p-4 flex justify-between items-center">
          <h3 class="text-white font-bold text-lg">Penyelesaian Pembayaran</h3>
          <button @click="showPaymentModal = false" class="text-white hover:text-gray-200 font-bold text-xl">&times;</button>
        </div>

        <div class="p-6 space-y-6">
          <div class="text-center bg-blue-50 p-4 rounded-xl border border-blue-100">
            <div class="text-sm text-gray-500 font-medium mb-1">SISA TAGIHAN</div>
            <div class="text-4xl font-black text-blue-700">Rp {{ sisaTagihan.toLocaleString('id-ID') }}</div>
          </div>

          <div class="space-y-4">
            <div>
              <label class="block text-sm font-bold text-gray-700 mb-2">Metode Pembayaran</label>
              <select v-model="formPayment.method_id" class="w-full border-2 border-gray-200 rounded-lg p-3 focus:border-blue-500 focus:outline-none transition-colors">
                <option :value="1">💵 Tunai (Cash)</option>
                <option :value="2">📱 QRIS</option>
                <option :value="3">💳 Transfer Bank</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-bold text-gray-700 mb-2">Jumlah Uang Diterima (Rp)</label>
              <input type="number" v-model="formPayment.amount" class="w-full border-2 border-gray-200 rounded-lg p-3 text-xl font-bold text-gray-800 focus:border-blue-500 focus:outline-none transition-colors">
            </div>
          </div>
        </div>

        <div class="p-4 border-t border-gray-100 bg-gray-50 flex gap-3">
          <button @click="showPaymentModal = false" class="w-1/3 py-3 font-bold text-gray-600 bg-white border border-gray-300 rounded-xl hover:bg-gray-100 transition-colors">Batal</button>
          <button @click="submitPayment" class="w-2/3 py-3 font-bold text-white bg-green-500 hover:bg-green-600 rounded-xl shadow-lg hover:shadow-xl transition-all">Bayar Sekarang</button>
        </div>

      </div>
    </div>
  </main>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

@keyframes fadeInUp {
  from { opacity: 0; transform: translateY(20px) scale(0.95); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}
.animate-fade-in-up { animation: fadeInUp 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
</style>