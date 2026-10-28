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
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  issues: Object, // Paginated
  filters: Object,
  metrics: Object,
});

const searchQuery = ref('');
const selectedStatus = ref(props.filters?.status || 'all');
const selectedPriority = ref(props.filters?.priority || 'all');

const handleFilter = () => {
  router.get('/issues', {
    polda: props.selectedCode,
    status: selectedStatus.value,
    priority: selectedPriority.value,
  }, { preserveState: true, preserveScroll: true });
};

// Modal Buat Isu Baru
const isCreateModalOpen = ref(false);
const newIssue = ref({
  polda_id: props.selectedPolda?.id || 1,
  polres_id: '',
  title: '',
  summary: '',
  category: 'Harkamtibmas',
  priority: 'medium',
  sentiment: 'netral',
  source_media: '',
  source_url: '',
  assigned_unit: '',
  is_hoax: false,
});

const isSubmitting = ref(false);

const submitNewIssue = () => {
  isSubmitting.value = true;
  router.post('/issues', newIssue.value, {
    preserveScroll: true,
    onSuccess: () => {
      isSubmitting.value = false;
      isCreateModalOpen.value = false;
      newIssue.value = {
        polda_id: props.selectedPolda?.id || 1,
        polres_id: '',
        title: '',
        summary: '',
        category: 'Harkamtibmas',
        priority: 'medium',
        sentiment: 'netral',
        source_media: '',
        source_url: '',
        assigned_unit: '',
        is_hoax: false,
      };
    },
    onError: () => {
      isSubmitting.value = false;
    },
  });
};

// Modal Klarifikasi / Fact-Check
const isClarifyModalOpen = ref(false);
const clarifyTarget = ref(null);
const clarifyForm = ref({
  is_hoax: false,
  fact_check_result: '',
  official_clarification: '',
  clarification_url: '',
});

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

const filteredIssuesList = computed(() => {
  if (!props.issues?.data) return [];
  if (!searchQuery.value) return props.issues.data;
  const q = searchQuery.value.toLowerCase();
  return props.issues.data.filter(
    (i) => i.title.toLowerCase().includes(q) || i.summary.toLowerCase().includes(q)
  );
});
</script>

