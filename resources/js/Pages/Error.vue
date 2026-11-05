<script setup>
/**
 * Halaman galat resmi (403, 404, 419, 429, 500, 503). Berdiri sendiri (tanpa menu/props bersama) agar tetap tampil
 * walau sesi atau basis data bermasalah; mengikuti tema terang/gelap.
 */
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { withBase } from '../utils/basePath';
import { ShieldAlert, SearchX, Clock, Hourglass, ServerCrash, Wrench, ArrowLeft } from 'lucide-vue-next';

const props = defineProps({ status: { type: Number, default: 500 } });

const META = {
  403: { icon: ShieldAlert, title: 'Akses ditolak', text: 'Anda tidak memiliki wewenang untuk membuka halaman atau melakukan tindakan ini. Hubungi administrator bila seharusnya Anda memiliki akses.' },
  404: { icon: SearchX, title: 'Halaman tidak ditemukan', text: 'Alamat yang Anda tuju tidak ada atau sudah dipindahkan.' },
  419: { icon: Clock, title: 'Sesi halaman kedaluwarsa', text: 'Halaman terlalu lama dibiarkan terbuka. Muat ulang halaman lalu ulangi tindakan Anda.' },
  429: { icon: Hourglass, title: 'Terlalu banyak permintaan', text: 'Mohon tunggu sebentar sebelum mencoba lagi.' },
  500: { icon: ServerCrash, title: 'Terjadi kesalahan pada server', text: 'Kesalahan telah dicatat. Coba lagi beberapa saat; bila berulang, hubungi administrator.' },
  503: { icon: Wrench, title: 'Layanan sedang dalam pemeliharaan', text: 'Aplikasi sedang diperbarui. Silakan coba lagi beberapa menit lagi.' },
};
const meta = computed(() => META[props.status] || META[500]);
const reloadable = computed(() => [419, 429, 503].includes(props.status));
const goBack = () => { if (window.history.length > 1) window.history.back(); else window.location.href = withBase('/'); };
const reload = () => window.location.reload();
</script>

<template>
  <main class="min-h-screen flex items-center justify-center p-6 bg-slate-50 dark:bg-[#070d1d] text-slate-900 dark:text-white">
    <div class="w-full max-w-md text-center space-y-5">
      <div class="mx-auto w-16 h-16 rounded-2xl bg-amber-100 dark:bg-amber-400/10 text-amber-600 dark:text-amber-400 flex items-center justify-center">
        <component :is="meta.icon" class="w-8 h-8" />
      </div>
      <p class="text-xs font-black tracking-widest text-slate-500 dark:text-slate-400">KODE {{ status }}</p>
      <h1 class="text-2xl font-black tracking-tight">{{ meta.title }}</h1>
      <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">{{ meta.text }}</p>
      <div class="flex flex-wrap items-center justify-center gap-2.5 pt-2">
        <button type="button" @click="goBack" class="px-4 py-2.5 rounded-xl text-xs font-bold border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:border-amber-400 transition cursor-pointer inline-flex items-center gap-1.5">
          <ArrowLeft class="w-3.5 h-3.5" /> Kembali
        </button>
        <button v-if="reloadable" type="button" @click="reload" class="px-4 py-2.5 rounded-xl text-xs font-black bg-amber-400 hover:bg-amber-300 text-slate-950 transition cursor-pointer">Muat ulang</button>
        <Link v-else href="/" class="px-4 py-2.5 rounded-xl text-xs font-black bg-amber-400 hover:bg-amber-300 text-slate-950 transition">Ke Command Center</Link>
      </div>
    </div>
  </main>
</template>
