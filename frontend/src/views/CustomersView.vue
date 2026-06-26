<script setup lang="ts">
import { ref, onMounted } from 'vue'
import axios from 'axios'

// --- 1. STATE BASSIC ---
const customers = ref<any[]>([])
const isLoading = ref(false)

// --- 2. STATE UNTUK MODAL FORM ---
const showModal = ref(false)
const isEditMode = ref(false)
const formData = ref({
  id: null as number | null,
  name: '',
  phone: '',
  address: ''
})

// --- 3. FUNGSI READ (Ambil Data) ---
const fetchCustomers = async () => {
  isLoading.value = true
  try {
    // Sesuaikan endpoint ini dengan backend Phalcon Anda
    const response = await axios.get('http://localhost:8000/patients')
    customers.value = response.data.data || response.data
  } catch (error) {
    console.error('Gagal mengambil data dari API, menggunakan data lokal sementara.', error)
    // Fallback data lokal jika backend mati/belum siap
    if (customers.value.length === 0) {
      customers.value = [
        { id: 1, name: 'Budi Santoso', phone: '08123456789', address: 'Jl. Merdeka No. 1' },
        { id: 2, name: 'Siti Aminah', phone: '08987654321', address: 'Jl. Sudirman No. 5' },
      ]
    }
  } finally {
    isLoading.value = false
  }
}

// --- 4. FUNGSI BUKA MODAL ---
const openAddModal = () => {
  isEditMode.value = false
  formData.value = { id: null, name: '', phone: '', address: '' }
  showModal.value = true
}

const openEditModal = (customer: any) => {
  isEditMode.value = true
  formData.value = { ...customer } // Copy data ke dalam form
  showModal.value = true
}

// --- 5. FUNGSI CREATE & UPDATE (Simpan Data) ---
const saveCustomer = async () => {
  try {
    if (isEditMode.value) {
      // PROSES UPDATE (PUT)
      // await axios.put(`http://localhost:8000/patients/${formData.value.id}`, formData.value)
      
      // Simulasi update di layar
      const index = customers.value.findIndex(c => c.id === formData.value.id)
      if (index !== -1) customers.value[index] = { ...formData.value }
      alert('Data pasien berhasil diperbarui!')

    } else {
      // PROSES CREATE (POST)
      // await axios.post('http://localhost:8000/patients', formData.value)
      
      // Simulasi tambah di layar
      customers.value.push({ ...formData.value, id: Date.now() }) 
      alert('Data pasien berhasil ditambahkan!')
    }
    
    showModal.value = false
    // fetchCustomers() // Refresh data dari server
  } catch (error) {
    console.error(error)
    alert('Terjadi kesalahan saat menyimpan data.')
  }
}

// --- 6. FUNGSI DELETE (Hapus Data) ---
const deleteCustomer = async (id: number) => {
  if (!confirm('Apakah Anda yakin ingin menghapus data pasien ini?')) return

  try {
    // await axios.delete(`http://localhost:8000/patients/${id}`)
    
    // Simulasi hapus di layar
    customers.value = customers.value.filter(c => c.id !== id)
    
    alert('Data pasien berhasil dihapus!')
    // fetchCustomers()
  } catch (error) {
    console.error(error)
    alert('Terjadi kesalahan saat menghapus data.')
  }
}

// Panggil fetch saat halaman dimuat
onMounted(() => {
  fetchCustomers()
})
</script>

<template>
  <main class="min-h-screen bg-gray-100 p-8 relative">
    <div class="max-w-7xl mx-auto bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
      
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Manajemen Data Customer (Pasien)</h2>
        <button @click="openAddModal" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-xl shadow transition-colors">
          + Tambah Pasien
        </button>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-gray-50 text-gray-600 border-b-2 border-gray-200">
              <th class="p-4 font-bold">ID</th>
              <th class="p-4 font-bold">Nama Pasien</th>
              <th class="p-4 font-bold">No. HP</th>
              <th class="p-4 font-bold">Alamat</th>
              <th class="p-4 font-bold text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="isLoading">
              <td colspan="5" class="p-8 text-center text-gray-500 font-medium">Memuat data...</td>
            </tr>
            <tr v-else-if="customers.length === 0">
              <td colspan="5" class="p-8 text-center text-gray-500 font-medium">Belum ada data pasien.</td>
            </tr>
            <tr v-else v-for="(c, index) in customers" :key="index" class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
              <td class="p-4 text-gray-500">#{{ c.id }}</td>
              <td class="p-4 font-semibold text-gray-800">{{ c.name }}</td>
              <td class="p-4">{{ c.phone }}</td>
              <td class="p-4 text-gray-600 truncate max-w-xs">{{ c.address }}</td>
              <td class="p-4 flex justify-center gap-3">
                <button @click="openEditModal(c)" class="text-yellow-600 hover:text-yellow-800 bg-yellow-50 hover:bg-yellow-100 p-2 rounded-lg transition-colors" title="Edit">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                </button>
                <button @click="deleteCustomer(c.id)" class="text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 p-2 rounded-lg transition-colors" title="Hapus">
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
          <h3 class="font-bold text-lg text-gray-800">{{ isEditMode ? 'Edit Data Pasien' : 'Tambah Pasien Baru' }}</h3>
          <button @click="showModal = false" class="text-gray-400 hover:text-red-500 font-bold text-xl transition-colors">&times;</button>
        </div>

        <div class="p-6 space-y-4">
          <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">Nama Lengkap</label>
            <input type="text" v-model="formData.name" placeholder="Masukkan nama pasien" class="w-full border-2 border-gray-200 rounded-lg p-2.5 focus:border-blue-500 focus:outline-none transition-colors">
          </div>
          
          <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">Nomor Handphone</label>
            <input type="tel" v-model="formData.phone" placeholder="Contoh: 08123456789" class="w-full border-2 border-gray-200 rounded-lg p-2.5 focus:border-blue-500 focus:outline-none transition-colors">
          </div>

          <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">Alamat Domisili</label>
            <textarea v-model="formData.address" rows="3" placeholder="Masukkan alamat lengkap" class="w-full border-2 border-gray-200 rounded-lg p-2.5 focus:border-blue-500 focus:outline-none transition-colors"></textarea>
          </div>
        </div>

        <div class="p-4 border-t border-gray-100 bg-gray-50 flex gap-3 justify-end">
          <button @click="showModal = false" class="px-5 py-2.5 font-bold text-gray-600 bg-white border border-gray-300 rounded-xl hover:bg-gray-100 transition-colors">Batal</button>
          <button @click="saveCustomer" class="px-5 py-2.5 font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-md transition-colors">
            {{ isEditMode ? 'Simpan Perubahan' : 'Simpan Baru' }}
          </button>
        </div>

      </div>
    </div>
  </main>
</template>