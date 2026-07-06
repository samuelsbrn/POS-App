<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { usePosStore } from '../stores/posStore'
import { storeToRefs } from 'pinia'

// Hubungkan ke Store
const posStore = usePosStore()
const { patients } = storeToRefs(posStore) 

const isLoading = ref(false)
const showModal = ref(false)
const isEditMode = ref(false)
const toast = ref({ show: false, message: '', type: 'success' })

const formData = ref({ id: null as number | null, mrn: '', nik: '', name: '', gender: 'L', dob: '', phone: '', address: '' })

const showToast = (message: string, type: 'success' | 'error' = 'success') => {
  toast.value = { show: true, message, type }
  setTimeout(() => { toast.value.show = false }, 3000)
}

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
  try {
    if (!formData.value.name || !formData.value.phone) {
      showToast('Nama dan nomor handphone wajib diisi!', 'error')
      return
    }

    if (isEditMode.value && formData.value.id) {
      posStore.updatePatient(formData.value.id, formData.value)
      showToast('Data pasien berhasil diperbarui!', 'success')
    } else {
      posStore.addPatient(formData.value)
      showToast('Data pasien berhasil ditambahkan!', 'success')
    }
    
    showModal.value = false
  } catch (error) { 
    showToast('Gagal menyimpan data pasien.', 'error')
    console.error(error) 
  }
}

const deleteCustomer = async (id: number) => {
  if (!confirm('Hapus data rekam medis pasien ini?')) return
  try {
    posStore.deletePatient(id)
    showToast('Data pasien berhasil dihapus!', 'success')
  } catch (error) {
    showToast('Gagal menghapus data pasien.', 'error')
    console.error(error)
  }
}

const refreshData = async () => {
  isLoading.value = true
  try {
    await posStore.fetchPatients(true)
    showToast('Data pasien berhasil diperbarui dari server!', 'success')
  } catch (error) {
    console.error(error)
  } finally {
    isLoading.value = false
  }
}

