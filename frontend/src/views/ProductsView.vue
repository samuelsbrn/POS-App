<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { usePosStore } from '../stores/posStore'
import { storeToRefs } from 'pinia'

// --- 1. HUBUNGKAN KE STORE (KUNCI SINKRONISASI) ---
const posStore = usePosStore()
const { products } = storeToRefs(posStore) 

const isLoading = ref(false)
const toast = ref({ show: false, message: '', type: 'success' })

// --- 2. STATE UNTUK MODAL FORM ---
const showModal = ref(false)
const isEditMode = ref(false)
const formData = ref({
  id: null as number | null,
  name: '',
  type: 'Jasa',
  price: 0,
  category: 'jasa'
})

const showToast = (message: string, type: 'success' | 'error' = 'success') => {
  toast.value = { show: true, message, type }
  setTimeout(() => { toast.value.show = false }, 3000)
}

// --- 3. FUNGSI READ ---
const fetchProducts = async () => {
  isLoading.value = true
  try {
    await posStore.fetchProducts()
  } catch (error) {
    console.error(error)
  } finally {
    isLoading.value = false
  }
}

const refreshData = async () => {
  isLoading.value = true
  try {
    await posStore.fetchProducts(true)
    showToast('Data produk berhasil diperbarui dari server!', 'success')
  } catch (error) {
    console.error(error)
  } finally {
    isLoading.value = false
  }
}

// --- 4. FUNGSI BUKA MODAL ---
const openAddModal = () => {
  isEditMode.value = false
  formData.value = { id: null, name: '', type: 'Jasa', price: 0, category: 'jasa' }
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
    if (!formData.value.name || formData.value.price <= 0) {
      showToast('Nama dan harga wajib diisi dengan benar!', 'error')
      return
    }

    // Generate category based on type (untuk keperluan tampilan warna di kasir)
    const categoryName = formData.value.type === 'Barang' ? 'obat' : 'jasa'
    const productData = { ...formData.value, category: categoryName }

    if (isEditMode.value && formData.value.id) {
      posStore.updateProduct(formData.value.id, productData)
      showToast('Data produk berhasil diperbarui!', 'success')
    } else {
      posStore.addProduct(productData)
      showToast('Data produk berhasil ditambahkan!', 'success')
    }
    
    showModal.value = false
  } catch (error) {
    showToast('Gagal menyimpan data produk.', 'error')
    console.error(error)
  }
}

