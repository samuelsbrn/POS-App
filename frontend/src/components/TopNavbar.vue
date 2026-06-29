<script setup lang="ts">
import { RouterLink } from 'vue-router'
</script>

<template>
  <nav class="bg-teal-700 text-white shadow-lg border-b-4 border-teal-500 sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-18 py-3">
        
        <div class="flex-shrink-0 flex items-center gap-3">
          <div class="bg-white text-teal-700 p-2 rounded-lg shadow-sm">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
          </div>
          <div>
            <h1 class="font-extrabold text-xl tracking-widest leading-tight">ZiCare</h1>
            <p class="text-xs text-teal-200 tracking-wider font-semibold">HOSPITAL POS SYSTEM</p>
          </div>
        </div>

        <div class="hidden md:flex space-x-1 bg-teal-800/50 p-1 rounded-xl">
          <RouterLink to="/" active-class="bg-white text-teal-800 shadow-sm" class="text-teal-50 hover:bg-teal-600 px-5 py-2 rounded-lg font-bold transition-all text-sm tracking-wide">Pusat Kasir</RouterLink>
          <RouterLink to="/history" active-class="bg-white text-teal-800 shadow-sm" class="text-teal-50 hover:bg-teal-600 px-5 py-2 rounded-lg font-bold transition-all text-sm tracking-wide">Riwayat Tagihan</RouterLink>
          <RouterLink to="/customers" active-class="bg-white text-teal-800 shadow-sm" class="text-teal-50 hover:bg-teal-600 px-5 py-2 rounded-lg font-bold transition-all text-sm tracking-wide">Data Pasien</RouterLink>
          <RouterLink to="/products" active-class="bg-white text-teal-800 shadow-sm" class="text-teal-50 hover:bg-teal-600 px-5 py-2 rounded-lg font-bold transition-all text-sm tracking-wide">Farmasi & Layanan</RouterLink>
        </div>

      </div>
    </div>
  </nav>
</template>