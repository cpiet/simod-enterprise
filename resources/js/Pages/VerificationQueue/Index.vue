<script setup>
import { ref, computed } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import { priorityMeta, formatWitaDate } from '@/utils/issueFlow';
import {
  CheckCircle2,
  Clock,
  ShieldCheck,
  Building2,
  RotateCcw,
  X,
  MapPin,
  ChevronRight,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  issues: Object, // Paginated
  tab: String,
  priorityFilter: String,
  queueKpi: Object,
  metrics: Object,
});

const page = usePage();
const canVerify = computed(() => !!page.props.auth?.permissions?.issues_verify);

const currentTab = ref(props.tab || 'all');
const currentPriority = ref(props.priorityFilter || 'all');
const isSubmitting = ref(false);

// Dua tahap antrean (nilai tab server tetap: polres = monitoring, polda = investigasi)
const TABS = [
  { key: 'all', label: 'Semua', count: () => props.queueKpi?.total_pending || 0 },
  { key: 'polres', label: 'Perlu Telaah', count: () => props.queueKpi?.pending_polres || 0 },
  { key: 'polda', label: 'Dalam Penanganan', count: () => props.queueKpi?.pending_polda || 0 },
];

const load = () => {
  router.get('/verification-queue', { tab: currentTab.value, priority: currentPriority.value }, { preserveState: true, preserveScroll: true });
};
const handleTabChange = (t) => {
  currentTab.value = t;
  load();
};
const toggleAttention = () => {
  currentPriority.value = currentPriority.value === 'critical' ? 'all' : 'critical';
  load();
};

// Verifikasi cepat (hanya untuk laporan yang masih perlu telaah)
const isVerifyModalOpen = ref(false);
const activeIssue = ref(null);
const verifyPriority = ref('medium');
const verifyReason = ref('Laporan telah diperiksa bukti lapangannya dan dinyatakan sah terverifikasi.');

const openQuickVerify = (issue) => {
  activeIssue.value = issue;
  verifyPriority.value = issue.priority || 'medium';
  isVerifyModalOpen.value = true;
};

const submitVerify = () => {
  if (!activeIssue.value) return;
  isSubmitting.value = true;
  router.post('/issues/' + activeIssue.value.id + '/verify', {
    target_status: 'investigasi',
    priority: verifyPriority.value,
    reason: verifyReason.value,
  }, {
    preserveScroll: true,
    onSuccess: () => { isSubmitting.value = false; isVerifyModalOpen.value = false; },
    onError: () => { isSubmitting.value = false; },
  });
};

// Kembalikan untuk revisi
const isReturnModalOpen = ref(false);
const returnReason = ref('');

const openQuickReturn = (issue) => {
  activeIssue.value = issue;
  returnReason.value = '';
  isReturnModalOpen.value = true;
};

const submitReturn = () => {
  if (!activeIssue.value) return;
  isSubmitting.value = true;
  router.post('/issues/' + activeIssue.value.id + '/return', { reason: returnReason.value }, {
    preserveScroll: true,
    onSuccess: () => { isSubmitting.value = false; isReturnModalOpen.value = false; },
    onError: () => { isSubmitting.value = false; },
  });
};

const stageText = (issue) => (issue.status === 'monitoring'
  ? 'Menunggu telaah & verifikasi'
  : 'Terverifikasi, menunggu tindak lanjut (klarifikasi / penyelesaian)');
</script>

