<script setup lang="ts">
import { RouterLink } from 'vue-router'
</script>

<template>
  <nav class="bg-blue-700 text-white shadow-md">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-16">
        
        <div class="flex-shrink-0 flex items-center gap-2">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
          <span class="font-extrabold text-xl tracking-wider">POS KLINIK</span>
        </div>

        <div class="flex space-x-2">
          <RouterLink to="/" active-class="bg-blue-800" class="hover:bg-blue-600 px-4 py-2 rounded-lg font-medium transition-colors">🛒 Kasir</RouterLink>
          <RouterLink to="/history" active-class="bg-blue-800" class="hover:bg-blue-600 px-4 py-2 rounded-lg font-medium transition-colors">🧾 Riwayat</RouterLink>
          <RouterLink to="/customers" active-class="bg-blue-800" class="hover:bg-blue-600 px-4 py-2 rounded-lg font-medium transition-colors">👥 Customer</RouterLink>
          <RouterLink to="/products" active-class="bg-blue-800" class="hover:bg-blue-600 px-4 py-2 rounded-lg font-medium transition-colors">💊 Barang & Jasa</RouterLink>
        </div>

      </div>
    </div>
  </nav>
</template>