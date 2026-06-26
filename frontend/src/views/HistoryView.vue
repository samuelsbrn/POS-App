<script setup lang="ts">
import { ref, onMounted } from 'vue'
import axios from 'axios'

// --- STATE DATA ---
const historyList = ref<any[]>([])
const isLoading = ref(false)

// --- STATE MODAL STRUK ---
const showReceiptModal = ref(false)
const selectedReceipt = ref<any>(null)
const isLoadingReceipt = ref(false)

// 1. AMBIL DAFTAR SEMUA TRANSAKSI
const fetchHistoryList = async () => {
  isLoading.value = true
  try {
    // Sesuaikan dengan endpoint Phalcon yang menampilkan seluruh invoice
    const response = await axios.get('http://localhost:8000/billing/history')
    historyList.value = response.data.data
  } catch (error) {
    console.error('Gagal mengambil daftar riwayat, menggunakan data simulasi.', error)
    // Simulasi jika backend belum siap
    historyList.value = [
      { id: 1, invoice_number: 'ZC-POS/2026/06/00001', created_at: new Date().toISOString(), patient_name: 'Budi Santoso', total_amount: 150000, payment_status: 'Paid' },
      { id: 2, invoice_number: 'ZC-POS/2026/06/00002', created_at: new Date(Date.now() - 3600000).toISOString(), patient_name: 'Siti Aminah', total_amount: 250000, payment_status: 'Partially Paid' },
      { id: 3, invoice_number: 'ZC-POS/2026/06/00003', created_at: new Date(Date.now() - 7200000).toISOString(), patient_name: 'Walk-in Customer', total_amount: 50000, payment_status: 'Unpaid' }
    ]
  } finally {
    isLoading.value = false
  }
}

// 2. AMBIL DETAIL 1 TRANSAKSI UNTUK DICETAK
const printReceipt = async (invoiceId: number) => {
  showReceiptModal.value = true
  isLoadingReceipt.value = true
  selectedReceipt.value = null

  try {
    // Tembak ID yang spesifik untuk mengambil detail item belanjanya
    const response = await axios.get(`http://localhost:8000/billing/history?id=${invoiceId}`)
    selectedReceipt.value = response.data.data
  } catch (error) {
    // Simulasi detail struk jika API belum siap
    setTimeout(() => {
      const inv = historyList.value.find(i => i.id === invoiceId)
      selectedReceipt.value = {
        ...inv,
        subtotal: inv.total_amount,
        tax: 0,
        items: [{ item_name: 'Layanan / Obat (Simulasi)', quantity: 1, price: inv.total_amount }]
      }
      isLoadingReceipt.value = false
    }, 500)
  }
}

const formatRp = (value: number) => {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value || 0)
}

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' })
}

onMounted(() => {
  fetchHistoryList()
})
</script>

