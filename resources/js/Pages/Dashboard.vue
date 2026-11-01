<script setup>
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import {
  Shield,
  Radio,
  Building2,
  Flame,
  Newspaper,
  Clock,
  Layers,
  Users,
  CheckCircle2,
  AlertTriangle,
  ChevronRight,
  TrendingUp,
  MapPin,
  ExternalLink,
  PlusCircle,
  Download,
  FileBarChart2,
  Sparkles,
  ArrowRight,
  Activity,
  Check,
  ZoomIn,
  ZoomOut,
  RotateCcw,
  Grid,
  Map as MapIcon,
  X,
  Filter,
  ThumbsUp,
  Eye,
  Play,
  Share2,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  kpi: Object,
  socialStats: Object,
  topViralSocialPosts: Array,
  liveSocialPosts: Array,
  polresStatuses: Array,
  overallSituation: Object,
  topIssues: Array,
  sentiment7Days: Array,
  avgPositivePct: Number,
  liveRadarArticles: Array,
  publicIndex: Object,
  metrics: Object,
});

// View mode: 'map' (Peta Interaktif) or 'cards' (Matriks Kartu)
const viewMode = ref('map');

// Tabs for Trending & Live Stream
const activeTrendingTab = ref('social'); // 'social' | 'issues'
const activeStreamTab = ref('social'); // 'social' | 'media'

// Helper formatters
const formatNumber = (num) => {
  if (!num && num !== 0) return '0';
  if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
  if (num >= 1000) return (num / 1000).toFixed(1) + 'k';
  return num.toString();
};

const formatTimeAgo = (dateStr) => {
  if (!dateStr) return 'Baru saja';
  const d = new Date(dateStr);
  const diff = (Date.now() - d.getTime()) / 1000;
  if (diff < 60) return 'Baru saja';
  if (diff < 3600) return Math.floor(diff / 60) + ' mnt lalu';
  if (diff < 86400) return Math.floor(diff / 3600) + ' jam lalu';
  return Math.floor(diff / 86400) + ' hari lalu';
};

const getPlatformBadge = (platform) => {
  switch (platform?.toLowerCase()) {
    case 'youtube':
      return { label: 'YouTube', color: 'bg-rose-500/20 text-rose-300 border-rose-500/40', badgeColor: 'bg-rose-600' };
    case 'tiktok':
      return { label: 'TikTok', color: 'bg-cyan-500/20 text-cyan-300 border-cyan-500/40', badgeColor: 'bg-cyan-600' };
    case 'instagram':
      return { label: 'Instagram', color: 'bg-pink-500/20 text-pink-300 border-pink-500/40', badgeColor: 'bg-pink-600' };
    case 'x':
    case 'twitter':
      return { label: '𝕏', color: 'bg-slate-700/40 text-slate-200 border-slate-600/50', badgeColor: 'bg-slate-700' };
    default:
      return { label: platform || 'Medsos', color: 'bg-slate-800 text-slate-300 border-slate-700', badgeColor: 'bg-slate-800' };
  }
};

// Satwil filter / focus
const selectedSatwilCode = ref('ALL');

// Map Zoom State
const mapZoom = ref(1.0);
const zoomIn = () => {
  if (mapZoom.value < 1.6) mapZoom.value = Math.round((mapZoom.value + 0.15) * 100) / 100;
};
const zoomOut = () => {
  if (mapZoom.value > 0.8) mapZoom.value = Math.round((mapZoom.value - 0.15) * 100) / 100;
};
const resetZoom = () => {
  mapZoom.value = 1.0;
};

// Interactive Pin Callout Selection
const activePin = ref(null);
const selectPolresPin = (p) => {
  if (activePin.value?.id === p.id) {
    activePin.value = null;
  } else {
    activePin.value = p;
  }
};
const closeCallout = () => {
  activePin.value = null;
};

// Filtered Polres list based on Satwil dropdown
const filteredPolres = computed(() => {
  if (!props.polresStatuses) return [];
  if (selectedSatwilCode.value === 'ALL') return props.polresStatuses;
  return props.polresStatuses.filter((p) => p.code === selectedSatwilCode.value);
});

// Real-time status counts for legend
const atensiCount = computed(() => props.polresStatuses?.filter((p) => p.status === 'ATENSI').length || 0);
const waspadaCount = computed(() => props.polresStatuses?.filter((p) => p.status === 'WASPADA').length || 0);
const kondusifCount = computed(() => props.polresStatuses?.filter((p) => p.status === 'KONDUSIF').length || 0);

// Layer filters (active simulation)
const layerMedia = ref(true);
const layerMedsos = ref(true);
const layerLapangan = ref(true);
</script>