<template>
  <EnterpriseLayout
    :all-poldas="allPoldas"
    :selected-polda="selectedPolda"
    :selected-code="selectedCode"
    :metrics="metrics"
  >
    <!-- Top Action Bar & Title -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-400 text-slate-950">
            Bidang Humas
          </span>
          <span class="text-xs font-bold text-slate-400">
            {{ selectedCode === 'ALL' ? 'Semua Wilayah Hukum Mabes Polri' : selectedPolda?.name }}
          </span>
        </div>
        <h1 class="text-2xl md:text-3xl font-black text-white tracking-tight mt-1">
          Manajemen Isu Publik & Verifikasi Fakta
        </h1>
        <p class="text-xs md:text-sm text-slate-300">
          Pelacakan isu viralisasi, klarifikasi hoaks, mitigasi risiko reputasi, dan disposisi satker kewilayahan.
        </p>
      </div>

      <div class="flex items-center gap-3">
        <Link
          :href="`/?polda=${selectedCode}`"
          class="px-4 py-2.5 rounded-xl text-xs font-bold bg-slate-900 border border-slate-700 text-slate-200 hover:bg-slate-800 transition"
        >
          ← Kembali ke Dashboard
        </Link>
        <button
          @click="isCreateModalOpen = true"
          class="px-4 py-2.5 rounded-xl text-xs font-black bg-gradient-to-r from-amber-500 to-amber-400 text-slate-950 hover:brightness-110 shadow-lg shadow-amber-400/20 flex items-center gap-2 cursor-pointer transition"
        >
          <PlusCircle class="w-4 h-4" />
          <span>+ Buat Isu Baru</span>
        </button>
      </div>
    </div>

    <!-- Metrics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
      <div class="p-4 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
        <span class="text-[11px] font-bold text-slate-400">Total Kasus Terdata</span>
        <p class="text-2xl font-black text-white">{{ metrics.total_issues || 0 }}</p>
        <p class="text-[10px] text-slate-400">Semua riwayat</p>
      </div>

      <div class="p-4 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
        <span class="text-[11px] font-bold text-sky-400">Dalam Monitoring</span>
        <p class="text-2xl font-black text-sky-400">{{ metrics.monitoring || 0 }}</p>
        <p class="text-[10px] text-slate-400">Pantauan siber aktif</p>
      </div>

      <div class="p-4 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
        <span class="text-[11px] font-bold text-amber-400">Investigasi Lapangan</span>
        <p class="text-2xl font-black text-amber-400">{{ metrics.investigasi || 0 }}</p>
        <p class="text-[10px] text-slate-400">Disposisi Satker/Polres</p>
      </div>

      <div class="p-4 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
        <span class="text-[11px] font-bold text-emerald-400">Klarifikasi Dirilis</span>
        <p class="text-2xl font-black text-emerald-400">{{ metrics.klarifikasi || 0 }}</p>
        <p class="text-[10px] text-slate-400">Siaran pers terbit</p>
      </div>

      <div class="p-4 rounded-2xl bg-[#0b1426] border border-rose-900/60 shadow-lg space-y-1">
        <span class="text-[11px] font-bold text-rose-400">Hoaks Terdeteksi</span>
        <p class="text-2xl font-black text-rose-400">{{ metrics.hoax_count || 0 }}</p>
        <p class="text-[10px] text-slate-400">Terstempel Hoaks</p>
      </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="p-4 rounded-2xl bg-[#0b1426] border border-slate-800 flex flex-col md:flex-row items-center justify-between gap-4 shadow-xl">
      <!-- Search Input -->
      <div class="relative w-full md:w-96">
        <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-3 pointer-events-none" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Cari judul isu, kata kunci, fakta..."
          class="w-full pl-10 pr-4 py-2 rounded-xl bg-slate-900 border border-slate-700 text-xs font-semibold text-white placeholder-slate-400 focus:outline-none focus:border-amber-400"
        />
      </div>

      <!-- Select Filters -->
      <div class="flex items-center gap-3 w-full md:w-auto">
        <div class="flex items-center gap-1.5 text-xs font-bold text-slate-400">
          <Filter class="w-3.5 h-3.5" />
          <span>Status:</span>
        </div>
        <select
          v-model="selectedStatus"
          @change="handleFilter"
          class="px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-xs font-bold text-slate-200 outline-none focus:border-amber-400"
        >
          <option value="all">Semua Status</option>
          <option value="monitoring">Monitoring</option>
          <option value="investigasi">Investigasi</option>
          <option value="klarifikasi">Klarifikasi</option>
          <option value="selesai">Selesai</option>
        </select>

        <select
          v-model="selectedPriority"
          @change="handleFilter"
          class="px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-xs font-bold text-slate-200 outline-none focus:border-amber-400"
        >
          <option value="all">Semua Prioritas</option>
          <option value="critical">Kritis (Merah)</option>
          <option value="high">Tinggi (Kuning)</option>
          <option value="medium">Sedang</option>
          <option value="low">Rendah</option>
        </select>
      </div>
    </div>

    <!-- Issues List Cards -->
    <div class="space-y-4">
      <div
        v-for="issue in filteredIssuesList"
        :key="issue.id"
        class="rounded-3xl bg-[#0b1426] border border-slate-800/90 p-6 shadow-xl space-y-4 hover:border-slate-700 transition"
      >
        <!-- Card Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 border-b border-slate-800/80 pb-4">
          <div class="flex flex-wrap items-center gap-2">
            <!-- Priority Badge -->
            <span
              class="px-2.5 py-1 rounded-xl text-[10px] font-black uppercase tracking-wider"
              :class="{
                'bg-rose-500 text-white': issue.priority === 'critical',
                'bg-amber-400 text-slate-950 font-black': issue.priority === 'high',
                'bg-sky-500/20 text-sky-300 border border-sky-500/40': issue.priority === 'medium',
                'bg-slate-800 text-slate-400': issue.priority === 'low',
              }"
            >
              {{ issue.priority.toUpperCase() }}
            </span>

            <!-- Hoax Stamp -->
            <span
              v-if="issue.is_hoax"
              class="px-2.5 py-1 rounded-xl text-[10px] font-black uppercase tracking-wider bg-rose-600 text-white flex items-center gap-1 shadow-sm"
            >
              <XCircle class="w-3.5 h-3.5" /> HOAKS TERKONFIRMASI
            </span>
            <span
              v-else-if="issue.fact_check_result"
              class="px-2.5 py-1 rounded-xl text-[10px] font-black uppercase tracking-wider bg-emerald-500/20 text-emerald-400 border border-emerald-500/40 flex items-center gap-1"
            >
              <CheckCircle2 class="w-3.5 h-3.5" /> FAKTA TERVERIFIKASI
            </span>

            <!-- Category -->
            <span class="px-2.5 py-1 rounded-xl text-[10px] font-bold bg-slate-800 text-slate-300">
              {{ issue.category }}
            </span>

            <!-- Status Pill -->
            <span
              class="px-2.5 py-1 rounded-xl text-[10px] font-extrabold uppercase"
              :class="{
                'bg-sky-500/20 text-sky-400': issue.status === 'monitoring',
                'bg-amber-400/20 text-amber-300': issue.status === 'investigasi',
                'bg-emerald-500/20 text-emerald-400': issue.status === 'klarifikasi' || issue.status === 'selesai',
              }"
            >
              Status: {{ issue.status }}
            </span>
          </div>

          <div class="text-xs text-slate-400 font-mono">
            Satwil: <span class="text-white font-bold">{{ issue.polres?.name || issue.polda?.name }}</span>
          </div>
        </div>

        <!-- Issue Title & Summary -->
        <div class="space-y-2">
          <h3 class="text-lg md:text-xl font-black text-white leading-snug">
            {{ issue.title }}
          </h3>
          <p class="text-xs md:text-sm text-slate-300 leading-relaxed">
            {{ issue.summary }}
          </p>
        </div>

        <!-- Metric Badges & Assigned Unit -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 pt-2 text-xs">
          <div class="p-3 rounded-xl bg-slate-900/80 border border-slate-800">
            <span class="text-[10px] text-slate-400 block font-bold">Sumber Pantauan:</span>
            <span class="font-bold text-amber-400">{{ issue.source_media || 'Media Massa / Medsos' }}</span>
          </div>
          <div class="p-3 rounded-xl bg-slate-900/80 border border-slate-800">
            <span class="text-[10px] text-slate-400 block font-bold">Skor Viralisasi:</span>
            <span class="font-bold text-sky-400">{{ issue.virality_score }}/100</span>
          </div>
          <div class="p-3 rounded-xl bg-slate-900/80 border border-slate-800">
            <span class="text-[10px] text-slate-400 block font-bold">Estimasi Jangkauan:</span>
            <span class="font-bold text-emerald-400">{{ issue.engagement_count ? issue.engagement_count.toLocaleString() : '5.000+' }} Interaksi</span>
          </div>
          <div class="p-3 rounded-xl bg-slate-900/80 border border-slate-800">
            <span class="text-[10px] text-slate-400 block font-bold">Satker PIC:</span>
            <span class="font-bold text-white">{{ issue.assigned_unit || 'Bidhumas Polda' }}</span>
          </div>
        </div>

        <!-- Official Clarification Box if exists -->
        <div
          v-if="issue.official_clarification"
          class="p-4 rounded-2xl bg-gradient-to-r from-emerald-950/40 to-slate-900 border border-emerald-500/40 space-y-2"
        >
          <div class="flex items-center justify-between">
            <span class="text-xs font-black text-emerald-400 flex items-center gap-1.5">
              <CheckCircle2 class="w-4 h-4" /> Narasi Klarifikasi Resmi Humas Polri
            </span>
            <span class="text-[10px] text-slate-400">Status Publikasi: Terbit</span>
          </div>
          <p class="text-xs text-slate-200 leading-relaxed font-medium">
            {{ issue.official_clarification }}
          </p>
          <div v-if="issue.fact_check_result" class="text-[11px] text-slate-300 pt-1 border-t border-slate-800">
            <span class="font-bold text-amber-400">Hasil Fact Check:</span> {{ issue.fact_check_result }}
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap items-center justify-between pt-3 border-t border-slate-800/80 gap-3">
          <div class="flex items-center gap-2">
            <a
              v-if="issue.source_url"
              :href="issue.source_url"
              target="_blank"
              class="px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-900 border border-slate-700 text-slate-300 hover:text-white flex items-center gap-1.5 transition"
            >
              <ExternalLink class="w-3.5 h-3.5" /> Buka Sumber
            </a>
            <span class="text-[11px] text-slate-400">
              Dibuat: {{ new Date(issue.created_at).toLocaleString('id-ID') }}
            </span>
          </div>

          <div class="flex items-center gap-2">
            <button
              @click="openClarifyModal(issue)"
              class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-sky-500/10 hover:bg-sky-500/20 text-sky-400 border border-sky-500/30 flex items-center gap-1.5 transition cursor-pointer"
            >
              <Sparkles class="w-3.5 h-3.5" />
              <span>{{ issue.official_clarification ? 'Edit Klarifikasi / Fact-Check' : '+ Input Klarifikasi & Cek Fakta' }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL BUAT ISU BARU -->
    <div
      v-if="isCreateModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
    >
      <div class="max-w-xl w-full rounded-3xl bg-[#0b1426] border border-slate-700 shadow-2xl p-6 sm:p-8 space-y-5 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-800 pb-4">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-xl bg-amber-400/20 text-amber-400 flex items-center justify-center font-bold">
              +
            </div>
            <h2 class="text-lg font-black text-white">Buat Kasus / Isu Pemantauan Baru</h2>
          </div>
          <button @click="isCreateModalOpen = false" class="text-slate-400 hover:text-white p-1">
            <X class="w-5 h-5" />
          </button>
        </div>

        <form @submit.prevent="submitNewIssue" class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-slate-200 mb-1">Judul Isu / Berita</label>
            <input
              v-model="newIssue.title"
              type="text"
              required
              placeholder="Contoh: Beredar video komplain antrean solar di Mamuju"
              class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs font-semibold text-white focus:outline-none focus:border-amber-400"
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-bold text-slate-200 mb-1">Kategori</label>
              <select
                v-model="newIssue.category"
                class="w-full px-3 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs font-bold text-white focus:border-amber-400"
              >
                <option value="Harkamtibmas">Harkamtibmas</option>
                <option value="Lalu Lintas">Lalu Lintas</option>
                <option value="Kriminalitas">Kriminalitas</option>
                <option value="Pelayanan Publik">Pelayanan Publik</option>
                <option value="Bencana">Bencana</option>
                <option value="Internal Polri">Internal Polri</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-200 mb-1">Tingkat Prioritas</label>
              <select
                v-model="newIssue.priority"
                class="w-full px-3 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs font-bold text-white focus:border-amber-400"
              >
                <option value="low">Rendah (Low)</option>
                <option value="medium">Sedang (Medium)</option>
                <option value="high">Tinggi (High / Siaga Kuning)</option>
                <option value="critical">Kritis (Critical / Siaga Merah)</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-bold text-slate-200 mb-1">Sentimen Awal</label>
              <select
                v-model="newIssue.sentiment"
                class="w-full px-3 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs font-bold text-white focus:border-amber-400"
              >
                <option value="negatif">Negatif</option>
                <option value="netral">Netral</option>
                <option value="positif">Positif</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-200 mb-1">Sumber Media</label>
              <input
                v-model="newIssue.source_media"
                type="text"
                placeholder="TikTok, Antara, Facebook..."
                class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs font-semibold text-white focus:outline-none focus:border-amber-400"
              />
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-200 mb-1">Tautan / URL Sumber</label>
            <input
              v-model="newIssue.source_url"
              type="url"
              placeholder="https://..."
              class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs font-semibold text-white focus:outline-none focus:border-amber-400"
            />
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-200 mb-1">Satker Disposisi (PIC Unit)</label>
            <input
              v-model="newIssue.assigned_unit"
              type="text"
              placeholder="Contoh: Ditreskrimsus Polda Sulbar / Satlantas"
              class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs font-semibold text-white focus:outline-none focus:border-amber-400"
            />
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-200 mb-1">Ringkasan Narasi & Kronologi</label>
            <textarea
              v-model="newIssue.summary"
              rows="3"
              required
              placeholder="Uraikan kronologi singkat isu yang sedang berkembang di media..."
              class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs font-semibold text-white focus:outline-none focus:border-amber-400"
            ></textarea>
          </div>

          <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
            <button
              type="button"
              @click="isCreateModalOpen = false"
              class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-800 text-slate-300 hover:text-white"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-5 py-2.5 rounded-xl text-xs font-black bg-amber-400 text-slate-950 hover:bg-amber-300 shadow-md transition"
            >
              {{ isSubmitting ? 'Menyimpan...' : 'Simpan Isu Pemantauan' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL KLARIFIKASI / FACT-CHECK -->
    <div
      v-if="isClarifyModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
    >
      <div class="max-w-xl w-full rounded-3xl bg-[#0b1426] border border-slate-700 shadow-2xl p-6 sm:p-8 space-y-5 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-800 pb-4">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-xl bg-sky-500/20 text-sky-400 flex items-center justify-center font-bold">
              ✓
            </div>
            <div>
              <h2 class="text-base font-black text-white">Input Klarifikasi Resmi & Fact Check</h2>
              <p class="text-[11px] text-slate-400 truncate max-w-sm">{{ clarifyTarget?.title }}</p>
            </div>
          </div>
          <button @click="isClarifyModalOpen = false" class="text-slate-400 hover:text-white p-1">
            <X class="w-5 h-5" />
          </button>
        </div>

        <form @submit.prevent="submitClarification" class="space-y-4">
          <div class="p-3 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-between">
            <label class="text-xs font-bold text-white flex items-center gap-2 cursor-pointer">
              <input
                v-model="clarifyForm.is_hoax"
                type="checkbox"
                class="w-4 h-4 rounded text-rose-500 focus:ring-rose-500 bg-slate-800 border-slate-700"
              />
              <span>Tandai sebagai Informasi HOAKS / Disinformasi</span>
            </label>
            <span
              class="text-[10px] font-black px-2 py-0.5 rounded-full"
              :class="clarifyForm.is_hoax ? 'bg-rose-500 text-white' : 'bg-slate-800 text-slate-400'"
            >
              {{ clarifyForm.is_hoax ? 'STATUS: HOAKS' : 'STATUS: FAKTA' }}
            </span>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-200 mb-1">Hasil Fact Check / Temuan Tim Siber</label>
            <textarea
              v-model="clarifyForm.fact_check_result"
              rows="3"
              required
              placeholder="Contoh: Video tersebut adalah peristiwa lama di daerah lain tahun 2021 yang disebarkan ulang..."
              class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs font-semibold text-white focus:outline-none focus:border-amber-400"
            ></textarea>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-200 mb-1">Narasi Klarifikasi Resmi Humas (Untuk Media & Netizen)</label>
            <textarea
              v-model="clarifyForm.official_clarification"
              rows="4"
              required
              placeholder="Tuliskan draf rilis pers atau counter narasi resmi Bidhumas..."
              class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs font-semibold text-white focus:outline-none focus:border-amber-400"
            ></textarea>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-200 mb-1">Tautan Publikasi Rilis (Opsional)</label>
            <input
              v-model="clarifyForm.clarification_url"
              type="url"
              placeholder="https://humas.polri.go.id/..."
              class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs font-semibold text-white focus:outline-none focus:border-amber-400"
            />
          </div>

          <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
            <button
              type="button"
              @click="isClarifyModalOpen = false"
              class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-800 text-slate-300 hover:text-white"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-5 py-2.5 rounded-xl text-xs font-black bg-emerald-500 text-slate-950 hover:bg-emerald-400 shadow-md transition"
            >
              {{ isSubmitting ? 'Memproses...' : 'Publikasikan Klarifikasi' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </EnterpriseLayout>
</template>
