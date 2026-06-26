<script setup lang="ts">
import { RouterView } from 'vue-router'
import TopNavbar from './components/TopNavbar.vue' // Import Navbar-nya
</script>

<template>
  <TopNavbar />
  
  <RouterView />
</template>