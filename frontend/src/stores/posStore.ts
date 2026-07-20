import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

export const usePosStore = defineStore('pos', () => {
  // ========================================
  // 1. STATE GLOBAL (Penyimpanan Data Utama)
  // ========================================
  const patients = ref<any[]>([])
  const products = ref<any[]>([])
  const historyList = ref<any[]>([])
  
  const isPatientsLoaded = ref(false)
  const isProductsLoaded = ref(false)
  const isHistoryLoaded = ref(false)

  // STATE BARU: TUNGGAKAN
  const tunggakanAktif = ref<any>(null)
  const showModalTunggakan = ref(false)

  // ========================================
  // 2. FUNGSI SINKRONISASI DATA (Read)
  // ========================================
  const fetchPatients = async (forceRefresh = false) => {
    if (isPatientsLoaded.value && !forceRefresh) return
    try {
      const response = await axios.get('http://localhost:8000/patients')
      patients.value = response.data?.data || response.data || []
      isPatientsLoaded.value = true
    } catch (error) { 
      console.error('API Error Patients:', error)
      patients.value = []
    }
  }

  const fetchProducts = async (forceRefresh = false) => {
    if (isProductsLoaded.value && !forceRefresh) return
    try {
      const response = await axios.get('http://localhost:8000/healthcare-items')
      products.value = response.data?.data || response.data || []
      isProductsLoaded.value = true
    } catch (error) { 
      console.error('API Error Products:', error)
      if (products.value.length === 0) {
        products.value = [
          { id: 1, name: 'Konsultasi Dokter Umum', type: 'Jasa', category: 'jasa', price: 100000 },
          { id: 2, name: 'Obat Paracetamol', type: 'Barang', category: 'obat', price: 15000 }
        ]
      }
    }
  }

  const fetchHistory = async (forceRefresh = false) => {
    try {
      const response = await axios.get('http://localhost:8000/billing/history')
      historyList.value = response.data?.data || response.data || []
      isHistoryLoaded.value = true
      console.log('📊 History Fetched:', historyList.value.length, 'transactions')
      return historyList.value
    } catch (error) { 
      console.error('API Error History:', error)
      historyList.value = []
    }
  }

  // ========================================
  // 3. FUNGSI TAMBAH, EDIT, HAPUS DATA (Terhubung API)
  // ========================================
  const addPatient = async (patientData: any) => {
    try {
      // Hapus kata '/create' di akhir URL
      const response = await axios.post('http://localhost:8000/patients', patientData)
      await fetchPatients(true) 
      return response.data
    } catch (error) {
      console.error('Gagal menambah pasien:', error)
      throw error 
    }
  }

  const addProduct = async (productData: any) => {
    try {
      const response = await axios.post('http://localhost:8000/healthcare-items/create', productData)
      await fetchProducts(true)
      return response.data
    } catch (error) {
      console.error('Gagal menambah produk:', error)
      throw error
    }
  }

  const updatePatient = async (id: number, patientData: any) => {
    try {
      const response = await axios.put(`http://localhost:8000/patients/update/${id}`, patientData)
      await fetchPatients(true)
      return response.data
    } catch (error) {
      console.error('Gagal mengupdate pasien:', error)
      throw error
    }
  }

  const updateProduct = async (id: number, productData: any) => {
    try {
      const response = await axios.put(`http://localhost:8000/healthcare-items/update/${id}`, productData)
      await fetchProducts(true)
      return response.data
    } catch (error) {
      console.error('Gagal mengupdate produk:', error)
      throw error
    }
  }

  const deletePatient = async (id: number) => {
    try {
      await axios.delete(`http://localhost:8000/patients/delete/${id}`)
      await fetchPatients(true)
    } catch (error) {
      console.error('Gagal menghapus pasien:', error)
      throw error
    }
  }

  const deleteProduct = async (id: number) => {
    try {
      await axios.delete(`http://localhost:8000/healthcare-items/delete/${id}`)
      await fetchProducts(true)
    } catch (error) {
      console.error('Gagal menghapus produk:', error)
      throw error
    }
  }

  // ========================================
  // 4. LOGIKA TRANSAKSI TERPUSAT
  // ========================================
  const prosesCheckoutKasir = async (payloadBilling: any) => {
    try {
      const res = await axios.post('http://localhost:8000/billing/create', payloadBilling)
      // Refresh produk & history setelah checkout
      await fetchProducts(true)
      await fetchHistory(true)
      return res.data 
    } catch (error: any) {
      // TANGKAP ERROR TUNGGAKAN DARI PHALCON
      if (error.response && error.response.status === 400) {
        const errorData = error.response.data
        if (errorData.data_tunggakan) {
          tunggakanAktif.value = errorData.data_tunggakan
          showModalTunggakan.value = true
          throw new Error('TUNGGAKAN')
        }
      }
      throw error
    }
  }

  const prosesPembayaran = async (payloadPayment: any) => {
    try {
      console.log('💳 Processing Payment:', payloadPayment)
      const res = await axios.post('http://localhost:8000/billing/payment', payloadPayment)
      console.log('✅ Payment Response:', res.data)
      
      // Refresh history setelah pembayaran
      await fetchHistory(true)
      
      return res.data
    } catch (error) {
      console.error('❌ Payment Error:', error)
      throw error
    }
  }

  // ========================================
  // 5. COMPUTED PROPERTIES UNTUK QUERY
  // ========================================
  const getPatientById = computed(() => (id: number) => {
    return patients.value.find(p => p.id === id)
  })

  const getProductById = computed(() => (id: number) => {
    return products.value.find(p => p.id === id)
  })

  return { 
    // State
    patients, 
    products, 
    historyList,
    isPatientsLoaded,
    isProductsLoaded,
    isHistoryLoaded,
    tunggakanAktif,
    showModalTunggakan,
    
    // Fetch Functions
    fetchPatients, 
    fetchProducts, 
    fetchHistory,
    
    // Tambah/Update/Delete Functions
    addPatient,
    addProduct,
    updatePatient,
    updateProduct,
    deletePatient,
    deleteProduct,
    
    // Transaction Functions
    prosesCheckoutKasir, 
    prosesPembayaran,
    
    // Computed
    getPatientById,
    getProductById
  }
})