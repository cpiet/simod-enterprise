<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import SituationMap from '@/Components/SituationMap.vue';
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
  SlidersHorizontal,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  isNational: Boolean,
  nationalMapData: Array,
  latestMediaReport: Object,
  latestSocialReport: Object,
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

// Map Scope: 'national' (Peta Nusantara / 34 Polda) vs 'regional' (Peta Satwil / 6 Polres)
// Izin pengguna menentukan aksi cepat yang tampil di hero
const page = usePage();
const canCreateIssue = computed(() => !!page.props.auth?.permissions?.issues_create);
const canExportReports = computed(() => !!page.props.auth?.permissions?.reports_export);

// Tabs for Trending & Live Stream
const activeTrendingTab = ref('social'); // 'social' | 'issues'
const activeStreamTab = ref('social'); // 'social' | 'media'
const activeIntelligenceTab = ref('media'); // 'media' | 'social' | 'matrix'

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
    <div class="rounded-3xl bg-gradient-to-r from-[#0d182e] via-[#0b1426] to-[#070d1d] border border-slate-800 p-5 md:p-6 shadow-2xl relative overflow-hidden">
      <div class="absolute top-0 right-0 -mt-10 -mr-10 w-72 h-72 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>

      <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-5 relative z-10">
        <div class="space-y-3 min-w-0">
          <!-- Status situasi (satu-satunya lencana di hero) -->
          <span
            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold border transition"
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

          <div>
            <h1 class="text-2xl md:text-3xl font-black text-white tracking-tight">Command Center</h1>
            <p class="text-xs md:text-sm text-slate-400 mt-1">
              {{ selectedCode === 'ALL' ? 'Divhumas Polri (Nasional)' : selectedPolda?.name }}
            </p>
          </div>

          <!-- Aksi cepat (maks. 2, mengikuti izin) -->
          <div v-if="canCreateIssue || canExportReports" class="flex flex-wrap items-center gap-2.5">
            <Link
              v-if="canCreateIssue"
              href="/issues/create"
              class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-400 hover:from-amber-400 hover:to-amber-300 text-slate-950 text-xs font-black transition shadow-lg shadow-amber-500/20"
            >
              <PlusCircle class="w-4 h-4" />
              <span>Buat Laporan</span>
            </Link>

            <Link
              v-if="canExportReports"
              href="/reports"
              class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-200 text-xs font-bold transition"
            >
              <FileBarChart2 class="w-4 h-4 text-emerald-400" />
              <span>Unduh Rekap</span>
            </Link>
          </div>
        </div>

        <!-- Indeks citra publik: angka besar + selisih (tanpa nilai bawaan palsu) -->
        <div class="p-5 rounded-3xl bg-[#091122]/90 border border-amber-400/30 shadow-2xl flex flex-col items-center text-center space-y-2 shrink-0">
          <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Indeks Citra Publik</span>
          <div class="flex items-baseline gap-2">
            <span class="text-5xl font-black text-amber-400 tracking-tight">{{ publicIndex?.score != null ? publicIndex.score + '%' : '—' }}</span>
            <span v-if="publicIndex?.change" class="text-xs font-bold text-emerald-400 flex items-center">
              <TrendingUp class="w-3.5 h-3.5 mr-0.5" /> {{ publicIndex.change }}
            </span>
          </div>
          <span
            v-if="publicIndex?.status"
            class="px-3 py-1 rounded-full text-[10px] font-black uppercase border"
            :class="{
              'bg-emerald-500/20 text-emerald-300 border-emerald-500/40': publicIndex?.score >= 70,
              'bg-amber-500/20 text-amber-300 border-amber-500/40': publicIndex?.score >= 50 && publicIndex?.score < 70,
              'bg-rose-500/20 text-rose-300 border-rose-500/40': publicIndex?.score < 50,
            }"
          >
            {{ publicIndex.status }}
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
        href="/issues?priority=attention"
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
    <!-- 2.5 SINTESIS ANALISIS INTELIJEN PIMPINAN (MEDIA ONLINE & MEDIA SOSIAL)    -->
    <!-- ========================================================================= -->
    <div class="rounded-3xl bg-gradient-to-br from-[#0c162e] via-[#0b1426] to-[#070d1d] border border-amber-500/35 p-6 md:p-7 shadow-2xl space-y-6 relative overflow-hidden">
      <!-- Ambient Glow Effect -->
      <div class="absolute -top-12 -right-12 w-80 h-80 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>

      <!-- Header & Engine Switcher Tabs -->
      <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 border-b border-slate-800/90 pb-4 relative z-10">
        <div class="space-y-1">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="text-xs font-mono text-slate-400">
              {{ selectedCode === 'ALL' ? 'Mabes Polri (Skala Nasional)' : (selectedPolda?.name || 'Polda') }}
            </span>
            <span v-if="latestMediaReport?.updated_at || latestSocialReport?.updated_at" class="text-[10px] text-emerald-400 font-mono bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20 flex items-center gap-1">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-ping"></span>
              Analisis Terkini: {{ formatTimeAgo(activeIntelligenceTab === 'social' ? latestSocialReport?.updated_at : latestMediaReport?.updated_at) }}
            </span>
          </div>
          <h2 class="text-lg md:text-2xl font-black text-white tracking-tight flex items-center gap-2 flex-wrap">
            <span>Briefing Intelijen Pimpinan</span>
          </h2>
        </div>

        <!-- Engine Switcher Tab Bar -->
        <div class="inline-flex rounded-2xl bg-slate-900/95 border border-slate-800 p-1 text-xs font-bold shrink-0 self-start lg:self-auto shadow-inner">
          <button
            type="button"
            @click="activeIntelligenceTab = 'media'"
            class="flex items-center gap-2 px-3.5 py-2 rounded-xl transition cursor-pointer select-none"
            :class="activeIntelligenceTab === 'media'
              ? 'bg-amber-400 text-slate-950 font-black shadow-md shadow-amber-400/20'
              : 'text-slate-300 hover:text-white hover:bg-slate-800'"
          >
            <Newspaper class="w-3.5 h-3.5" :class="activeIntelligenceTab === 'media' ? 'text-slate-950' : 'text-sky-400'" />
            <span>📰 Media Online</span>
          </button>

          <button
            type="button"
            @click="activeIntelligenceTab = 'social'"
            class="flex items-center gap-2 px-3.5 py-2 rounded-xl transition cursor-pointer select-none"
            :class="activeIntelligenceTab === 'social'
              ? 'bg-amber-400 text-slate-950 font-black shadow-md shadow-amber-400/20'
              : 'text-slate-300 hover:text-white hover:bg-slate-800'"
          >
            <Radio class="w-3.5 h-3.5" :class="activeIntelligenceTab === 'social' ? 'text-slate-950' : 'text-amber-400 animate-pulse'" />
            <span>📱 Media Sosial</span>
          </button>

          <button
            type="button"
            @click="activeIntelligenceTab = 'matrix'"
            class="flex items-center gap-2 px-3.5 py-2 rounded-xl transition cursor-pointer select-none"
            :class="activeIntelligenceTab === 'matrix'
              ? 'bg-amber-400 text-slate-950 font-black shadow-md shadow-amber-400/20'
              : 'text-slate-300 hover:text-white hover:bg-slate-800'"
          >
            <Shield class="w-3.5 h-3.5" :class="activeIntelligenceTab === 'matrix' ? 'text-slate-950' : 'text-emerald-400'" />
            <span>🛡️ Respon &amp; Cooling</span>
          </button>
        </div>
      </div>

      <!-- TAB 1 CONTENT: ANALISIS MEDIA ONLINE (PERS & PORTAL SIBER) -->
      <div v-if="activeIntelligenceTab === 'media'" class="space-y-5 relative z-10">
        <!-- Top Concern Alert Banner -->
        <div class="p-4 rounded-2xl bg-gradient-to-r from-rose-950/40 via-[#181120] to-amber-950/30 border border-rose-500/40 space-y-1.5 shadow-md">
          <div class="flex items-center justify-between text-xs gap-2 flex-wrap">
            <span class="flex items-center gap-2 text-rose-400 font-black uppercase tracking-wider text-[11px]">
              <Flame class="w-4 h-4 text-rose-500 animate-pulse" />
              <span>Isu Kritis Prioritas Media Online (Top Concern)</span>
            </span>
            <span class="text-[10px] font-mono font-bold text-slate-400">
              Skop: {{ selectedCode === 'ALL' ? 'Pemberitaan Nasional' : 'Pemberitaan Daerah' }}
            </span>
          </div>
          <p class="text-xs md:text-sm font-bold text-white leading-relaxed">
            {{ latestMediaReport?.top_concern || 'Situasi pemberitaan media siber nasional dan daerah terpantau kondusif, didominasi publikasi kegiatan harkamtibmas, pelayanan publik, dan penegakan hukum humanis.' }}
          </p>
        </div>

        <!-- 2-Column Grid: Executive Briefing & Sentimen + Isu Strategis -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
          <!-- Left: Executive Briefing & Core Decision (Col 7) -->
          <div class="lg:col-span-7 rounded-2xl bg-slate-950/60 border border-slate-800/80 p-5 space-y-4 flex flex-col justify-between">
            <div class="space-y-3">
              <div class="flex items-center justify-between border-b border-slate-800/80 pb-2.5 flex-wrap gap-2">
                <h4 class="text-xs font-black text-white uppercase tracking-wider flex items-center gap-2">
                  <Sparkles class="w-3.5 h-3.5 text-amber-400" />
                  <span>Executive Briefing Situasi Pemberitaan (24 Jam Terakhir)</span>
                </h4>
                <div class="flex items-center gap-2">
                  <span class="text-[10px] font-mono text-amber-400 bg-amber-400/10 px-2 py-0.5 rounded border border-amber-400/20">
                    SADEWA AI Engine
                  </span>
                  <Link
                    href="/media-intelligence?tab=02"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-amber-400/10 hover:bg-amber-400 border border-amber-400/30 hover:border-amber-400 text-amber-300 hover:text-slate-950 text-[10px] font-black transition shadow-sm group cursor-pointer"
                    title="Buka Lembar Executive Briefing Lengkap & Format WA Resmi"
                  >
                    <span>Buka Lembar Briefing &amp; Berita</span>
                    <ExternalLink class="w-3 h-3 group-hover:translate-x-0.5 transition-transform" />
                  </Link>
                </div>
              </div>
              <p class="text-xs text-slate-200 leading-relaxed whitespace-pre-line">
                {{ latestMediaReport?.executive_brief || 'Analisis agregasi 29 portal media menunjukkan stabilitas opini publik terjaga dengan baik. Kemitraan media pers dan siaran Tribratanews berjalan efektif mengimbangi narasi minor di ruang siber.' }}
              </p>
            </div>

            <!-- Keputusan / Arahan Inti Pimpinan (if present) -->
            <div v-if="latestMediaReport?.core_decision" class="mt-3 p-3 rounded-xl bg-amber-500/10 border-l-4 border-amber-400 text-xs text-amber-100 space-y-1">
              <span class="text-[10px] font-black uppercase tracking-wider text-amber-300 block">Arahan Taktis Komando:</span>
              <p class="italic leading-relaxed">"{{ latestMediaReport.core_decision }}"</p>
            </div>

            <!-- Quick Link / Jump to full briefing -->
            <div class="pt-2.5 border-t border-slate-800/60 flex items-center justify-between text-[11px] flex-wrap gap-2">
              <span class="text-slate-400 text-[10px] italic">
                Sumber: Agregasi 33 Portal Media Online Nasional &amp; Daerah
              </span>
              <Link
                href="/media-intelligence?tab=02"
                class="inline-flex items-center gap-1.5 text-amber-400 hover:text-amber-300 font-bold hover:underline"
              >
                <span>Format Naskah WA Pimpinan &amp; Cetak A4</span>
                <ArrowRight class="w-3 h-3" />
              </Link>
            </div>
          </div>

          <!-- Right: Barometer Sentimen & Isu Strategis (Col 5) -->
          <div class="lg:col-span-5 rounded-2xl bg-slate-950/60 border border-slate-800/80 p-5 space-y-4">
            <!-- Sentiment Breakdown Bar -->
            <div class="space-y-2">
              <div class="flex items-center justify-between text-xs">
                <span class="font-black text-white uppercase text-[11px] tracking-wider">Barometer Sentimen Media</span>
                <span class="text-[10px] font-mono text-emerald-400 font-bold">
                  {{ (latestMediaReport?.sentiment_national?.positif || latestMediaReport?.sentiment_sulbar?.positif || avgPositivePct || 80) }}% Positif
                </span>
              </div>
              <div class="h-3 rounded-full bg-slate-900 overflow-hidden flex shadow-inner">
                <div
                  class="bg-emerald-500 transition-all duration-300"
                  :style="{ width: (latestMediaReport?.sentiment_national?.positif || latestMediaReport?.sentiment_sulbar?.positif || avgPositivePct || 80) + '%' }"
                  :title="`Positif: ${(latestMediaReport?.sentiment_national?.positif || latestMediaReport?.sentiment_sulbar?.positif || avgPositivePct || 80)}%`"
                ></div>
                <div
                  class="bg-slate-600 transition-all duration-300"
                  :style="{ width: (latestMediaReport?.sentiment_national?.netral || latestMediaReport?.sentiment_sulbar?.netral || 15) + '%' }"
                  :title="`Netral: ${(latestMediaReport?.sentiment_national?.netral || latestMediaReport?.sentiment_sulbar?.netral || 15)}%`"
                ></div>
                <div
                  class="bg-rose-500 transition-all duration-300"
                  :style="{ width: (latestMediaReport?.sentiment_national?.negatif || latestMediaReport?.sentiment_sulbar?.negatif || 5) + '%' }"
                  :title="`Negatif: ${(latestMediaReport?.sentiment_national?.negatif || latestMediaReport?.sentiment_sulbar?.negatif || 5)}%`"
                ></div>
              </div>
              <div class="flex items-center justify-between text-[10px] text-slate-400 font-mono">
                <span class="text-emerald-400 font-bold">● Positif {{ (latestMediaReport?.sentiment_national?.positif || latestMediaReport?.sentiment_sulbar?.positif || avgPositivePct || 80) }}%</span>
                <span class="text-slate-400 font-bold">● Netral {{ (latestMediaReport?.sentiment_national?.netral || latestMediaReport?.sentiment_sulbar?.netral || 15) }}%</span>
                <span class="text-rose-400 font-bold">● Negatif {{ (latestMediaReport?.sentiment_national?.negatif || latestMediaReport?.sentiment_sulbar?.negatif || 5) }}%</span>
              </div>
            </div>

            <!-- Isu Strategis Media Terdeteksi -->
            <div class="space-y-2 pt-2 border-t border-slate-800/80">
              <div class="flex items-center justify-between">
                <span class="text-[11px] font-black text-slate-300 uppercase tracking-wider block">
                  Isu Strategis Terdeteksi ({{ (latestMediaReport?.national_issues || latestMediaReport?.sulbar_issues || []).length || 3 }} Isu)
                </span>
                <span class="text-[9px] text-slate-400 font-mono italic">Klik kartu untuk membuka berita</span>
              </div>
              <div class="space-y-2">
                <a
                  v-for="(iss, idx) in (latestMediaReport?.national_issues || latestMediaReport?.sulbar_issues || []).slice(0, 3)"
                  :key="iss.title || idx"
                  :href="iss.url || `/media-intelligence?q=${encodeURIComponent(iss.title || '')}`"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="group block p-2.5 rounded-xl bg-slate-900/90 hover:bg-slate-850 border border-slate-800 hover:border-amber-400/50 text-xs space-y-1.5 transition shadow-sm cursor-pointer"
                  :title="`Buka berita asli: ${iss.title}`"
                >
                  <div class="flex items-center justify-between gap-1.5">
                    <span class="font-bold text-white group-hover:text-amber-300 text-[11px] line-clamp-1 flex items-center gap-1">
                      <ExternalLink class="w-3 h-3 text-amber-400 shrink-0 opacity-70 group-hover:opacity-100" />
                      <span class="truncate">{{ iss.title }}</span>
                    </span>
                    <span
                      class="px-1.5 py-0.5 rounded text-[8px] font-black uppercase shrink-0"
                      :class="(iss.sentiment || '').toLowerCase() === 'positif' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : (iss.sentiment || '').toLowerCase() === 'negatif' ? 'bg-rose-500/20 text-rose-300 border border-rose-500/30' : 'bg-slate-800 text-slate-300 border border-slate-700'"
                    >
                      {{ iss.sentiment || 'Netral' }}
                    </span>
                  </div>
                  <p v-if="iss.fact || iss.summary" class="text-[10px] text-slate-400 line-clamp-1 italic">
                    {{ iss.fact || iss.summary }}
                  </p>
                  <div v-if="iss.source" class="flex items-center justify-between text-[9px] text-slate-400 font-mono pt-1 border-t border-slate-800/60">
                    <span class="truncate max-w-[200px]">{{ iss.source }}</span>
                    <span class="text-amber-400 font-bold group-hover:underline flex items-center gap-0.5">
                      <span>Buka Berita</span> &rarr;
                    </span>
                  </div>
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- Media Footer Action Toolbar -->
        <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-800/80 text-xs">
          <div class="flex items-center gap-2 text-slate-400 text-[11px]">
            <Radio class="w-3.5 h-3.5 text-sky-400" />
            <span>Pemantauan aktif 29/33 portal media nasional &amp; daerah ({{ kpi?.total_articles || 0 }} berita tersinkron)</span>
          </div>
          <div class="flex items-center gap-2.5">
            <Link
              href="/media-intelligence?tab=02"
              class="flex items-center gap-1.5 px-4 py-2 rounded-xl bg-amber-400 text-slate-950 font-black hover:bg-amber-300 transition shadow-sm text-xs"
            >
              <span>Buka Radar Media Online Lengkap</span>
              <ArrowRight class="w-3.5 h-3.5" />
            </Link>
          </div>
        </div>
      </div>

      <!-- TAB 2 CONTENT: ANALISIS MEDIA SOSIAL (PATROLI SIBER & WARGANET) -->
      <div v-else-if="activeIntelligenceTab === 'social'" class="space-y-5 relative z-10">
        <!-- Top Concern Social Alert Banner -->
        <div class="p-4 rounded-2xl bg-gradient-to-r from-violet-950/40 via-[#131124] to-cyan-950/30 border border-violet-500/40 space-y-1.5 shadow-md">
          <div class="flex items-center justify-between text-xs gap-2 flex-wrap">
            <span class="flex items-center gap-2 text-cyan-300 font-black uppercase tracking-wider text-[11px]">
              <Radio class="w-4 h-4 text-cyan-400 animate-pulse" />
              <span>Isu Viral &amp; Atensi Percakapan Warganet (Medsos Top Concern)</span>
            </span>
            <span class="text-[10px] font-mono font-bold text-slate-400">
              Platform: 𝕏 • TikTok • Instagram • YouTube ({{ socialStats?.total_posts || 0 }} Post)
            </span>
          </div>
          <p class="text-xs md:text-sm font-bold text-white leading-relaxed">
            {{ latestSocialReport?.top_concern || 'Percakapan publik di media sosial terpantau stabil, didominasi apresiasi penanganan kamtibmas cepat dan interaksi netizen pada konten edukasi humas.' }}
          </p>
        </div>

        <!-- 2-Column Grid: Social Briefing & Polarisasi Buzzer + KOL -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
          <!-- Left: Executive Social Briefing & Buzzer vs Organic Assessment (Col 7) -->
          <div class="lg:col-span-7 rounded-2xl bg-slate-950/60 border border-slate-800/80 p-5 space-y-4 flex flex-col justify-between">
            <div class="space-y-3">
              <div class="flex items-center justify-between border-b border-slate-800/80 pb-2.5 flex-wrap gap-2">
                <h4 class="text-xs font-black text-white uppercase tracking-wider flex items-center gap-2">
                  <Sparkles class="w-3.5 h-3.5 text-cyan-400" />
                  <span>Executive Briefing Situasi Media Sosial &amp; Warganet</span>
                </h4>
                <div class="flex items-center gap-2">
                  <span class="text-[10px] font-mono text-cyan-400 bg-cyan-400/10 px-2 py-0.5 rounded border border-cyan-400/20">
                    Social Listening Engine
                  </span>
                  <Link
                    href="/social-intelligence?tab=briefing"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-cyan-400/10 hover:bg-cyan-400 border border-cyan-400/30 hover:border-cyan-400 text-cyan-300 hover:text-slate-950 text-[10px] font-black transition shadow-sm group cursor-pointer"
                    title="Buka Lembar Executive Briefing Medsos Lengkap"
                  >
                    <span>Buka Lembar Briefing Medsos</span>
                    <ExternalLink class="w-3 h-3 group-hover:translate-x-0.5 transition-transform" />
                  </Link>
                </div>
              </div>
              <p class="text-xs text-slate-200 leading-relaxed whitespace-pre-line">
                {{ latestSocialReport?.executive_brief || 'Pemantauan lintas platform media sosial mengindikasikan narasi warganet didominasi respon positif dan netral terhadap program kepolisian presisi.' }}
              </p>
            </div>

            <!-- Polarisasi Buzzer vs Netizen Organik Card -->
            <div v-if="latestSocialReport?.buzzer_vs_organic" class="mt-3 p-3.5 rounded-xl bg-slate-900 border border-slate-800 space-y-2 text-xs">
              <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-300 flex items-center gap-1.5">
                  <Shield class="w-3.5 h-3.5 text-amber-400" />
                  <span>Deteksi Polarisasi Buzzer vs Warganet Organik</span>
                </span>
                <span
                  class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-wider"
                  :class="latestSocialReport.buzzer_vs_organic.risk_level === 'tinggi' ? 'bg-rose-500/20 text-rose-300 border border-rose-500/30' : latestSocialReport.buzzer_vs_organic.risk_level === 'sedang' ? 'bg-amber-400/20 text-amber-300 border border-amber-400/30' : 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30'"
                >
                  Risiko: {{ latestSocialReport.buzzer_vs_organic.risk_level || 'Rendah' }}
                </span>
              </div>
              <p class="text-[11px] text-slate-300 leading-relaxed">
                {{ latestSocialReport.buzzer_vs_organic.assessment }}
              </p>
            </div>

            <!-- Quick Link / Jump to full social briefing -->
            <div class="pt-2.5 border-t border-slate-800/60 flex items-center justify-between text-[11px] flex-wrap gap-2">
              <span class="text-slate-400 text-[10px] italic">
                Sumber: Patroli Siber 𝕏, TikTok, Instagram, YouTube &amp; KOL
              </span>
              <Link
                href="/social-intelligence?tab=briefing"
                class="inline-flex items-center gap-1.5 text-cyan-400 hover:text-cyan-300 font-bold hover:underline"
              >
                <span>Format Naskah WA Pimpinan &amp; Matriks Cooling System</span>
                <ArrowRight class="w-3 h-3" />
              </Link>
            </div>
          </div>

          <!-- Right: Barometer Medsos & Akun Kritis (Col 5) -->
          <div class="lg:col-span-5 rounded-2xl bg-slate-950/60 border border-slate-800/80 p-5 space-y-4">
            <!-- Sentiment Breakdown Medsos -->
            <div class="space-y-2">
              <div class="flex items-center justify-between text-xs">
                <span class="font-black text-white uppercase text-[11px] tracking-wider">Barometer Sentimen Medsos</span>
                <span class="text-[10px] font-mono text-cyan-400 font-bold">
                  {{ (latestSocialReport?.sentiment_analysis?.positif || socialStats?.sentiment?.positif || 65) }}% Positif
                </span>
              </div>
              <div class="h-3 rounded-full bg-slate-900 overflow-hidden flex shadow-inner">
                <div
                  class="bg-emerald-500 transition-all duration-300"
                  :style="{ width: (latestSocialReport?.sentiment_analysis?.positif || socialStats?.sentiment?.positif || 65) + '%' }"
                ></div>
                <div
                  class="bg-slate-600 transition-all duration-300"
                  :style="{ width: (latestSocialReport?.sentiment_analysis?.netral || socialStats?.sentiment?.netral || 20) + '%' }"
                ></div>
                <div
                  class="bg-rose-500 transition-all duration-300"
                  :style="{ width: (latestSocialReport?.sentiment_analysis?.negatif || socialStats?.sentiment?.negatif || 15) + '%' }"
                ></div>
              </div>
              <div class="flex items-center justify-between text-[10px] text-slate-400 font-mono">
                <span class="text-emerald-400 font-bold">● Positif {{ (latestSocialReport?.sentiment_analysis?.positif || socialStats?.sentiment?.positif || 65) }}%</span>
                <span class="text-slate-400 font-bold">● Netral {{ (latestSocialReport?.sentiment_analysis?.netral || socialStats?.sentiment?.netral || 20) }}%</span>
                <span class="text-rose-400 font-bold">● Negatif {{ (latestSocialReport?.sentiment_analysis?.negatif || socialStats?.sentiment?.negatif || 15) }}%</span>
              </div>
            </div>

            <!-- Akun Kritis / KOL Terpantau -->
            <div class="space-y-2 pt-2 border-t border-slate-800/80">
              <span class="text-[11px] font-black text-slate-300 uppercase tracking-wider block">
                Akun Kritis &amp; KOL Terdeteksi ({{ (latestSocialReport?.critical_accounts || []).length || socialStats?.kol_count || 0 }})
              </span>
              <div class="space-y-2">
                <div
                  v-for="(acc, idx) in (latestSocialReport?.critical_accounts || []).slice(0, 3)"
                  :key="acc.handle || idx"
                  class="p-2.5 rounded-xl bg-slate-900 border border-slate-800 text-xs space-y-1 hover:border-slate-700 transition"
                >
                  <div class="flex items-center justify-between gap-1">
                    <span class="font-bold text-white text-[11px]">{{ acc.handle || acc.name }}</span>
                    <span class="px-1.5 py-0.2 rounded text-[8px] font-black uppercase bg-slate-800 text-amber-400">
                      {{ acc.platform || 'Medsos' }}
                    </span>
                  </div>
                  <p class="text-[10px] text-slate-400 line-clamp-1 italic">{{ acc.concern || acc.note }}</p>
                </div>
                <div v-if="!latestSocialReport?.critical_accounts || latestSocialReport.critical_accounts.length === 0" class="text-slate-400 text-[11px] py-1">
                  Tidak ada akun kritis berisiko tinggi yang terdeteksi saat ini.
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Social Footer Action Toolbar -->
        <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-800/80 text-xs">
          <div class="flex items-center gap-2 text-slate-400 text-[11px]">
            <Radio class="w-3.5 h-3.5 text-cyan-400" />
            <span>Pantauan aktif {{ socialStats?.total_posts || 0 }} postingan medsos &amp; {{ socialStats?.kol_count || 0 }} akun KOL</span>
          </div>
          <div class="flex items-center gap-2.5">
            <Link
              href="/social-intelligence?tab=02"
              class="flex items-center gap-1.5 px-4 py-2 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:brightness-110 text-white font-black transition shadow-sm text-xs"
            >
              <span>Buka Radar Media Sosial Lengkap</span>
              <ArrowRight class="w-3.5 h-3.5" />
            </Link>
          </div>
        </div>
      </div>

      <!-- TAB 3 CONTENT: MATRIKS RESPON & COOLING SYSTEM HUMAS -->
      <div v-else-if="activeIntelligenceTab === 'matrix'" class="space-y-4 relative z-10">
        <div class="p-4 rounded-2xl bg-emerald-950/20 border border-emerald-500/30 flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs">
          <div class="space-y-0.5">
            <span class="text-[10px] font-black text-emerald-400 uppercase tracking-wider block">Matriks Cooling System Humas Polri</span>
            <p class="text-slate-300 font-medium">Orkestrasi langkah penyejuk suasana, rilis klarifikasi pers, dan penyebaran konten edukatif lintas kanal.</p>
          </div>
          <Link
            href="/tasks"
            class="px-3.5 py-1.5 rounded-xl bg-emerald-500 text-slate-950 font-black hover:bg-emerald-400 transition shrink-0 inline-flex items-center gap-1.5 text-xs"
          >
            <span>Buka Disposisi Tugas &rarr;</span>
          </Link>
        </div>

        <!-- Cooling System Table / Cards -->
        <div class="overflow-x-auto rounded-2xl border border-slate-800 bg-slate-950/60">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="border-b border-slate-800 text-[10px] font-black uppercase text-slate-400 bg-slate-900/80">
                <th class="p-3">Waktu Eksekusi</th>
                <th class="p-3">Satker Pelaksana (PIC)</th>
                <th class="p-3">Kanal Distribusi</th>
                <th class="p-3">Tindakan Humas Terarah</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
              <tr
                v-for="(item, idx) in (latestSocialReport?.cooling_system || latestMediaReport?.recommendations || [
                  { timing: '0-2 Jam', pic: 'Bidhumas & Subbid Penmas', channel: 'Tribratanews & Siaran Pers', action: 'Rilis pernyataan awal klarifikasi fakta objektif untuk meredam spekulasi liar.' },
                  { timing: '2-6 Jam', pic: 'Subbid Multimedia', channel: 'TikTok, Instagram, X', action: 'Produksi infografis ringkas dan video ramah seluler bantahan hoaks.' },
                  { timing: '24 Jam', pic: 'Subbid PID & Satwil', channel: 'Media Massa Lokal & Bhabinkamtibmas', action: 'Pendekatan tokoh masyarakat dan cooling system tatap muka di wilayah rawan.' }
                ])"
                :key="idx"
                class="hover:bg-slate-900/40 transition"
              >
                <td class="p-3 font-mono font-bold text-amber-400 whitespace-nowrap">{{ item.timing || item.time || 'Segera' }}</td>
                <td class="p-3 font-bold text-white whitespace-nowrap">{{ item.pic || item.actor || 'Divhumas / Bidhumas' }}</td>
                <td class="p-3">
                  <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-800 text-sky-300 border border-slate-700 whitespace-nowrap">
                    {{ item.channel || 'Multi-Kanal' }}
                  </span>
                </td>
                <td class="p-3 text-slate-200 leading-relaxed">{{ item.action || item.recommendation || item }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 3. MIDDLE GRID: PETA SITUASI NUSANTARA / SATWIL & SENTIMEN AI             -->
    <!-- ========================================================================= -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <SituationMap :national="nationalMapData" :regional="polresStatuses" :selected-code="selectedCode" />

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