<template>
  <EnterpriseLayout
    :all-poldas="allPoldas"
    :selected-polda="selectedPolda"
    :selected-code="selectedCode"
    :metrics="metrics"
  >
    <!-- ========================================================================= -->
    <!-- 1. HERO BANNER: COMMAND CENTER PRESISI                                    -->
    <!-- ========================================================================= -->
    <div class="rounded-3xl bg-gradient-to-r from-[#0d182e] via-[#0b1426] to-[#070d1d] border border-slate-800 p-6 md:p-8 shadow-2xl relative overflow-hidden">
      <div class="absolute top-0 right-0 -mt-10 -mr-10 w-72 h-72 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>

      <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 relative z-10">
        <div class="space-y-3">
          <div class="flex flex-wrap items-center gap-2">
            <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-rose-500/20 text-rose-400 border border-rose-500/40">
              Klasifikasi: Rahasia Terbatas / Command Center
            </span>

            <!-- Dynamic Situation Status Badge -->
            <span
              class="flex items-center gap-1.5 px-3 py-0.5 rounded-full text-[10px] font-bold border transition"
              :class="{
                'bg-rose-500/20 text-rose-300 border-rose-500/40': overallSituation?.color === 'rose',
                'bg-amber-400/20 text-amber-300 border-amber-400/40': overallSituation?.color === 'amber',
                'bg-emerald-500/20 text-emerald-300 border-emerald-500/40': overallSituation?.color === 'emerald',
              }"
            >
              <span
                class="w-2 h-2 rounded-full"
                :class="{
                  'bg-rose-400 animate-ping': overallSituation?.color === 'rose',
                  'bg-amber-400 animate-ping': overallSituation?.color === 'amber',
                  'bg-emerald-400': overallSituation?.color === 'emerald',
                }"
              ></span>
              {{ overallSituation?.text || 'SITUASI KONDUSIF & TERKENDALI' }}
            </span>
          </div>

          <h1 class="text-2xl md:text-4xl font-black text-white tracking-tight">
            Dashboard Media Intelligence & Kamtibmas
          </h1>
          <p class="text-xs md:text-sm text-slate-300 max-w-2xl leading-relaxed">
            Monitoring terpadu pemberitaan 29 portal media online, radar 4 platform media sosial (𝕏, TikTok, Instagram, YouTube), laporan lapangan Satwil, dan orkestrasi sentimen pimpinan <strong>{{ selectedCode === 'ALL' ? 'Divhumas Polri (Nasional)' : selectedPolda?.name }}</strong>.
          </p>

          <!-- Quick Action Buttons -->
          <div class="flex flex-wrap items-center gap-2.5 pt-2">
            <Link
              href="/issues/create"
              class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-400 hover:from-amber-400 hover:to-amber-300 text-slate-950 text-xs font-black transition shadow-lg shadow-amber-500/20"
            >
              <PlusCircle class="w-4 h-4" />
              <span>+ Buat Laporan / Disposisi Cepat</span>
            </Link>

            <Link
              href="/social-intelligence"
              class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-200 text-xs font-bold transition"
            >
              <Radio class="w-4 h-4 text-amber-400" />
              <span>Radar Medsos ({{ socialStats?.total_posts || 0 }}) &rarr;</span>
            </Link>

            <Link
              href="/media-intelligence"
              class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-200 text-xs font-bold transition"
            >
              <Newspaper class="w-4 h-4 text-sky-400" />
              <span>Radar Media Online ({{ kpi?.total_articles || 0 }}) &rarr;</span>
            </Link>

            <Link
              href="/reports"
              class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-200 text-xs font-bold transition"
            >
              <FileBarChart2 class="w-4 h-4 text-emerald-400" />
              <span>Unduh Rekap</span>
            </Link>

            <Link
              href="/verification-queue"
              class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-200 text-xs font-bold transition"
            >
              <CheckCircle2 class="w-4 h-4 text-amber-400" />
              <span>Verifikasi ({{ kpi?.pending_verify || 0 }})</span>
            </Link>
          </div>
        </div>

        <!-- Right: Public Sentiment Gauge Dynamic -->
        <div class="p-6 rounded-3xl bg-[#091122]/90 border border-amber-400/30 shadow-2xl flex flex-col items-center text-center space-y-2 shrink-0">
          <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Indeks Citra Publik Terpadu</span>
          <div class="flex items-baseline gap-2">
            <span class="text-5xl font-black text-amber-400 tracking-tight">{{ publicIndex?.score || 78 }}%</span>
            <span class="text-xs font-bold text-emerald-400 flex items-center">
              <TrendingUp class="w-3.5 h-3.5 mr-0.5" /> {{ publicIndex?.change || '+6%' }}
            </span>
          </div>
          <span
            class="px-3 py-1 rounded-full text-[10px] font-black uppercase border"
            :class="{
              'bg-emerald-500/20 text-emerald-300 border-emerald-500/40': publicIndex?.score >= 70,
              'bg-amber-500/20 text-amber-300 border-amber-500/40': publicIndex?.score >= 50 && publicIndex?.score < 70,
              'bg-rose-500/20 text-rose-300 border-rose-500/40': publicIndex?.score < 50,
            }"
          >
            Kategori: {{ publicIndex?.status || 'Positif' }}
          </span>
          <p class="text-[10px] text-slate-400 font-medium">Media Online + Medsos (7 Hari)</p>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 2. 5 OPERATIONAL KPI CARDS (Media Online, Medsos, Isu, Dumas, Tugas)      -->
    <!-- ========================================================================= -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
      <!-- 1. Radar Media Online -->
      <Link
        href="/media-intelligence"
        class="p-4 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1.5 hover:border-sky-500/50 transition group block"
      >
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
          <span class="group-hover:text-white transition">Radar Media Online</span>
          <Newspaper class="w-4 h-4 text-sky-400" />
        </div>
        <div class="flex items-baseline justify-between">
          <p class="text-2xl md:text-3xl font-black text-white">{{ kpi?.total_articles || 0 }}</p>
          <span class="text-[10px] font-black text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20">
            {{ kpi?.active_feeds_count || 29 }} Portal
          </span>
        </div>
        <p class="text-[11px] text-slate-400">Agregasi Berimbang Fair Quota</p>
      </Link>

      <!-- 2. Radar Media Sosial -->
      <Link
        href="/social-intelligence"
        class="p-4 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1.5 hover:border-amber-500/50 transition group block"
      >
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
          <span class="group-hover:text-white transition">Radar Media Sosial</span>
          <Radio class="w-4 h-4 text-amber-400 animate-pulse" />
        </div>
        <div class="flex items-baseline justify-between">
          <p class="text-2xl md:text-3xl font-black text-amber-400">{{ socialStats?.total_posts || 0 }}</p>
          <span class="text-[10px] font-black text-amber-300 bg-amber-500/10 px-2 py-0.5 rounded border border-amber-500/20">
            4 Platform
          </span>
        </div>
        <div class="flex items-center gap-1.5 text-[10px] text-slate-400 font-mono">
          <span>{{ socialStats?.platforms?.youtube || 0 }} YT</span>
          <span>•</span>
          <span>{{ socialStats?.platforms?.tiktok || 0 }} TT</span>
          <span>•</span>
          <span>{{ socialStats?.platforms?.instagram || 0 }} IG</span>
          <span>•</span>
          <span>{{ socialStats?.platforms?.x || 0 }} 𝕏</span>
        </div>
      </Link>

      <!-- 3. Isu Prioritas Atensi -->
      <Link
        href="/issues?priority=critical"
        class="p-4 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1.5 hover:border-rose-500/50 transition group block"
      >
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
          <span class="group-hover:text-white transition">Isu Prioritas Atensi</span>
          <Flame class="w-4 h-4 text-rose-400" />
        </div>
        <div class="flex items-baseline justify-between">
          <p class="text-2xl md:text-3xl font-black text-rose-400">{{ kpi?.priority_issues || 0 }}</p>
          <span
            class="px-2 py-0.5 rounded text-[10px] font-black"
            :class="kpi?.priority_issues > 0 ? 'bg-rose-500/20 text-rose-300 border border-rose-500/40' : 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40'"
          >
            {{ kpi?.priority_issues > 0 ? 'Siaga' : 'Terkendali' }}
          </span>
        </div>
        <p class="text-[11px] text-slate-400">Perlu Respon & Klarifikasi</p>
      </Link>

      <!-- 4. Laporan Lapangan Polres -->
      <Link
        href="/issues"
        class="p-4 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1.5 hover:border-amber-500/50 transition group block"
      >
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
          <span class="group-hover:text-white transition">Laporan Lapangan</span>
          <MapPin class="w-4 h-4 text-emerald-400" />
        </div>
        <div class="flex items-baseline justify-between">
          <p class="text-2xl md:text-3xl font-black text-white">{{ kpi?.field_reports || 0 }}</p>
          <span class="text-xs font-bold text-amber-300">{{ kpi?.pending_verify || 0 }} pending</span>
        </div>
        <p class="text-[11px] text-slate-400">Dumas & Kamtibmas 6 Polres</p>
      </Link>

      <!-- 5. Tindak Lanjut Selesai -->
      <Link
        href="/tasks"
        class="p-4 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1.5 hover:border-emerald-500/50 transition group block"
      >
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
          <span class="group-hover:text-white transition">Tindak Lanjut Tugas</span>
          <CheckCircle2 class="w-4 h-4 text-emerald-400" />
        </div>
        <div class="flex items-baseline justify-between">
          <p class="text-2xl md:text-3xl font-black text-emerald-400">{{ kpi?.tasks_resolved || 0 }}</p>
          <span class="text-xs font-bold text-emerald-300">{{ kpi?.task_completion_rate || 100 }}% Selesai</span>
        </div>
        <p class="text-[11px] text-slate-400">Disposisi & Klarifikasi Media</p>
      </Link>
    </div>

    <!-- ========================================================================= -->
    <!-- 3. MIDDLE GRID: PETA 6 POLRES SULBAR & SENTIMEN AI                        -->
    <!-- ========================================================================= -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Col 1 & 2: Peta Sebaran Isu & Sentimen Kamtibmas 6 Polres -->
      <div class="lg:col-span-2 rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-2xl space-y-4">
        <!-- Card Header with Mode Toggle & Filters -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800/80 pb-4">
          <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
              <MapPin class="w-4 h-4" />
            </div>
            <div>
              <h2 class="text-base font-black text-white uppercase tracking-wider flex items-center gap-2">
                {{ selectedCode === 'ALL' ? 'Peta Situasi Kamtibmas & Sentimen Satwil Jajaran' : 'Peta Sebaran Isu & Sentimen Kamtibmas 6 Polres' }}
              </h2>
              <p class="text-xs text-slate-400">
                {{ selectedCode === 'ALL' ? 'Orkestrasi Nasional Divhumas Polri' : (selectedPolda?.name ? `Wilayah Hukum ${selectedPolda.name}` : 'Wilayah Hukum Polda Sulawesi Barat') }}
              </p>
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <!-- View Mode Toggle: Map vs Cards -->
            <div class="inline-flex rounded-xl bg-slate-900 border border-slate-800 p-0.5 text-xs font-bold">
              <button
                type="button"
                @click="viewMode = 'map'"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg transition"
                :class="viewMode === 'map' ? 'bg-amber-400 text-slate-950 font-black shadow' : 'text-slate-400 hover:text-white'"
              >
                <MapIcon class="w-3.5 h-3.5" />
                <span>Peta Interaktif</span>
              </button>
              <button
                type="button"
                @click="viewMode = 'cards'"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg transition"
                :class="viewMode === 'cards' ? 'bg-amber-400 text-slate-950 font-black shadow' : 'text-slate-400 hover:text-white'"
              >
                <Grid class="w-3.5 h-3.5" />
                <span>Matriks Kartu</span>
              </button>
            </div>

            <!-- Satwil Filter Select -->
            <select
              v-model="selectedSatwilCode"
              class="px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-800 text-xs font-bold text-slate-300 focus:outline-none focus:border-amber-400"
            >
              <option value="ALL">{{ selectedCode === 'ALL' ? 'Semua Jajaran Satwil' : 'Semua Satwil (6 Polres)' }}</option>
              <option v-for="p in polresStatuses" :key="p.id" :value="p.code">
                {{ p.name }} ({{ p.status }})
              </option>
            </select>
          </div>
        </div>

        <!-- Layer Checkboxes -->
        <div class="flex flex-wrap items-center justify-between text-xs text-slate-400 gap-2 pb-1">
          <div class="flex items-center gap-3">
            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Layer Pantauan:</span>
            <label class="inline-flex items-center gap-1.5 cursor-pointer hover:text-white transition">
              <input type="checkbox" v-model="layerMedia" class="rounded bg-slate-800 border-slate-700 text-amber-500 focus:ring-0 w-3.5 h-3.5" />
              <span>Media RSS</span>
            </label>
            <label class="inline-flex items-center gap-1.5 cursor-pointer hover:text-white transition">
              <input type="checkbox" v-model="layerMedsos" class="rounded bg-slate-800 border-slate-700 text-amber-500 focus:ring-0 w-3.5 h-3.5" />
              <span>Media Sosial</span>
            </label>
            <label class="inline-flex items-center gap-1.5 cursor-pointer hover:text-white transition">
              <input type="checkbox" v-model="layerLapangan" class="rounded bg-slate-800 border-slate-700 text-amber-500 focus:ring-0 w-3.5 h-3.5" />
              <span>Laporan Lapangan</span>
            </label>
          </div>

          <span class="text-xs font-mono text-emerald-400 flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
            {{ selectedCode === 'ALL' ? 'Komando Nasional Terpantau Aktif' : '6 Satwil Terpantau Aktif' }}
          </span>
        </div>

        <!-- ========================================================================= -->
        <!-- VIEW 1: INTERACTIVE GEOSPATIAL VECTOR MAP (Kamtibmas Sulbar Map)          -->
        <!-- ========================================================================= -->
        <div
          v-if="viewMode === 'map'"
          class="relative w-full h-[520px] rounded-2xl bg-[#071022] border border-slate-800/80 overflow-hidden select-none"
        >
          <!-- Background Grid & Coordinates -->
          <div
            class="absolute inset-0 opacity-20 pointer-events-none"
            style="background-image: radial-gradient(#38bdf8 1px, transparent 1px); background-size: 24px 24px;"
          ></div>
          <div class="absolute inset-0 opacity-15 pointer-events-none">
            <div class="w-full h-px bg-sky-400 absolute top-1/4"></div>
            <div class="w-full h-px bg-sky-400 absolute top-1/2"></div>
            <div class="w-full h-px bg-sky-400 absolute top-3/4"></div>
            <div class="h-full w-px bg-sky-400 absolute left-1/3"></div>
            <div class="h-full w-px bg-sky-400 absolute left-2/3"></div>
          </div>

          <!-- Selat Makassar Watermark -->
          <div class="absolute left-6 top-1/2 -translate-y-1/2 text-slate-700/60 font-black text-xs md:text-sm tracking-[0.4em] uppercase pointer-events-none select-none z-0">
            S E L A T &nbsp; M A K A S S A R
          </div>

          <!-- Compass North Indicator -->
          <div class="absolute left-4 top-4 z-20 flex flex-col items-center bg-slate-900/90 border border-slate-700/80 p-2 rounded-xl text-[10px] text-slate-300 font-mono shadow-xl backdrop-blur-sm">
            <span class="font-black text-amber-400">▲ N</span>
            <span class="text-[8px] text-slate-400 font-bold">WITA</span>
          </div>

          <!-- Map Zoom Controls (Top Right) -->
          <div class="absolute right-4 top-4 z-20 flex flex-col gap-1.5 bg-slate-900/90 border border-slate-700/80 p-1.5 rounded-xl shadow-xl backdrop-blur-sm">
            <button
              type="button"
              @click="zoomIn"
              class="w-7 h-7 rounded-lg bg-slate-800 hover:bg-slate-700 text-white flex items-center justify-center transition text-xs font-bold"
              title="Perbesar Peta (+)"
            >
              <ZoomIn class="w-3.5 h-3.5" />
            </button>
            <button
              type="button"
              @click="zoomOut"
              class="w-7 h-7 rounded-lg bg-slate-800 hover:bg-slate-700 text-white flex items-center justify-center transition text-xs font-bold"
              title="Perkecil Peta (-)"
            >
              <ZoomOut class="w-3.5 h-3.5" />
            </button>
            <button
              type="button"
              @click="resetZoom"
              class="w-7 h-7 rounded-lg bg-slate-800 hover:bg-slate-700 text-amber-400 flex items-center justify-center transition text-xs font-bold"
              title="Reset Tampilan (100%)"
            >
              <RotateCcw class="w-3.5 h-3.5" />
            </button>
          </div>

          <!-- Scalable Map Layer -->
          <div
            class="absolute inset-0 transition-transform duration-500 ease-out origin-center"
            :style="{ transform: `scale(${mapZoom})` }"
          >
            <!-- SVG Landmass of Sulawesi Barat -->
            <svg class="w-full h-full" viewBox="0 0 600 500" preserveAspectRatio="xMidYMid meet">
              <defs>
                <filter id="glowRed" x="-20%" y="-20%" width="140%" height="140%">
                  <feGaussianBlur stdDeviation="3" result="blur" />
                  <feComposite in="SourceGraphic" in2="blur" operator="over" />
                </filter>
                <filter id="glowGreen" x="-20%" y="-20%" width="140%" height="140%">
                  <feGaussianBlur stdDeviation="3" result="blur" />
                  <feComposite in="SourceGraphic" in2="blur" operator="over" />
                </filter>
                <linearGradient id="sulbarGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                  <stop offset="0%" stop-color="#14244d" stop-opacity="0.9" />
                  <stop offset="50%" stop-color="#0f1e40" stop-opacity="0.85" />
                  <stop offset="100%" stop-color="#182a57" stop-opacity="0.95" />
                </linearGradient>
                <linearGradient id="coastGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                  <stop offset="0%" stop-color="#38bdf8" stop-opacity="0.8" />
                  <stop offset="100%" stop-color="#3b82f6" stop-opacity="0.4" />
                </linearGradient>
              </defs>

              <!-- Regional Sea Boundary Dotted Curves -->
              <path d="M 120 180 Q 180 220 200 320" fill="none" stroke="#1e293b" stroke-width="1.5" stroke-dasharray="4 6" opacity="0.4" />
              <path d="M 90 260 Q 140 310 160 410" fill="none" stroke="#1e293b" stroke-width="1.5" stroke-dasharray="4 6" opacity="0.4" />

              <g class="transition-all duration-300">
                <!-- Authentic Coastline Landmass of Sulawesi Barat -->
                <path
                  d="
                    M 340 45
                    C 365 60, 390 85, 395 115
                    C 400 145, 385 170, 380 200
                    C 375 225, 420 250, 435 290
                    C 445 320, 430 355, 415 375
                    C 400 395, 360 410, 340 435
                    C 325 450, 280 445, 275 425
                    C 270 410, 230 395, 215 385
                    C 195 370, 190 350, 205 340
                    C 220 330, 235 305, 240 280
                    C 245 255, 260 240, 265 210
                    C 270 175, 305 130, 320 95
                    Z
                  "
                  fill="url(#sulbarGrad)"
                  stroke="#2563eb"
                  stroke-width="1.8"
                  class="drop-shadow-[0_0_15px_rgba(37,99,235,0.25)]"
                />

                <!-- Internal District Contour Curves -->
                <path d="M 330 115 Q 365 125 390 120" stroke="#3b82f6" stroke-width="1" stroke-dasharray="3 3" opacity="0.6" fill="none" />
                <path d="M 280 195 Q 330 205 380 195" stroke="#3b82f6" stroke-width="1" stroke-dasharray="3 3" opacity="0.6" fill="none" />
                <path d="M 245 275 Q 310 265 375 255" stroke="#3b82f6" stroke-width="1" stroke-dasharray="3 3" opacity="0.6" fill="none" />
                <path d="M 215 375 Q 260 365 295 370" stroke="#3b82f6" stroke-width="1" stroke-dasharray="3 3" opacity="0.6" fill="none" />
                <path d="M 305 365 Q 330 345 375 340" stroke="#3b82f6" stroke-width="1" stroke-dasharray="3 3" opacity="0.6" fill="none" />

                <!-- Trans-Sulawesi Highway Dashed Route -->
                <path
                  d="
                    M 345 55
                    L 360 110
                    L 330 185
                    L 265 260
                    L 215 350
                    L 235 385
                    L 295 430
                  "
                  fill="none"
                  stroke="#0284c7"
                  stroke-width="1.8"
                  stroke-dasharray="6 4"
                  opacity="0.75"
                />
              </g>
            </svg>

            <!-- Interactive Pin Markers for 6 Polres -->
            <div
              v-for="p in polresStatuses"
              :key="p.id"
              class="absolute pointer-events-auto transform -translate-x-1/2 -translate-y-1/2 cursor-pointer z-30"
              :style="{ left: `${p.map_x}%`, top: `${p.map_y}%` }"
            >
              <div
                @click="selectPolresPin(p)"
                class="relative flex flex-col items-center group"
              >
                <!-- Sonar Radar Pulsing Ring -->
                <span
                  class="absolute w-8 h-8 rounded-full pointer-events-none"
                  :class="{
                    'bg-rose-500/70 animate-ping': p.status === 'ATENSI',
                    'bg-amber-400/60 animate-ping': p.status === 'WASPADA',
                    'bg-emerald-400/50 animate-ping': p.status === 'KONDUSIF',
                  }"
                ></span>

                <!-- Pin Circle Button -->
                <div
                  class="w-6 h-6 rounded-full flex items-center justify-center shadow-xl transition-transform transform group-hover:scale-125 border-2 z-10"
                  :class="{
                    'bg-rose-600 border-white text-white shadow-rose-500/50 ring-2 ring-rose-400/50': p.status === 'ATENSI',
                    'bg-amber-500 border-white text-white shadow-amber-500/50 ring-2 ring-amber-400/50': p.status === 'WASPADA',
                    'bg-emerald-500 border-white text-white shadow-emerald-500/50 ring-2 ring-emerald-400/50': p.status === 'KONDUSIF',
                  }"
                >
                  <span class="w-2 h-2 rounded-full bg-white"></span>
                </div>

                <!-- Short Name Label Tag Below Pin -->
                <div
                  class="mt-1 px-2 py-0.5 rounded-full text-[10px] font-bold whitespace-nowrap border shadow-lg backdrop-blur-md transition group-hover:scale-105"
                  :class="{
                    'bg-slate-900/90 text-rose-300 border-rose-500/40': p.status === 'ATENSI',
                    'bg-slate-900/90 text-amber-300 border-amber-500/40': p.status === 'WASPADA',
                    'bg-slate-900/90 text-emerald-300 border-emerald-500/40': p.status === 'KONDUSIF',
                  }"
                >
                  {{ p.short_name }}
                </div>
              </div>

              <!-- Interactive Callout Popover Card -->
              <div
                v-if="activePin?.id === p.id"
                class="absolute z-50 w-64 md:w-72 rounded-2xl p-4 shadow-2xl backdrop-blur-xl transition-all duration-200 border"
                :class="[
                  p.status === 'ATENSI'
                    ? 'bg-[#091122]/95 border-rose-500/70 shadow-rose-950/60'
                    : p.status === 'WASPADA'
                    ? 'bg-[#091122]/95 border-amber-500/70 shadow-amber-950/60'
                    : 'bg-[#091122]/95 border-emerald-500/70 shadow-emerald-950/60',
                  p.map_x > 50 ? 'right-7' : 'left-7',
                  p.map_y > 60 ? 'bottom-0' : '-top-4',
                ]"
              >
                <!-- Callout Header -->
                <div class="flex items-start justify-between gap-2 border-b border-slate-800 pb-2 mb-2">
                  <div class="flex items-center gap-1.5">
                    <span
                      class="w-2.5 h-2.5 rounded-full"
                      :class="{
                        'bg-rose-500 animate-pulse': p.status === 'ATENSI',
                        'bg-amber-400 animate-pulse': p.status === 'WASPADA',
                        'bg-emerald-400': p.status === 'KONDUSIF',
                      }"
                    ></span>
                    <span class="font-black text-white text-xs">{{ p.name }}</span>
                  </div>
                  <div class="flex items-center gap-1">
                    <span
                      class="text-[9px] font-black px-1.5 py-0.5 rounded uppercase tracking-wider"
                      :class="{
                        'bg-rose-500/30 text-rose-300 border border-rose-500/50': p.status === 'ATENSI',
                        'bg-amber-500/30 text-amber-300 border border-amber-500/50': p.status === 'WASPADA',
                        'bg-emerald-500/30 text-emerald-300 border border-emerald-500/50': p.status === 'KONDUSIF',
                      }"
                    >
                      {{ p.status }}
                    </span>
                    <button
                      type="button"
                      @click.stop="closeCallout"
                      class="p-0.5 rounded text-slate-400 hover:text-white hover:bg-slate-800 transition"
                      title="Tutup"
                    >
                      <X class="w-3.5 h-3.5" />
                    </button>
                  </div>
                </div>

                <!-- Callout Body -->
                <p class="text-[10px] text-slate-400 font-bold mb-1">{{ p.tag_desc }}</p>
                <p class="text-[11px] text-slate-200 line-clamp-3 leading-relaxed mb-3 bg-slate-900/60 p-2 rounded-xl border border-slate-800/80">
                  {{ p.summary }}
                </p>

                <!-- Callout Footer -->
                <div class="flex items-center justify-between text-[10px] pt-1 border-t border-slate-800">
                  <span class="text-slate-400">
                    Skor Opini: <strong class="text-white">{{ p.score }}</strong>
                  </span>
                  <span class="text-amber-400 font-bold">
                    {{ p.issues_count }} Isu Aktif ({{ p.total_issues }} Laporan)
                  </span>
                </div>

                <div class="mt-2.5 pt-2 border-t border-slate-800/80 flex items-center justify-between">
                  <Link
                    :href="`/issues?polres=${p.code}`"
                    class="text-[10px] font-bold text-amber-400 hover:underline flex items-center gap-1"
                  >
                    <span>Lihat Laporan Polres</span>
                    <ArrowRight class="w-3 h-3" />
                  </Link>
                  <span class="text-[9px] font-mono text-slate-500">
                    {{ p.latitude }}, {{ p.longitude }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ========================================================================= -->
        <!-- VIEW 2: MATRIKS KARTU 6 POLRES                                            -->
        <!-- ========================================================================= -->
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
          <div
            v-for="p in filteredPolres"
            :key="p.id"
            @click="selectPolresPin(p)"
            class="p-4 rounded-2xl bg-slate-900 border transition cursor-pointer hover:border-amber-400/60 group relative overflow-hidden"
            :class="p.status === 'ATENSI' ? 'border-rose-500/40 bg-rose-500/5' : 'border-slate-800'"
          >
            <div class="flex items-center justify-between mb-2">
              <span class="font-bold text-white text-xs group-hover:text-amber-400 transition">{{ p.name }}</span>
              <span
                class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-wider"
                :class="{
                  'bg-rose-500/20 text-rose-300 border border-rose-500/30': p.status === 'ATENSI',
                  'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30': p.status === 'KONDUSIF',
                  'bg-amber-400/20 text-amber-300 border border-amber-400/30': p.status === 'WASPADA',
                }"
              >
                {{ p.status }}
              </span>
            </div>
            <p class="text-[11px] text-slate-300 line-clamp-2 leading-relaxed mb-3">
              {{ p.summary }}
            </p>
            <div class="flex items-center justify-between text-[10px] text-slate-400 pt-2 border-t border-slate-800">
              <span>Skor Opini: <strong class="text-white">{{ p.score }}</strong></span>
              <span class="text-amber-400 font-bold">{{ p.issues_count }} Isu Aktif</span>
            </div>
          </div>
        </div>

        <!-- Bottom Map Legend & Radar Link -->
        <div class="pt-3 flex flex-wrap items-center justify-between text-xs text-slate-400 border-t border-slate-800/80 gap-2">
          <div class="flex items-center gap-4">
            <span class="flex items-center gap-1.5 font-bold">
              <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
              Kondusif ({{ kondusifCount }})
            </span>
            <span class="flex items-center gap-1.5 font-bold">
              <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
              Waspada ({{ waspadaCount }})
            </span>
            <span class="flex items-center gap-1.5 font-bold">
              <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
              Atensi Pimpinan ({{ atensiCount }})
            </span>
          </div>
          <Link href="/media-intelligence" class="text-amber-400 font-bold hover:underline inline-flex items-center gap-1 text-xs">
            <span>Buka Radar Media Online</span>
            <ArrowRight class="w-3.5 h-3.5" />
          </Link>
        </div>
      </div>

      <!-- Col 3: Sentimen 7 Hari & Top Viral / Isu (100% Dynamic) -->
      <div class="space-y-6">
        <!-- 7-Day Trend Chart -->
        <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
          <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <div>
              <h3 class="text-xs font-black text-white uppercase tracking-wider">Tren Sentimen Publik (7 Hari)</h3>
              <p class="text-[10px] text-slate-400">Agregasi Media Online & Medsos</p>
            </div>
            <span class="text-[10px] font-bold text-emerald-400 font-mono bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20">
              {{ avgPositivePct || 84 }}% Rata-rata Positif
            </span>
          </div>

          <!-- Bar Visualizer -->
          <div class="space-y-2 text-xs">
            <div v-for="t in sentiment7Days" :key="t.day" class="space-y-1">
              <div class="flex items-center justify-between text-[11px]">
                <span class="font-bold text-slate-300">{{ t.day }}</span>
                <span class="font-mono text-emerald-400">{{ t.positif }}% Positif</span>
              </div>
              <div class="h-2 rounded-full bg-slate-900 overflow-hidden flex" :title="`+${t.positif}% | ~${t.netral}% | -${t.negatif}%`">
                <div class="bg-emerald-400 transition-all duration-300" :style="{ width: t.positif + '%' }"></div>
                <div class="bg-slate-600 transition-all duration-300" :style="{ width: t.netral + '%' }"></div>
                <div class="bg-rose-500 transition-all duration-300" :style="{ width: t.negatif + '%' }"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Top Trending Switcher: Viral Medsos vs Isu Kamtibmas -->
        <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
          <div class="flex items-center justify-between border-b border-slate-800 pb-3 gap-2">
            <div class="inline-flex rounded-xl bg-slate-900 border border-slate-800 p-0.5 text-[10px] font-bold">
              <button
                type="button"
                @click="activeTrendingTab = 'social'"
                class="px-2.5 py-1 rounded-lg transition"
                :class="activeTrendingTab === 'social' ? 'bg-amber-400 text-slate-950 font-black shadow' : 'text-slate-400 hover:text-white'"
              >
                🔥 Top Viral Medsos
              </button>
              <button
                type="button"
                @click="activeTrendingTab = 'issues'"
                class="px-2.5 py-1 rounded-lg transition"
                :class="activeTrendingTab === 'issues' ? 'bg-amber-400 text-slate-950 font-black shadow' : 'text-slate-400 hover:text-white'"
              >
                🚨 {{ selectedCode === 'ALL' ? 'Top Isu Nasional' : 'Top Isu Daerah' }} ({{ topIssues?.length || 0 }})
              </button>
            </div>

            <Link
              :href="activeTrendingTab === 'social' ? '/social-intelligence' : '/issues'"
              class="text-[10px] text-amber-400 font-bold hover:underline shrink-0"
            >
              Semua &rarr;
            </Link>
          </div>

          <!-- TAB 1: TOP VIRAL MEDSOS -->
          <div v-if="activeTrendingTab === 'social'" class="space-y-3">
            <div
              v-for="(post, idx) in topViralSocialPosts"
              :key="post.id || idx"
              class="p-3 rounded-2xl bg-slate-900 border border-slate-800/80 space-y-2 text-xs hover:border-slate-700 transition"
            >
              <div class="flex items-center justify-between gap-1">
                <div class="flex items-center gap-1.5 min-w-0">
                  <span class="font-black text-amber-400 text-[11px]">#{{ idx + 1 }}</span>
                  <span
                    class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase tracking-wider border shrink-0"
                    :class="getPlatformBadge(post.platform).color"
                  >
                    {{ getPlatformBadge(post.platform).label }}
                  </span>
                  <span class="text-[11px] font-bold text-slate-300 truncate">
                    {{ post.author_handle || post.author_name }}
                  </span>
                </div>
                <span class="font-mono text-[10px] text-rose-400 font-black bg-rose-500/10 px-1.5 py-0.5 rounded border border-rose-500/20 shrink-0 flex items-center gap-0.5">
                  <Flame class="w-2.5 h-2.5 text-rose-400" />
                  {{ post.virality_score }}%
                </span>
              </div>

              <p class="font-medium text-slate-200 text-xs line-clamp-2 leading-snug">
                {{ post.content }}
              </p>

              <div class="flex items-center justify-between pt-1 border-t border-slate-800/60 text-[10px] text-slate-400">
                <div class="flex items-center gap-2.5">
                  <span class="flex items-center gap-1">
                    <Eye class="w-3 h-3 text-sky-400" />
                    {{ formatNumber(post.views_count) }}
                  </span>
                  <span class="flex items-center gap-1">
                    <ThumbsUp class="w-3 h-3 text-emerald-400" />
                    {{ formatNumber(post.likes_count) }}
                  </span>
                </div>
                <a
                  :href="post.post_url"
                  target="_blank"
                  class="text-amber-400 hover:text-amber-300 font-bold flex items-center gap-1 hover:underline"
                >
                  <span>Buka Post</span>
                  <ExternalLink class="w-3 h-3" />
                </a>
              </div>
            </div>

            <div v-if="!topViralSocialPosts || topViralSocialPosts.length === 0" class="text-center py-6 text-slate-400 text-xs">
              <Radio class="w-6 h-6 mx-auto mb-2 text-slate-600" />
              <p>Belum ada postingan medsos viral tersimpan.</p>
              <Link href="/social-intelligence" class="text-amber-400 hover:underline font-bold mt-1 inline-block">
                Sinkronkan Apify Sekarang &rarr;
              </Link>
            </div>
          </div>

          <!-- TAB 2: TOP ISU KAMTIBMAS -->
          <div v-else class="space-y-3">
            <div
              v-for="(iss, idx) in topIssues"
              :key="iss.id || idx"
              class="p-3 rounded-2xl bg-slate-900 border border-slate-800/80 space-y-1 text-xs hover:border-slate-700 transition"
            >
              <div class="flex items-center justify-between gap-1">
                <span class="font-black text-amber-400 text-[11px]">#{{ idx + 1 }}</span>
                <span class="px-2 py-0.2 rounded text-[9px] font-black uppercase bg-slate-800 text-slate-300">
                  {{ iss.category }}
                </span>
                <span class="font-mono text-[10px] text-emerald-400 font-bold">Virality {{ iss.virality_score }}%</span>
              </div>
              <p class="font-bold text-white text-xs line-clamp-2 leading-snug">{{ iss.title }}</p>
            </div>

            <div v-if="!topIssues || topIssues.length === 0" class="text-center py-6 text-slate-400 text-xs">
              <p>Belum ada isu prioritas terdaftar.</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 4. BOTTOM GRID: MATRIKS OPINI 6 POLRES & INTELLIGENCE LIVE STREAM         -->
    <!-- ========================================================================= -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Left: Matriks Kondusifitas Opini 6 Polres & Bar Sentimen Medsos -->
      <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-2xl space-y-4 flex flex-col justify-between">
        <div>
          <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <h3 class="text-sm font-black text-white uppercase tracking-wider">
              {{ selectedCode === 'ALL' ? 'Matriks Kondusifitas Satwil & Jajaran Polda' : 'Matriks Kondusifitas Opini 6 Polres Jajaran' }}
            </h3>
            <span class="text-xs text-slate-400">
              {{ selectedCode === 'ALL' ? 'Divhumas Polri (Orkestrasi Nasional)' : (selectedPolda?.name || 'Polda Sulbar') }}
            </span>
          </div>

          <div class="overflow-x-auto mt-3">
            <table class="w-full text-left border-collapse text-xs">
              <thead>
                <tr class="border-b border-slate-800 text-[10px] font-black uppercase text-slate-400">
                  <th class="py-2.5">Satwil</th>
                  <th class="py-2.5">Skor Opini</th>
                  <th class="py-2.5">Status</th>
                  <th class="py-2.5 text-right">Isu Aktif</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800/60">
                <tr
                  v-for="p in polresStatuses"
                  :key="p.id"
                  @click="selectPolresPin(p); viewMode = 'map'"
                  class="hover:bg-slate-900/40 transition cursor-pointer"
                >
                  <td class="py-3 font-bold text-white flex items-center gap-2">
                    <span
                      class="w-2 h-2 rounded-full"
                      :class="{
                        'bg-rose-500': p.status === 'ATENSI',
                        'bg-amber-400': p.status === 'WASPADA',
                        'bg-emerald-400': p.status === 'KONDUSIF',
                      }"
                    ></span>
                    <span>{{ p.name }}</span>
                  </td>
                  <td class="py-3 font-mono font-bold text-emerald-400">{{ p.score }}</td>
                  <td class="py-3">
                    <span
                      class="px-2 py-0.5 rounded text-[9px] font-black uppercase"
                      :class="{
                        'bg-rose-500/20 text-rose-300 border border-rose-500/30': p.status === 'ATENSI',
                        'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30': p.status === 'KONDUSIF',
                        'bg-amber-400/20 text-amber-300 border border-amber-400/30': p.status === 'WASPADA',
                      }"
                    >
                      {{ p.status }}
                    </span>
                  </td>
                  <td class="py-3 text-right font-bold text-amber-400">{{ p.issues_count }} Isu</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Mini Bar Analisis Sentimen Medsos -->
        <div class="mt-4 pt-4 border-t border-slate-800 space-y-2">
          <div class="flex items-center justify-between text-xs">
            <span class="font-bold text-slate-300 flex items-center gap-1.5">
              <Radio class="w-3.5 h-3.5 text-amber-400" />
              <span>Komposisi Sentimen Radar Medsos</span>
            </span>
            <span class="text-[10px] text-slate-400 font-mono">
              Total {{ socialStats?.total_posts || 0 }} Postingan
            </span>
          </div>

          <div class="h-2.5 rounded-full bg-slate-900 overflow-hidden flex">
            <div
              class="bg-emerald-500 transition-all duration-300"
              :style="{ width: (socialStats?.sentiment?.positif || 60) + '%' }"
              :title="`Positif: ${socialStats?.sentiment?.positif || 0}%`"
            ></div>
            <div
              class="bg-slate-600 transition-all duration-300"
              :style="{ width: (socialStats?.sentiment?.netral || 25) + '%' }"
              :title="`Netral: ${socialStats?.sentiment?.netral || 0}%`"
            ></div>
            <div
              class="bg-rose-500 transition-all duration-300"
              :style="{ width: (socialStats?.sentiment?.negatif || 15) + '%' }"
              :title="`Negatif: ${socialStats?.sentiment?.negatif || 0}%`"
            ></div>
          </div>

          <div class="flex flex-wrap items-center justify-between text-[10px] text-slate-400 pt-1 gap-2">
            <span class="text-emerald-400 font-bold">● Positif {{ socialStats?.sentiment?.positif || 0 }}%</span>
            <span class="text-slate-400 font-bold">● Netral {{ socialStats?.sentiment?.netral || 0 }}%</span>
            <span class="text-rose-400 font-bold">● Negatif {{ socialStats?.sentiment?.negatif || 0 }}%</span>
            <span class="text-sky-400 font-bold">Interaksi: {{ formatNumber((socialStats?.views_count || 0) + (socialStats?.likes_count || 0)) }}</span>
          </div>
        </div>
      </div>

      <!-- Right: Feed Intelijen Real-Time Terpadu (Tabs: Medsos Live vs 29 RSS Berita) -->
      <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-2xl space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-800 pb-3">
          <div class="inline-flex rounded-xl bg-slate-900 border border-slate-800 p-0.5 text-xs font-bold">
            <button
              type="button"
              @click="activeStreamTab = 'social'"
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg transition"
              :class="activeStreamTab === 'social' ? 'bg-amber-400 text-slate-950 font-black shadow' : 'text-slate-400 hover:text-white'"
            >
              <Radio class="w-3.5 h-3.5" />
              <span>Radar Medsos Live ({{ liveSocialPosts?.length || 0 }})</span>
            </button>
            <button
              type="button"
              @click="activeStreamTab = 'media'"
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg transition"
              :class="activeStreamTab === 'media' ? 'bg-amber-400 text-slate-950 font-black shadow' : 'text-slate-400 hover:text-white'"
            >
              <Newspaper class="w-3.5 h-3.5" />
              <span>Radar Berita 29 RSS ({{ liveRadarArticles?.length || 0 }})</span>
            </button>
          </div>

          <Link
            :href="activeStreamTab === 'social' ? '/social-intelligence' : '/media-intelligence'"
            class="text-xs text-amber-400 font-bold hover:underline flex items-center gap-1"
          >
            <span>Buka Selengkapnya</span>
            <ArrowRight class="w-3.5 h-3.5" />
          </Link>
        </div>

        <!-- TAB STREAM 1: RADAR MEDSOS LIVE -->
        <div v-if="activeStreamTab === 'social'" class="space-y-3">
          <div
            v-for="post in liveSocialPosts"
            :key="post.id"
            class="p-3.5 rounded-2xl bg-slate-900 border border-slate-800 hover:border-slate-700 transition space-y-2 text-xs"
          >
            <div class="flex items-center justify-between gap-2">
              <div class="flex items-center gap-1.5 min-w-0">
                <span
                  class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-wider border shrink-0"
                  :class="getPlatformBadge(post.platform).color"
                >
                  {{ getPlatformBadge(post.platform).label }}
                </span>
                <span class="font-bold text-white truncate text-[11px]">
                  {{ post.author_handle || post.author_name }}
                </span>
              </div>
              <span class="text-[10px] text-slate-400 font-mono shrink-0">
                {{ formatTimeAgo(post.posted_at) }}
              </span>
            </div>

            <!-- Content with optional thumbnail -->
            <div class="flex items-start gap-3">
              <div
                v-if="post.thumbnail_url"
                class="w-20 h-14 rounded-xl overflow-hidden shrink-0 border border-slate-800 relative bg-slate-950 group"
              >
                <img
                  :src="post.thumbnail_url"
                  :alt="post.author_name"
                  class="w-full h-full object-cover group-hover:scale-110 transition duration-300"
                  loading="lazy"
                />
                <div
                  v-if="post.platform === 'youtube' || post.platform === 'tiktok'"
                  class="absolute inset-0 bg-black/30 flex items-center justify-center"
                >
                  <div class="w-5 h-5 rounded-full bg-rose-600/90 text-white flex items-center justify-center shadow">
                    <Play class="w-2.5 h-2.5 fill-white ml-0.5" />
                  </div>
                </div>
              </div>

              <div class="flex-1 min-w-0">
                <p class="text-slate-300 line-clamp-2 leading-relaxed text-[11px]">
                  {{ post.content }}
                </p>
              </div>
            </div>

            <div class="flex items-center justify-between pt-1 border-t border-slate-800/60 text-[10px] text-slate-400">
              <div class="flex items-center gap-3">
                <span class="flex items-center gap-1">
                  <Eye class="w-3 h-3 text-sky-400" />
                  {{ formatNumber(post.views_count) }}
                </span>
                <span class="flex items-center gap-1">
                  <ThumbsUp class="w-3 h-3 text-emerald-400" />
                  {{ formatNumber(post.likes_count) }}
                </span>
                <span
                  class="px-1.5 py-0.2 rounded text-[9px] font-black uppercase"
                  :class="{
                    'bg-emerald-500/10 text-emerald-300': post.sentiment === 'positif',
                    'bg-slate-500/10 text-slate-300': post.sentiment === 'netral',
                    'bg-rose-500/10 text-rose-300': post.sentiment === 'negatif',
                  }"
                >
                  {{ post.sentiment }}
                </span>
              </div>
              <a
                :href="post.post_url"
                target="_blank"
                class="text-amber-400 hover:text-amber-300 font-bold flex items-center gap-1 hover:underline"
              >
                <span>Lihat</span>
                <ExternalLink class="w-3 h-3" />
              </a>
            </div>
          </div>

          <div v-if="!liveSocialPosts || liveSocialPosts.length === 0" class="text-center py-8 text-slate-400 text-xs">
            <Radio class="w-8 h-8 mx-auto mb-2 text-slate-600 animate-pulse" />
            <p>Belum ada postingan medsos terkini.</p>
            <Link href="/social-intelligence" class="text-amber-400 hover:underline font-bold mt-1 inline-block">
              Buka Radar Media Sosial &rarr;
            </Link>
          </div>
        </div>

        <!-- TAB STREAM 2: RADAR BERITA 29 RSS -->
        <div v-else class="space-y-3">
          <div
            v-for="art in liveRadarArticles"
            :key="art.id"
            class="p-3.5 rounded-2xl bg-slate-900 border border-slate-800 hover:border-slate-700 transition space-y-1.5 text-xs"
          >
            <div class="flex items-center justify-between">
              <span class="px-2 py-0.2 rounded text-[9px] font-black uppercase bg-slate-800 text-amber-400 border border-slate-700">
                {{ art.source_name }}
              </span>
              <span class="text-[10px] text-slate-400 font-mono">
                {{ art.pub_date ? new Date(art.pub_date).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) + ' WITA' : 'Baru saja' }}
              </span>
            </div>
            <a :href="art.url" target="_blank" class="block font-bold text-white hover:text-amber-400 transition leading-snug line-clamp-1">
              {{ art.title }}
            </a>
            <p class="text-[11px] text-slate-400 line-clamp-2 leading-relaxed">
              {{ art.summary }}
            </p>
          </div>

          <div v-if="!liveRadarArticles || liveRadarArticles.length === 0" class="text-center py-8 text-slate-400 text-xs">
            <p>Belum ada berita online tersimpan.</p>
          </div>
        </div>
      </div>
    </div>
  </EnterpriseLayout>
</template>
