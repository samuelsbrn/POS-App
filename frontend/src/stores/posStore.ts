import { defineStore } from 'pinia'
import { ref } from 'vue'
import axios from 'axios'

export const usePosStore = defineStore('pos', () => {
  // === STATE (Data Global) ===
  const patients = ref<any[]>([])
  const products = ref<any[]>([])
  const isPatientsLoaded = ref(false)
  const isProductsLoaded = ref(false)

  // === ACTIONS (Fungsi untuk mengambil & memodifikasi data) ===

  // 1. Ambil Data Pasien dari Backend
  const fetchPatients = async (forceRefresh = false) => {
    // Jika data sudah ada dan tidak dipaksa refresh, jangan hit API lagi (hemat kuota server)
    if (isPatientsLoaded.value && !forceRefresh) return

    try {
      const response = await axios.get('http://localhost:8000/patients')
      patients.value = response.data.data || response.data
      isPatientsLoaded.value = true
    } catch (error) {
      console.error('Gagal memuat pasien:', error)
      // Fallback Data Simulasi
      patients.value = [
        { id: 1, mrn: 'RM-00123', nik: '32010101', name: 'Budi Santoso', gender: 'L', dob: '1990-05-15', phone: '0812', address: 'Laguboti' },
        { id: 2, mrn: 'RM-00124', nik: '32010202', name: 'Siti Aminah', gender: 'P', dob: '1985-10-20', phone: '0898', address: 'Balige' }
      ]
    }
  }

  // 2. Ambil Data Produk/Layanan dari Backend
  const fetchProducts = async (forceRefresh = false) => {
    if (isProductsLoaded.value && !forceRefresh) return

    try {
      const response = await axios.get('http://localhost:8000/healthcare-items')
      products.value = response.data.data || response.data
      isProductsLoaded.value = true
    } catch (error) {
      console.error('Gagal memuat produk:', error)
      // Fallback Data Simulasi
      products.value = [
        { id: 1, name: 'Konsultasi Dokter Umum', type: 'Jasa', price: 100000, category: 'tindakan_medis' },
        { id: 2, name: 'Cek Darah Lengkap', type: 'Jasa', price: 250000, category: 'laboratorium' },
        { id: 3, name: 'Obat Paracetamol', type: 'Barang', price: 15000, category: 'obat' },
      ]
    }
  }

  // === RETURN STATE & ACTIONS ===
  return { 
    patients, 
    products, 
    fetchPatients, 
    fetchProducts 
  }
})