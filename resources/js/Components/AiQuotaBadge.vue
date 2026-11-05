<script setup>
/**
 * Indikator pemakaian AI untuk pengguna. Netral (tanpa vendor/model) dan tidak membuka batas total:
 * jumlah sisa hanya tampil saat tinggal sedikit. Data dari props bersama `aiQuota` (AiQuota::publicStatus).
 */
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

defineProps({ compact: { type: Boolean, default: false } });

const page = usePage();
const q = computed(() => page.props.aiQuota || null);

const view = computed(() => {
  const s = q.value;
  if (!s) return null;
  switch (s.state) {
    case 'exhausted': return { dot: 'bg-rose-500', text: 'Batas analisis AI harian Anda tercapai', cls: 'text-rose-600 dark:text-rose-400' };
    case 'blocked': return { dot: 'bg-amber-500', text: 'Layanan AI dibatasi sementara, coba beberapa menit lagi', cls: 'text-amber-700 dark:text-amber-400' };
    case 'low': return { dot: 'bg-amber-500', text: `Sisa ${s.remaining} analisis AI hari ini`, cls: 'text-amber-700 dark:text-amber-400' };
    default: return { dot: 'bg-emerald-500', text: 'Analisis AI tersedia', cls: 'text-slate-500 dark:text-slate-400' };
  }
});
</script>

<template>
  <span v-if="view" class="inline-flex items-center gap-1.5 text-[11px] font-semibold" :class="view.cls" :title="view.text">
    <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="view.dot"></span>
    <span v-if="!compact">{{ view.text }}</span>
  </span>
</template>
