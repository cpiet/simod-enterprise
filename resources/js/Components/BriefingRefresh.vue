<script setup>
/**
 * Perbarui Briefing (Command Center): satu aksi yang menyegarkan Radar Media Online dan Radar Media Sosial.
 * Memanggil endpoint yang sudah ada satu per satu sehingga RBAC dan tenant tiap endpoint tidak berubah.
 * Langkah yang tidak diizinkan peran pengguna dilewati; kegagalan satu jalur tidak membatalkan jalur lain.
 */
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';
import { router, usePage } from '@inertiajs/vue3';
import { RefreshCw, CheckCircle2, XCircle, MinusCircle, Loader2, X } from 'lucide-vue-next';

const props = defineProps({
  mediaUpdatedAt: { type: String, default: null },
  socialUpdatedAt: { type: String, default: null },
});

const page = usePage();
const perms = computed(() => page.props.auth?.permissions || {});
const canMedia = computed(() => !!perms.value.media_intelligence_ai);
const canSocial = computed(() => !!perms.value.social_intelligence_sync);
const canRefresh = computed(() => canMedia.value || canSocial.value);

const FRESH_MIN = 5; // batas "baru diperbarui" (menit) agar tidak memicu proses/biaya berulang
const STALE_HOURS = 6;
const now = ref(Date.now());
let ticker = null;
onMounted(() => { ticker = setInterval(() => { now.value = Date.now(); }, 30000); });
onBeforeUnmount(() => clearInterval(ticker));

const minutesAgo = (iso) => {
  if (!iso) return null;
  const t = new Date(iso).getTime();
  return isNaN(t) ? null : Math.max(0, Math.floor((now.value - t) / 60000));
};
const label = (iso) => {
  const m = minutesAgo(iso);
  if (m === null) return 'belum ada';
  if (m < 1) return 'baru saja';
  if (m < 60) return `${m} mnt lalu`;
  if (m < 1440) return `${Math.floor(m / 60)} jam lalu`;
  return `${Math.floor(m / 1440)} hari lalu`;
};
const isStale = (iso) => { const m = minutesAgo(iso); return m === null || m > STALE_HOURS * 60; };
const isFresh = (iso) => { const m = minutesAgo(iso); return m !== null && m < FRESH_MIN; };

// Status tiap jalur: idle | running | done | skipped | error
const media = ref({ state: 'idle', text: '' });
const social = ref({ state: 'idle', text: '' });
const running = computed(() => media.value.state === 'running' || social.value.state === 'running');
const hasResult = computed(() => [media.value, social.value].some((t) => t.state !== 'idle'));

const allFresh = computed(() => {
  const checks = [];
  if (canMedia.value) checks.push(isFresh(props.mediaUpdatedAt));
  if (canSocial.value) checks.push(isFresh(props.socialUpdatedAt));
  return checks.length > 0 && checks.every(Boolean);
});

const errText = (e, fallback) => e?.response?.data?.message || fallback;

const runMedia = async () => {
  if (!canMedia.value) return;
  if (isFresh(props.mediaUpdatedAt)) {
    media.value = { state: 'skipped', text: 'Baru diperbarui, dilewati' };
    return;
  }
  media.value = { state: 'running', text: 'Menarik berita dan menganalisis…' };
  try {
    const res = await axios.post('/media-intelligence/analyze-gemini?sync=1');
    media.value = res.data?.skipped
      ? { state: 'skipped', text: res.data.message }
      : { state: 'done', text: res.data?.message || 'Berita ditarik dan dianalisis' };
  } catch (e) {
    media.value = { state: 'error', text: errText(e, 'Gagal memperbarui media online') };
  }
};

