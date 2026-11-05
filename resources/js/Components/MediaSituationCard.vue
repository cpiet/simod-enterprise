<script setup>
/**
 * "Situasi 24 Jam Terakhir" untuk pimpinan (Kadivhumas / Kabid Humas) di atas tab Briefing Radar Media Online.
 * Seluruh angka berasal dari data nyata di basis data (bukan keluaran AI) dan sudah menggabungkan berita ganda.
 */
import { computed } from 'vue';
import { AlertTriangle, Newspaper, Radio, MessageSquare, ExternalLink, ArrowRight } from 'lucide-vue-next';

const props = defineProps({
  situation: { type: Object, default: () => ({ top_negative: [], top_sources: [] }) },
  stats: { type: Object, default: () => ({}) },
  fresh: { type: Object, default: () => ({ level: 'red', label: '' }) },
  topConcern: { type: String, default: '' },
});

const emit = defineEmits(['show-redzone', 'copy-wa']);

const deltaOf = (cur, prev) => {
  const c = Number(cur) || 0;
  const p = Number(prev) || 0;
  if (!c && !p) return { text: 'belum ada berita', tone: 'text-slate-400' };
  if (!p) return { text: 'tanpa pembanding', tone: 'text-slate-400' };
  const d = c - p;
  if (d === 0) return { text: '— sama dengan 24 jam sebelumnya', tone: 'text-slate-400' };
  return { text: (d > 0 ? '▲ +' : '▼ ') + d + ' vs 24 jam sebelumnya', tone: d > 0 ? 'text-amber-300' : 'text-emerald-300' };
};

const totalDelta = computed(() => deltaOf(props.stats?.last24, props.stats?.prev24));
const negativeDelta = computed(() => {
  const base = deltaOf(props.stats?.negative24, props.stats?.negative_prev24);
  const rising = (props.stats?.negative24 || 0) > (props.stats?.negative_prev24 || 0);

  return { ...base, tone: rising ? 'text-rose-300 font-bold' : base.tone };
});

const freshTone = computed(() => ({
  green: 'text-emerald-300 border-emerald-500/40 bg-emerald-500/10',
  yellow: 'text-amber-300 border-amber-400/40 bg-amber-400/10',
  red: 'text-rose-300 border-rose-500/40 bg-rose-500/10',
}[props.fresh?.level] || 'text-slate-300 border-slate-600 bg-slate-800'));

const negatives = computed(() => props.situation?.top_negative || []);
const sources = computed(() => props.situation?.top_sources || []);
</script>

<template>
  <section class="rounded-3xl bg-gradient-to-br from-[#0c162e] to-[#080f20] border border-amber-400/30 p-5 md:p-6 shadow-2xl space-y-5" aria-label="Situasi 24 jam terakhir">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-2.5">
        <Radio class="w-4 h-4 text-amber-400" />
        <h2 class="text-sm font-black text-white uppercase tracking-wider">Situasi 24 Jam Terakhir</h2>
      </div>
      <span class="px-2.5 py-1 rounded-full text-[11px] font-bold border" :class="freshTone">{{ fresh.label }}</span>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
      <div class="rounded-2xl bg-[#070d19] border border-slate-800 p-4 space-y-1">
        <p class="text-[11px] font-bold text-slate-400 flex items-center gap-1.5"><Newspaper class="w-3.5 h-3.5 text-sky-400" /> Berita masuk</p>
        <p class="text-2xl font-black text-white leading-none">{{ stats?.last24 ?? 0 }}</p>
        <p class="text-[11px]" :class="totalDelta.tone">{{ totalDelta.text }}</p>
      </div>
      <div class="rounded-2xl bg-[#070d19] border border-slate-800 p-4 space-y-1">
        <p class="text-[11px] font-bold text-slate-400 flex items-center gap-1.5"><AlertTriangle class="w-3.5 h-3.5 text-rose-400" /> Zona Merah</p>
        <p class="text-2xl font-black leading-none" :class="(stats?.negative24 || 0) > 0 ? 'text-rose-400' : 'text-white'">{{ stats?.negative24 ?? 0 }}</p>
        <p class="text-[11px]" :class="negativeDelta.tone">{{ negativeDelta.text }}</p>
      </div>
      <div class="rounded-2xl bg-[#070d19] border border-slate-800 p-4 space-y-1">
        <p class="text-[11px] font-bold text-slate-400">Portal paling aktif</p>
        <ul v-if="sources.length" class="space-y-0.5">
          <li v-for="s in sources" :key="s.name" class="flex items-center justify-between gap-2 text-[11px] text-slate-200">
            <span class="truncate">{{ s.name }}</span>
            <span class="font-mono text-slate-400">{{ s.count }}</span>
          </li>
        </ul>
        <p v-else class="text-[11px] text-slate-400">Belum ada berita 24 jam terakhir.</p>
      </div>
    </div>

    <div v-if="topConcern" class="rounded-2xl bg-amber-400/5 border border-amber-400/30 p-4">
      <p class="text-[11px] font-black uppercase tracking-wider text-amber-300 mb-1">Perhatian utama (analisis AI terakhir)</p>
      <p class="text-xs text-slate-200 leading-relaxed">{{ topConcern }}</p>
    </div>

    <div class="space-y-2">
      <p class="text-[11px] font-black uppercase tracking-wider text-rose-300">Berita sensitif terbaru</p>
      <ul v-if="negatives.length" class="space-y-2">
        <li v-for="n in negatives" :key="n.id">
          <a
            :href="n.link"
            target="_blank"
            rel="noopener noreferrer"
            class="group flex items-start justify-between gap-3 rounded-xl bg-[#070d19] hover:bg-slate-900 border border-slate-800 hover:border-rose-500/40 px-3.5 py-2.5 transition"
          >
            <span class="min-w-0">
              <span class="block text-xs font-bold text-white group-hover:text-rose-200 leading-snug">{{ n.title }}</span>
              <span class="block text-[11px] text-slate-400 mt-0.5">{{ n.source_name }} &bull; {{ n.ago }}</span>
            </span>
            <ExternalLink class="w-3.5 h-3.5 text-slate-500 group-hover:text-rose-300 shrink-0 mt-0.5" />
          </a>
        </li>
      </ul>
      <p v-else class="text-xs text-slate-400 rounded-xl bg-[#070d19] border border-slate-800 px-3.5 py-3">
        Tidak ada berita bersentimen negatif dalam 24 jam terakhir.
      </p>
    </div>

    <div class="flex flex-wrap items-center gap-2 pt-1">
      <button
        type="button"
        @click="emit('show-redzone')"
        class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-rose-500/15 hover:bg-rose-500/25 text-rose-200 border border-rose-500/40 text-xs font-black transition cursor-pointer"
      >
        <span>Lihat semua Zona Merah</span>
        <ArrowRight class="w-3.5 h-3.5" />
      </button>
      <button
        type="button"
        @click="emit('copy-wa')"
        class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-300 border border-emerald-500/40 text-xs font-black transition cursor-pointer"
      >
        <MessageSquare class="w-3.5 h-3.5" />
        <span>Salin naskah WA pimpinan</span>
      </button>
    </div>
  </section>
</template>
