<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import { formatWitaDate } from '@/utils/issueFlow';
import {
  PlusCircle,
  ExternalLink,
  CheckCircle2,
  Clock,
  XCircle,
  X,
  FileText,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  publications: Object, // Paginated
  channelFilter: { type: String, default: 'all' },
  statusFilter: { type: String, default: 'all' },
  statusCounts: { type: Object, default: () => ({}) },
  metrics: Object,
});

// Kanal yang didukung backend (sumber tunggal untuk filter, formulir, dan label)
const CHANNELS = [
  { key: 'tribratanews', label: 'Tribratanews', long: 'Tribratanews (portal web resmi)' },
  { key: 'instagram', label: 'Instagram', long: 'Instagram feed / story' },
  { key: 'tiktok', label: 'TikTok', long: 'TikTok video' },
  { key: 'facebook', label: 'Facebook', long: 'Facebook fanpage' },
  { key: 'twitter', label: 'X / Twitter', long: 'X / Twitter' },
  { key: 'youtube', label: 'YouTube', long: 'YouTube video / Shorts' },
  { key: 'siaran_pers', label: 'Siaran Pers', long: 'Siaran pers resmi' },
];
const channelLabel = (key) => CHANNELS.find((c) => c.key === key)?.label || key;
const channelCount = (key) => props.metrics?.channels?.[key] || 0;

