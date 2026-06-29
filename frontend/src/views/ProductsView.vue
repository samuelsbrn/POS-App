<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { usePosStore } from '../stores/posStore'
import { storeToRefs } from 'pinia'

// --- 1. HUBUNGKAN KE STORE (KUNCI SINKRONISASI) ---
const posStore = usePosStore()
const { products } = storeToRefs(posStore) 

const isLoading = ref(false)

// --- 2. STATE UNTUK MODAL FORM ---
const showModal = ref(false)
const isEditMode = ref(false)
const formData = ref({
  id: null as number | null,
  name: '',
  type: 'Jasa',
  price: 0
})

// --- 3. FUNGSI READ ---
const fetchProducts = async () => {
  isLoading.value = true
  await posStore.fetchProducts()
  isLoading.value = false
}

// --- 4. FUNGSI BUKA MODAL ---
const openAddModal = () => {
  isEditMode.value = false
  formData.value = { id: null, name: '', type: 'Jasa', price: 0 }
  showModal.value = true
}

const openEditModal = (item: any) => {
  isEditMode.value = true
  formData.value = { ...item } 
  showModal.value = true
}

// --- 5. FUNGSI CREATE & UPDATE ---
const saveProduct = async () => {
  try {
    // Generate category based on type (untuk keperluan tampilan warna di kasir)
    const categoryName = formData.value.type === 'Barang' ? 'obat' : 'jasa';

    if (isEditMode.value) {
      // Simulasi update ke global state
      const index = products.value.findIndex(p => p.id === formData.value.id)
      if (index !== -1) {
        products.value[index] = { ...formData.value, category: categoryName }
      }
      alert('Data berhasil diperbarui!')
    } else {
      // Simulasi insert ke global state (Otomatis sinkron ke kasir)
      products.value.push({ ...formData.value, category: categoryName, id: Date.now() }) 
      alert('Data berhasil ditambahkan!')
    }
    
    // Jika API backend Anda sudah siap, uncomment ini:
    // await posStore.fetchProducts(true)
    
    showModal.value = false
  } catch (error) {
    console.error(error)
    alert('Terjadi kesalahan saat menyimpan data.')
  }
}

// --- 6. FUNGSI DELETE ---
const deleteProduct = async (id: number) => {
  if (!confirm('Apakah Anda yakin ingin menghapus item ini?')) return
  try {
    // Simulasi hapus dari global state
    products.value = products.value.filter(p => p.id !== id)
    alert('Data berhasil dihapus!')
    
    // Jika API backend Anda sudah siap, uncomment ini:
    // await posStore.fetchProducts(true)
  } catch (error) {
    console.error(error)
    alert('Terjadi kesalahan saat menghapus data.')
  }
}

// Format Rupiah
const formatRp = (value: number) => {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value)
}

onMounted(() => {
  fetchProducts()
})
</script>

<template>
  <main class="min-h-screen bg-gray-100 p-8 relative">
    <div class="max-w-7xl mx-auto bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
      
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Manajemen Barang & Layanan</h2>
        <button @click="openAddModal" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-xl shadow transition-colors">
          + Tambah Item
        </button>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-gray-50 text-gray-600 border-b-2 border-gray-200">
              <th class="p-4 font-bold">ID</th>
              <th class="p-4 font-bold">Nama Item</th>
              <th class="p-4 font-bold">Jenis</th>
              <th class="p-4 font-bold">Harga</th>
              <th class="p-4 font-bold text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="isLoading">
              <td colspan="5" class="p-8 text-center text-gray-500 font-medium">Memuat data...</td>
            </tr>
            <tr v-else-if="products.length === 0">
              <td colspan="5" class="p-8 text-center text-gray-500 font-medium">Belum ada data barang/jasa.</td>
            </tr>
            <tr v-else v-for="(p, index) in products" :key="index" class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
              <td class="p-4 text-gray-500">#{{ p.id }}</td>
              <td class="p-4 font-semibold text-gray-800">{{ p.name }}</td>
              <td class="p-4">
                <span :class="p.type === 'Jasa' ? 'bg-purple-100 text-purple-700 border-purple-200' : 'bg-blue-100 text-blue-700 border-blue-200'" class="px-3 py-1 rounded-full text-xs font-bold border">
                  {{ p.type || p.category }}
                </span>
              </td>
              <td class="p-4 text-blue-600 font-bold">{{ formatRp(p.price) }}</td>
              <td class="p-4 flex justify-center gap-3">
                <button @click="openEditModal(p)" class="text-yellow-600 hover:text-yellow-800 bg-yellow-50 hover:bg-yellow-100 p-2 rounded-lg transition-colors" title="Edit">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                </button>
                <button @click="deleteProduct(p.id)" class="text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 p-2 rounded-lg transition-colors" title="Hapus">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="showModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
      <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden">
        <div class="bg-gray-50 border-b border-gray-200 p-4 flex justify-between items-center">
          <h3 class="font-bold text-lg text-gray-800">{{ isEditMode ? 'Edit Item' : 'Tambah Item Baru' }}</h3>
          <button @click="showModal = false" class="text-gray-400 hover:text-red-500 font-bold text-xl transition-colors">&times;</button>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">Nama Item</label>
            <input type="text" v-model="formData.name" placeholder="Misal: Perban / Cek Darah" class="w-full border-2 border-gray-200 rounded-lg p-2.5 focus:border-blue-500 focus:outline-none transition-colors">
          </div>
          <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">Jenis Item</label>
            <select v-model="formData.type" class="w-full border-2 border-gray-200 rounded-lg p-2.5 focus:border-blue-500 focus:outline-none transition-colors">
              <option value="Barang">Barang (Fisik/Obat)</option>
              <option value="Jasa">Jasa (Layanan/Konsultasi)</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">Harga (Rp)</label>
            <input type="number" v-model="formData.price" class="w-full border-2 border-gray-200 rounded-lg p-2.5 focus:border-blue-500 focus:outline-none transition-colors">
          </div>
        </div>
        <div class="p-4 border-t border-gray-100 bg-gray-50 flex gap-3 justify-end">
          <button @click="showModal = false" class="px-5 py-2.5 font-bold text-gray-600 bg-white border border-gray-300 rounded-xl hover:bg-gray-100 transition-colors">Batal</button>
          <button @click="saveProduct" class="px-5 py-2.5 font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-md transition-colors">
            {{ isEditMode ? 'Simpan Perubahan' : 'Simpan Baru' }}
          </button>
        </div>
      </div>
    </div>
  </main>
</template>