<template>
  <EnterpriseLayout
    :all-poldas="allPoldas"
    :selected-polda="selectedPolda"
    :selected-code="selectedCode"
    :metrics="metrics"
  >
    <!-- Header ringkas -->
    <div class="min-w-0">
      <p class="text-xs font-bold text-slate-400">
        {{ selectedCode === 'ALL' ? 'Mabes Polri (Nasional)' : selectedPolda?.name }}
      </p>
      <h1 class="text-2xl md:text-3xl font-black text-white tracking-tight">Antrean Verifikasi</h1>
      <p class="text-xs text-slate-400 mt-1">
        Telaah fakta dan bukti, lalu <strong class="text-slate-200">verifikasi</strong> atau <strong class="text-slate-200">kembalikan</strong> untuk revisi.
        Laporan yang sudah terverifikasi berlanjut ke penanganan di halaman detail.
      </p>
    </div>

    <!-- Tab tahap antrean (menggantikan 4 kartu KPI) + atensi -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1">
      <button
        v-for="t in TABS"
        :key="t.key"
        type="button"
        @click="handleTabChange(t.key)"
        class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 cursor-pointer select-none whitespace-nowrap border"
        :class="currentTab === t.key
          ? 'bg-amber-400 text-slate-950 font-black border-amber-300 shadow-md'
          : 'bg-slate-900 text-slate-300 hover:text-white hover:bg-slate-800 border-slate-700'"
      >
        <span>{{ t.label }}</span>
        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono" :class="currentTab === t.key ? 'bg-slate-950 text-amber-300 font-black' : 'bg-slate-800 text-slate-300 font-bold'">{{ t.count() }}</span>
      </button>

      <span class="h-5 w-px bg-slate-700 shrink-0"></span>

      <button
        type="button"
        @click="toggleAttention"
        class="px-3.5 py-2 rounded-xl text-xs font-black transition cursor-pointer select-none whitespace-nowrap border"
        :class="currentPriority === 'critical' ? 'bg-rose-600 text-white border-rose-500 shadow-md' : 'bg-slate-900 text-rose-400 border-rose-900/60 hover:bg-rose-950/30'"
        title="Tampilkan hanya prioritas Kritis"
      >
        Prioritas Kritis
      </button>
    </div>

    <!-- Daftar antrean -->
    <div v-if="issues?.data?.length > 0" class="space-y-3">
      <div
        v-for="issue in issues.data"
        :key="issue.id"
        class="rounded-2xl bg-[#0b1426] border border-slate-800 p-4 md:p-5 shadow-lg space-y-2.5 hover:border-slate-700 transition"
      >
        <div class="flex flex-wrap items-center gap-2">
          <span class="px-2 py-0.5 rounded-lg bg-amber-400/15 text-amber-300 font-mono font-bold text-[11px] border border-amber-400/30">
            {{ issue.report_code || 'SIM-' + issue.id }}
          </span>
          <span class="px-2 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-wider" :class="priorityMeta(issue.priority).cls">
            {{ priorityMeta(issue.priority).label }}
          </span>
          <span class="text-[11px] font-bold text-sky-400 flex items-center gap-1">
            <Building2 class="w-3.5 h-3.5" />
            {{ issue.polres?.name || issue.polda?.name || 'Divhumas Polri' }}
          </span>
          <span class="ml-auto text-[11px] text-slate-400 font-mono flex items-center gap-1">
            <Clock class="w-3.5 h-3.5 text-amber-400" />
            {{ formatWitaDate(issue.created_at) }}
          </span>
        </div>

        <div class="flex gap-4 justify-between items-start">
          <div class="space-y-1 flex-1 min-w-0">
            <Link :href="'/issues/' + issue.id" class="text-base font-black text-white hover:text-amber-400 transition block leading-snug">
              {{ issue.title }}
            </Link>
            <p class="text-xs text-slate-300 leading-relaxed line-clamp-2">{{ issue.summary }}</p>
            <p class="text-[11px] text-slate-400 flex flex-wrap items-center gap-x-3 gap-y-1">
              <span v-if="issue.category" class="font-bold text-slate-300">{{ issue.category }}</span>
              <span class="flex items-center gap-1"><MapPin class="w-3 h-3 text-amber-400" /> {{ issue.location_text || 'Wilayah hukum' }}</span>
              <span>Sumber: <strong class="text-slate-300">{{ issue.source_media || 'Laporan Lapangan' }}</strong></span>
            </p>
          </div>

          <div
            v-if="issue.media_files && issue.media_files.length > 0"
            class="w-20 h-16 rounded-xl overflow-hidden bg-slate-900 shrink-0 border border-slate-800 relative"
          >
            <img :src="issue.media_files[0].file_url" :alt="issue.title" class="w-full h-full object-cover" />
            <span class="absolute bottom-1 right-1 px-1 rounded bg-black/80 text-[9px] font-bold text-amber-400">{{ issue.media_files.length }}</span>
          </div>
        </div>

        <div class="flex flex-wrap items-center justify-between pt-2.5 border-t border-slate-800/80 gap-3">
          <span class="text-[11px] font-bold text-amber-400 flex items-center gap-1.5">
            <Clock class="w-3.5 h-3.5" />
            <span>{{ stageText(issue) }}</span>
          </span>

          <div class="flex items-center gap-2">
            <button
              v-if="canVerify"
              @click="openQuickReturn(issue)"
              type="button"
              class="px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-900 hover:bg-slate-800 border border-slate-700 text-amber-300 transition cursor-pointer flex items-center gap-1.5"
              title="Kembalikan laporan dengan catatan revisi"
            >
              <RotateCcw class="w-3.5 h-3.5" />
              <span>Kembalikan</span>
            </button>

            <!-- Tahap telaah: verifikasi cepat. Tahap penanganan: lanjut ke detail (klarifikasi / penyelesaian) -->
            <button
              v-if="canVerify && issue.status === 'monitoring'"
              @click="openQuickVerify(issue)"
              type="button"
              class="px-4 py-1.5 rounded-xl text-xs font-black bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition shadow cursor-pointer flex items-center gap-1.5"
            >
              <ShieldCheck class="w-3.5 h-3.5" />
              <span>Verifikasi</span>
            </button>

            <Link
              :href="'/issues/' + issue.id"
              class="px-4 py-1.5 rounded-xl text-xs font-black transition flex items-center gap-1.5"
              :class="issue.status === 'monitoring'
                ? 'bg-slate-900 border border-slate-700 text-slate-300 hover:text-white font-bold'
                : 'bg-amber-400 hover:bg-amber-300 text-slate-950 shadow'"
            >
              <span>{{ issue.status === 'monitoring' ? 'Detail' : 'Tindak Lanjut' }}</span>
              <ChevronRight class="w-3.5 h-3.5" />
            </Link>
          </div>
        </div>
      </div>

      <!-- Paginasi -->
      <div v-if="issues?.links && issues.links.length > 3" class="flex items-center justify-between gap-3 p-3 rounded-2xl bg-[#0b1426] border border-slate-800">
        <p class="text-xs text-slate-400">Halaman {{ issues.current_page }} dari {{ issues.last_page }} &bull; {{ issues.total }} laporan</p>
        <div class="flex items-center gap-1 flex-wrap">
          <template v-for="(link, idx) in issues.links" :key="idx">
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

    <!-- Empty State -->
    <div v-else class="rounded-3xl bg-[#0b1426] border border-slate-800 p-12 text-center space-y-3">
      <CheckCircle2 class="w-12 h-12 mx-auto text-emerald-400" />
      <h3 class="text-base font-bold text-white">Antrean bersih</h3>
      <p class="text-xs text-slate-400 max-w-sm mx-auto">Tidak ada laporan yang memerlukan tindakan pada kriteria ini.</p>
    </div>

    <!-- Modal verifikasi cepat -->
    <div v-if="isVerifyModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
      <div class="w-full max-w-lg rounded-3xl bg-[#0b1426] border border-slate-700 shadow-2xl p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <ShieldCheck class="w-5 h-5 text-emerald-400" />
            <h3 class="text-base font-extrabold text-white">Verifikasi Laporan</h3>
          </div>
          <button @click="isVerifyModalOpen = false" class="text-slate-400 hover:text-white cursor-pointer"><X class="w-5 h-5" /></button>
        </div>

        <p class="text-xs text-slate-300">
          Laporan <strong>{{ activeIssue?.report_code }}</strong> &mdash; {{ activeIssue?.title }}. Setelah diverifikasi, laporan berlanjut ke tahap <strong>Dalam Penanganan</strong>.
        </p>

        <div class="space-y-3 text-xs">
          <label class="block">
            <span class="block font-bold text-slate-300 mb-1">Prioritas tindak lanjut</span>
            <select v-model="verifyPriority" class="w-full p-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white font-bold outline-none focus:border-amber-400">
              <option value="critical">Kritis (atensi pimpinan)</option>
              <option value="high">Tinggi</option>
              <option value="medium">Sedang</option>
              <option value="low">Rendah</option>
            </select>
          </label>

          <label class="block">
            <span class="block font-bold text-slate-300 mb-1">Catatan telaah</span>
            <textarea v-model="verifyReason" rows="3" class="w-full p-3 rounded-xl bg-slate-900 border border-slate-700 text-slate-200 outline-none focus:border-amber-400"></textarea>
          </label>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
          <button @click="isVerifyModalOpen = false" class="px-4 py-2 rounded-xl bg-slate-900 text-xs text-slate-300 hover:text-white cursor-pointer">Batal</button>
          <button @click="submitVerify" :disabled="isSubmitting" class="px-5 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black text-xs transition disabled:opacity-50 cursor-pointer">
            {{ isSubmitting ? 'Memproses...' : 'Setujui Verifikasi' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal kembalikan -->
    <div v-if="isReturnModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
      <div class="w-full max-w-lg rounded-3xl bg-[#0b1426] border border-slate-700 shadow-2xl p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <RotateCcw class="w-5 h-5 text-amber-400" />
            <h3 class="text-base font-extrabold text-white">Kembalikan untuk Revisi</h3>
          </div>
          <button @click="isReturnModalOpen = false" class="text-slate-400 hover:text-white cursor-pointer"><X class="w-5 h-5" /></button>
        </div>

        <p class="text-xs text-slate-300">Cantumkan instruksi perbaikan atau bukti tambahan yang wajib dilengkapi pelapor.</p>

        <label class="block">
          <span class="block text-xs font-bold text-slate-300 mb-1">Catatan revisi (wajib)</span>
          <textarea
            v-model="returnReason"
            rows="4"
            placeholder="Mohon sertakan dokumentasi foto TKP dan rincian identitas saksi/korban..."
            class="w-full p-3 rounded-xl bg-slate-900 border border-slate-700 text-xs text-slate-200 outline-none focus:border-amber-400"
          ></textarea>
        </label>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
          <button @click="isReturnModalOpen = false" class="px-4 py-2 rounded-xl bg-slate-900 text-xs text-slate-300 hover:text-white cursor-pointer">Batal</button>
          <button @click="submitReturn" :disabled="isSubmitting || !returnReason.trim()" class="px-5 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-black text-xs transition disabled:opacity-50 cursor-pointer">
            {{ isSubmitting ? 'Mengirim...' : 'Kembalikan Laporan' }}
          </button>
        </div>
      </div>
    </div>
  </EnterpriseLayout>
</template>