const STATUS_TABS = [
  { key: 'all', label: 'Semua' },
  { key: 'pending', label: 'Menunggu Persetujuan' },
  { key: 'published', label: 'Terbit' },
  { key: 'rejected', label: 'Ditolak' },
];
const statusBadge = (st) => {
  if (['published', 'approved'].includes(st)) return { label: 'Terbit', cls: 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/40', icon: CheckCircle2 };
  if (st === 'rejected') return { label: 'Ditolak', cls: 'bg-rose-500/15 text-rose-300 border border-rose-500/40', icon: XCircle };
  return { label: 'Menunggu Persetujuan', cls: 'bg-amber-400/15 text-amber-300 border border-amber-400/40', icon: Clock };
};

const channel = ref(props.channelFilter || 'all');
const status = ref(props.statusFilter || 'all');
const load = () => {
  router.get('/publications', { channel: channel.value, status: status.value }, { preserveState: true, preserveScroll: true });
};
const setStatus = (k) => { status.value = k; load(); };
const setChannel = () => load();

// Formulir publikasi baru
const emptyForm = () => ({ polda_id: props.selectedPolda?.id || 1, channel: 'instagram', title: '', content_draft: '', url: '' });
const isModalOpen = ref(false);
const form = ref(emptyForm());
const isSubmitting = ref(false);

const submitPublication = () => {
  isSubmitting.value = true;
  router.post('/publications', form.value, {
    preserveScroll: true,
    onSuccess: () => { isSubmitting.value = false; isModalOpen.value = false; form.value = emptyForm(); },
    onError: () => { isSubmitting.value = false; },
  });
};

// Persetujuan / penolakan draf
const isRejectOpen = ref(false);
const rejectTarget = ref(null);
const rejectNotes = ref('');

const approve = (pub) => {
  router.post('/publications/' + pub.id + '/approve', {}, { preserveScroll: true });
};
const openReject = (pub) => { rejectTarget.value = pub; rejectNotes.value = ''; isRejectOpen.value = true; };
const submitReject = () => {
  if (!rejectTarget.value || !rejectNotes.value.trim()) return;
  isSubmitting.value = true;
  router.post('/publications/' + rejectTarget.value.id + '/reject', { review_notes: rejectNotes.value }, {
    preserveScroll: true,
    onSuccess: () => { isSubmitting.value = false; isRejectOpen.value = false; },
    onError: () => { isSubmitting.value = false; },
  });
};

const isPending = (pub) => ['draft_release', 'pending_approval'].includes(pub.approval_status);
</script>

<template>
  <EnterpriseLayout
    :all-poldas="allPoldas"
    :selected-polda="selectedPolda"
    :selected-code="selectedCode"
    :metrics="metrics"
  >
    <!-- Header ringkas -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div class="min-w-0">
        <p class="text-xs font-bold text-slate-400">{{ selectedCode === 'ALL' ? 'Mabes Polri (Nasional)' : selectedPolda?.name }}</p>
        <h1 class="text-2xl md:text-3xl font-black text-white tracking-tight">Publikasi Multi-Kanal</h1>
      </div>

      <button
        @click="isModalOpen = true"
        class="px-4 py-2.5 rounded-xl text-xs font-black bg-gradient-to-r from-amber-500 to-amber-400 text-slate-950 hover:brightness-110 shadow-lg shadow-amber-400/20 flex items-center gap-2 cursor-pointer transition shrink-0"
      >
        <PlusCircle class="w-4 h-4" />
        <span>Buat Publikasi</span>
      </button>
    </div>

    <!-- Tab status persetujuan (draf rilis dari detail isu masuk "Menunggu Persetujuan") + filter kanal -->
    <div class="flex flex-wrap items-center gap-2">
      <button
        v-for="t in STATUS_TABS"
        :key="t.key"
        type="button"
        @click="setStatus(t.key)"
        class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 cursor-pointer select-none whitespace-nowrap border"
        :class="status === t.key
          ? 'bg-amber-400 text-slate-950 font-black border-amber-300 shadow-md'
          : 'bg-slate-900 text-slate-300 hover:text-white hover:bg-slate-800 border-slate-700'"
      >
        <span>{{ t.label }}</span>
        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono" :class="status === t.key ? 'bg-slate-950 text-amber-300 font-black' : 'bg-slate-800 text-slate-300 font-bold'">{{ statusCounts[t.key] ?? 0 }}</span>
      </button>

      <select
        v-model="channel"
        @change="setChannel"
        class="ml-auto px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-xs font-bold text-slate-200 outline-none focus:border-amber-400"
        title="Filter kanal"
      >
        <option value="all">Semua kanal ({{ metrics?.total || 0 }})</option>
        <option v-for="c in CHANNELS" :key="c.key" :value="c.key">{{ c.label }} ({{ channelCount(c.key) }})</option>
      </select>
    </div>

    <!-- Daftar publikasi -->
    <div v-if="publications?.data?.length > 0" class="space-y-4">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="pub in publications.data"
          :key="pub.id"
          class="rounded-2xl bg-[#0b1426] border border-slate-800/90 p-5 shadow-lg flex flex-col justify-between space-y-4 hover:border-slate-700 transition"
        >
          <div class="space-y-3">
            <div class="flex items-center justify-between gap-2">
              <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-slate-900 border border-slate-700 text-amber-400">{{ channelLabel(pub.channel) }}</span>
              <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider flex items-center gap-1" :class="statusBadge(pub.approval_status).cls">
                <component :is="statusBadge(pub.approval_status).icon" class="w-3 h-3" />
                {{ statusBadge(pub.approval_status).label }}
              </span>
            </div>

            <h3 class="font-extrabold text-white text-base leading-snug">{{ pub.title }}</h3>
            <p class="text-xs text-slate-300 leading-relaxed whitespace-pre-line line-clamp-4">{{ pub.content_draft }}</p>

            <p v-if="pub.approval_status === 'rejected' && pub.review_notes" class="text-[11px] text-rose-300 bg-rose-500/10 border border-rose-500/30 rounded-lg p-2">
              <strong>Catatan revisi:</strong> {{ pub.review_notes }}
            </p>
          </div>

          <div class="pt-3 border-t border-slate-800/80 space-y-2 text-xs">
            <div class="flex items-center justify-between text-[11px] text-slate-400">
              <span class="font-mono">{{ formatWitaDate(pub.created_at) }}</span>
              <Link v-if="pub.issue" :href="'/issues/' + pub.issue.id" class="text-sky-400 hover:underline flex items-center gap-1 truncate">
                <FileText class="w-3 h-3" /> {{ pub.issue.report_code || 'Laporan terkait' }}
              </Link>
            </div>

            <div class="flex items-center justify-between gap-2">
              <a v-if="pub.url" :href="pub.url" target="_blank" rel="noopener noreferrer" class="text-sky-400 hover:text-sky-300 font-bold flex items-center gap-1">
                <ExternalLink class="w-3.5 h-3.5" /> Buka tautan
              </a>
              <span v-else></span>

              <div v-if="isPending(pub)" class="flex items-center gap-2">
                <button type="button" @click="openReject(pub)" class="px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-900 hover:bg-slate-800 border border-slate-700 text-rose-300 cursor-pointer">Tolak</button>
                <button type="button" @click="approve(pub)" class="px-3.5 py-1.5 rounded-xl text-xs font-black bg-emerald-500 hover:bg-emerald-400 text-slate-950 cursor-pointer">Setujui &amp; Terbitkan</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="publications?.links && publications.links.length > 3" class="flex items-center justify-between gap-3 p-3 rounded-2xl bg-[#0b1426] border border-slate-800">
        <p class="text-xs text-slate-400">Halaman {{ publications.current_page }} dari {{ publications.last_page }} &bull; {{ publications.total }} publikasi</p>
        <div class="flex items-center gap-1 flex-wrap">
          <template v-for="(link, idx) in publications.links" :key="idx">
            <Link
              v-if="link.url"
              :href="link.url"
              class="px-3 py-1.5 rounded-xl text-xs font-bold transition"
              :class="link.active ? 'bg-amber-400 text-slate-950 font-black' : 'bg-slate-900 text-slate-300 hover:bg-slate-800 border border-slate-700'"
              v-html="link.label"
            />
            <span v-else class="px-3 py-1.5 rounded-xl text-xs font-bold text-slate-600 bg-slate-950 border border-slate-900 select-none" v-html="link.label" />
          </template>
        </div>
      </div>
    </div>

    <div v-else class="p-12 rounded-3xl bg-[#0b1426] border border-slate-800 text-center text-slate-400 text-xs">
      Belum ada publikasi pada tampilan ini.
    </div>

    <!-- Modal buat publikasi -->
    <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
      <div class="max-w-xl w-full rounded-3xl bg-[#0d182e] border border-slate-700 p-6 sm:p-8 space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div>
            <h3 class="text-base font-extrabold text-white">Publikasi Baru</h3>
            <p class="text-[11px] text-slate-400">Dicatat langsung sebagai terbit pada kanal yang dipilih</p>
          </div>
          <button @click="isModalOpen = false" class="text-slate-400 hover:text-white p-1 cursor-pointer"><X class="w-5 h-5" /></button>
        </div>

        <form @submit.prevent="submitPublication" class="space-y-4">
          <label class="block">
            <span class="block text-xs font-bold text-slate-300 mb-1">Kanal</span>
            <select v-model="form.channel" class="w-full px-3 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs font-bold text-white focus:border-amber-400 outline-none">
              <option v-for="c in CHANNELS" :key="c.key" :value="c.key">{{ c.long }}</option>
            </select>
          </label>

          <label class="block">
            <span class="block text-xs font-bold text-slate-300 mb-1">Judul / headline</span>
            <input v-model="form.title" type="text" required placeholder="Contoh: Sosialisasi tertib lalin Polresta Mamuju" class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs font-semibold text-white focus:outline-none focus:border-amber-400" />
          </label>

          <label class="block">
            <span class="block text-xs font-bold text-slate-300 mb-1">Narasi / caption &amp; hashtag</span>
            <textarea v-model="form.content_draft" rows="4" required placeholder="Tuliskan caption lengkap dengan hashtag resmi..." class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs text-white focus:outline-none focus:border-amber-400"></textarea>
          </label>

          <label class="block">
            <span class="block text-xs font-bold text-slate-300 mb-1">Tautan postingan (opsional)</span>
            <input v-model="form.url" type="url" placeholder="https://instagram.com/p/..." class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs text-white focus:outline-none focus:border-amber-400" />
          </label>

          <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
            <button type="button" @click="isModalOpen = false" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-300 hover:bg-slate-800 border border-slate-700 transition cursor-pointer">Batal</button>
            <button type="submit" :disabled="isSubmitting" class="px-5 py-2.5 rounded-xl text-xs font-black bg-amber-400 text-slate-950 hover:bg-amber-300 shadow-md transition disabled:opacity-50 cursor-pointer">
              {{ isSubmitting ? 'Menyimpan...' : 'Simpan Publikasi' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal tolak -->
    <div v-if="isRejectOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
      <div class="max-w-lg w-full rounded-3xl bg-[#0d182e] border border-slate-700 p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <h3 class="text-base font-extrabold text-white">Tolak Draf Publikasi</h3>
          <button @click="isRejectOpen = false" class="text-slate-400 hover:text-white p-1 cursor-pointer"><X class="w-5 h-5" /></button>
        </div>
        <p class="text-xs text-slate-300">{{ rejectTarget?.title }}</p>
        <label class="block">
          <span class="block text-xs font-bold text-slate-300 mb-1">Catatan revisi (wajib)</span>
          <textarea v-model="rejectNotes" rows="4" class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs text-white focus:outline-none focus:border-amber-400"></textarea>
        </label>
        <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
          <button type="button" @click="isRejectOpen = false" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-300 hover:bg-slate-800 border border-slate-700 cursor-pointer">Batal</button>
          <button type="button" @click="submitReject" :disabled="isSubmitting || !rejectNotes.trim()" class="px-5 py-2.5 rounded-xl text-xs font-black bg-rose-500 text-white hover:bg-rose-400 transition disabled:opacity-50 cursor-pointer">Tolak Draf</button>
        </div>
      </div>
    </div>
  </EnterpriseLayout>
</template>
