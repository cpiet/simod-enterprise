<script setup>
import { ref } from 'vue';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import { Image, Video, FileText, Filter, Eye } from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  metrics: Object,
});

const activeType = ref('all');

const mediaItems = ref([
  {
    id: 1,
    title: 'Apel Gelar Pasukan Pengamanan Pilkada 2026',
    category: 'Giat Kepolisian',
    url: 'https://images.unsplash.com/photo-1541872703-74c5e44368f9?w=600&auto=format&fit=crop&q=80',
    type: 'photo',
    date: '28 Okt 2026',
  },
  {
    id: 2,
    title: 'Patroli Perairan Satpolairud di Perbatasan Pesisir',
    category: 'Harkamtibmas',
    url: 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=600&auto=format&fit=crop&q=80',
    type: 'photo',
    date: '27 Okt 2026',
  },
  {
    id: 3,
    title: 'Sosialisasi Tertib Berlalu Lintas di Jalur Trans',
    category: 'Lalu Lintas',
    url: 'https://images.unsplash.com/photo-1449965408869-eaa3f722e40d?w=600&auto=format&fit=crop&q=80',
    type: 'photo',
    date: '26 Okt 2026',
  },
]);
</script>

<template>
  <EnterpriseLayout
    :all-poldas="allPoldas"
    :selected-polda="selectedPolda"
    :selected-code="selectedCode"
    :metrics="metrics"
  >
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-sky-500/20 text-sky-400 border border-sky-500/40">
            Penyimpanan Terproteksi
          </span>
          <span class="text-xs font-bold text-slate-400">{{ selectedPolda?.name }}</span>
        </div>
        <h1 class="text-2xl md:text-3xl font-black text-white tracking-tight mt-1">
          Galeri Dokumentasi Media & Bukti
        </h1>
        <p class="text-xs md:text-sm text-slate-300">
          Repositori foto, video kegiatan dinas, dan dokumentasi alat bukti peliputan Humas Polri.
        </p>
      </div>
    </div>

    <!-- Gallery Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
      <div
        v-for="item in mediaItems"
        :key="item.id"
        class="group rounded-3xl bg-[#0b1426] border border-slate-800 overflow-hidden shadow-xl hover:border-slate-600 transition"
      >
        <div class="relative h-48 overflow-hidden bg-slate-900">
          <img
            :src="item.url"
            :alt="item.title"
            class="w-full h-full object-cover group-hover:scale-105 transition duration-500"
          />
          <div class="absolute inset-0 bg-gradient-to-t from-[#0b1426] via-transparent to-transparent"></div>
          <span class="absolute top-3 left-3 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-slate-950/80 text-amber-400 border border-slate-700 backdrop-blur-md">
            {{ item.category }}
          </span>
        </div>

        <div class="p-4 space-y-2">
          <h3 class="text-sm font-extrabold text-white leading-snug">{{ item.title }}</h3>
          <p class="text-[11px] text-slate-400 font-mono">{{ item.date }}</p>
        </div>
      </div>
    </div>
  </EnterpriseLayout>
</template>
