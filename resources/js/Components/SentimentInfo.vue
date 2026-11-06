<script setup>
/**
 * Label sentimen yang dapat diklik: menjelaskan MENGAPA sebuah berita/unggahan berlabel positif/netral/negatif
 * (kata/pola yang cocok dari App\Support\NewsSentiment) dan metodologinya.
 *
 * Props:
 *  - sentiment: label tersimpan ('positif'|'netral'|'negatif')
 *  - reason: hasil NewsSentiment::explain()/explainSocial() untuk item ini (null = mode metodologi saja)
 *  - kind: 'media' | 'social'
 *  - lexicon: NewsSentiment::lexicon() (opsional; untuk "lihat daftar kata")
 *  - variant: 'badge' (label berwarna) | 'dot' (titik kecil + label ringkas) | 'icon' (hanya ikon ⓘ, untuk kartu statistik)
 */
import { ref, computed, nextTick, onBeforeUnmount } from 'vue';
import { Info, X } from 'lucide-vue-next';

const props = defineProps({
  sentiment: { type: String, default: null },
  reason: { type: Object, default: null },
  kind: { type: String, default: 'media' },
  lexicon: { type: Object, default: null },
  variant: { type: String, default: 'badge' },
});

const open = ref(false);
const showWords = ref(false);
const anchor = ref(null);
const pos = ref({ top: 0, left: 0 });
const panelId = `sentiment-info-${Math.random().toString(36).slice(2, 9)}`;

const label = computed(() => ({ positif: 'Positif', netral: 'Netral', negatif: 'Negatif' }[props.sentiment] ?? 'Belum dinilai'));
const tone = computed(() => ({
  positif: 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-500/15 dark:text-emerald-300 dark:border-emerald-500/40',
  negatif: 'bg-rose-100 text-rose-800 border-rose-300 dark:bg-rose-500/15 dark:text-rose-300 dark:border-rose-500/40',
  netral: 'bg-slate-100 text-slate-700 border-slate-300 dark:bg-slate-700/40 dark:text-slate-200 dark:border-slate-600',
}[props.sentiment] ?? 'bg-slate-100 text-slate-600 border-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700'));
const dotTone = computed(() => ({ positif: 'bg-emerald-500', negatif: 'bg-rose-500' }[props.sentiment] ?? 'bg-slate-400'));
// Label tersimpan berbeda dari aturan saat ini (mis. diisi sumber eksternal sebelum aturan diperbarui).
const differs = computed(() => props.reason && props.sentiment && props.reason.sentiment !== props.sentiment);
const wordGroups = computed(() => {
  const l = props.lexicon;
  if (!l) return [];
  const groups = [
    { title: 'Konteks Polri', words: l.police_context },
    { title: 'Isu sensitif bagi Polri (→ negatif bila ada konteks Polri)', words: l.negative_polri },
    { title: 'Gangguan kamtibmas (penanda negatif)', words: l.negative_kamtibmas },
    { title: 'Penanda positif', words: l.positive },
  ];
  if (props.kind === 'social') {
    groups.push({ title: 'Keluhan warganet (penanda negatif, khusus medsos)', words: l.social_negative });
    groups.push({ title: 'Apresiasi warganet (penanda positif, khusus medsos)', words: l.social_positive });
  }
  return groups.filter((g) => g.words?.length);
});

const place = () => {
  const r = anchor.value?.getBoundingClientRect();
  if (!r) return;
  const width = Math.min(360, window.innerWidth - 16);
  const left = Math.min(Math.max(8, r.left), window.innerWidth - width - 8);
  // Buka ke sisi yang ruangnya lebih lega; tinggi maksimum = ruang yang tersedia agar popup selalu di dalam layar.
  const spaceBelow = window.innerHeight - r.bottom - 14;
  const spaceAbove = r.top - 14;
  pos.value = spaceBelow >= spaceAbove
    ? { top: r.bottom + 6, left, width, maxHeight: spaceBelow }
    : { bottom: window.innerHeight - r.top + 6, left, width, maxHeight: spaceAbove };
};
const close = () => {
  open.value = false;
  showWords.value = false;
  window.removeEventListener('scroll', close, true);
  window.removeEventListener('resize', close);
  document.removeEventListener('keydown', onKey);
  document.removeEventListener('mousedown', onOutside);
};
const onKey = (e) => { if (e.key === 'Escape') close(); };
const onOutside = (e) => {
  if (!document.getElementById(panelId)?.contains(e.target) && !anchor.value?.contains(e.target)) close();
};
const toggle = async () => {
  if (open.value) return close();
  place();
  open.value = true;
  await nextTick();
  window.addEventListener('scroll', close, true);
  window.addEventListener('resize', close);
  document.addEventListener('keydown', onKey);
  document.addEventListener('mousedown', onOutside);
};
onBeforeUnmount(close);
</script>