// --- 6. FUNGSI DELETE ---
const deleteProduct = async (id: number) => {
  if (!confirm('Apakah Anda yakin ingin menghapus item ini?')) return
  try {
    posStore.deleteProduct(id)
    showToast('Data produk berhasil dihapus!', 'success')
  } catch (error) {
    showToast('Gagal menghapus data produk.', 'error')
    console.error(error)
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
  <main class="min-h-screen bg-slate-50 p-8 relative font-sans">
    <!-- TOAST NOTIFICATION -->
    <Transition name="toast">
      <div v-if="toast.show" 
           class="fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg font-bold flex items-center gap-2"
           :class="{ 'bg-teal-600 text-white': toast.type === 'success', 'bg-red-600 text-white': toast.type === 'error' }">
        {{ toast.message }}
      </div>
    </Transition>

    <div class="max-w-7xl mx-auto bg-white p-8 rounded-3xl shadow-sm border border-slate-200">
      
      <div class="flex justify-between items-center mb-8">
        <h2 class="text-2xl font-extrabold text-slate-800 flex items-center gap-3">
          <span class="w-2 h-8 bg-teal-500 rounded-full"></span> Manajemen Barang & Layanan
        </h2>
        <div class="flex gap-3">
          <button @click="refreshData" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold py-3 px-6 rounded-2xl flex items-center gap-2 transition-colors uppercase tracking-wider text-sm shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            Refresh
          </button>
          <button @click="openAddModal" class="bg-teal-600 hover:bg-teal-700 active:bg-teal-800 text-white font-bold py-3 px-6 rounded-2xl shadow-lg transition-all uppercase tracking-wider text-sm">
            + Tambah Item
          </button>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-slate-50 text-slate-500 border-b-2 border-slate-200 uppercase tracking-wider text-xs">
              <th class="p-4 font-bold">ID</th>
              <th class="p-4 font-bold">Nama Item</th>
              <th class="p-4 font-bold">Jenis</th>
              <th class="p-4 font-bold">Harga</th>
              <th class="p-4 font-bold text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="isLoading">
              <td colspan="5" class="p-8 text-center text-slate-400 font-medium">Memuat data dari server...</td>
            </tr>
            <tr v-else-if="products.length === 0">
              <td colspan="5" class="p-8 text-center text-slate-400 font-medium">Belum ada data barang/jasa. Silakan tambahkan item baru.</td>
            </tr>
            <tr v-else v-for="(p, index) in products" :key="p.id || index" class="border-b border-slate-100 hover:bg-teal-50/50 transition-colors">
              <td class="p-4 font-mono text-slate-500">#{{ p.id }}</td>
              <td class="p-4 font-bold text-slate-700">{{ p.name }}</td>
              <td class="p-4">
                <span :class="p.type === 'Jasa' ? 'bg-blue-100 text-blue-700' : 'bg-orange-100 text-orange-700'" class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider">
                  {{ p.type }}
                </span>
              </td>
              <td class="p-4 text-teal-600 font-black">{{ formatRp(p.price) }}</td>
              <td class="p-4 flex justify-center gap-3">
                <button @click="openEditModal(p)" class="text-yellow-600 bg-yellow-50 hover:bg-yellow-100 p-2 rounded-xl transition-all font-bold text-xs uppercase tracking-widest">Edit</button>
                <button @click="deleteProduct(p.id)" class="text-red-600 bg-red-50 hover:bg-red-100 p-2 rounded-xl transition-all font-bold text-xs uppercase tracking-widest">Hapus</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="showModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
      <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl overflow-hidden border border-slate-100">
        <div class="bg-teal-600 p-5 flex justify-between items-center">
          <h3 class="text-white font-black tracking-widest uppercase text-sm">{{ isEditMode ? 'Edit Item Barang/Layanan' : 'Tambah Item Baru' }}</h3>
          <button @click="showModal = false" class="text-teal-100 hover:text-white font-black text-2xl">&times;</button>
        </div>

        <div class="p-8 space-y-5">
          <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Nama Item</label>
            <input type="text" v-model="formData.name" placeholder="Misal: Perban / Cek Darah" class="w-full border-2 border-slate-200 rounded-xl p-3 font-bold text-slate-700 focus:border-teal-500 focus:ring-0">
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Jenis Item</label>
            <select v-model="formData.type" class="w-full border-2 border-slate-200 rounded-xl p-3 font-bold text-slate-700 focus:border-teal-500 focus:ring-0">
              <option value="Barang">Barang (Fisik/Obat)</option>
              <option value="Jasa">Jasa (Layanan/Konsultasi)</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Harga (Rp)</label>
            <input type="number" v-model="formData.price" placeholder="0" class="w-full border-2 border-slate-200 rounded-xl p-3 font-bold text-slate-700 focus:border-teal-500 focus:ring-0">
          </div>
        </div>

        <div class="p-5 border-t border-slate-100 bg-slate-50 flex gap-4">
          <button @click="showModal = false" class="w-1/3 py-4 font-bold border-2 border-slate-200 rounded-2xl bg-white text-slate-500 hover:bg-slate-50 uppercase tracking-wider text-xs">Batal</button>
          <button @click="saveProduct" class="w-2/3 py-4 font-black text-white bg-teal-500 hover:bg-teal-600 rounded-2xl shadow-lg uppercase tracking-wider text-sm">
            {{ isEditMode ? 'Perbarui' : 'Simpan Baru' }}
          </button>
        </div>
      </div>
    </div>
  </main>
</template>