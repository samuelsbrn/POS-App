import { defineStore } from 'pinia'
import { ref } from 'vue'
import axios from 'axios'

export const usePosStore = defineStore('pos', () => {
  // 1. STATE GLOBAL (Penyimpanan Data Utama)
  const patients = ref<any[]>([])
  const products = ref<any[]>([])
  const historyList = ref<any[]>([])
  
  const isPatientsLoaded = ref(false)
  const isProductsLoaded = ref(false)
  const isHistoryLoaded = ref(false)

  // 2. FUNGSI SINKRONISASI DATA (Read)
  const fetchPatients = async (forceRefresh = false) => {
    if (isPatientsLoaded.value && !forceRefresh) return
    try {
      const response = await axios.get('http://localhost:8000/patients')
      patients.value = response.data.data || response.data
      isPatientsLoaded.value = true
    } catch (error) { console.error('API Error Patients') }
  }

  const fetchProducts = async (forceRefresh = false) => {
    if (isProductsLoaded.value && !forceRefresh) return
    try {
      const response = await axios.get('http://localhost:8000/healthcare-items')
      products.value = response.data.data || response.data
      isProductsLoaded.value = true
    } catch (error) { console.error('API Error Products') }
  }

  const fetchHistory = async (forceRefresh = false) => {
    if (isHistoryLoaded.value && !forceRefresh) return
    try {
      const response = await axios.get('http://localhost:8000/billing/history')
      historyList.value = response.data.data || response.data
      isHistoryLoaded.value = true
    } catch (error) { console.error('API Error History') }
  }

  // 3. LOGIKA TRANSAKSI TERPUSAT (Agar semua halaman otomatis sinkron)
  const prosesCheckoutKasir = async (payloadBilling: any) => {
    try {
      // 1. Tembak API Buat Nota
      const res = await axios.post('http://localhost:8000/billing/create', payloadBilling)
      
      // 2. KARENA STOK BERKURANG DI BACKEND, KITA PAKSA REFRESH DATA PRODUK
      await fetchProducts(true)
      
      return res.data // Kembalikan data ke Kasir untuk lanjut ke Modal Pembayaran
    } catch (error) {
      throw error
    }
  }

  const prosesPembayaran = async (payloadPayment: any) => {
    try {
      // 1. Tembak API Pembayaran
      const res = await axios.post('http://localhost:8000/billing/payment', payloadPayment)
      
      // 2. KARENA ADA TRANSAKSI BARU, KITA PAKSA REFRESH DATA RIWAYAT
      await fetchHistory(true)
      
      return res.data
    } catch (error) {
      throw error
    }
  }

  return { 
    patients, products, historyList, 
    fetchPatients, fetchProducts, fetchHistory,
    prosesCheckoutKasir, prosesPembayaran
  }
})