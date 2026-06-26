<script setup lang="ts">
import { ref, onMounted } from 'vue'
import axios from 'axios'

// State untuk menyimpan data riwayat
const historyData = ref<any>(null)

// Fungsi untuk mengambil data dari backend Phalcon
const fetchHistory = async () => {
  try {
    const response = await axios.get('http://localhost:8000/billing/history?id=8')
    historyData.value = response.data.data
  } catch (error) {
    console.error('Gagal mengambil data riwayat:', error)
  }
}

onMounted(() => {
  fetchHistory()
})

// Helper untuk format angka ke Rupiah dengan rapi
const formatRp = (value: number) => {
  return new Intl.NumberFormat('id-ID', { 
    style: 'currency', 
    currency: 'IDR', 
    minimumFractionDigits: 0 
  }).format(value || 0)
}
</script>

<template>
  <main class="min-h-screen bg-gray-100 p-8">
    <div class="max-w-3xl mx-auto">
      
      <div class="flex justify-between items-center mb-8">
        <h2 class="text-2xl font-bold text-gray-800">Detail Riwayat Transaksi</h2>
        <button 
          onclick="window.print()" 
          class="bg-gray-800 hover:bg-black text-white px-4 py-2 rounded-lg font-bold flex items-center gap-2 transition-all shadow-md hover:shadow-lg"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
          Cetak Struk
        </button>
      </div>

      <div v-if="historyData" class="bg-white p-8 rounded-sm shadow-2xl border-t-8 border-gray-800 max-w-sm mx-auto relative">
        
        <div class="text-center mb-6">
          <h1 class="text-3xl font-black text-gray-900 tracking-widest">POS KLINIK</h1>
          <p class="text-sm text-gray-500 mt-1">Jl. Kesehatan No. 123, Laguboti</p>
          <p class="text-sm text-gray-500">Telp: (0632) 123456</p>
        </div>

        <div class="border-b-2 border-dashed border-gray-300 mb-5"></div>

        <div class="mb-5 text-sm text-gray-700 grid grid-cols-2 gap-4">
          <div>
            <p class="text-xs text-gray-500">No. Nota</p>
            <p class="font-bold font-mono">{{ historyData.invoice_number || 'INV-000' }}</p>
          </div>
          <div class="text-right">
            <p class="text-xs text-gray-500">Tanggal</p>
            <p class="font-bold">{{ historyData.created_at ? new Date(historyData.created_at).toLocaleDateString('id-ID') : 'Hari ini' }}</p>
          </div>
          <div class="col-span-2">
            <p class="text-xs text-gray-500">Pasien</p>
            <p class="font-bold">{{ historyData.patient_name || 'Pasien Umum (ID: ' + (historyData.patient_id || '-') + ')' }}</p>
          </div>
        </div>

        <div class="border-b-2 border-dashed border-gray-300 mb-5"></div>

        <div class="mb-5">
          <table class="w-full text-sm text-gray-800 font-mono">
            <thead>
              <tr class="text-left font-bold text-gray-900 border-b-2 border-gray-200">
                <th class="pb-2">Item</th>
                <th class="pb-2 text-right">Total</th>
              </tr>
            </thead>
            <tbody class="align-top">
              <tr v-for="(item, index) in historyData.items" :key="index" class="border-b border-gray-100 last:border-0">
                <td class="py-3">
                  <div class="font-bold">{{ item.item_name || 'Layanan/Obat' }}</div>
                  <div class="text-gray-500 text-xs mt-1">{{ item.quantity }}x @ {{ formatRp(item.price) }}</div>
                </td>
                <td class="py-3 text-right font-bold">{{ formatRp(item.quantity * item.price) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="border-b-2 border-dashed border-gray-300 mb-5"></div>

        <div class="space-y-2 text-sm text-gray-800 font-mono">
          <div class="flex justify-between">
            <span>Subtotal</span>
            <span class="font-bold">{{ formatRp(historyData.subtotal) }}</span>
          </div>
          <div class="flex justify-between">
            <span>Pajak (Tax)</span>
            <span class="font-bold">{{ formatRp(historyData.tax) }}</span>
          </div>
          <div class="flex justify-between text-xl font-black mt-4 pt-4 border-t-2 border-gray-800">
            <span>TOTAL</span>
            <span>{{ formatRp(historyData.total_amount) }}</span>
          </div>
        </div>

        <div class="border-b-2 border-dashed border-gray-300 my-6"></div>

        <div class="text-center mb-6">
          <span 
            class="px-4 py-2 rounded-full text-sm font-black tracking-widest border-2"
            :class="historyData.payment_status === 'Paid' ? 'bg-green-100 text-green-700 border-green-500' : 'bg-red-100 text-red-700 border-red-500'"
          >
            {{ historyData.payment_status ? historyData.payment_status.toUpperCase() : 'UNPAID' }}
          </span>
        </div>

        <div class="text-center text-gray-500 text-xs mt-8 font-mono">
          <p>Terima kasih atas kunjungan Anda.</p>
          <p class="mt-1">Semoga lekas sembuh!</p>
        </div>

      </div>

      <div v-else class="text-gray-500 italic flex flex-col items-center justify-center gap-4 mt-32">
        <svg class="animate-spin h-10 w-10 text-gray-800" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
        <span class="font-bold tracking-widest">MENCETAK STRUK...</span>
      </div>

    </div>
  </main>
</template>

<style scoped>
/* Memastikan background abu-abu tidak ikut tercetak saat tombol Print ditekan */
@media print {
  body * {
    visibility: hidden;
  }
  .max-w-sm, .max-w-sm * {
    visibility: visible;
  }
  .max-w-sm {
    position: absolute;
    left: 0;
    top: 0;
    box-shadow: none !important;
  }
}
</style>