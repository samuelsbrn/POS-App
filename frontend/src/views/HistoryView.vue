<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { usePosStore } from '../stores/posStore'
import { storeToRefs } from 'pinia'
import axios from 'axios'

// 1. Hubungkan ke Gudang Pusat
const posStore = usePosStore()
const { historyList } = storeToRefs(posStore)

const isLoading = ref(false)
const showReceiptModal = ref(false)
const selectedReceipt = ref<any>(null)
const isLoadingReceipt = ref(false)

const refreshData = async () => {
  isLoading.value = true
  await posStore.fetchHistory(true) 
  isLoading.value = false
}

// 2. FUNGSI AMBIL DETAIL STRUK TERPISAH
const printReceipt = async (id: number, type: string) => {
  showReceiptModal.value = true
  isLoadingReceipt.value = true
  selectedReceipt.value = null

  try {
    // Kita kirim tipe agar backend tahu ini ngambil detail Invoice atau detail Split Payment
    const response = await axios.get(`http://localhost:8000/billing/history?id=${id}&type=${type}`)
    selectedReceipt.value = response.data.data
  } catch (error) {
    console.error(error)
    alert("Gagal menarik detail struk dari server.")
    showReceiptModal.value = false
  } finally {
    isLoadingReceipt.value = false 
  }
}

const formatRp = (value: number) => {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value || 0)
}

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' })
}

onMounted(() => {
  refreshData()
})
</script>

