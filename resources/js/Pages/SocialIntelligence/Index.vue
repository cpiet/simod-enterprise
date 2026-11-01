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
  Bookmark,
  RefreshCw,
  Sliders,
  Send,
  Eye,
  Heart,
  Repeat,
  Radio,
  KeyRound,
  Check,
  ChevronRight,
  UserCheck,
  Hash,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  posts: Object, // Paginated
  metrics: Object,
  kolProfiles: Array,
  trendingHashtags: Array,
  settings: Object,
  filters: Object,
});

const activeTab = ref(props.filters?.tab || 'radar');
const selectedPlatform = ref(props.filters?.platform || 'all');
const selectedSentiment = ref(props.filters?.sentiment || 'all');
const kolOnly = ref(Boolean(props.filters?.kol));
const selectedSort = ref(props.filters?.sort || 'latest');
const searchQuery = ref(props.filters?.q || '');

const handleFilter = () => {
  router.get('/social-intelligence', {
    polda: props.selectedCode,
    tab: activeTab.value,
    platform: selectedPlatform.value,
    sentiment: selectedSentiment.value,
    kol: kolOnly.value ? 1 : 0,
    sort: selectedSort.value,
    q: searchQuery.value,
  }, { preserveState: true, preserveScroll: true });
};

const switchTab = (tab) => {
  activeTab.value = tab;
  handleFilter();
};

const isSyncing = ref(false);
const triggerSync = () => {
  isSyncing.value = true;
  router.post('/social-intelligence/sync', {
    polda_id: props.selectedPolda?.id || 1,
  }, {
    preserveScroll: true,
    onFinish: () => {
      isSyncing.value = false;
    },
  });
};

const toggleBookmark = (id) => {
  router.post(`/social-intelligence/${id}/bookmark`, {}, {
    preserveScroll: true,
  });
};

// Form Settings Apify
const isSavingSettings = ref(false);
const saveFeedback = ref(null);

const settingsForm = ref({
  polda_id: props.selectedPolda?.id || props.settings?.polda_id || 5,
  apify_api_token: props.settings?.apify_api_token || '',
  keywords_tracked: props.settings?.keywords_tracked || 'Polda Sulbar, Polresta Mamuju, Polisi Sulbar, Polres Majene, Polres Polman',
  sync_frequency: props.settings?.sync_frequency || 'every_3_hours',
  auto_sync_enabled: Boolean(props.settings?.auto_sync_enabled ?? true),
});

const saveSettings = () => {
  isSavingSettings.value = true;
  saveFeedback.value = null;

  router.post('/social-intelligence/settings', settingsForm.value, {
    preserveScroll: true,
    onSuccess: () => {
      saveFeedback.value = {
        type: 'success',
        message: '✓ Konfigurasi Apify dan kata kunci monitoring berhasil disimpan ke sistem!',
      };
      setTimeout(() => {
        if (saveFeedback.value?.type === 'success') {
          saveFeedback.value = null;
        }
      }, 6000);
    },
    onError: (errs) => {
      saveFeedback.value = {
        type: 'error',
        message: Object.values(errs).join(', ') || 'Terjadi kesalahan saat menyimpan pengaturan.',
      };
    },
    onFinish: () => {
      isSavingSettings.value = false;
    },
  });
};

// Test Connection Apify
const isTestingConnection = ref(false);
const testConnectionResult = ref(null);

const testConnection = async () => {
  if (!settingsForm.value.apify_api_token) {
    testConnectionResult.value = {
      success: false,
      message: 'Masukkan Personal API Token Apify terlebih dahulu sebelum melakukan pengujian koneksi.',
    };
    return;
  }

  isTestingConnection.value = true;
  testConnectionResult.value = null;

  try {
    const res = await fetch('/social-intelligence/test-connection', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        apify_api_token: settingsForm.value.apify_api_token,
      }),
    });
    const data = await res.json();
    testConnectionResult.value = data;
  } catch (err) {
    testConnectionResult.value = {
      success: false,
      message: 'Terjadi kegagalan jaringan saat menghubungi server backend atau Apify.',
    };
  } finally {
    isTestingConnection.value = false;
  }
};

