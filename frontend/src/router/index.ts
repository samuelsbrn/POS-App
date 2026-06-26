import { createRouter, createWebHistory } from 'vue-router'
import PosView from '../views/PosView.vue'
import HistoryView from '../views/HistoryView.vue'
import CustomersView from '../views/CustomersView.vue' // <-- Import
import ProductsView from '../views/ProductsView.vue'   // <-- Import

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', name: 'pos', component: PosView },
    { path: '/history', name: 'history', component: HistoryView },
    { path: '/customers', name: 'customers', component: CustomersView }, // <-- Rute Customer
    { path: '/products', name: 'products', component: ProductsView }     // <-- Rute Barang
  ]
})

export default router