<template>
  <main class="min-h-screen bg-slate-50 p-8 relative font-sans">
    <div class="max-w-7xl mx-auto bg-white p-8 rounded-3xl shadow-sm border border-slate-200">
      
      <div class="flex justify-between items-center mb-8">
        <h2 class="text-2xl font-extrabold text-slate-800 flex items-center gap-3">
          <span class="w-2 h-8 bg-teal-500 rounded-full"></span> Riwayat Tagihan (Invoices)
        </h2>
        <button @click="refreshData" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold py-3 px-6 rounded-2xl flex items-center gap-2 transition-colors uppercase tracking-wider text-sm shadow-sm">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
          Refresh Data
        </button>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-slate-50 text-slate-500 border-b-2 border-slate-200 uppercase tracking-wider text-xs">
              <th class="p-4 font-bold">No. Nota & Waktu</th>
              <th class="p-4 font-bold">Data Pasien</th>
              <th class="p-4 font-bold">Nominal Transaksi</th>
              <th class="p-4 font-bold text-center">Metode</th>
              <th class="p-4 font-bold text-center">Status</th>
              <th class="p-4 font-bold text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="isLoading">
              <td colspan="6" class="p-8 text-center text-slate-400 font-medium">Memuat riwayat transaksi dari MariaDB...</td>
            </tr>
            <tr v-else-if="historyList.length === 0">
              <td colspan="6" class="p-8 text-center text-slate-400 font-medium">Belum ada transaksi sama sekali.</td>
            </tr>
            <tr v-else v-for="(inv, index) in historyList" :key="index" class="border-b border-slate-100 hover:bg-teal-50/50 transition-colors">
              <td class="p-4">
                <div class="font-black text-teal-700 tracking-wide">{{ inv.invoice_number }}</div>
                <div class="text-xs text-slate-400 font-mono mt-1">{{ formatDate(inv.created_at) }}</div>
              </td>
              <td class="p-4 font-bold text-slate-700">{{ inv.patient_name }}</td>
              <td class="p-4 font-black text-slate-800">{{ formatRp(inv.total_amount) }}</td>
              <td class="p-4 text-center font-bold text-slate-600">
                <span class="bg-slate-100 px-3 py-1 rounded-md text-xs border border-slate-200">{{ inv.payment_method }}</span>
              </td>
              <td class="p-4 text-center">
                <span 
                  class="px-4 py-1.5 rounded-full text-[10px] font-black tracking-widest uppercase border"
                  :class="{
                    'bg-green-100 text-green-700 border-green-300': inv.payment_status.includes('Paid'),
                    'bg-red-100 text-red-700 border-red-300': inv.payment_status === 'Unpaid',
                    'bg-slate-100 text-slate-700 border-slate-300': inv.payment_status === 'Void'
                  }"
                >
                  {{ inv.payment_status }}
                </span>
              </td>
              <td class="p-4 flex justify-center">
                <button @click="printReceipt(inv.id, inv.type)" class="bg-teal-600 hover:bg-teal-700 text-white px-5 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider flex items-center gap-2 shadow-md transition-all">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                  Lihat Struk
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="showReceiptModal" class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm flex items-center justify-center z-50 p-4">
      
      <div class="bg-white p-8 rounded-sm shadow-2xl border-t-8 border-slate-800 max-w-sm w-full relative print-area overflow-y-auto max-h-[90vh]">
        
        <button @click="showReceiptModal = false" class="absolute top-2 right-2 text-slate-300 hover:text-red-500 font-black text-2xl no-print">&times;</button>
        
        <div v-if="isLoadingReceipt" class="flex flex-col items-center py-12">
          <svg class="animate-spin h-10 w-10 text-teal-600 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
          <span class="font-mono text-sm text-slate-500 font-bold uppercase tracking-widest">Mencari Struk...</span>
        </div>

        <div v-else-if="selectedReceipt">
          <div class="text-center mb-6">
            <h1 class="text-3xl font-black text-slate-900 tracking-widest">ZiCare POS</h1>
            <p class="text-xs text-slate-500 mt-1 font-mono uppercase tracking-wider">Klinik & Apotek</p>
          </div>

          <div class="border-b-2 border-dashed border-slate-300 mb-4"></div>

          <div class="mb-4 text-xs text-slate-700 font-mono space-y-1.5">
            <div class="flex justify-between"><span class="text-slate-500">Nota:</span> <span class="font-bold">{{ selectedReceipt.invoice_number }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Waktu:</span> <span>{{ formatDate(selectedReceipt.created_at) }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Pasien:</span> <span class="font-bold">{{ selectedReceipt.patient_name }}</span></div>
          </div>

          <div class="border-b-2 border-dashed border-slate-300 mb-4"></div>

          <div class="mb-4 font-mono text-xs text-slate-800 space-y-3">
            <div v-for="(item, idx) in selectedReceipt.items" :key="idx">
              <div class="font-bold">{{ item.item_name }}</div>
              <div class="flex justify-between text-slate-500 mt-1">
                <span>{{ item.quantity }}x @ {{ formatRp(item.price) }}</span>
                <span class="text-slate-800 font-bold">{{ formatRp(item.quantity * item.price) }}</span>
              </div>
            </div>
          </div>

          <div class="border-b-2 border-dashed border-slate-300 mb-4"></div>

          <div class="flex justify-between text-lg font-black mt-3 pt-3">
            <span>TOTAL KESELURUHAN</span><span>{{ formatRp(selectedReceipt.total_tagihan_utama) }}</span>
          </div>

          <div v-if="selectedReceipt.is_split" class="mt-4 border-2 border-slate-800 p-4 rounded-lg bg-slate-50">
             <h3 class="text-center font-bold text-xs uppercase tracking-widest text-slate-800 mb-3">BUKTI PEMBAYARAN (SPLIT KE-{{ selectedReceipt.split_sequence }})</h3>
             <div class="space-y-1.5 text-xs font-mono text-slate-800">
               <div class="flex justify-between"><span>Metode Bayar:</span><span class="font-bold">{{ selectedReceipt.method_name }}</span></div>
               <div class="flex justify-between"><span>Nominal Masuk:</span><span class="font-bold">{{ formatRp(selectedReceipt.amount_paid) }}</span></div>
               <div class="flex justify-between"><span>Kembalian:</span><span>{{ formatRp(selectedReceipt.change_amount) }}</span></div>
             </div>
             <div class="border-t-2 border-slate-300 mt-3 pt-3 flex justify-between font-bold text-sm">
               <span>SISA TAGIHAN:</span><span>{{ formatRp(selectedReceipt.sisa_tagihan) }}</span>
             </div>
          </div>

          <div v-else class="text-center mt-6">
            <span class="px-5 py-2 rounded-full text-[10px] font-black tracking-widest border-2 bg-red-100 text-red-700 border-red-500">
              UNPAID / BELUM DIBAYAR
            </span>
          </div>

          <div class="mt-8 text-center no-print">
            <button onclick="window.print()" class="bg-teal-600 hover:bg-teal-700 text-white font-black uppercase tracking-widest py-3 px-6 rounded-xl shadow-lg w-full transition-all">
              🖨️ Cetak Struk
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
/* Mempercantik Scrollbar untuk modal pop-up */
.overflow-y-auto::-webkit-scrollbar { width: 6px; }
.overflow-y-auto::-webkit-scrollbar-track { background: transparent; }
.overflow-y-auto::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
.overflow-y-auto::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>