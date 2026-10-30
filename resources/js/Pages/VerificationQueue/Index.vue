<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import {
  CheckCircle2,
  XCircle,
  Clock,
  ShieldCheck,
  AlertTriangle,
  Building2,
  ExternalLink,
  RotateCcw,
  RefreshCw,
  Search,
  Filter,
  Eye,
  X,
  MapPin,
  Flame,
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

const currentTab = ref(props.tab || 'all');
const currentPriority = ref(props.priorityFilter || 'all');
const isSubmitting = ref(false);

const handleTabChange = (t) => {
  currentTab.value = t;
  router.get('/verification-queue', {
    polda: props.selectedCode,
    tab: t,
    priority: currentPriority.value,
  }, { preserveState: true, preserveScroll: true });
};

const handlePriorityChange = () => {
  router.get('/verification-queue', {
    polda: props.selectedCode,
    tab: currentTab.value,
    priority: currentPriority.value,
  }, { preserveState: true, preserveScroll: true });
};

const refreshQueue = () => {
  router.reload({ preserveScroll: true });
};

// Quick Verify Modal
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
  router.post(`/issues/${activeIssue.value.id}/verify`, {
    target_status: 'investigasi',
    priority: verifyPriority.value,
    reason: verifyReason.value,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      isSubmitting.value = false;
      isVerifyModalOpen.value = false;
    },
    onError: () => { isSubmitting.value = false; },
  });
};

// Quick Return Modal
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
  router.post(`/issues/${activeIssue.value.id}/return`, {
    reason: returnReason.value,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      isSubmitting.value = false;
      isReturnModalOpen.value = false;
    },
    onError: () => { isSubmitting.value = false; },
  });
};
</script>

