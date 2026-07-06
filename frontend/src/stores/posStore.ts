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
  // 3. FUNGSI TAMBAH DATA MANUAL
  // ========================================
  const addPatient = (patientData: any) => {
    const newPatient = {
      ...patientData,
      id: patientData.id || Date.now()
    }
    patients.value.push(newPatient)
    return newPatient
  }

  const addProduct = (productData: any) => {
    const newProduct = {
      ...productData,
      id: productData.id || Date.now()
    }
    products.value.push(newProduct)
    return newProduct
  }

  const updatePatient = (id: number, patientData: any) => {
    const index = patients.value.findIndex(p => p.id === id)
    if (index !== -1) {
      patients.value[index] = { ...patients.value[index], ...patientData }
    }
  }

  const updateProduct = (id: number, productData: any) => {
    const index = products.value.findIndex(p => p.id === id)
    if (index !== -1) {
      products.value[index] = { ...products.value[index], ...productData }
    }
  }

  const deletePatient = (id: number) => {
    patients.value = patients.value.filter(p => p.id !== id)
  }

  const deleteProduct = (id: number) => {
    products.value = products.value.filter(p => p.id !== id)
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
    } catch (error) {
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