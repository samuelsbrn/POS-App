<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { usePosStore } from '../stores/posStore'
import { storeToRefs } from 'pinia'
import axios from 'axios'

const posStore = usePosStore()
// Tarik data pasien dari gudang pusat
const { patients } = storeToRefs(posStore) 

const isLoading = ref(false)
const showModal = ref(false)
const isEditMode = ref(false)

const formData = ref({ id: null as number | null, mrn: '', nik: '', name: '', gender: 'L', dob: '', phone: '', address: '' })

const openAddModal = () => {
  isEditMode.value = false
  const randomMRN = 'RM-' + Math.floor(10000 + Math.random() * 90000)
  formData.value = { id: null, mrn: randomMRN, nik: '', name: '', gender: 'L', dob: '', phone: '', address: '' }
  showModal.value = true
}

const openEditModal = (customer: any) => {
  isEditMode.value = true
  formData.value = { ...customer }
  showModal.value = true
}

const saveCustomer = async () => {
  // Logika update/create ke API tetap di sini, 
  // tapi setelah berhasil, kita perintahkan Store untuk mengambil data terbaru
  try {
    if (isEditMode.value) {
      // await axios.put(...)
      const index = patients.value.findIndex(c => c.id === formData.value.id)
      if (index !== -1) patients.value[index] = { ...formData.value } // Ubah data lokal sementara
    } else {
      // await axios.post(...)
      patients.value.push({ ...formData.value, id: Date.now() }) // Ubah data lokal sementara
    }
    
    // Nanti jika API Phalcon jalan, cukup aktifkan baris ini untuk merefresh gudang pusat:
    // await posStore.fetchPatients(true) 
    
    showModal.value = false
  } catch (error) { console.error(error) }
}

const deleteCustomer = async (id: number) => {
  if (!confirm('Hapus data pasien ini?')) return
  // await axios.delete(...)
  patients.value = patients.value.filter(c => c.id !== id)
}

onMounted(async () => {
  isLoading.value = true
  await posStore.fetchPatients() // Panggil dari gudang
  isLoading.value = false
})
</script>

<template>
  <main class="min-h-screen bg-gray-100 p-8 relative">
    <div class="max-w-7xl mx-auto bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
      
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Manajemen Pasien (Customer)</h2>
        <button @click="openAddModal" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-xl shadow transition-colors">+ Tambah Pasien</button>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-gray-50 text-gray-600 border-b-2 border-gray-200">
              <th class="p-4 font-bold">MRN / NIK</th>
              <th class="p-4 font-bold">Nama Pasien</th>
              <th class="p-4 font-bold">L/P & Tgl Lahir</th>
              <th class="p-4 font-bold">Kontak</th>
              <th class="p-4 font-bold text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(c, index) in customers" :key="index" class="border-b border-gray-100 hover:bg-gray-50">
              <td class="p-4">
                <div class="font-bold text-blue-700">{{ c.mrn }}</div>
                <div class="text-xs text-gray-500">{{ c.nik }}</div>
              </td>
              <td class="p-4 font-semibold text-gray-800">{{ c.name }}</td>
              <td class="p-4 text-sm text-gray-600">
                <span :class="c.gender === 'L' ? 'bg-blue-100 text-blue-700' : 'bg-pink-100 text-pink-700'" class="px-2 py-0.5 rounded font-bold mr-2">{{ c.gender }}</span>
                {{ c.dob }}
              </td>
              <td class="p-4 text-sm text-gray-600">{{ c.phone }}</td>
              <td class="p-4 flex justify-center gap-2">
                <button @click="openEditModal(c)" class="text-yellow-600 hover:text-yellow-800 bg-yellow-50 p-2 rounded-lg">Edit</button>
                <button @click="deleteCustomer(c.id)" class="text-red-600 hover:text-red-800 bg-red-50 p-2 rounded-lg">Hapus</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="showModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
      <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden">
        <div class="bg-gray-50 border-b border-gray-200 p-4 flex justify-between items-center">
          <h3 class="font-bold text-lg text-gray-800">{{ isEditMode ? 'Edit Pasien' : 'Registrasi Pasien Baru' }}</h3>
          <button @click="showModal = false" class="text-gray-400 hover:text-red-500 font-bold text-xl">&times;</button>
        </div>

        <div class="p-6 grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">MRN (Medical Record Number)</label>
            <input type="text" v-model="formData.mrn" class="w-full border-2 border-gray-200 rounded-lg p-2 bg-gray-50" readonly>
          </div>
          <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">NIK (Nomor KTP)</label>
            <input type="text" v-model="formData.nik" placeholder="16 digit NIK" class="w-full border-2 border-gray-200 rounded-lg p-2">
          </div>
          <div class="col-span-2">
            <label class="block text-sm font-bold text-gray-700 mb-1">Nama Lengkap</label>
            <input type="text" v-model="formData.name" placeholder="Nama sesuai KTP" class="w-full border-2 border-gray-200 rounded-lg p-2">
          </div>
          <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">Jenis Kelamin</label>
            <select v-model="formData.gender" class="w-full border-2 border-gray-200 rounded-lg p-2">
              <option value="L">Laki-laki</option>
              <option value="P">Perempuan</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">Tanggal Lahir</label>
            <input type="date" v-model="formData.dob" class="w-full border-2 border-gray-200 rounded-lg p-2">
          </div>
          <div class="col-span-2">
            <label class="block text-sm font-bold text-gray-700 mb-1">Nomor Handphone</label>
            <input type="tel" v-model="formData.phone" class="w-full border-2 border-gray-200 rounded-lg p-2">
          </div>
          <div class="col-span-2">
            <label class="block text-sm font-bold text-gray-700 mb-1">Alamat Domisili</label>
            <textarea v-model="formData.address" rows="2" class="w-full border-2 border-gray-200 rounded-lg p-2"></textarea>
          </div>
        </div>

        <div class="p-4 border-t border-gray-100 bg-gray-50 flex gap-3 justify-end">
          <button @click="showModal = false" class="px-5 py-2 font-bold text-gray-600 bg-white border rounded-xl hover:bg-gray-100">Batal</button>
          <button @click="saveCustomer" class="px-5 py-2 font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl">Simpan</button>
        </div>
      </div>
    </div>
  </main>
</template>