<template>
  <EnterpriseLayout
    :all-poldas="allPoldas"
    :selected-polda="selectedPolda"
    :selected-code="selectedCode"
    :metrics="metrics"
  >
    <!-- Top Action Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-rose-500/20 text-rose-300 border border-rose-500/40">
            Telaah Fakta & Validasi
          </span>
          <span class="text-xs font-bold text-slate-400">
            {{ selectedCode === 'ALL' ? 'Mabes Polri (Nasional)' : selectedPolda?.name }}
          </span>
        </div>
        <h1 class="text-2xl md:text-3xl font-black text-white tracking-tight mt-1">
          Antrean Verifikasi Laporan
        </h1>
        <p class="text-xs md:text-sm text-slate-300">
          Telaah fakta lapangan, periksa kelayakan dokumentasi visual, dan putuskan verifikasi atau pengembalian revisi.
        </p>
      </div>

      <div class="flex items-center gap-3">
        <button
          @click="refreshQueue"
          class="flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-300 hover:text-white text-xs font-bold transition"
        >
          <RefreshCw class="w-3.5 h-3.5" />
          <span>Perbarui Antrean</span>
        </button>
      </div>
    </div>

    <!-- 4 Top KPI Cards (Persis SIMOD-HUMAS) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
          <span>Total Menunggu</span>
          <Clock class="w-4 h-4 text-amber-400" />
        </div>
        <p class="text-3xl font-black text-white">{{ queueKpi?.total_pending || 0 }}</p>
        <p class="text-[11px] text-slate-400">Perlu tindakan verifikator</p>
      </div>

      <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
          <span>Verifikasi Polres</span>
          <Building2 class="w-4 h-4 text-sky-400" />
        </div>
        <p class="text-3xl font-black text-sky-400">{{ queueKpi?.pending_polres || 0 }}</p>
        <p class="text-[11px] text-slate-400">Ditinjau Kasihumas wilayah</p>
      </div>

      <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
          <span>Verifikasi Polda</span>
          <ShieldCheck class="w-4 h-4 text-emerald-400" />
        </div>
        <p class="text-3xl font-black text-emerald-400">{{ queueKpi?.pending_polda || 0 }}</p>
        <p class="text-[11px] text-slate-400">Ditinjau Humas Polda Sulbar</p>
      </div>

      <div class="p-5 rounded-2xl bg-[#0b1426] border border-rose-900/60 shadow-xl space-y-1">
        <div class="flex items-center justify-between text-rose-400 text-xs font-bold">
          <span>Isu Prioritas Tinggi</span>
          <Flame class="w-4 h-4 text-rose-500" />
        </div>
        <p class="text-3xl font-black text-rose-400">{{ queueKpi?.critical_high || 0 }}</p>
        <p class="text-[11px] text-slate-400">Perlu respon darurat/cepat</p>
      </div>
    </div>

    <!-- Filter Tabs & Priority Filter -->
    <div class="p-4 rounded-2xl bg-[#0b1426] border border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-xl">
      <!-- Tabs (Semua, Menunggu Polres, Menunggu Polda) -->
      <div class="flex items-center gap-1.5 p-1 rounded-xl bg-slate-900 border border-slate-800 text-xs font-bold">
        <button
          @click="handleTabChange('all')"
          class="px-3.5 py-1.5 rounded-lg transition"
          :class="currentTab === 'all' ? 'bg-amber-400 text-slate-950 font-black shadow' : 'text-slate-400 hover:text-white'"
        >
          Semua ({{ queueKpi?.total_pending || 0 }})
        </button>
        <button
          @click="handleTabChange('polres')"
          class="px-3.5 py-1.5 rounded-lg transition"
          :class="currentTab === 'polres' ? 'bg-amber-400 text-slate-950 font-black shadow' : 'text-slate-400 hover:text-white'"
        >
          Menunggu Polres ({{ queueKpi?.pending_polres || 0 }})
        </button>
        <button
          @click="handleTabChange('polda')"
          class="px-3.5 py-1.5 rounded-lg transition"
          :class="currentTab === 'polda' ? 'bg-amber-400 text-slate-950 font-black shadow' : 'text-slate-400 hover:text-white'"
        >
          Menunggu Polda ({{ queueKpi?.pending_polda || 0 }})
        </button>
      </div>

      <!-- Priority Filter Select -->
      <div class="flex items-center gap-2 text-xs">
        <span class="text-slate-400 font-bold">Prioritas:</span>
        <select
          v-model="currentPriority"
          @change="handlePriorityChange"
          class="px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-700 text-white font-bold outline-none focus:border-amber-400"
        >
          <option value="all">Semua Prioritas</option>
          <option value="critical">Kritis (Merah)</option>
          <option value="high">Tinggi (Kuning)</option>
          <option value="medium">Sedang (Standar)</option>
          <option value="low">Rendah (Hijau)</option>
        </select>
      </div>
    </div>

    <!-- Issues Queue List -->
    <div v-if="issues?.data?.length > 0" class="space-y-4">
      <div
        v-for="issue in issues.data"
        :key="issue.id"
        class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4 hover:border-slate-700 transition"
      >
        <!-- Card Top Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-800/80 pb-3">
          <div class="flex flex-wrap items-center gap-2">
            <!-- Report Code -->
            <span class="px-2.5 py-0.5 rounded-lg bg-amber-400/20 text-amber-300 font-mono font-bold text-xs border border-amber-400/30">
              {{ issue.report_code || `SIM-2026-${String(issue.id).padStart(5, '0')}` }}
            </span>

            <!-- Priority Badge -->
            <span
              class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase"
              :class="{
                'bg-rose-500 text-white': issue.priority === 'critical',
                'bg-amber-500 text-slate-950': issue.priority === 'high',
                'bg-sky-500/20 text-sky-400': issue.priority === 'medium',
                'bg-slate-800 text-slate-300': issue.priority === 'low',
              }"
            >
              Prioritas: {{ issue.priority }}
            </span>

            <!-- Category -->
            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-800 text-slate-300">
              {{ issue.category }}
            </span>

            <!-- Satwil -->
            <span class="text-xs font-bold text-sky-400 flex items-center gap-1">
              <Building2 class="w-3.5 h-3.5" />
              {{ issue.polres?.name || issue.polda?.name || 'Polda Sulbar' }}
            </span>
          </div>

          <span class="text-xs text-slate-400 font-mono flex items-center gap-1">
            <Clock class="w-3.5 h-3.5 text-amber-400" />
            {{ new Date(issue.created_at).toLocaleString('id-ID') }}
          </span>
        </div>

        <!-- Issue Title & Summary -->
        <div class="flex flex-col md:flex-row gap-4 justify-between items-start">
          <div class="space-y-1.5 flex-1">
            <Link
              :href="`/issues/${issue.id}?polda=${selectedCode}`"
              class="text-base md:text-lg font-black text-white hover:text-amber-400 transition block leading-snug"
            >
              {{ issue.title }}
            </Link>
            <p class="text-xs text-slate-300 leading-relaxed line-clamp-2">
              {{ issue.summary }}
            </p>
            <div class="flex items-center gap-3 text-xs text-slate-400 pt-1">
              <span class="flex items-center gap-1">
                <MapPin class="w-3 h-3 text-amber-400" />
                {{ issue.location_text || 'Wilayah Hukum' }}
              </span>
              <span>&bull;</span>
              <span>Sumber: <strong class="text-slate-300">{{ issue.source_media || 'Laporan Lapangan' }}</strong></span>
            </div>
          </div>

          <!-- Thumbnail Evidence if available -->
          <div
            v-if="issue.media_files && issue.media_files.length > 0"
            class="w-24 h-20 rounded-xl overflow-hidden bg-slate-900 shrink-0 border border-slate-800 relative group"
          >
            <img
              :src="issue.media_files[0].file_url"
              :alt="issue.title"
              class="w-full h-full object-cover group-hover:scale-105 transition"
            />
            <span class="absolute bottom-1 right-1 px-1 rounded bg-black/80 text-[9px] font-bold text-amber-400">
              {{ issue.media_files.length }} Foto
            </span>
          </div>
        </div>

        <!-- Card Footer Actions -->
        <div class="flex flex-wrap items-center justify-between pt-3 border-t border-slate-800/80 gap-3">
          <span class="text-xs font-bold text-amber-400 flex items-center gap-1.5">
            <Clock class="w-3.5 h-3.5" />
            <span>{{ issue.status === 'monitoring' ? 'Menunggu Telaah Kasihumas' : 'Menunggu Persetujuan Kabid Humas' }}</span>
          </span>

          <div class="flex items-center gap-2">
            <!-- Return Button -->
            <button
              @click="openQuickReturn(issue)"
              class="px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-900 hover:bg-slate-800 border border-slate-700 text-amber-300 transition"
              title="Kembalikan laporan dengan catatan revisi"
            >
              <RotateCcw class="w-3.5 h-3.5 inline mr-1" />
              Kembalikan
            </button>

            <!-- Quick Verify Button -->
            <button
              @click="openQuickVerify(issue)"
              class="px-4 py-1.5 rounded-xl text-xs font-black bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition shadow"
            >
              <ShieldCheck class="w-3.5 h-3.5 inline mr-1" />
              Verifikasi & Setujui
            </button>

            <!-- Full Detail Link -->
            <Link
              :href="`/issues/${issue.id}?polda=${selectedCode}`"
              class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-slate-900 border border-slate-700 text-slate-300 hover:text-white transition"
            >
              Detail Lengkap &rarr;
            </Link>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="rounded-3xl bg-[#0b1426] border border-slate-800 p-12 text-center space-y-3">
      <CheckCircle2 class="w-12 h-12 mx-auto text-emerald-400" />
      <h3 class="text-base font-bold text-white">Antrean Verifikasi Bersih!</h3>
      <p class="text-xs text-slate-400 max-w-sm mx-auto">
        Tidak ada laporan yang saat ini memerlukan verifikasi pada kriteria ini. Seluruh laporan telah ditindaklanjuti.
      </p>
    </div>

    <!-- Quick Verify Modal -->
    <div
      v-if="isVerifyModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
    >
      <div class="w-full max-w-lg rounded-3xl bg-[#0b1426] border border-slate-700 shadow-2xl p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <ShieldCheck class="w-5 h-5 text-emerald-400" />
            <h3 class="text-base font-extrabold text-white">Verifikasi & Setujui Laporan</h3>
          </div>
          <button @click="isVerifyModalOpen = false" class="text-slate-400 hover:text-white">
            <X class="w-5 h-5" />
          </button>
        </div>

        <p class="text-xs text-slate-300">
          Anda akan memverifikasi laporan <strong>{{ activeIssue?.report_code }}</strong> ({{ activeIssue?.title }}).
        </p>

        <div class="space-y-3 text-xs">
          <div>
            <label class="block font-bold text-slate-300 mb-1">Tingkat Prioritas Tindak Lanjut</label>
            <select
              v-model="verifyPriority"
              class="w-full p-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white font-bold outline-none focus:border-amber-400"
            >
              <option value="critical">Kritis (Atensi Pimpinan / Merah)</option>
              <option value="high">Tinggi (Kuning)</option>
              <option value="medium">Sedang (Standar)</option>
              <option value="low">Rendah (Hijau)</option>
            </select>
          </div>

          <div>
            <label class="block font-bold text-slate-300 mb-1">Catatan Telaah Verifikasi</label>
            <textarea
              v-model="verifyReason"
              rows="3"
              class="w-full p-3 rounded-xl bg-slate-900 border border-slate-700 text-slate-200 outline-none focus:border-amber-400"
            ></textarea>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
          <button
            @click="isVerifyModalOpen = false"
            class="px-4 py-2 rounded-xl bg-slate-900 text-xs text-slate-300 hover:text-white"
          >
            Batal
          </button>
          <button
            @click="submitVerify"
            :disabled="isSubmitting"
            class="px-5 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black text-xs transition disabled:opacity-50"
          >
            {{ isSubmitting ? 'Memproses...' : 'Setujui Verifikasi' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Quick Return Modal -->
    <div
      v-if="isReturnModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
    >
      <div class="w-full max-w-lg rounded-3xl bg-[#0b1426] border border-slate-700 shadow-2xl p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <RotateCcw class="w-5 h-5 text-amber-400" />
            <h3 class="text-base font-extrabold text-white">Kembalikan Laporan untuk Revisi</h3>
          </div>
          <button @click="isReturnModalOpen = false" class="text-slate-400 hover:text-white">
            <X class="w-5 h-5" />
          </button>
        </div>

        <p class="text-xs text-slate-300">
          Cantumkan instruksi perbaikan atau bukti tambahan yang wajib dilengkapi oleh operator pelapor kewilayahan.
        </p>

        <div>
          <label class="block text-xs font-bold text-slate-300 mb-1">Catatan Revisi Wajib</label>
          <textarea
            v-model="returnReason"
            rows="4"
            placeholder="Mohon sertakan dokumentasi foto TKP dan rincian identitas saksi/korban..."
            class="w-full p-3 rounded-xl bg-slate-900 border border-slate-700 text-xs text-slate-200 outline-none focus:border-amber-400"
          ></textarea>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
          <button
            @click="isReturnModalOpen = false"
            class="px-4 py-2 rounded-xl bg-slate-900 text-xs text-slate-300 hover:text-white"
          >
            Batal
          </button>
          <button
            @click="submitReturn"
            :disabled="isSubmitting || !returnReason.trim()"
            class="px-5 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-black text-xs transition disabled:opacity-50"
          >
            {{ isSubmitting ? 'Mengirim...' : 'Kembalikan Laporan' }}
          </button>
        </div>
      </div>
    </div>
  </EnterpriseLayout>
</template>