<template>
  <main class="min-h-screen bg-gray-100 p-8 relative">
    <div class="max-w-7xl mx-auto bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
      
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Riwayat Transaksi (Invoices)</h2>
        <button @click="fetchHistoryList" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded-lg flex items-center gap-2 transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
          Refresh Data
        </button>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-gray-50 text-gray-600 border-b-2 border-gray-200">
              <th class="p-4 font-bold">No. Nota & Waktu</th>
              <th class="p-4 font-bold">Pasien</th>
              <th class="p-4 font-bold">Total Tagihan</th>
              <th class="p-4 font-bold text-center">Status</th>
              <th class="p-4 font-bold text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="isLoading">
              <td colspan="5" class="p-8 text-center text-gray-500 font-medium">Memuat riwayat transaksi...</td>
            </tr>
            <tr v-else v-for="(inv, index) in historyList" :key="index" class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
              <td class="p-4">
                <div class="font-bold text-blue-700">{{ inv.invoice_number }}</div>
                <div class="text-xs text-gray-500">{{ formatDate(inv.created_at) }}</div>
              </td>
              <td class="p-4 font-semibold text-gray-800">{{ inv.patient_name }}</td>
              <td class="p-4 font-bold text-gray-800">{{ formatRp(inv.total_amount) }}</td>
              <td class="p-4 text-center">
                <span 
                  class="px-3 py-1 rounded-full text-xs font-black tracking-wider uppercase border"
                  :class="{
                    'bg-green-100 text-green-700 border-green-300': inv.payment_status === 'Paid',
                    'bg-yellow-100 text-yellow-700 border-yellow-300': inv.payment_status === 'Partially Paid',
                    'bg-red-100 text-red-700 border-red-300': inv.payment_status === 'Unpaid',
                    'bg-gray-100 text-gray-700 border-gray-300': inv.payment_status === 'Void'
                  }"
                >
                  {{ inv.payment_status }}
                </span>
              </td>
              <td class="p-4 flex justify-center">
                <button @click="printReceipt(inv.id)" class="bg-gray-800 hover:bg-black text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2 shadow transition-all">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                  Lihat Struk
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="showReceiptModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 p-4">
      
      <div class="bg-white p-8 rounded-sm shadow-2xl border-t-8 border-gray-800 max-w-sm w-full relative print-area">
        
        <button @click="showReceiptModal = false" class="absolute top-2 right-2 text-gray-400 hover:text-red-500 font-black text-2xl no-print">&times;</button>
        
        <div v-if="isLoadingReceipt" class="flex flex-col items-center py-10">
          <svg class="animate-spin h-8 w-8 text-gray-800 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
          <span class="font-mono text-sm text-gray-500">Mencari data struk...</span>
        </div>

        <div v-else-if="selectedReceipt">
          <div class="text-center mb-6">
            <h1 class="text-3xl font-black text-gray-900 tracking-widest">POS KLINIK</h1>
            <p class="text-xs text-gray-500 mt-1 font-mono">Jl. Kesehatan No. 123, Laguboti</p>
          </div>

          <div class="border-b-2 border-dashed border-gray-300 mb-4"></div>

          <div class="mb-4 text-xs text-gray-700 font-mono space-y-1">
            <div class="flex justify-between"><span class="text-gray-500">Nota:</span> <span class="font-bold">{{ selectedReceipt.invoice_number }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Waktu:</span> <span>{{ formatDate(selectedReceipt.created_at) }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Pasien:</span> <span class="font-bold">{{ selectedReceipt.patient_name }}</span></div>
          </div>

          <div class="border-b-2 border-dashed border-gray-300 mb-4"></div>

          <div class="mb-4 font-mono text-xs text-gray-800">
            <div v-for="(item, idx) in selectedReceipt.items" :key="idx" class="mb-2">
              <div class="font-bold">{{ item.item_name }}</div>
              <div class="flex justify-between text-gray-500 mt-0.5">
                <span>{{ item.quantity }}x @ {{ formatRp(item.price) }}</span>
                <span class="text-gray-800 font-bold">{{ formatRp(item.quantity * item.price) }}</span>
              </div>
            </div>
          </div>

          <div class="border-b-2 border-dashed border-gray-300 mb-4"></div>

          <div class="space-y-1 text-xs text-gray-800 font-mono">
            <div class="flex justify-between"><span>Subtotal</span><span>{{ formatRp(selectedReceipt.subtotal) }}</span></div>
            <div class="flex justify-between"><span>Pajak</span><span>{{ formatRp(selectedReceipt.tax) }}</span></div>
            <div class="flex justify-between text-lg font-black mt-3 pt-3 border-t-2 border-gray-800">
              <span>TOTAL</span><span>{{ formatRp(selectedReceipt.total_amount) }}</span>
            </div>
          </div>

          <div class="text-center mt-6">
            <span class="px-4 py-1.5 rounded-full text-xs font-black tracking-widest border-2"
              :class="selectedReceipt.payment_status === 'Paid' ? 'bg-green-100 text-green-700 border-green-500' : 'bg-red-100 text-red-700 border-red-500'"
            >
              {{ selectedReceipt.payment_status.toUpperCase() }}
            </span>
          </div>

          <div class="mt-8 text-center no-print">
            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg shadow w-full">
              🖨️ Cetak Struk Ini
            </button>
          </div>
        </div>
      </div>
    </div>
  </main>
</template>

<style scoped>
@media print {
  body * { visibility: hidden; }
  .print-area, .print-area * { visibility: visible; }
  .print-area {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    box-shadow: none !important;
  }
  .no-print { display: none !important; }
}
</style>