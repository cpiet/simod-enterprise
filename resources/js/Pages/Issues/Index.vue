<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import {
  Shield,
  Building2,
  AlertTriangle,
  Flame,
  Search,
  Filter,
  PlusCircle,
  CheckCircle2,
  XCircle,
  ExternalLink,
  MessageSquare,
  TrendingUp,
  Share2,
  Clock,
  Sparkles,
  Info,
  X,
  Download,
  FileText,
  Copy,
  Check,
  ChevronRight,
  MapPin,
  Calendar,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  polresList: Array,
  issues: Object, // Paginated
  filters: Object,
  metrics: Object,
});

const searchQuery = ref(props.filters?.q || '');
const selectedStatus = ref(props.filters?.status || 'all');
const selectedPriority = ref(props.filters?.priority || 'all');
const selectedCategory = ref(props.filters?.category || 'all');
const selectedPolres = ref(props.filters?.polres || 'all');

const handleFilter = () => {
  router.get('/issues', {
    polda: props.selectedCode,
    status: selectedStatus.value,
    priority: selectedPriority.value,
    category: selectedCategory.value,
    polres: selectedPolres.value,
    q: searchQuery.value,
  }, { preserveState: true, preserveScroll: true });
};

const resetFilter = () => {
  searchQuery.value = '';
  selectedStatus.value = 'all';
  selectedPriority.value = 'all';
  selectedCategory.value = 'all';
  selectedPolres.value = 'all';
  router.get('/issues', { polda: props.selectedCode }, { preserveState: true, preserveScroll: true });
};

// Modal Klarifikasi / Fact-Check Cepat
const isClarifyModalOpen = ref(false);
const clarifyTarget = ref(null);
const clarifyForm = ref({
  is_hoax: false,
  fact_check_result: '',
  official_clarification: '',
  clarification_url: '',
});
const isSubmitting = ref(false);

const openClarifyModal = (issue) => {
  clarifyTarget.value = issue;
  clarifyForm.value = {
    is_hoax: issue.is_hoax || false,
    fact_check_result: issue.fact_check_result || '',
    official_clarification: issue.official_clarification || '',
    clarification_url: issue.clarification_url || '',
  };
  isClarifyModalOpen.value = true;
};

const submitClarification = () => {
  if (!clarifyTarget.value) return;
  isSubmitting.value = true;
  router.post(`/issues/${clarifyTarget.value.id}/clarify`, clarifyForm.value, {
    preserveScroll: true,
    onSuccess: () => {
      isSubmitting.value = false;
      isClarifyModalOpen.value = false;
    },
    onError: () => {
      isSubmitting.value = false;
    },
  });
};

const copiedCode = ref(null);
const copyCode = (code) => {
  if (!code) return;
  navigator.clipboard.writeText(code);
  copiedCode.value = code;
  setTimeout(() => {
    copiedCode.value = null;
  }, 2000);
};

// Format Helpers
const getStatusLabel = (status) => {
  const map = {
    draft: 'Draf',
    submitted: 'Menunggu Polres',
    unit_verified: 'Terverifikasi Polres',
    polda_verified: 'Terverifikasi Polda',
    in_progress: 'Sedang Ditangani',
    resolved: 'Hasil Diajukan',
    closed: 'Selesai & Ditutup',
    monitoring: 'Dalam Pantauan',
    investigasi: 'Investigasi',
    klarifikasi: 'Klarifikasi Dirilis',
    selesai: 'Selesai & Ditutup',
  };
  return map[status] || status;
};

const getStatusBadgeClass = (status) => {
  switch (status) {
    case 'closed':
    case 'selesai':
      return 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/40';
    case 'resolved':
    case 'polda_verified':
      return 'bg-blue-500/20 text-blue-400 border border-blue-500/40';
    case 'in_progress':
    case 'investigasi':
      return 'bg-amber-400/20 text-amber-300 border border-amber-400/40';
    case 'unit_verified':
      return 'bg-purple-500/20 text-purple-300 border border-purple-500/40';
    case 'submitted':
    case 'monitoring':
      return 'bg-sky-500/20 text-sky-400 border border-sky-500/40';
    case 'draft':
    default:
      return 'bg-slate-800 text-slate-400 border border-slate-700';
  }
};

const getPriorityBadgeClass = (priority) => {
  switch (priority) {
    case 'critical':
      return 'bg-rose-500 text-white font-black';
    case 'high':
      return 'bg-amber-400 text-slate-950 font-black shadow-sm';
    case 'medium':
      return 'bg-sky-500/20 text-sky-300 border border-sky-500/40';
    case 'low':
    default:
      return 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-400 border border-slate-300 dark:border-slate-700';
  }
};
</script>

