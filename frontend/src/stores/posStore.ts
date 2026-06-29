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
    } catch (error) { 
      console.error('API Error Patients') 
    }
  }

  const fetchProducts = async (forceRefresh = false) => {
    if (isProductsLoaded.value && !forceRefresh) return
    try {
      const response = await axios.get('http://localhost:8000/healthcare-items')
      products.value = response.data.data || response.data
      isProductsLoaded.value = true
    } catch (error) { 
      console.error('API Error Products - Menggunakan Data Lokal Sementara')
      // Fallback data lokal jika backend belum siap agar aplikasi tidak kosong
      if (products.value.length === 0) {
        products.value = [
          { id: 1, name: 'Konsultasi Dokter Umum', type: 'Jasa', category: 'jasa', price: 100000 },
          { id: 2, name: 'Obat Paracetamol', type: 'Barang', category: 'obat', price: 15000 }
        ]
      }
    }
  }

  const fetchHistory = async (forceRefresh = false) => {
    if (isHistoryLoaded.value && !forceRefresh) return
    try {
      const response = await axios.get('http://localhost:8000/billing/history')
      historyList.value = response.data.data || response.data
      isHistoryLoaded.value = true
    } catch (error) { console.error('API Error History') }
  }

  // 3. LOGIKA TRANSAKSI TERPUSAT
  const prosesCheckoutKasir = async (payloadBilling: any) => {
    try {
      const res = await axios.post('http://localhost:8000/billing/create', payloadBilling)
      await fetchProducts(true)
      return res.data 
    } catch (error) {
      throw error
    }
  }

  const prosesPembayaran = async (payloadPayment: any) => {
    try {
      const res = await axios.post('http://localhost:8000/billing/payment', payloadPayment)
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