// Format Helpers
const getPlatformBadge = (platform) => {
  switch (platform) {
    case 'x':
      return {
        label: '𝕏 (Twitter)',
        class: 'bg-slate-900 text-white border-slate-700 dark:bg-slate-800',
      };
    case 'tiktok':
      return {
        label: 'TikTok',
        class: 'bg-black text-white border-cyan-400 shadow-sm shadow-cyan-400/20',
      };
    case 'instagram':
      return {
        label: 'Instagram',
        class: 'bg-gradient-to-r from-fuchsia-600 to-pink-500 text-white',
      };
    case 'youtube':
      return {
        label: 'YouTube',
        class: 'bg-red-600 text-white',
      };
    default:
      return {
        label: platform.toUpperCase(),
        class: 'bg-slate-800 text-slate-300',
      };
  }
};

const getSentimentClass = (sentiment) => {
  switch (sentiment) {
    case 'positif':
      return 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/40';
    case 'negatif':
      return 'bg-rose-500/20 text-rose-400 border border-rose-500/40';
    case 'netral':
    default:
      return 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700';
  }
};

const formatNumber = (num) => {
  if (!num) return '0';
  if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
  if (num >= 1000) return (num / 1000).toFixed(1) + 'k';
  return num.toLocaleString('id-ID');
};
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
            Cyber Patrol & Social Listening
          </span>
          <span class="text-xs font-bold text-slate-500 dark:text-slate-400">
            {{ selectedCode === 'ALL' ? 'Mabes Polri' : selectedPolda?.name }}
          </span>
        </div>
        <h1 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white tracking-tight mt-1">
          Radar Intelijen Media Sosial
        </h1>
        <p class="text-xs md:text-sm text-slate-600 dark:text-slate-300">
          Pemantauan percakapan publik, sentimen netizen, tren tagar, dan radar tokoh kritis di 𝕏, TikTok, Instagram, dan YouTube.
        </p>
      </div>

      <!-- Action Buttons -->
      <div class="flex flex-wrap items-center gap-2.5">
        <Link
          :href="`/?polda=${selectedCode}`"
          class="px-4 py-2.5 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800 border border-slate-300 transition"
        >
          &larr; Dashboard
        </Link>

        <!-- Sinkronisasi Medsos -->
        <button
          @click="triggerSync"
          type="button"
          :disabled="isSyncing"
          class="px-4 py-2.5 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-800 dark:bg-slate-900 dark:hover:bg-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700 transition flex items-center gap-2 cursor-pointer shadow-sm select-none disabled:opacity-50"
        >
          <RefreshCw class="w-4 h-4 text-sky-500" :class="{ 'animate-spin': isSyncing }" />
          <span>{{ isSyncing ? 'Menyinkronkan...' : 'Sinkronisasi Radar' }}</span>
        </button>

        <!-- Buat Laporan Baru -->
        <Link
          :href="`/issues/create?polda=${selectedCode}`"
          class="px-4 py-2.5 rounded-xl text-xs font-black bg-amber-400 text-slate-950 hover:bg-amber-300 shadow-lg shadow-amber-400/25 flex items-center gap-2 cursor-pointer transition select-none"
        >
          <span>+ Buat Laporan Isu</span>
        </Link>
      </div>
    </div>

    <!-- Flash / Toast Alert Messages -->
    <div v-if="$page.props.flash?.success || $page.props.flash?.error" class="transition-all duration-300">
      <div v-if="$page.props.flash.success" class="p-3.5 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-800 dark:text-emerald-300 text-xs font-bold flex items-center justify-between">
        <div class="flex items-center gap-2.5">
          <CheckCircle2 class="w-4 h-4 text-emerald-500 shrink-0" />
          <span>{{ $page.props.flash.success }}</span>
        </div>
      </div>
      <div v-if="$page.props.flash.error" class="p-3.5 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-800 dark:text-rose-300 text-xs font-bold flex items-center justify-between">
        <div class="flex items-center gap-2.5">
          <AlertTriangle class="w-4 h-4 text-rose-500 shrink-0" />
          <span>{{ $page.props.flash.error }}</span>
        </div>
      </div>
    </div>

    <!-- Metrics Cards (4 KPI Cards) -->
    <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
      <!-- Total Percakapan -->
      <div class="p-4 rounded-2xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 shadow-sm space-y-1">
        <div class="flex items-center justify-between">
          <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400">Percakapan Terpantau</span>
          <MessageSquare class="w-4 h-4 text-sky-500" />
        </div>
        <p class="text-2xl font-black text-slate-900 dark:text-white">{{ metrics?.total_posts || 0 }}</p>
        <p class="text-[10px] text-slate-500 dark:text-slate-400">
          Total Jangkauan: <span class="font-bold text-slate-800 dark:text-slate-200">{{ formatNumber(metrics?.total_views) }} views</span>
        </p>
      </div>

      <!-- Sentimen Polarisasi -->
      <div class="p-4 rounded-2xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 shadow-sm space-y-1">
        <div class="flex items-center justify-between">
          <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400">Sentimen Publik</span>
          <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
            {{ metrics?.positive_pct }}% Positif
          </span>
        </div>
        <!-- Triple Sentiment Progress Bar -->
        <div class="w-full h-2 rounded-full overflow-hidden flex bg-slate-200 dark:bg-slate-800 my-2">
          <div :style="{ width: `${metrics?.positive_pct || 0}%` }" class="bg-emerald-500 h-full" title="Positif"></div>
          <div :style="{ width: `${metrics?.neutral_pct || 0}%` }" class="bg-slate-400 dark:bg-slate-600 h-full" title="Netral"></div>
          <div :style="{ width: `${metrics?.negative_pct || 0}%` }" class="bg-rose-500 h-full" title="Negatif"></div>
        </div>
        <div class="flex items-center justify-between text-[10px] text-slate-500 dark:text-slate-400 font-medium">
          <span class="text-emerald-600 dark:text-emerald-400 font-bold">🟢 {{ metrics?.positive_pct }}% Pos</span>
          <span>⚪ {{ metrics?.neutral_pct }}% Net</span>
          <span class="text-rose-600 dark:text-rose-400 font-bold">🔴 {{ metrics?.negative_pct }}% Neg</span>
        </div>
      </div>

      <!-- Radar Tokoh Kritis / KOL -->
      <div class="p-4 rounded-2xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 shadow-sm space-y-1">
        <div class="flex items-center justify-between">
          <span class="text-[11px] font-bold text-amber-600 dark:text-amber-400">Tokoh Kritis & KOL</span>
          <UserCheck class="w-4 h-4 text-amber-500" />
        </div>
        <p class="text-2xl font-black text-amber-600 dark:text-amber-400">{{ metrics?.kol_count || 0 }}</p>
        <p class="text-[10px] text-slate-500 dark:text-slate-400">Akun pengamat, LBH & aktivis aktif</p>
      </div>

      <!-- Isu Potensi Viral -->
      <div class="p-4 rounded-2xl bg-white dark:bg-[#0b1426] border border-rose-200 dark:border-rose-900/60 shadow-sm space-y-1">
        <div class="flex items-center justify-between">
          <span class="text-[11px] font-bold text-rose-600 dark:text-rose-400">Potensi Viral (Atensi)</span>
          <Flame class="w-4 h-4 text-rose-500" />
        </div>
        <p class="text-2xl font-black text-rose-600 dark:text-rose-400">{{ metrics?.viral_count || 0 }}</p>
        <p class="text-[10px] text-slate-500 dark:text-slate-400">Skor viralisasi &gt; 75 / 100</p>
      </div>
    </div>

    <!-- 4 Navigasi Tab Eksekutif -->
    <div class="flex items-center gap-2 border-b border-slate-200 dark:border-slate-800 pb-2 overflow-x-auto">
      <button
        @click="switchTab('radar')"
        type="button"
        class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 cursor-pointer select-none whitespace-nowrap"
        :class="activeTab === 'radar'
          ? 'bg-amber-400 text-slate-950 font-black shadow-md'
          : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700'"
      >
        <Radio class="w-4 h-4" />
        <span>01. Radar Multi-Platform</span>
      </button>

      <button
        @click="switchTab('kol')"
        type="button"
        class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 cursor-pointer select-none whitespace-nowrap"
        :class="activeTab === 'kol'
          ? 'bg-amber-400 text-slate-950 font-black shadow-md'
          : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700'"
      >
        <UserCheck class="w-4 h-4" />
        <span>02. Tokoh Kritis & KOL Tracker</span>
      </button>

      <button
        @click="switchTab('trends')"
        type="button"
        class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 cursor-pointer select-none whitespace-nowrap"
        :class="activeTab === 'trends'
          ? 'bg-amber-400 text-slate-950 font-black shadow-md'
          : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700'"
      >
        <TrendingUp class="w-4 h-4" />
        <span>03. Tren Tagar & Isu Viral</span>
      </button>

      <button
        @click="switchTab('settings')"
        type="button"
        class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 cursor-pointer select-none whitespace-nowrap"
        :class="activeTab === 'settings'
          ? 'bg-amber-400 text-slate-950 font-black shadow-md'
          : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700'"
      >
        <KeyRound class="w-4 h-4" />
        <span>04. Integrasi Apify & Pengaturan</span>
      </button>
    </div>

    <!-- TAB 01: RADAR MULTI-PLATFORM (LIVE FEEDS) -->
    <div v-if="activeTab === 'radar'" class="space-y-4">
      <!-- Toolbar Filters -->
      <div class="p-4 rounded-2xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3 shadow-sm">
        <!-- Platform Selector Pills -->
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 lg:pb-0">
          <button
            v-for="p in [
              { id: 'all', label: 'Semua Platform' },
              { id: 'x', label: '𝕏' },
              { id: 'tiktok', label: 'TikTok' },
              { id: 'instagram', label: 'Instagram' },
              { id: 'youtube', label: 'YouTube' },
            ]"
            :key="p.id"
            @click="selectedPlatform = p.id; handleFilter()"
            type="button"
            class="px-3 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer select-none"
            :class="selectedPlatform === p.id
              ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-950 font-black shadow'
              : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700'"
          >
            {{ p.label }}
          </button>
        </div>

        <!-- Right Filters (Search, Sentiment, KOL, Sort) -->
        <div class="flex flex-wrap items-center gap-2">
          <!-- Search -->
          <div class="relative min-w-[200px] flex-1 sm:flex-initial">
            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5 pointer-events-none" />
            <input
              v-model="searchQuery"
              @keydown.enter="handleFilter"
              type="text"
              placeholder="Cari isu / akun..."
              class="w-full pl-8 pr-3 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-semibold text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-amber-400"
            />
          </div>

          <!-- Sentiment Filter -->
          <select
            v-model="selectedSentiment"
            @change="handleFilter"
            class="px-3 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 outline-none focus:border-amber-400"
          >
            <option value="all">Semua Sentimen</option>
            <option value="positif">Positif</option>
            <option value="netral">Netral</option>
            <option value="negatif">Negatif</option>
          </select>

          <!-- KOL Only Toggle -->
          <button
            @click="kolOnly = !kolOnly; handleFilter()"
            type="button"
            class="px-3 py-1.5 rounded-xl text-xs font-bold transition border cursor-pointer select-none flex items-center gap-1.5"
            :class="kolOnly
              ? 'bg-amber-400 text-slate-950 font-black border-amber-400 shadow-sm'
              : 'bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-700'"
          >
            <UserCheck class="w-3.5 h-3.5" />
            <span>KOL Saja</span>
          </button>

          <!-- Sort -->
          <select
            v-model="selectedSort"
            @change="handleFilter"
            class="px-3 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 outline-none focus:border-amber-400"
          >
            <option value="latest">Terkini</option>
            <option value="viral">Paling Viral</option>
            <option value="engagement">Interaksi Terbanyak</option>
            <option value="views">Paling Banyak Dilihat</option>
          </select>
        </div>
      </div>

      <!-- Feed Grid Cards -->
      <div v-if="posts?.data && posts.data.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="post in posts.data"
          :key="post.id"
          class="rounded-2xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 p-5 shadow-sm hover:border-slate-400 dark:hover:border-slate-700 transition flex flex-col justify-between space-y-4"
        >
          <!-- Card Top: Author & Platform -->
          <div class="space-y-3">
            <div class="flex items-center justify-between gap-2">
              <!-- Platform Pill -->
              <span
                class="px-2.5 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-wider border"
                :class="getPlatformBadge(post.platform).class"
              >
                {{ getPlatformBadge(post.platform).label }}
              </span>

              <!-- Sentiment Pill -->
              <span
                class="px-2 py-0.5 rounded-lg text-[10px] font-extrabold capitalize"
                :class="getSentimentClass(post.sentiment)"
              >
                {{ post.sentiment }}
              </span>
            </div>

            <!-- Author Profile -->
            <div class="flex items-start gap-2.5">
              <div class="w-9 h-9 rounded-full bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center font-bold text-slate-700 dark:text-slate-300 text-xs shrink-0">
                {{ post.author_name ? post.author_name.charAt(0) : '@' }}
              </div>
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-1.5 flex-wrap">
                  <h4 class="font-black text-slate-900 dark:text-white text-xs truncate">
                    {{ post.author_name }}
                  </h4>
                  <span
                    v-if="post.is_kol"
                    class="px-1.5 py-0.2 rounded text-[9px] font-black bg-amber-400 text-slate-950 uppercase"
                    title="Tokoh Kritis / Key Opinion Leader"
                  >
                    KOL
                  </span>
                </div>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
                  {{ post.author_handle }} &bull; <span v-if="post.kol_category" class="text-amber-600 dark:text-amber-400 font-bold">{{ post.kol_category }}</span>
                </p>
              </div>
            </div>

            <!-- Content Caption / Tweet Text -->
            <p class="text-xs text-slate-800 dark:text-slate-200 leading-relaxed font-normal whitespace-pre-line line-clamp-4">
              {{ post.content }}
            </p>

            <!-- Optional Media Thumbnail -->
            <div v-if="post.thumbnail_url" class="relative rounded-xl overflow-hidden h-36 bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
              <img
                :src="post.thumbnail_url"
                :alt="post.author_name"
                class="w-full h-full object-cover"
                loading="lazy"
              />
            </div>
          </div>

          <!-- Card Bottom: Metrics, Virality & Actions -->
          <div class="space-y-3 pt-3 border-t border-slate-100 dark:border-slate-800/80">
            <!-- Virality & Engagement Metrics -->
            <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400 font-mono">
              <span class="flex items-center gap-1" title="Views">
                <Eye class="w-3.5 h-3.5 text-slate-400" />
                <span>{{ formatNumber(post.views_count) }}</span>
              </span>
              <span class="flex items-center gap-1" title="Likes">
                <Heart class="w-3.5 h-3.5 text-rose-500" />
                <span>{{ formatNumber(post.likes_count) }}</span>
              </span>
              <span class="flex items-center gap-1" title="Shares">
                <Repeat class="w-3.5 h-3.5 text-sky-500" />
                <span>{{ formatNumber(post.shares_count) }}</span>
              </span>
              <span class="flex items-center gap-1" title="Comments">
                <MessageSquare class="w-3.5 h-3.5 text-amber-500" />
                <span>{{ formatNumber(post.comments_count) }}</span>
              </span>
            </div>

            <!-- Virality Progress Bar -->
            <div>
              <div class="flex items-center justify-between text-[10px] mb-1">
                <span class="text-slate-500 dark:text-slate-400 font-bold">Skor Viralisasi:</span>
                <span
                  class="font-black"
                  :class="post.virality_score >= 80 ? 'text-rose-500' : (post.virality_score >= 60 ? 'text-amber-500' : 'text-slate-500')"
                >
                  {{ post.virality_score }}/100
                </span>
              </div>
              <div class="w-full h-1.5 rounded-full bg-slate-200 dark:bg-slate-800 overflow-hidden">
                <div
                  :style="{ width: `${post.virality_score}%` }"
                  class="h-full rounded-full transition-all"
                  :class="post.virality_score >= 80 ? 'bg-rose-500' : (post.virality_score >= 60 ? 'bg-amber-400' : 'bg-sky-400')"
                ></div>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center justify-between gap-2 pt-1">
              <!-- Bookmark Toggle -->
              <button
                @click="toggleBookmark(post.id)"
                type="button"
                class="p-2 rounded-xl border text-xs transition cursor-pointer"
                :class="post.is_bookmarked
                  ? 'bg-amber-50 dark:bg-amber-400/10 text-amber-600 dark:text-amber-400 border-amber-400'
                  : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-400 border-slate-300 dark:border-slate-700'"
                :title="post.is_bookmarked ? 'Tersimpan di Arsip' : 'Tandai Bukti Arsip'"
              >
                <Bookmark class="w-3.5 h-3.5" :class="{ 'fill-current': post.is_bookmarked }" />
              </button>

              <div class="flex items-center gap-1.5">
                <!-- External Link -->
                <a
                  :href="post.post_url"
                  target="_blank"
                  class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 text-xs transition flex items-center justify-center"
                  title="Buka Postingan Asli"
                >
                  <ExternalLink class="w-3.5 h-3.5" />
                </a>

                <!-- One-Click Escalation to Laporan Isu (/issues/create) -->
                <Link
                  :href="`/issues/create?polda=${selectedCode}&title=${encodeURIComponent(`[PATROLI MEDSOS] ${post.author_handle}: ${post.content.slice(0, 60)}...`)}&category=📱 Isu Medsos & Opini Publik (Cyber Patrol)&source_media=${encodeURIComponent(getPlatformBadge(post.platform).label)}&source_url=${encodeURIComponent(post.post_url)}&summary=${encodeURIComponent(post.content)}`"
                  class="px-3 py-1.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-black text-xs transition flex items-center gap-1 shadow-sm cursor-pointer select-none"
                  title="Jadikan Kasus / Laporan Isu di Polres/Polda"
                >
                  <span>🚨 Eskalasi Jadi Isu</span>
                </Link>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div
        v-else
        class="rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 p-12 text-center shadow-sm space-y-3"
      >
        <Radio class="w-10 h-10 text-slate-400 mx-auto" />
        <h3 class="text-base font-black text-slate-900 dark:text-white">Tidak ada percakapan media sosial ditemukan</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto">
          Coba sesuaikan kata kunci pencarian, filter platform, atau tekan tombol Sinkronisasi Radar.
        </p>
        <button
          @click="selectedPlatform = 'all'; selectedSentiment = 'all'; kolOnly = false; searchQuery = ''; handleFilter()"
          type="button"
          class="px-4 py-2 rounded-xl bg-amber-400 text-slate-950 font-black text-xs hover:bg-amber-300 transition"
        >
          Reset Filter
        </button>
      </div>

      <!-- Pagination -->
      <div v-if="posts?.links && posts.links.length > 3" class="flex flex-col sm:flex-row items-center justify-between gap-3 p-4 rounded-2xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 shadow-sm">
        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">
          Halaman <span class="font-bold text-amber-600 dark:text-amber-400">{{ posts.current_page }}</span> dari <span class="font-bold text-slate-900 dark:text-white">{{ posts.last_page }}</span> &bull; Menampilkan {{ posts.from }}-{{ posts.to }} dari {{ posts.total }} Percakapan
        </p>

        <div class="flex items-center gap-1 flex-wrap">
          <template v-for="(link, idx) in posts.links" :key="idx">
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

    <!-- TAB 02: TOKOH KRITIS & KOL TRACKER -->
    <div v-if="activeTab === 'kol'" class="space-y-4">
      <div class="rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-4">
        <div class="border-b border-slate-200 dark:border-slate-800 pb-3">
          <h2 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
            <UserCheck class="w-5 h-5 text-amber-500" />
            <span>Direktori Tokoh Kritis, Pengamat Hukum & KOL Wilayah</span>
          </h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
            Akun-akun yang memiliki daya jangkau tinggi (*high amplification*) dan secara berkala menyoroti kinerja penegakan hukum dan kamtibmas di Sulawesi Barat.
          </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="(kol, idx) in kolProfiles"
            :key="idx"
            class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 space-y-3"
          >
            <div class="flex items-center justify-between">
              <span
                class="px-2 py-0.5 rounded text-[10px] font-black uppercase"
                :class="getPlatformBadge(kol.platform).class"
              >
                {{ kol.platform.toUpperCase() }}
              </span>
              <span class="text-[10px] font-bold text-amber-600 dark:text-amber-400 px-2 py-0.5 rounded bg-amber-50 dark:bg-amber-400/10">
                {{ kol.kol_category || 'Tokoh Kritis' }}
              </span>
            </div>

            <div>
              <h4 class="font-black text-slate-900 dark:text-white text-sm">
                {{ kol.author_name }}
              </h4>
              <p class="text-xs font-mono text-slate-500 dark:text-slate-400">
                {{ kol.author_handle }}
              </p>
            </div>

            <div class="pt-2 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
              <button
                @click="searchQuery = kol.author_handle; activeTab = 'radar'; handleFilter()"
                type="button"
                class="text-xs font-bold text-amber-600 dark:text-amber-400 hover:underline flex items-center gap-1 cursor-pointer"
              >
                <span>Lihat Riwayat Postingan</span>
                <ChevronRight class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 03: TREN TAGAR & ISU VIRAL -->
    <div v-if="activeTab === 'trends'" class="space-y-4">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Leaderboard Tagar Viral -->
        <div class="lg:col-span-2 rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-4">
          <div class="border-b border-slate-200 dark:border-slate-800 pb-3">
            <h2 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
              <Hash class="w-5 h-5 text-amber-500" />
              <span>Top Tagar & Isu Paling Banyak Dibicarakan</span>
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
              Daftar kata kunci dan tagar dengan volume percakapan tertinggi sepekan terakhir di Sulbar.
            </p>
          </div>

          <div class="space-y-2.5">
            <div
              v-for="(trend, idx) in trendingHashtags"
              :key="idx"
              class="flex items-center justify-between p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 hover:border-amber-400 transition"
            >
              <div class="flex items-center gap-3">
                <span class="w-6 h-6 rounded-lg bg-amber-400/20 text-amber-600 dark:text-amber-400 flex items-center justify-center font-black text-xs">
                  {{ idx + 1 }}
                </span>
                <div>
                  <h4 class="font-black text-slate-900 dark:text-white text-xs">
                    {{ trend.tag }}
                  </h4>
                  <p class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">
                    {{ formatNumber(trend.count) }} Percakapan &bull; Platform: {{ trend.platform.toUpperCase() }}
                  </p>
                </div>
              </div>

              <div class="flex items-center gap-2">
                <span
                  class="px-2 py-0.5 rounded text-[10px] font-black capitalize"
                  :class="getSentimentClass(trend.sentiment)"
                >
                  {{ trend.sentiment }}
                </span>
                <button
                  @click="searchQuery = trend.tag; activeTab = 'radar'; handleFilter()"
                  type="button"
                  class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300"
                  title="Filter postingan dengan tagar ini"
                >
                  <Search class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Info Card: Jam Puncak Obrolan -->
        <div class="rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-4">
          <div class="border-b border-slate-200 dark:border-slate-800 pb-3">
            <h3 class="text-sm font-black text-slate-900 dark:text-white flex items-center gap-2">
              <Clock class="w-4 h-4 text-amber-500" />
              <span>Jam Puncak Obrolan Netizen</span>
            </h3>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">
              Waktu WITA paling aktif warganet Sulbar
            </p>
          </div>

          <div class="space-y-3 text-xs">
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
              <span class="text-[10px] text-slate-500 font-bold block">Puncak Siang:</span>
              <p class="font-black text-slate-900 dark:text-white text-sm">11.30 – 13.30 WITA</p>
              <p class="text-[10px] text-slate-500">Isu keluhan pelayanan & lalin siang hari</p>
            </div>

            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
              <span class="text-[10px] text-slate-500 font-bold block">Puncak Malam:</span>
              <p class="font-black text-amber-500 text-sm">19.00 – 22.00 WITA</p>
              <p class="text-[10px] text-slate-500">Puncak viralisasi video TikTok & opini medsos X</p>
            </div>

            <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-400/10 border border-amber-300 dark:border-amber-400/30 text-[11px] text-amber-900 dark:text-amber-200 space-y-1">
              <p class="font-bold">💡 Rekomendasi Jam Rilis Konter Narasi:</p>
              <p class="leading-relaxed">
                Rilis siaran pers atau infografis klarifikasi paling efektif didistribusikan pada pukul <strong>12.00 WITA</strong> atau <strong>19.30 WITA</strong> agar menjangkau 70% pengguna aktif.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 04: KONFIGURASI APIFY & KATA KUNCI -->
    <div v-if="activeTab === 'settings'" class="space-y-4">
      <div class="max-w-3xl rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 p-6 md:p-8 shadow-sm space-y-6">
        <div class="border-b border-slate-200 dark:border-slate-800 pb-4">
          <div class="flex items-center gap-2">
            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-500 text-white">
              Apify Free Tier Ready
            </span>
            <h2 class="text-base font-black text-slate-900 dark:text-white">
              Pengaturan Integrasi Apify Scraper & Kata Kunci Pelacakan
            </h2>
          </div>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
            Gunakan paket gratis Apify (gratis $5/bulan) untuk scraping data X, TikTok, Instagram, dan YouTube.
          </p>
        </div>

        <form @submit.prevent="saveSettings" class="space-y-5 text-xs">
          <!-- Token Apify -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label class="block font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider text-[11px]">
                APIFY PERSONAL API TOKEN (KREDENSIAL API)
              </label>
              <button
                type="button"
                @click="testConnection"
                :disabled="isTestingConnection || !settingsForm.apify_api_token"
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-[11px] font-bold bg-sky-500/10 hover:bg-sky-500/20 text-sky-600 dark:text-sky-400 border border-sky-500/30 transition disabled:opacity-40 cursor-pointer"
              >
                <RefreshCw :class="['w-3 h-3', isTestingConnection ? 'animate-spin' : '']" />
                <span>{{ isTestingConnection ? 'Menguji...' : 'Uji Koneksi Token' }}</span>
              </button>
            </div>
            <input
              v-model="settingsForm.apify_api_token"
              type="text"
              placeholder="apify_api_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
              class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white font-mono placeholder-slate-400 focus:outline-none focus:border-amber-400"
            />
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
              Dapatkan token dari menu <a href="https://console.apify.com/account/integrations" target="_blank" class="text-amber-500 underline font-semibold">Apify Console Integrations</a>. Masukkan token Anda untuk mengaktifkan penarikan data live dari internet.
            </p>

            <!-- Status Hasil Uji Koneksi -->
            <div v-if="testConnectionResult" class="mt-2.5 p-3 rounded-xl border text-xs" :class="testConnectionResult.success ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-700 dark:text-emerald-300' : 'bg-rose-500/10 border-rose-500/30 text-rose-700 dark:text-rose-300'">
              <div class="flex items-start gap-2">
                <CheckCircle2 v-if="testConnectionResult.success" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" />
                <AlertTriangle v-else class="w-4 h-4 text-rose-500 shrink-0 mt-0.5" />
                <div class="space-y-1">
                  <p class="font-bold">{{ testConnectionResult.message }}</p>
                  <div v-if="testConnectionResult.user" class="text-[11px] opacity-90 space-y-0.5 font-mono">
                    <p>• Akun: {{ testConnectionResult.user.username }} ({{ testConnectionResult.user.email }})</p>
                    <p>• Paket: {{ testConnectionResult.user.plan }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Kata Kunci Pelacakan -->
          <div>
            <label class="block font-black text-slate-800 dark:text-slate-200 mb-1.5 uppercase tracking-wider text-[11px]">
              KATA KUNCI PEMANTAUAN WILAYAH (PISAHKAN DENGAN KOMA)
            </label>
            <textarea
              v-model="settingsForm.keywords_tracked"
              rows="3"
              class="w-full p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white font-medium placeholder-slate-400 focus:outline-none focus:border-amber-400 leading-relaxed"
            ></textarea>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
              Contoh: Polda Sulbar, Polresta Mamuju, Polisi Sulbar, Polres Majene, Polres Polman, Lantas Sulbar
            </p>
          </div>

          <!-- Frekuensi Sinkronisasi -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block font-black text-slate-800 dark:text-slate-200 mb-1.5 uppercase tracking-wider text-[11px]">
                INTERVAL PENARIKAN OTOMATIS
              </label>
              <select
                v-model="settingsForm.sync_frequency"
                class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white font-bold outline-none focus:border-amber-400"
              >
                <option value="every_1_hour">Setiap 1 Jam</option>
                <option value="every_3_hours">Setiap 3 Jam (Direkomendasikan)</option>
                <option value="every_6_hours">Setiap 6 Jam</option>
                <option value="daily">Sekali Sehari (Pukul 08.00 WITA)</option>
              </select>
            </div>

            <div>
              <label class="block font-black text-slate-800 dark:text-slate-200 mb-1.5 uppercase tracking-wider text-[11px]">
                STATUS OTOMASI
              </label>
              <div class="flex items-center gap-3 p-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 mt-0.5">
                <input
                  id="auto_sync"
                  type="checkbox"
                  v-model="settingsForm.auto_sync_enabled"
                  class="w-4 h-4 rounded text-amber-500 focus:ring-amber-400"
                />
                <label for="auto_sync" class="font-bold text-slate-800 dark:text-slate-200 cursor-pointer">
                  Aktifkan Sinkronisasi Background Otomatis
                </label>
              </div>
            </div>
          </div>

          <!-- Feedback Notifikasi Simpan -->
          <div v-if="saveFeedback" class="p-3.5 rounded-xl text-xs font-bold flex items-center gap-2.5" :class="saveFeedback.type === 'success' ? 'bg-emerald-500/15 border border-emerald-500/40 text-emerald-700 dark:text-emerald-300' : 'bg-rose-500/15 border border-rose-500/40 text-rose-700 dark:text-rose-300'">
            <CheckCircle2 v-if="saveFeedback.type === 'success'" class="w-4 h-4 text-emerald-500 shrink-0" />
            <AlertTriangle v-else class="w-4 h-4 text-rose-500 shrink-0" />
            <span>{{ saveFeedback.message }}</span>
          </div>

          <div class="flex items-center justify-between gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
            <button
              type="button"
              @click="triggerSync"
              :disabled="isSyncing || !settingsForm.apify_api_token"
              class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-xs font-bold bg-sky-500/10 hover:bg-sky-500/20 text-sky-600 dark:text-sky-400 border border-sky-500/30 transition disabled:opacity-40 cursor-pointer"
            >
              <RefreshCw :class="['w-3.5 h-3.5', isSyncing ? 'animate-spin' : '']" />
              <span>{{ isSyncing ? 'Sedang Menarik Data...' : '⚡ Tarik Data Live Apify Sekarang' }}</span>
            </button>

            <button
              type="submit"
              :disabled="isSavingSettings"
              class="px-6 py-2.5 rounded-xl text-xs font-black bg-amber-400 hover:bg-amber-300 text-slate-950 transition shadow disabled:opacity-50 cursor-pointer"
            >
              {{ isSavingSettings ? 'Menyimpan...' : 'Simpan Konfigurasi Apify' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </EnterpriseLayout>
</template>