onMounted(async () => {
  isLoading.value = true
  try {
    await posStore.fetchPatients()
  } catch (error) {
    console.error(error)
  } finally {
    isLoading.value = false
  }
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
          <span class="w-2 h-8 bg-teal-500 rounded-full"></span> Data Master Pasien
        </h2>
        <div class="flex gap-3">
          <button @click="refreshData" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold py-3 px-6 rounded-2xl flex items-center gap-2 transition-colors uppercase tracking-wider text-sm shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            Refresh
          </button>
          <button @click="openAddModal" class="bg-teal-600 hover:bg-teal-700 active:bg-teal-800 text-white font-bold py-3 px-6 rounded-2xl shadow-lg transition-all uppercase tracking-wider text-sm">
            + Registrasi Pasien
          </button>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-slate-50 text-slate-500 border-b-2 border-slate-200 uppercase tracking-wider text-xs">
              <th class="p-4 font-bold">MRN / NIK</th>
              <th class="p-4 font-bold">Nama Pasien</th>
              <th class="p-4 font-bold">Demografi</th>
              <th class="p-4 font-bold">Kontak</th>
              <th class="p-4 font-bold text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="isLoading">
              <td colspan="5" class="p-8 text-center text-slate-400 font-medium">Memuat data dari server...</td>
            </tr>
            <tr v-else-if="patients.length === 0">
              <td colspan="5" class="p-8 text-center text-slate-400 font-medium">Belum ada data pasien. Silakan tambahkan data baru.</td>
            </tr>
            <tr v-else v-for="(p, index) in patients" :key="p.id || index" class="border-b border-slate-100 hover:bg-teal-50/50 transition-colors">
              <td class="p-4">
                <div class="font-black text-teal-700 tracking-wide">{{ p.mrn }}</div>
                <div class="text-xs text-slate-400 font-mono mt-1">{{ p.nik }}</div>
              </td>
              <td class="p-4 font-bold text-slate-700">{{ p.name }}</td>
              <td class="p-4 text-sm text-slate-600">
                <span :class="p.gender === 'L' ? 'bg-blue-100 text-blue-700' : 'bg-pink-100 text-pink-700'" class="px-2 py-0.5 rounded font-black text-xs mr-2">{{ p.gender }}</span>
                {{ p.dob }}
              </td>
              <td class="p-4 text-sm text-slate-600 font-mono">{{ p.phone }}</td>
              <td class="p-4 flex justify-center gap-3">
                <button @click="openEditModal(p)" class="text-yellow-600 bg-yellow-50 hover:bg-yellow-100 p-2 rounded-xl transition-all font-bold text-xs uppercase tracking-widest">Edit</button>
                <button @click="deleteCustomer(p.id)" class="text-red-600 bg-red-50 hover:bg-red-100 p-2 rounded-xl transition-all font-bold text-xs uppercase tracking-widest">Hapus</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="showModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
      <div class="bg-white w-full max-w-2xl rounded-3xl shadow-2xl overflow-hidden border border-slate-100">
        <div class="bg-teal-600 p-5 flex justify-between items-center">
          <h3 class="text-white font-black tracking-widest uppercase text-sm">{{ isEditMode ? 'Edit Profil Pasien' : 'Registrasi Pasien Baru' }}</h3>
          <button @click="showModal = false" class="text-teal-100 hover:text-white font-black text-2xl">&times;</button>
        </div>

        <div class="p-8 grid grid-cols-2 gap-5">
          <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Nomor Rekam Medis (MRN)</label>
            <input type="text" v-model="formData.mrn" class="w-full border-2 border-slate-200 rounded-xl p-3 bg-slate-100 font-black text-slate-600 cursor-not-allowed" readonly>
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">NIK (KTP)</label>
            <input type="text" v-model="formData.nik" placeholder="16 digit NIK" class="w-full border-2 border-slate-200 rounded-xl p-3 font-bold text-slate-700 focus:border-teal-500 focus:ring-0">
          </div>
          <div class="col-span-2">
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Nama Lengkap Pasien</label>
            <input type="text" v-model="formData.name" placeholder="Sesuai kartu identitas" class="w-full border-2 border-slate-200 rounded-xl p-3 font-bold text-slate-700 focus:border-teal-500 focus:ring-0">
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Jenis Kelamin</label>
            <select v-model="formData.gender" class="w-full border-2 border-slate-200 rounded-xl p-3 font-bold text-slate-700 focus:border-teal-500 focus:ring-0">
              <option value="L">Laki-laki</option>
              <option value="P">Perempuan</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Tanggal Lahir</label>
            <input type="date" v-model="formData.dob" class="w-full border-2 border-slate-200 rounded-xl p-3 font-bold text-slate-700 focus:border-teal-500 focus:ring-0">
          </div>
          <div class="col-span-2">
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Nomor Handphone</label>
            <input type="tel" v-model="formData.phone" class="w-full border-2 border-slate-200 rounded-xl p-3 font-bold text-slate-700 focus:border-teal-500 focus:ring-0">
          </div>
          <div class="col-span-2">
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Alamat Domisili</label>
            <textarea v-model="formData.address" rows="2" class="w-full border-2 border-slate-200 rounded-xl p-3 font-bold text-slate-700 focus:border-teal-500 focus:ring-0"></textarea>
          </div>
        </div>

        <div class="p-5 border-t border-slate-100 bg-slate-50 flex gap-4">
          <button @click="showModal = false" class="w-1/3 py-4 font-bold border-2 border-slate-200 rounded-2xl bg-white text-slate-500 hover:bg-slate-50 uppercase tracking-wider text-xs">Batal</button>
          <button @click="saveCustomer" class="w-2/3 py-4 font-black text-white bg-teal-500 hover:bg-teal-600 rounded-2xl shadow-lg uppercase tracking-wider text-sm">Simpan Data Medis</button>
        </div>
      </div>
    </div>
  </main>
</template>