<template>
  <button
    ref="anchor"
    type="button"
    @click.stop.prevent="toggle"
    :aria-expanded="open"
    :aria-controls="panelId"
    :title="variant === 'icon' ? 'Cara SIMOD menentukan sentimen' : `Sentimen ${label}: klik untuk melihat alasannya`"
    class="inline-flex items-center gap-1 cursor-pointer align-middle"
    :class="variant === 'badge' ? ['px-2 py-0.5 rounded-md border text-[10px] font-black uppercase tracking-wide', tone] : variant === 'dot' ? 'text-[10px] font-bold text-slate-600 dark:text-slate-300' : 'p-0.5 rounded text-slate-400 hover:text-amber-500'"
  >
    <span v-if="variant === 'dot'" class="w-2 h-2 rounded-full shrink-0" :class="dotTone"></span>
    <span v-if="variant !== 'icon'">{{ label }}</span>
    <Info class="w-3 h-3 shrink-0 opacity-70" :aria-label="variant === 'icon' ? 'Metodologi sentimen' : undefined" />
  </button>

  <Teleport to="body">
    <div
      v-if="open"
      :id="panelId"
      role="dialog"
      :aria-label="`Penjelasan sentimen ${label}`"
      class="fixed z-100 overflow-y-auto rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-[#0b1426] text-slate-700 dark:text-slate-200 shadow-2xl p-4 space-y-3 text-[12px] leading-relaxed"
      :style="{ top: pos.top != null ? pos.top + 'px' : undefined, bottom: pos.bottom != null ? pos.bottom + 'px' : undefined, left: pos.left + 'px', width: pos.width + 'px', maxHeight: Math.max(160, pos.maxHeight) + 'px' }"
      @click.stop
    >
      <div class="flex items-start justify-between gap-2">
        <p class="font-black text-slate-900 dark:text-white text-sm">
          {{ variant === 'icon' ? 'Bagaimana sentimen ditentukan?' : `Mengapa ${label}?` }}
        </p>
        <button type="button" @click="close" class="p-0.5 rounded text-slate-400 hover:text-slate-700 dark:hover:text-white cursor-pointer" aria-label="Tutup"><X class="w-4 h-4" /></button>
      </div>

      <div v-if="reason" class="rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-3 space-y-1">
        <p>{{ reason.reason }}</p>
        <p class="text-[11px] text-slate-500 dark:text-slate-400">Dinilai dari: {{ reason.basis }}{{ reason.police_context ? ' · menyebut Polri' : '' }}</p>
        <p v-if="differs" class="text-[11px] font-semibold text-amber-700 dark:text-amber-300">
          Label tersimpan "{{ label }}" berasal dari sumber/penilaian sebelumnya; aturan saat ini menghasilkan "{{ reason.sentiment }}".
        </p>
      </div>

      <div class="space-y-1.5">
        <p class="font-bold text-slate-900 dark:text-white">Metodologi (berbasis aturan, tanpa AI)</p>
        <ol class="list-decimal pl-4 space-y-1 text-[11px]">
          <li>Dinilai dari sudut pandang <b>citra dan kewaspadaan Polri</b>, bukan baik-buruknya peristiwa. Penindakan oleh polisi (mis. "polisi tangkap pengedar") dihitung <b>positif</b> sebagai kinerja.</li>
          <li>Bila teks menyebut Polri <b>dan</b> memuat isu sensitif (mis. oknum, pungli, salah tangkap, anggota ditangkap/diperiksa) → <b>Negatif</b>.</li>
          <li>Selain itu, jumlah <b>penanda negatif</b> (gangguan kamtibmas: bentrok, begal, tewas…<span v-if="kind === 'social'">; keluhan warganet: kecewa, bohong, lambat…</span>) dibandingkan dengan <b>penanda positif</b> (apresiasi, berhasil, humanis…<span v-if="kind === 'social'">; mantap, terima kasih, salut…</span>). Yang lebih banyak menentukan label; sama banyak atau tidak ada → <b>Netral</b>.</li>
          <li v-if="kind === 'media'">Judul dinilai lebih dulu; ringkasan baru dipakai bila judul sendiri netral.</li>
          <li v-else>Yang dinilai adalah isi unggahan yang tersimpan (judul + cuplikan untuk hasil pencarian).</li>
          <li>Kata dicocokkan utuh ("aman" tidak cocok dengan "pengamanan").</li>
        </ol>
        <p class="text-[11px] text-slate-500 dark:text-slate-400">
          Keterbatasan: aturan tidak memahami sindiran, konteks panjang, atau gambar/video. Label ini adalah klasifikasi awal dan perlu ditinjau analis. Ringkasan analisis AI (SADEWA) adalah penilaian terpisah.
        </p>
      </div>

      <div v-if="wordGroups.length">
        <button type="button" @click="showWords = !showWords" class="text-[11px] font-bold text-amber-700 dark:text-amber-400 hover:underline cursor-pointer">
          {{ showWords ? 'Sembunyikan daftar kata' : 'Lihat daftar kata yang dipakai' }}
        </button>
        <div v-if="showWords" class="mt-2 space-y-2">
          <div v-for="g in wordGroups" :key="g.title">
            <p class="text-[10px] font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ g.title }}</p>
            <p class="text-[11px]">{{ g.words.join(', ') }}</p>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