const runSocial = async () => {
  if (!canSocial.value) return;
  if (isFresh(props.socialUpdatedAt)) {
    social.value = { state: 'skipped', text: 'Baru diperbarui, dilewati' };
    return;
  }
  social.value = { state: 'running', text: 'Menarik postingan…' };
  let pulled = '';
  try {
    const res = await axios.post('/social-intelligence/sync', { economy: 1 });
    pulled = res.data?.new_posts > 0 ? `${res.data.new_posts} postingan baru` : 'tidak ada postingan baru';
  } catch (e) {
    social.value = { state: 'error', text: errText(e, 'Gagal menarik postingan medsos') };
    return;
  }
  social.value = { state: 'running', text: `${pulled}; menganalisis…` };
  try {
    const an = await axios.post('/social-intelligence/analyze-ai', {});
    social.value = an.data?.skipped
      ? { state: 'skipped', text: `${pulled}; analisis dilewati (data sama, tanpa kuota AI)` }
      : { state: 'done', text: `${pulled}; analisis selesai` };
  } catch (e) {
    social.value = { state: 'error', text: `${pulled}; analisis gagal: ${errText(e, 'periksa konfigurasi AI')}` };
  }
};

const refresh = async () => {
  if (running.value || !canRefresh.value || allFresh.value) return;
  media.value = { state: 'idle', text: '' };
  social.value = { state: 'idle', text: '' };
  await Promise.all([runMedia(), runSocial()]);
  router.reload({ preserveScroll: true });
};

const dismiss = () => {
  media.value = { state: 'idle', text: '' };
  social.value = { state: 'idle', text: '' };
};

const rows = computed(() => [
  canMedia.value && { key: 'media', name: 'Media Online', ...media.value },
  canSocial.value && { key: 'social', name: 'Media Sosial', ...social.value },
].filter(Boolean));
</script>

<template>
  <div class="space-y-2.5">
    <div class="flex flex-wrap items-center gap-2">
      <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-semibold border"
        :class="isStale(mediaUpdatedAt) ? 'bg-amber-500/10 text-amber-300 border-amber-500/30' : 'bg-slate-900 text-slate-300 border-slate-700'">
        Media Online: <b>{{ label(mediaUpdatedAt) }}</b>
      </span>
      <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-semibold border"
        :class="isStale(socialUpdatedAt) ? 'bg-amber-500/10 text-amber-300 border-amber-500/30' : 'bg-slate-900 text-slate-300 border-slate-700'">
        Media Sosial: <b>{{ label(socialUpdatedAt) }}</b>
      </span>

      <button
        v-if="canRefresh"
        type="button"
        @click="refresh"
        :disabled="running || allFresh"
        class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-black bg-gradient-to-r from-amber-400 to-amber-500 text-slate-950 shadow-md shadow-amber-400/20 hover:brightness-110 transition cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
        :title="allFresh ? 'Briefing baru diperbarui beberapa menit lalu' : 'Tarik data terbaru lalu susun ulang briefing media online dan media sosial'"
      >
        <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': running }" />
        <span>{{ running ? 'Memperbarui…' : allFresh ? 'Baru diperbarui' : 'Perbarui Briefing' }}</span>
      </button>
    </div>

    <ul v-if="canRefresh && hasResult" class="rounded-xl border border-slate-700/80 bg-slate-900/70 divide-y divide-slate-800" aria-live="polite">
      <li v-for="(r, idx) in rows" :key="r.key" class="flex items-start gap-2.5 px-3 py-2 text-[11.5px]">
        <Loader2 v-if="r.state === 'running'" class="w-4 h-4 mt-0.5 shrink-0 text-amber-400 animate-spin" />
        <CheckCircle2 v-else-if="r.state === 'done'" class="w-4 h-4 mt-0.5 shrink-0 text-emerald-400" />
        <XCircle v-else-if="r.state === 'error'" class="w-4 h-4 mt-0.5 shrink-0 text-rose-400" />
        <MinusCircle v-else class="w-4 h-4 mt-0.5 shrink-0 text-slate-500" />
        <div class="min-w-0 flex-1">
          <span class="font-bold text-white">{{ r.name }}</span>
          <span class="text-slate-400"> · {{ r.text || 'Menunggu' }}</span>
        </div>
        <button v-if="idx === 0 && !running" type="button" @click="dismiss" class="text-slate-500 hover:text-white cursor-pointer" title="Tutup"><X class="w-3.5 h-3.5" /></button>
      </li>
    </ul>
  </div>
</template>