<template>
  <EnterpriseLayout
    :all-poldas="allPoldas"
    :selected-polda="selectedPolda"
    :selected-code="selectedCode"
    :metrics="metrics"
  >
    <!-- Top Action Bar & Title (Standardized with SIMOD-HUMAS) -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-400 text-slate-950">
            Bidang Humas
          </span>
          <span class="text-xs font-bold text-slate-500 dark:text-slate-400">
            {{ selectedCode === 'ALL' ? 'Semua Wilayah Hukum Mabes Polri' : selectedPolda?.name }}
          </span>
        </div>
        <h1 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white tracking-tight mt-1">
          Daftar Monitoring Laporan
        </h1>
        <p class="text-xs md:text-sm text-slate-600 dark:text-slate-300">
          Menampilkan total <span class="font-bold text-amber-600 dark:text-amber-400">{{ issues?.total || 0 }}</span> laporan kamtibmas & pemantauan opini publik tercatat di sistem
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2.5">
        <!-- Kembali ke Dashboard -->
        <Link
          :href="`/?polda=${selectedCode}`"
          class="px-4 py-2.5 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800 border border-slate-300 transition"
        >
          &larr; Dashboard
        </Link>

        <!-- Ekspor CSV Resmi -->
        <a
          :href="`/issues-export-csv?polda=${selectedCode}&status=${selectedStatus}&priority=${selectedPriority}&category=${selectedCategory}&polres=${selectedPolres}&q=${searchQuery}`"
          class="px-4 py-2.5 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-800 dark:bg-slate-900 dark:hover:bg-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700 transition flex items-center gap-2 cursor-pointer shadow-sm select-none"
        >
          <Download class="w-4 h-4 text-slate-600 dark:text-slate-400" />
          <span>Ekspor CSV</span>
        </a>

        <!-- Buat Laporan Baru (Direct Navigate to Full Form Page) -->
        <Link
          :href="`/issues/create?polda=${selectedCode}`"
          class="px-4 py-2.5 rounded-xl text-xs font-black bg-amber-400 text-slate-950 hover:bg-amber-300 shadow-lg shadow-amber-400/25 flex items-center gap-2 cursor-pointer transition select-none"
        >
          <PlusCircle class="w-4 h-4 text-slate-950" />
          <span>Buat Laporan Baru</span>
        </Link>
      </div>
    </div>

    <!-- Metrics Cards (Presisi Operational KPI) -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3.5">
      <div class="p-4 rounded-2xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 shadow-sm space-y-1">
        <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400">Total Laporan</span>
        <p class="text-2xl font-black text-slate-900 dark:text-white">{{ metrics.total_issues || 0 }}</p>
        <p class="text-[10px] text-slate-500 dark:text-slate-400">Semua riwayat</p>
      </div>

      <div class="p-4 rounded-2xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 shadow-sm space-y-1">
        <span class="text-[11px] font-bold text-sky-600 dark:text-sky-400">Menunggu Verifikasi</span>
        <p class="text-2xl font-black text-sky-600 dark:text-sky-400">{{ metrics.pending_verify || 0 }}</p>
        <p class="text-[10px] text-slate-500 dark:text-slate-400">Polres & Polda</p>
      </div>

      <div class="p-4 rounded-2xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 shadow-sm space-y-1">
        <span class="text-[11px] font-bold text-amber-600 dark:text-amber-400">Sedang Ditangani</span>
        <p class="text-2xl font-black text-amber-600 dark:text-amber-400">{{ metrics.in_progress || 0 }}</p>
        <p class="text-[10px] text-slate-500 dark:text-slate-400">Tindak Lanjut / Disposisi</p>
      </div>

      <div class="p-4 rounded-2xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 shadow-sm space-y-1">
        <span class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400">Selesai & Ditutup</span>
        <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ metrics.resolved || 0 }}</p>
        <p class="text-[10px] text-slate-500 dark:text-slate-400">Tuntas & Klarifikasi</p>
      </div>

      <div class="p-4 rounded-2xl bg-white dark:bg-[#0b1426] border border-rose-200 dark:border-rose-900/60 shadow-sm space-y-1">
        <span class="text-[11px] font-bold text-rose-600 dark:text-rose-400">Atensi Pimpinan (Tinggi)</span>
        <p class="text-2xl font-black text-rose-600 dark:text-rose-400">{{ metrics.high_priority || 0 }}</p>
        <p class="text-[10px] text-slate-500 dark:text-slate-400">Perlu Respons Cepat</p>
      </div>
    </div>

    <!-- Filter & Search Bar (Standardized 5-Control Toolbar) -->
    <div class="p-4 rounded-2xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3 shadow-md">
      <!-- Search Input -->
      <div class="relative flex-1 min-w-[240px]">
        <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-3 pointer-events-none" />
        <input
          v-model="searchQuery"
          @keydown.enter="handleFilter"
          type="text"
          placeholder="Cari kode laporan (SIM-...), judul kejadian, lokasi, atau narasi..."
          class="w-full pl-10 pr-4 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-semibold text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-amber-400"
        />
      </div>

      <!-- Select Filters -->
      <div class="flex flex-wrap items-center gap-2">
        <!-- Status Filter -->
        <select
          v-model="selectedStatus"
          @change="handleFilter"
          class="px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 outline-none focus:border-amber-400"
        >
          <option value="all">Semua Status</option>
          <option value="draft">Draf</option>
          <option value="submitted">Menunggu Polres</option>
          <option value="unit_verified">Terverifikasi Polres</option>
          <option value="polda_verified">Terverifikasi Polda</option>
          <option value="in_progress">Sedang Ditangani</option>
          <option value="resolved">Hasil Diajukan</option>
          <option value="closed">Selesai & Ditutup</option>
        </select>

        <!-- Kategori Filter -->
        <select
          v-model="selectedCategory"
          @change="handleFilter"
          class="px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 outline-none focus:border-amber-400"
        >
          <option value="all">Semua Kategori</option>
          <option value="🚨 Kejadian / Insiden Lapangan">🚨 Kejadian Lapangan</option>
          <option value="🛡️ Kegiatan Positif Kepolisian">🛡️ Kegiatan Positif</option>
          <option value="🗣️ Keluhan Warga (Dumas / Pelayanan)">🗣️ Keluhan Warga</option>
          <option value="📱 Isu Medsos & Opini Publik (Cyber Patrol)">📱 Isu Medsos</option>
        </select>

        <!-- Prioritas Filter -->
        <select
          v-model="selectedPriority"
          @change="handleFilter"
          class="px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 outline-none focus:border-amber-400"
        >
          <option value="all">Semua Prioritas</option>
          <option value="critical">🔥 Kritis (Merah)</option>
          <option value="high">🔴 Tinggi (Atensi)</option>
          <option value="medium">🟡 Sedang</option>
          <option value="low">🟢 Rendah</option>
        </select>

        <!-- Polres / Wilayah Filter -->
        <select
          v-if="polresList && polresList.length > 0"
          v-model="selectedPolres"
          @change="handleFilter"
          class="px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 outline-none focus:border-amber-400"
        >
          <option value="all">Semua Wilayah</option>
          <option v-for="p in polresList" :key="p.id" :value="p.code">
            {{ p.name }}
          </option>
        </select>

        <button
          v-if="selectedStatus !== 'all' || selectedPriority !== 'all' || selectedCategory !== 'all' || selectedPolres !== 'all' || searchQuery"
          @click="resetFilter"
          class="px-3 py-2 rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400 border border-rose-300 dark:border-rose-800 text-xs font-bold transition hover:bg-rose-100"
          title="Reset Semua Filter"
        >
          Reset
        </button>
      </div>
    </div>

    <!-- Issues List / Table (Standardized with SIMOD-HUMAS) -->
    <div class="space-y-3.5">
      <!-- Empty State -->
      <div
        v-if="!issues?.data || issues.data.length === 0"
        class="rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 p-12 text-center shadow-lg space-y-3"
      >
        <div class="w-14 h-14 rounded-2xl bg-amber-100 dark:bg-amber-400/10 text-amber-600 dark:text-amber-400 flex items-center justify-center mx-auto">
          <FileText class="w-7 h-7" />
        </div>
        <h3 class="text-base font-black text-slate-900 dark:text-white">Tidak ada laporan ditemukan</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto">
          Ubah kata kunci pencarian atau buat laporan lapangan baru menggunakan tombol di bawah ini.
        </p>
        <div class="pt-2">
          <Link
            :href="`/issues/create?polda=${selectedCode}`"
            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-amber-400 text-slate-950 font-black text-xs hover:bg-amber-300 shadow-md transition"
          >
            <PlusCircle class="w-4 h-4" />
            <span>Buat Laporan Baru</span>
          </Link>
        </div>
      </div>

      <!-- Item Card Row -->
      <div
        v-for="issue in issues.data"
        :key="issue.id"
        class="rounded-2xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800/90 p-5 shadow-sm hover:border-slate-400 dark:hover:border-slate-700 transition space-y-3"
      >
        <!-- Card Top Bar: Badges & Satwil -->
        <div class="flex flex-wrap items-center justify-between gap-2.5 border-b border-slate-100 dark:border-slate-800/80 pb-3">
          <div class="flex flex-wrap items-center gap-2">
            <!-- Kode Laporan (SIM-...) -->
            <button
              @click="copyCode(issue.report_code || `SIM-${issue.id}`)"
              type="button"
              class="px-2.5 py-1 rounded-lg text-[11px] font-mono font-bold bg-slate-100 dark:bg-slate-900 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-800 hover:border-amber-400 flex items-center gap-1.5 cursor-pointer transition"
              :title="copiedCode === (issue.report_code || `SIM-${issue.id}`) ? 'Tersalin!' : 'Klik untuk salin kode'"
            >
              <Check v-if="copiedCode === (issue.report_code || `SIM-${issue.id}`)" class="w-3 h-3 text-emerald-500" />
              <Copy v-else class="w-3 h-3 text-slate-400" />
              <span>{{ issue.report_code || `SIM-${issue.id}` }}</span>
            </button>

            <!-- Priority Badge -->
            <span
              class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider"
              :class="getPriorityBadgeClass(issue.priority)"
            >
              {{ issue.priority }}
            </span>

            <!-- Status Verifikasi Pill -->
            <span
              class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider"
              :class="getStatusBadgeClass(issue.status)"
            >
              {{ getStatusLabel(issue.status) }}
            </span>

            <!-- Category Badge -->
            <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
              {{ issue.category }}
            </span>

            <!-- Hoax Flag -->
            <span
              v-if="issue.is_hoax"
              class="px-2 py-0.5 rounded-lg text-[10px] font-black bg-rose-600 text-white flex items-center gap-1 shadow-sm"
            >
              <XCircle class="w-3 h-3" /> HOAKS
            </span>
          </div>

          <!-- Satwil / Pelapor Info -->
          <div class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
            <Building2 class="w-3.5 h-3.5 text-amber-500" />
            <span>Satwil:</span>
            <span class="font-bold text-slate-900 dark:text-white">{{ issue.polres?.name || issue.polda?.name || (selectedCode === 'ALL' ? 'Divhumas Polri' : 'Polda Sulbar') }}</span>
          </div>
        </div>

        <!-- Issue Title & Kronologi -->
        <div class="space-y-1.5">
          <Link
            :href="`/issues/${issue.id}?polda=${selectedCode}`"
            class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-snug hover:text-amber-500 dark:hover:text-amber-400 transition block"
          >
            {{ issue.title }}
          </Link>
          <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed line-clamp-2">
            {{ issue.summary }}
          </p>
        </div>

        <!-- Metadata & Context Footer -->
        <div class="flex flex-wrap items-center justify-between gap-3 pt-2 text-xs border-t border-slate-100 dark:border-slate-800/80">
          <div class="flex flex-wrap items-center gap-4 text-slate-500 dark:text-slate-400 text-[11px]">
            <span v-if="issue.location_text" class="flex items-center gap-1">
              <MapPin class="w-3.5 h-3.5 text-slate-400" />
              <span>{{ issue.location_text }}</span>
            </span>

            <span class="flex items-center gap-1">
              <Calendar class="w-3.5 h-3.5 text-slate-400" />
              <span>{{ issue.occurred_at ? new Date(issue.occurred_at).toLocaleString('id-ID') : new Date(issue.created_at).toLocaleString('id-ID') }} WITA</span>
            </span>

            <span v-if="issue.media_files_count > 0" class="px-2 py-0.5 rounded-md bg-amber-50 dark:bg-amber-400/10 text-amber-700 dark:text-amber-400 font-bold">
              📷 {{ issue.media_files_count }} Foto Bukti
            </span>

            <a
              v-if="issue.source_url"
              :href="issue.source_url"
              target="_blank"
              class="text-sky-600 dark:text-sky-400 hover:underline flex items-center gap-1"
            >
              <ExternalLink class="w-3 h-3" /> Sumber Media
            </a>
          </div>

          <div class="flex items-center gap-2">
            <button
              @click="openClarifyModal(issue)"
              type="button"
              class="px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 transition cursor-pointer"
            >
              <Sparkles class="w-3.5 h-3.5 inline mr-1 text-amber-500" />
              <span>Klarifikasi</span>
            </button>

            <Link
              :href="`/issues/${issue.id}?polda=${selectedCode}`"
              class="px-4 py-1.5 rounded-xl text-xs font-black bg-amber-400 hover:bg-amber-300 text-slate-950 flex items-center gap-1.5 transition shadow"
            >
              <span>Detail & Tindak Lanjut</span>
              <ChevronRight class="w-3.5 h-3.5" />
            </Link>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="issues?.links && issues.links.length > 3" class="flex flex-col sm:flex-row items-center justify-between gap-3 p-4 rounded-2xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 shadow-sm">
        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">
          Halaman <span class="font-bold text-amber-600 dark:text-amber-400">{{ issues.current_page }}</span> dari <span class="font-bold text-slate-900 dark:text-white">{{ issues.last_page }}</span> &bull; Menampilkan {{ issues.from }}-{{ issues.to }} dari {{ issues.total }} Laporan
        </p>

        <div class="flex items-center gap-1 flex-wrap">
          <template v-for="(link, idx) in issues.links" :key="idx">
            <Link
              v-if="link.url"
              :href="link.url"
              class="px-3 py-1.5 rounded-xl text-xs font-bold transition"
              :class="link.active
                ? 'bg-amber-400 text-slate-950 font-black shadow-md'
                : 'bg-slate-100 dark:bg-slate-900 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700'"
              v-html="link.label"
            />
            <span
              v-else
              class="px-3 py-1.5 rounded-xl text-xs font-bold text-slate-400 dark:text-slate-600 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-900 select-none"
              v-html="link.label"
            />
          </template>
        </div>
      </div>
    </div>

    <!-- MODAL KLARIFIKASI / FACT-CHECK CEPAT -->
    <div
      v-if="isClarifyModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm"
    >
      <div class="max-w-xl w-full rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-300 dark:border-slate-700 shadow-2xl p-6 sm:p-8 space-y-5 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-4">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-xl bg-amber-400/20 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold">
              ✓
            </div>
            <h2 class="text-lg font-black text-slate-900 dark:text-white">Klarifikasi & Fact-Check Laporan</h2>
          </div>
          <button @click="isClarifyModalOpen = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white p-1">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div v-if="clarifyTarget" class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs">
          <span class="font-bold text-slate-500 dark:text-slate-400 block mb-1">Target Laporan:</span>
          <p class="font-black text-slate-900 dark:text-white leading-snug">{{ clarifyTarget.title }}</p>
        </div>

        <form @submit.prevent="submitClarification" class="space-y-4 text-xs">
          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Status Verifikasi Hoaks</label>
            <div class="flex items-center gap-4 p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700">
              <label class="flex items-center gap-2 font-bold cursor-pointer text-slate-800 dark:text-slate-200">
                <input
                  type="radio"
                  :value="false"
                  v-model="clarifyForm.is_hoax"
                  class="accent-amber-400"
                />
                <span>Bukan Hoaks / Fakta Lapangan</span>
              </label>
              <label class="flex items-center gap-2 font-bold cursor-pointer text-rose-600 dark:text-rose-400">
                <input
                  type="radio"
                  :value="true"
                  v-model="clarifyForm.is_hoax"
                  class="accent-rose-500"
                />
                <span>🚨 Terkonfirmasi HOAKS / Misinformasi</span>
              </label>
            </div>
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Hasil Fact-Check Lapangan</label>
            <input
              v-model="clarifyForm.fact_check_result"
              type="text"
              placeholder="Contoh: Kejadian tersebut merupakan video tahun 2022 di luar wilayah Sulbar"
              class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-amber-400"
            />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Narasi Klarifikasi Resmi Humas</label>
            <textarea
              v-model="clarifyForm.official_clarification"
              rows="4"
              placeholder="Susun rilis sanggahan / klarifikasi resmi yang akan didistribusikan ke media massa dan medsos..."
              class="w-full p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-amber-400 leading-relaxed"
            ></textarea>
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Tautan Publikasi Klarifikasi (Opsional)</label>
            <input
              v-model="clarifyForm.clarification_url"
              type="url"
              placeholder="https://sulbar.polri.go.id/klarifikasi-isu-..."
              class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-amber-400"
            />
          </div>

          <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-200 dark:border-slate-800">
            <button
              type="button"
              @click="isClarifyModalOpen = false"
              class="px-5 py-2.5 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 transition"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-5 py-2.5 rounded-xl text-xs font-black bg-amber-400 hover:bg-amber-300 text-slate-950 transition shadow disabled:opacity-50"
            >
              {{ isSubmitting ? 'Menyimpan...' : 'Simpan Klarifikasi Resmi' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </EnterpriseLayout>
</template>
