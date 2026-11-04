<script setup>
import { ref, computed, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import axios from 'axios';
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
  Play,
  Globe,
  Layers,
  ArrowUpRight,
  Activity,
  Zap,
  Cloud,
  RotateCw,
  Target,
  FolderOpen,
  PlusCircle,
  Printer,
  FileText,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  isNational: Boolean,
  posts: Object, // Paginated
  totalPostsCount: Number,
  bookmarkedCount: Number,
  investigationCases: { type: Array, default: () => [] },
  selectedCase: { type: Object, default: null },
  casePosts: { type: Object, default: null },
  metrics: Object,
  kolProfiles: Array,
  trendingHashtags: Array,
  trendingByPlatform: Object,
  publicTrends: Object,
  supportedActors: Array,
  spikeTimeline: Object,
  wordCloud: Object,
  peakHours: Object,
  settings: Object,
  latestReport: { type: Object, default: null },
  reportHistory: { type: Array, default: () => [] },
  satwils: { type: Array, default: () => [] },
  filters: Object,
});

const activeTab = ref(props.filters?.tab || 'radar');
const selectedPlatform = ref(props.filters?.platform || 'all');
const selectedSentiment = ref(props.filters?.sentiment || 'all');
const kolOnly = ref(Boolean(props.filters?.kol));
const selectedSort = ref(props.filters?.sort || 'latest');
const searchQuery = ref(props.filters?.q || '');
const selectedSatwil = ref(props.filters?.satwil || 'all');

// Smart Dossier & Topik Atensi Selection States
const selectedCaseId = ref(props.filters?.case_id ? Number(props.filters.case_id) : (props.selectedCase?.id || null));
const showBookmarksOnly = ref(Boolean(props.filters?.bookmarked));

watch(() => [props.filters, props.selectedCase], () => {
  if (props.filters?.case_id !== undefined) {
    selectedCaseId.value = props.filters.case_id ? Number(props.filters.case_id) : null;
  } else if (props.selectedCase?.id) {
    selectedCaseId.value = props.selectedCase.id;
  } else {
    selectedCaseId.value = null;
  }
  showBookmarksOnly.value = Boolean(props.filters?.bookmarked);
});

const handleFilter = () => {
  router.get('/social-intelligence', {
    polda: props.selectedCode,
    tab: activeTab.value,
    case_id: selectedCaseId.value || undefined,
    bookmarked: showBookmarksOnly.value ? 1 : undefined,
    platform: selectedPlatform.value,
    sentiment: selectedSentiment.value,
    kol: kolOnly.value ? 1 : 0,
    sort: selectedSort.value,
    q: searchQuery.value,
    satwil: selectedSatwil.value,
  }, { preserveState: true, preserveScroll: true });
};

const switchTab = (tab) => {
  activeTab.value = tab;
  handleFilter();
};

const isRefreshingCase = ref(false);

const selectAllPosts = () => {
  selectedCaseId.value = null;
  showBookmarksOnly.value = false;
  handleFilter();
};

const selectBookmarked = () => {
  selectedCaseId.value = null;
  showBookmarksOnly.value = true;
  handleFilter();
};

const selectCasePill = (caseId) => {
  if (selectedCaseId.value === caseId) {
    selectedCaseId.value = null;
  } else {
    selectedCaseId.value = caseId;
    showBookmarksOnly.value = false;
  }
  handleFilter();
};

const closeCaseTab = (caseId) => {
  if (confirm('Tutup topik atensi pimpinan ini dan simpan ke arsip?')) {
    router.post(`/social-intelligence/cases/${caseId}/close`, {
      polda: props.selectedCode,
    }, {
      preserveScroll: true,
      onSuccess: () => {
        if (selectedCaseId.value === caseId) {
          selectedCaseId.value = null;
        }
      },
    });
  }
};

const refreshCaseTab = (caseId) => {
  isRefreshingCase.value = true;
  router.post(`/social-intelligence/cases/${caseId}/refresh`, {
    polda: props.selectedCode,
  }, {
    preserveScroll: true,
    onFinish: () => {
      isRefreshingCase.value = false;
    },
  });
};

const getCaseEscalationUrl = (c) => {
  if (!c) return '/issues/create';
  const params = new URLSearchParams({
    polda: props.selectedCode,
    title: `[Medsos Atensi] ${c.title}`,
    category: '📱 Isu Medsos & Opini Publik (Cyber Patrol)',
    priority: c.priority === 'urgent' ? 'urgent' : 'high',
    summary: `Hasil pemantauan berkas kasus intelijen siber "${c.title}". Total himpunan ${c.posts_count || 0} percakapan warganet dengan estimasi jangkauan ${formatNumber(c.total_views || 0)} views. Polarisasi sentimen: ${c.sentiment_breakdown?.neg_pct || 0}% negatif dan ${c.sentiment_breakdown?.pos_pct || 0}% positif. Rekomendasi Humas: ${c.intelligence_notes || ''}`,
    source_media: 'Multi-Platform Medsos',
  });
  return `/issues/create?${params.toString()}`;
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
  polda_id: props.selectedPolda?.id || props.settings?.polda_id || props.allPoldas?.[0]?.id || 1,
  apify_api_token: props.settings?.apify_api_token || '',
  keywords_tracked: props.settings?.keywords_tracked || 'Polri, Humas Polri, Polisi Presisi, Dumas Presisi',
  accounts_tracked: props.settings?.accounts_tracked || '@divisihumaspri, @humaspolri, @polripresisi, @halo_polisi',
  sync_frequency: props.settings?.sync_frequency || 'every_3_hours',
  auto_sync_enabled: Boolean(props.settings?.auto_sync_enabled ?? true),
});

// Keep settings form continuously in sync with active tenant props across reloads & domain switches
watch(
  () => [props.settings, props.selectedPolda],
  ([newSettings, newPolda]) => {
    if (newSettings || newPolda) {
      settingsForm.value.polda_id = newPolda?.id || newSettings?.polda_id || props.allPoldas?.[0]?.id || 1;
      if (newSettings?.apify_api_token !== undefined) {
        settingsForm.value.apify_api_token = newSettings.apify_api_token || '';
      }
      if (newSettings?.keywords_tracked !== undefined) {
        settingsForm.value.keywords_tracked = newSettings.keywords_tracked || '';
      }
      if (newSettings?.accounts_tracked !== undefined) {
        settingsForm.value.accounts_tracked = newSettings.accounts_tracked || '';
      }
      if (newSettings?.sync_frequency !== undefined) {
        settingsForm.value.sync_frequency = newSettings.sync_frequency || 'every_3_hours';
      }
      if (newSettings?.auto_sync_enabled !== undefined) {
        settingsForm.value.auto_sync_enabled = Boolean(newSettings.auto_sync_enabled);
      }
    }
  },
  { deep: true }
);

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
    const res = await axios.post('/social-intelligence/test-connection', {
      apify_api_token: settingsForm.value.apify_api_token,
    });
    testConnectionResult.value = res.data;
  } catch (err) {
    testConnectionResult.value = {
      success: false,
      message: err.response?.data?.message || 'Terjadi kegagalan jaringan saat menghubungi server backend atau Apify.',
    };
  } finally {
    isTestingConnection.value = false;
  }
};

// Format Helpers
const getPlatformBadge = (platform) => {
  const p = (platform || 'umum').toLowerCase();
  switch (p) {
    case 'x':
      return {
        label: '𝕏 (Twitter)',
        class: 'bg-slate-900 text-white border-slate-700 dark:bg-slate-800',
        badgeClass: 'bg-slate-900 text-white border border-slate-700 dark:bg-slate-800',
        avatarBg: 'bg-slate-800 text-white border border-slate-700',
      };
    case 'tiktok':
      return {
        label: 'TikTok',
        class: 'bg-black text-white border-cyan-400 shadow-sm shadow-cyan-400/20',
        badgeClass: 'bg-black text-white border border-cyan-400 shadow-sm shadow-cyan-400/20',
        avatarBg: 'bg-black text-cyan-400 border border-cyan-400/40',
      };
    case 'instagram':
      return {
        label: 'Instagram',
        class: 'bg-gradient-to-r from-fuchsia-600 to-pink-500 text-white',
        badgeClass: 'bg-gradient-to-r from-fuchsia-600 to-pink-500 text-white',
        avatarBg: 'bg-gradient-to-br from-purple-600 to-pink-500 text-white',
      };
    case 'youtube':
      return {
        label: 'YouTube',
        class: 'bg-red-600 text-white',
        badgeClass: 'bg-red-600 text-white',
        avatarBg: 'bg-red-600 text-white',
      };
    case 'facebook':
      return {
        label: 'Facebook',
        class: 'bg-blue-600 text-white',
        badgeClass: 'bg-blue-600 text-white',
        avatarBg: 'bg-blue-600 text-white',
      };
    default:
      return {
        label: (platform || 'UMUM').toUpperCase(),
        class: 'bg-slate-800 text-slate-300',
        badgeClass: 'bg-slate-800 text-slate-300 border border-slate-700',
        avatarBg: 'bg-slate-800 text-slate-300 border border-slate-700',
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

const getSentimentBadge = (sentiment) => {
  const s = (sentiment || 'netral').toLowerCase();
  switch (s) {
    case 'positif':
      return {
        label: 'Positif',
        class: 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/40',
        dot: 'bg-emerald-500',
      };
    case 'negatif':
      return {
        label: 'Negatif',
        class: 'bg-rose-500/20 text-rose-400 border border-rose-500/40',
        dot: 'bg-rose-500',
      };
    case 'netral':
    default:
      return {
        label: 'Netral',
        class: 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700',
        dot: 'bg-slate-400',
      };
  }
};

const formatNumber = (num) => {
  if (!num) return '0';
  if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
  if (num >= 1000) return (num / 1000).toFixed(1) + 'k';
  return num.toLocaleString('id-ID');
};

const selectedTrendPlatform = ref(
  props.filters?.platform && props.filters.platform !== 'all' ? props.filters.platform : 'all'
);

const trendPlatformTabs = [
  { id: 'all', label: 'Semua Platform' },
  { id: 'instagram', label: 'Instagram' },
  { id: 'tiktok', label: 'TikTok' },
  { id: 'x', label: '𝕏 (Twitter)' },
  { id: 'youtube', label: 'YouTube' },
];

const currentTrendingList = computed(() => {
  if (selectedTrendPlatform.value !== 'all' && props.trendingByPlatform && props.trendingByPlatform[selectedTrendPlatform.value]) {
    return props.trendingByPlatform[selectedTrendPlatform.value];
  }
  return props.trendingHashtags || (props.trendingByPlatform ? props.trendingByPlatform.all : []) || [];
});

const trendingTitle = computed(() => {
  if (selectedTrendPlatform.value === 'all') {
    return props.isNational ? 'Top Tagar & Isu Nasional Paling Banyak Dibicarakan' : 'Top Tagar & Isu Paling Banyak Dibicarakan';
  }
  return `Top Tagar & Isu ${getPlatformBadge(selectedTrendPlatform.value).label} Paling Banyak Dibicarakan`;
});

const trendingSubtitle = computed(() => {
  if (selectedTrendPlatform.value === 'all') {
    return props.isNational
      ? 'Daftar kata kunci dan tagar nasional seputar kepolisian dengan volume percakapan tertinggi sepekan terakhir.'
      : `Daftar kata kunci dan tagar dengan volume percakapan tertinggi sepekan terakhir di ${props.selectedPolda?.name || 'wilayah jajaran'}.`;
  }
  return `Daftar kata kunci dan tagar terpopuler khusus ${getPlatformBadge(selectedTrendPlatform.value).label} seputar kepolisian berdasarkan volume dan jangkauan interaksi.`;
});

const filterByTrend = (trend) => {
  searchQuery.value = trend.tag;
  if (selectedTrendPlatform.value !== 'all') {
    selectedPlatform.value = selectedTrendPlatform.value;
  } else if (trend.platform) {
    selectedPlatform.value = trend.platform;
  }
  activeTab.value = 'radar';
  handleFilter();
};

// Dual-Mode Lens for Trends Tab ('kamtibmas' vs 'public')
const trendLensMode = ref('kamtibmas');
const selectedPublicPlatform = ref('all');
const securityOnly = ref(false);
const searchTrendQuery = ref('');
const isSyncingTrends = ref(false);

const publicPlatformTabs = [
  { id: 'all', label: 'Semua Platform' },
  { id: 'x', label: '𝕏 (Twitter)' },
  { id: 'youtube', label: 'YouTube' },
  { id: 'instagram', label: 'Instagram' },
  { id: 'tiktok', label: 'TikTok' },
  { id: 'facebook', label: 'Facebook' },
];

const currentPublicTrendsList = computed(() => {
  const platform = selectedPublicPlatform.value;
  const list = props.publicTrends?.platforms?.[platform] || props.publicTrends?.platforms?.all || [];
  
  return list.filter((item) => {
    if (securityOnly.value && !item.is_security_relevant) {
      return false;
    }
    if (searchTrendQuery.value) {
      const q = searchTrendQuery.value.toLowerCase();
      const matchName = item.name?.toLowerCase().includes(q);
      const matchCat = item.category?.toLowerCase().includes(q);
      const matchType = item.relevance_type?.toLowerCase().includes(q);
      const matchNote = item.relevance_note?.toLowerCase().includes(q);
      return matchName || matchCat || matchType || matchNote;
    }
    return true;
  });
});

const securityRelevantPublicCount = computed(() => {
  const platform = selectedPublicPlatform.value;
  const list = props.publicTrends?.platforms?.[platform] || props.publicTrends?.platforms?.all || [];
  return list.filter(item => item.is_security_relevant).length;
});

const triggerSyncTrends = () => {
  isSyncingTrends.value = true;
  router.post('/social-intelligence/sync-trends', {
    apify_api_token: settingsForm.value.apify_api_token,
  }, {
    preserveScroll: true,
    onFinish: () => {
      isSyncingTrends.value = false;
    },
  });
};

const filterByPublicTrend = (item) => {
  searchQuery.value = item.name.replace(/^#/, '');
  if (item.platform && item.platform !== 'all') {
    selectedPlatform.value = item.platform;
  }
  activeTab.value = 'radar';
  handleFilter();
};

const getEscalationUrl = (item) => {
  const params = new URLSearchParams({
    title: item.escalation?.title || `Atensi Isu Tren: ${item.name}`,
    category: item.escalation?.category || '📱 Isu Medsos & Opini Publik',
    summary: item.escalation?.description || `${item.relevance_note || ''} (Volume: ${item.volume})`,
    source_media: item.platform_label || item.platform?.toUpperCase(),
    source_url: item.sample_url || '',
    priority: item.is_security_relevant ? 'high' : 'medium',
  });
  return `/issues/create?${params.toString()}`;
};

// --- PILIHAN C: MULTI-LINE SPIKE CHART REACTIVE MATH ---
const spikeTimelineDays = computed(() => props.spikeTimeline?.days || []);

const spikeMaxVal = computed(() => {
  let m = 1;
  for (const d of spikeTimelineDays.value) {
    if (d.positif > m) m = d.positif;
    if (d.netral > m) m = d.netral;
    if (d.negatif > m) m = d.negatif;
    if (d.total > m) m = d.total;
  }
  return m;
});

const activeDayIndex = ref(null);
const activeDayData = computed(() => {
  if (activeDayIndex.value !== null && spikeTimelineDays.value[activeDayIndex.value]) {
    return spikeTimelineDays.value[activeDayIndex.value];
  }
  const spikeIdx = spikeTimelineDays.value.findIndex(d => d.is_spike);
  if (spikeIdx !== -1) return spikeTimelineDays.value[spikeIdx];
  return spikeTimelineDays.value[spikeTimelineDays.value.length - 1] || null;
});

const getSvgX = (index) => {
  const total = Math.max(1, spikeTimelineDays.value.length - 1);
  return 45 + (index / total) * (650 - 90);
};

const getSvgY = (val) => {
  const max = Math.max(1, spikeMaxVal.value);
  return 150 - (val / max) * 115;
};

const posPolyline = computed(() => {
  return spikeTimelineDays.value.map((d, i) => `${getSvgX(i)},${getSvgY(d.positif)}`).join(' ');
});

const netPolyline = computed(() => {
  return spikeTimelineDays.value.map((d, i) => `${getSvgX(i)},${getSvgY(d.netral)}`).join(' ');
});

const negPolyline = computed(() => {
  return spikeTimelineDays.value.map((d, i) => `${getSvgX(i)},${getSvgY(d.negatif)}`).join(' ');
});

const totalAreaPath = computed(() => {
  if (spikeTimelineDays.value.length === 0) return '';
  const firstX = getSvgX(0);
  const lastX = getSvgX(spikeTimelineDays.value.length - 1);
  const points = spikeTimelineDays.value.map((d, i) => `L ${getSvgX(i)} ${getSvgY(d.total)}`).join(' ');
  return `M ${firstX} 150 ${points} L ${lastX} 150 Z`;
});

// --- PILIHAN C: WORD CLOUD FILTERING ---
const filterByWord = (word) => {
  searchQuery.value = word.text;
  activeTab.value = 'radar';
  handleFilter();
};

const getWordStyle = (word) => {
  let colorClass = 'text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 border-slate-300 dark:border-slate-700 hover:border-amber-400';
  if (word.sentiment === 'positif') {
    colorClass = 'text-emerald-700 dark:text-emerald-300 bg-emerald-500/10 border-emerald-500/30 hover:bg-emerald-500/20 hover:border-emerald-500';
  } else if (word.sentiment === 'negatif') {
    colorClass = 'text-rose-700 dark:text-rose-300 bg-rose-500/10 border-rose-500/30 hover:bg-rose-500/20 hover:border-rose-500';
  } else if (word.sentiment === 'netral') {
    colorClass = 'text-amber-700 dark:text-amber-300 bg-amber-500/10 border-amber-500/30 hover:bg-amber-500/20 hover:border-amber-500';
  }
  return {
    fontSize: `${Math.max(12, Math.min(34, word.weight))}px`,
    classes: colorClass,
  };
};

// --- PILIHAN C: ON-DEMAND KEYWORD TRACKER MODAL ---
const showTrackModal = ref(false);
const trackForm = ref({
  keyword: '',
  platforms: ['x', 'tiktok', 'instagram', 'youtube', 'facebook'],
  max_items: 20,
  polda_id: props.selectedPolda?.id || 1,
});
const isTrackingKeyword = ref(false);

const openTrackKeywordModal = () => {
  trackForm.value.keyword = searchQuery.value || '';
  trackForm.value.polda_id = props.selectedPolda?.id || 1;
  showTrackModal.value = true;
};

const toggleTrackPlatform = (platId) => {
  const idx = trackForm.value.platforms.indexOf(platId);
  if (idx > -1) {
    if (trackForm.value.platforms.length > 1) {
      trackForm.value.platforms.splice(idx, 1);
    }
  } else {
    trackForm.value.platforms.push(platId);
  }
};

const submitTrackKeyword = () => {
  if (!trackForm.value.keyword.trim()) return;
  isTrackingKeyword.value = true;
  router.post('/social-intelligence/track-keyword', trackForm.value, {
    preserveScroll: true,
    onFinish: () => {
      isTrackingKeyword.value = false;
      showTrackModal.value = false;
    },
  });
};

const getPublicPlatformBadge = (platform) => {
  switch (platform) {
    case 'x':
      return { label: '𝕏', class: 'bg-black text-white border border-slate-700' };
    case 'youtube':
      return { label: 'YouTube', class: 'bg-red-600 text-white' };
    case 'instagram':
      return { label: 'Instagram', class: 'bg-gradient-to-r from-pink-500 via-rose-500 to-amber-500 text-white' };
    case 'tiktok':
      return { label: 'TikTok', class: 'bg-slate-900 text-white border border-rose-500/30' };
    case 'facebook':
      return { label: 'Facebook', class: 'bg-blue-600 text-white' };
    default:
      return { label: (platform || 'SEMUA').toUpperCase(), class: 'bg-slate-800 text-slate-300' };
  }
};

// ─── SADEWA SOCIAL AI SYNTHESIS & PERSISTENCE ─────────────────────────────────
const isAnalyzingAI = ref(false);
const socialAiReport = ref(props.latestReport || null);
const selectedReportId = ref(props.latestReport?.id || (props.reportHistory?.[0]?.id || null));
const isFetchingReport = ref(false);
const socialAiError = ref(null);
const showWaCopied = ref(false);
const showClarificationCopied = ref(false);
const showHumanistCopied = ref(false);
const showInfographicCopied = ref(false);
const activeAiTab = ref('briefing'); // 'briefing' | 'polarisasi' | 'cooling' | 'counter' | 'wa'

const loadReport = async (reportId) => {
  if (!reportId) return;
  isFetchingReport.value = true;
  socialAiError.value = null;
  try {
    const res = await axios.get(`/social-intelligence/reports/${reportId}`);
    if (res.data.success && res.data.report) {
      socialAiReport.value = res.data.report;
      selectedReportId.value = reportId;
    }
  } catch (err) {
    socialAiError.value = 'Gagal memuat arsip laporan intelijen medsos.';
  } finally {
    isFetchingReport.value = false;
  }
};

const analyzeAI = async () => {
  isAnalyzingAI.value = true;
  socialAiError.value = null;
  try {
    const res = await axios.post('/social-intelligence/analyze-ai', {
      polda: props.selectedCode,
    });
    if (res.data.success) {
      socialAiReport.value = res.data;
      if (res.data.report_id) {
        selectedReportId.value = res.data.report_id;
      }
    } else {
      socialAiError.value = res.data.message || 'Gagal mendapatkan respons AI.';
    }
  } catch (err) {
    socialAiError.value = err.response?.data?.message || 'Gagal menghubungi server analisis AI.';
  } finally {
    isAnalyzingAI.value = false;
  }
};

const triggerSyncAndAnalyze = () => {
  isSyncing.value = true;
  router.post('/social-intelligence/sync', {
    polda_id: props.selectedPolda?.id || 1,
  }, {
    preserveScroll: true,
    onFinish: () => {
      isSyncing.value = false;
      activeTab.value = 'briefing';
      analyzeAI();
    },
  });
};

const copyWaScript = () => {
  const script = socialAiReport.value?.data?.whatsapp_script || '';
  navigator.clipboard.writeText(script).then(() => {
    showWaCopied.value = true;
    setTimeout(() => { showWaCopied.value = false; }, 3000);
  });
};

const copyClarification = () => {
  const text = socialAiReport.value?.data?.counter_narrative?.objective_clarification || '';
  navigator.clipboard.writeText(text).then(() => {
    showClarificationCopied.value = true;
    setTimeout(() => { showClarificationCopied.value = false; }, 3000);
  });
};

const copyHumanist = () => {
  const text = socialAiReport.value?.data?.counter_narrative?.humanist_reply || '';
  navigator.clipboard.writeText(text).then(() => {
    showHumanistCopied.value = true;
    setTimeout(() => { showHumanistCopied.value = false; }, 3000);
  });
};

const copyInfographic = () => {
  const msgs = socialAiReport.value?.data?.counter_narrative?.infographic_key_messages || [];
  const text = msgs.map((m, i) => `${i + 1}. ${m}`).join('\n');
  navigator.clipboard.writeText(text).then(() => {
    showInfographicCopied.value = true;
    setTimeout(() => { showInfographicCopied.value = false; }, 3000);
  });
};

const printA4SocialReport = () => {
  window.print();
};

const isCriticalSpike = (post) => {
  const viralScore = Number(post.virality_score || 0);
  const views = Number(post.views_count || 0);
  const engage = Number(post.likes_count || 0) + Number(post.shares_count || 0);
  const isNeg = (post.sentiment || '').toLowerCase() === 'negatif';
  return (viralScore >= 80) || (views >= 8000 && isNeg) || (engage >= 400 && isNeg);
};

const getSentimentStabilityClass = (score) => {
  if (score >= 75) return 'text-emerald-600 dark:text-emerald-400';
  if (score >= 50) return 'text-amber-600 dark:text-amber-400';
  return 'text-rose-600 dark:text-rose-400';
};

const getBuzzerRiskClass = (level) => {
  if (level === 'tinggi') return 'bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-500/30';
  if (level === 'sedang') return 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30';
  return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30';
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
            {{ isNational ? 'Cyber Patrol & Social Listening Nasional' : 'Cyber Patrol & Social Listening' }}
          </span>
          <span class="text-xs font-bold text-slate-500 dark:text-slate-400">
            {{ isNational ? 'Divhumas Polri (Komando Nasional)' : selectedPolda?.name }}
          </span>
        </div>
        <h1 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white tracking-tight mt-1">
          {{ isNational ? 'Radar Intelijen Media Sosial Nasional' : 'Radar Intelijen Media Sosial' }}
        </h1>
        <p class="text-xs md:text-sm text-slate-600 dark:text-slate-300">
          {{ isNational
            ? 'Pemantauan komprehensif percakapan publik skala nasional, orkestrasi sentimen kewilayahan, deteksi dini isu viral, dan manajemen narasi Mabes Polri di 𝕏, TikTok, Instagram, dan YouTube.'
            : 'Pemantauan percakapan publik, sentimen netizen, tren tagar, dan radar tokoh kritis di 𝕏, TikTok, Instagram, dan YouTube.'
          }}
        </p>
      </div>

      <!-- Action Buttons -->
      <div class="flex flex-col items-end gap-2">
        <div class="flex flex-wrap items-center gap-2.5">
          <Link
            :href="`/?polda=${selectedCode}`"
            class="px-4 py-2.5 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800 border border-slate-300 transition"
          >
            &larr; Dashboard
          </Link>

          <!-- ↺ Perbarui Data (secondary) -->
          <button
            @click="triggerSync"
            type="button"
            :disabled="isSyncing || isAnalyzingAI"
            class="px-4 py-2.5 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-800 dark:bg-slate-900 dark:hover:bg-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700 transition flex items-center gap-2 cursor-pointer shadow-sm select-none disabled:opacity-50"
          >
            <RefreshCw class="w-4 h-4 text-sky-500" :class="{ 'animate-spin': isSyncing }" />
            <span>{{ isSyncing ? 'Memperbarui...' : '↺ Perbarui Data' }}</span>
          </button>

          <!-- Lacak Topik Baru On-Demand -->
          <button
            @click="openTrackKeywordModal"
            type="button"
            class="px-4 py-2.5 rounded-xl text-xs font-black bg-gradient-to-r from-sky-500 to-indigo-600 hover:from-sky-400 hover:to-indigo-500 text-white shadow-md shadow-sky-500/20 flex items-center gap-2 cursor-pointer transition select-none"
          >
            <Sparkles class="w-4 h-4 text-amber-300" />
            <span>⚡ Lacak Topik Baru</span>
          </button>

          <!-- MAIN CTA: Sinkron & Sintesis Intelijen Medsos -->
          <button
            @click="triggerSyncAndAnalyze"
            type="button"
            :disabled="isSyncing || isAnalyzingAI"
            class="px-5 py-2.5 rounded-xl text-xs font-black bg-gradient-to-r from-amber-400 to-amber-500 hover:brightness-110 text-slate-950 shadow-lg shadow-amber-400/25 flex items-center gap-2 cursor-pointer transition select-none disabled:opacity-60"
          >
            <Sparkles class="w-4 h-4" :class="{ 'animate-spin': isSyncing || isAnalyzingAI }" />
            <span>{{ (isSyncing || isAnalyzingAI) ? 'SADEWA Sedang Bekerja...' : '⚡ Sinkron & Analisis Intelijen Medsos' }}</span>
          </button>

          <!-- Buat Laporan Baru -->
          <Link
            :href="`/issues/create?polda=${selectedCode}`"
            class="px-4 py-2.5 rounded-xl text-xs font-black bg-rose-500 text-white hover:bg-rose-600 shadow-lg shadow-rose-500/20 flex items-center gap-2 cursor-pointer transition select-none"
          >
            <span>+ Buat Laporan Isu</span>
          </Link>
        </div>
        <!-- Engine Status Badge -->
        <div class="flex items-center gap-1.5 text-[10px] font-bold text-emerald-600 dark:text-emerald-400">
          <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse inline-block"></span>
          <span>Multi-Tier Engine Aktif (Sindikasi Google News + Apify Standby)</span>
        </div>
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
        <span>01. Radar Percakapan</span>
      </button>

      <button
        @click="switchTab('briefing')"
        type="button"
        class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 cursor-pointer select-none whitespace-nowrap"
        :class="activeTab === 'briefing'
          ? 'bg-amber-400 text-slate-950 font-black shadow-md'
          : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700'"
      >
        <Sparkles class="w-4 h-4 text-amber-500" />
        <span>02. Executive Briefing & Naskah WA</span>
        <span class="px-1.5 py-0.5 rounded text-[9px] font-black bg-amber-400 text-slate-950" v-if="activeTab !== 'briefing'">AI</span>
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
        <span>03. Radar KOL & Akun Kritis</span>
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
        <span>04. Tren Tagar & Kerawanan Siber</span>
      </button>
    </div>

    <!-- TAB 01: RADAR MULTI-PLATFORM (LIVE FEEDS) -->
    <div v-if="activeTab === 'radar'" class="space-y-5">
      <!-- SMART DOSSIER: TOPIK ATENSI & REQUEST PIMPINAN (IDENTIK DENGAN MEDIA INTELLIGENCE) -->
      <div class="rounded-3xl bg-[#0b1426] border border-amber-500/30 p-5 shadow-2xl space-y-4">
        <!-- Top Bar: Title & Action -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-800">
          <div class="space-y-0.5">
            <div class="flex items-center gap-2">
              <span class="flex h-2.5 w-2.5 relative">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500"></span>
              </span>
              <h3 class="text-sm md:text-base font-extrabold text-white flex items-center gap-1.5 flex-wrap">
                <span>Topik Atensi &amp; Request Pimpinan</span>
                <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-amber-400/20 text-amber-300 border border-amber-400/30">
                  Smart Dossier
                </span>
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold bg-slate-900 border border-slate-700 text-slate-300 flex items-center gap-1.5 shadow-xs">
                  <span>{{ investigationCases.length }} Kasus Dipantau</span>
                  <span v-if="investigationCases.some(c => c.priority === 'urgent')" class="text-rose-400 font-black">
                    • {{ investigationCases.filter(c => c.priority === 'urgent').length }} Mendesak
                  </span>
                </span>
              </h3>
            </div>
            <p class="text-[11px] text-slate-400">
              Penyaringan kasus viral / atensi khusus Kapolda &amp; Kadiv Humas lintas 5 platform medsos (𝕏, TikTok, Instagram, YouTube, Facebook).
            </p>
          </div>

          <div class="flex items-center gap-2">
            <button
              @click="openTrackKeywordModal"
              type="button"
              class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-amber-400 hover:brightness-110 text-slate-950 text-xs font-black shadow-md shadow-amber-500/20 transition cursor-pointer select-none"
            >
              <PlusCircle class="w-3.5 h-3.5" />
              <span>+ Lacak Topik Atensi</span>
            </button>
          </div>
        </div>

        <!-- KASUS ATENSI SELECTOR (ADAPTIVE RESPONSIVE FLEX BADGES - RAPI & BEBAS OVERFLOW) -->
        <div class="space-y-2">
          <!-- Empty State jika belum ada kasus -->
          <div
            v-if="investigationCases.length === 0"
            class="p-4 rounded-2xl bg-[#070d19]/80 border border-dashed border-slate-700/80 text-center space-y-1.5"
          >
            <p class="text-xs font-bold text-slate-300">Belum ada topik atensi pimpinan yang aktif di media sosial.</p>
            <p class="text-[11px] text-slate-500">
              Klik <strong>"+ Lacak Topik Atensi"</strong> di atas untuk memantau tagar atau kasus spesifik secara terisolasi.
            </p>
          </div>

          <!-- Adaptive Case Badges List -->
          <div v-else class="flex flex-wrap items-center gap-2.5">
            <div
              v-for="c in investigationCases"
              :key="c.id"
              class="group relative inline-flex items-center rounded-2xl transition-all shadow-sm border"
              :class="selectedCaseId === c.id
                ? 'bg-amber-400 border-amber-300 text-slate-950 font-black shadow-lg shadow-amber-400/20 ring-2 ring-amber-400/40'
                : c.priority === 'urgent'
                  ? 'bg-[#18111e] hover:bg-[#22132b] border-rose-500/50 hover:border-rose-400 text-rose-200'
                  : 'bg-[#070d19] hover:bg-slate-800 border-slate-700/80 hover:border-amber-400/60 text-slate-200'"
            >
              <!-- Main Click Area -->
              <button
                @click="selectedCaseId === c.id ? selectAllPosts() : selectCasePill(c.id)"
                type="button"
                class="px-3.5 py-2 text-xs font-bold transition flex items-center gap-2 cursor-pointer select-none text-left"
              >
                <!-- Priority Dot -->
                <span
                  class="w-2.5 h-2.5 rounded-full shrink-0"
                  :class="selectedCaseId === c.id
                    ? 'bg-slate-950'
                    : c.priority === 'urgent' ? 'bg-rose-500 animate-pulse' : 'bg-amber-400'"
                ></span>

                <!-- Title (Generous width, clean reading) -->
                <span
                  class="truncate max-w-[280px] sm:max-w-[340px] md:max-w-[420px] font-bold"
                  :class="selectedCaseId === c.id ? 'text-slate-950' : 'text-slate-100'"
                  :title="c.short_title"
                >
                  {{ c.short_title }}
                </span>

                <!-- Post Count Badge -->
                <span
                  class="text-[10px] px-2 py-0.5 rounded-full font-mono font-black shrink-0"
                  :class="selectedCaseId === c.id
                    ? 'bg-slate-950 text-amber-300'
                    : c.priority === 'urgent' ? 'bg-rose-500/30 text-rose-300' : 'bg-slate-800 text-amber-300 border border-slate-700'"
                >
                  {{ c.posts_count || 0 }} Post
                </span>
              </button>

              <!-- Archive Action Button -->
              <div class="flex items-center pr-1 border-l" :class="selectedCaseId === c.id ? 'border-slate-950/20' : 'border-slate-800'">
                <button
                  @click.stop="closeCaseTab(c.id)"
                  type="button"
                  class="p-1.5 rounded-xl hover:bg-black/20 transition cursor-pointer"
                  :class="selectedCaseId === c.id ? 'text-slate-950 hover:text-rose-950' : 'text-slate-400 hover:text-rose-400'"
                  title="Tutup & Arsipkan Topik Atensi Ini"
                >
                  <X class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>

            <!-- Quick Reset Button when a case is filtered -->
            <button
              v-if="selectedCaseId !== null"
              @click="selectAllPosts"
              type="button"
              class="px-3 py-2 rounded-2xl text-xs font-bold bg-slate-800 hover:bg-slate-700 border border-slate-600 text-slate-300 hover:text-white transition flex items-center gap-1.5 cursor-pointer shadow-sm select-none"
              title="Tutup filter kasus dan tampilkan semua percakapan medsos"
            >
              <X class="w-3.5 h-3.5" />
              <span>Tutup Filter Kasus</span>
            </button>
          </div>
        </div>

        <!-- Dossier Detail Bar (Muncul saat Kasus Dipilih) -->
        <div
          v-if="selectedCase"
          class="p-4 md:p-5 rounded-2xl bg-gradient-to-r from-amber-500/10 via-slate-900/95 to-slate-900 border border-amber-500/40 space-y-4"
        >
          <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 pb-3 border-b border-slate-800">
            <div class="space-y-1">
              <div class="flex items-center gap-2 flex-wrap">
                <span
                  class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-wider"
                  :class="selectedCase.priority === 'urgent' ? 'bg-rose-500 text-white' : 'bg-amber-400 text-slate-950'"
                >
                  {{ selectedCase.priority === 'urgent' ? '🚨 SANGAT MENDESAK' : '⭐ PRIORITAS TINGGI' }}
                </span>
                <h4 class="text-sm md:text-base font-black text-white flex items-center gap-2">
                  <FolderOpen class="w-4 h-4 text-amber-400" />
                  <span>{{ selectedCase.title }}</span>
                </h4>
                <span class="text-[11px] text-amber-400 font-mono font-bold">
                  ({{ selectedCase.posts_count || 0 }} Percakapan Terkait)
                </span>
                <span class="text-[11px] text-slate-400">
                  • Diperbarui: {{ selectedCase.last_crawled_at }}
                </span>
              </div>

              <!-- Keywords Tags with Hash -->
              <div class="flex items-center gap-1.5 flex-wrap pt-1">
                <span class="text-[10px] text-slate-400 font-bold">Kata Kunci Pantau:</span>
                <span
                  v-for="kw in (selectedCase.keywords_array || [])"
                  :key="kw"
                  class="px-2 py-0.5 rounded-md text-[9px] font-mono bg-slate-800 text-slate-200 border border-slate-700"
                >
                  #{{ kw }}
                </span>
              </div>
            </div>

            <!-- Action Buttons for Selected Case -->
            <div class="flex items-center gap-2 shrink-0 flex-wrap">
              <button
                @click="refreshCaseTab(selectedCase.id)"
                :disabled="isRefreshingCase"
                type="button"
                class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white border border-slate-700 text-xs font-bold transition cursor-pointer disabled:opacity-50"
                title="Tarik data postingan terbaru seputar kasus ini"
              >
                <RotateCw class="w-3.5 h-3.5" :class="{ 'animate-spin': isRefreshingCase }" />
                <span>{{ isRefreshingCase ? 'Menyinkronkan...' : 'Tarik Update Postingan' }}</span>
              </button>

              <a
                :href="getCaseEscalationUrl(selectedCase)"
                class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-black shadow-md transition cursor-pointer select-none"
                title="Eskalasi temuan medsos ke laporan isu kepolisian"
              >
                <AlertTriangle class="w-3.5 h-3.5" />
                <span>🚨 Eskalasi Jadi Isu Kamtibmas</span>
              </a>

              <button
                @click="selectAllPosts"
                type="button"
                class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white border border-slate-700 text-xs transition cursor-pointer"
                title="Tutup Tampilan Kasus (Kembali ke Semua Percakapan)"
              >
                <X class="w-4 h-4" />
              </button>
            </div>
          </div>

          <!-- 4 Mini KPIs -->
          <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5">
            <div class="p-3 rounded-xl bg-slate-900/80 border border-slate-800">
              <span class="text-[10px] font-bold text-slate-400">Total Percakapan Kasus</span>
              <p class="text-xl font-black text-white mt-0.5">{{ selectedCase.posts_count || 0 }} <span class="text-[10px] font-normal text-slate-400">Post</span></p>
            </div>
            <div class="p-3 rounded-xl bg-slate-900/80 border border-slate-800">
              <span class="text-[10px] font-bold text-slate-400">Estimasi Jangkauan (Views)</span>
              <p class="text-xl font-black text-indigo-400 mt-0.5">{{ formatNumber(selectedCase.total_views || 0) }}</p>
            </div>
            <div class="p-3 rounded-xl bg-slate-900/80 border border-slate-800">
              <span class="text-[10px] font-bold text-slate-400">Total Interaksi Warganet</span>
              <p class="text-xl font-black text-emerald-400 mt-0.5">{{ formatNumber(selectedCase.total_engagements || 0) }}</p>
            </div>
            <div class="p-3 rounded-xl bg-slate-900/80 border border-slate-800">
              <span class="text-[10px] font-bold text-slate-400">Polarisasi Negatif</span>
              <p class="text-xl font-black text-rose-400 mt-0.5">{{ selectedCase.sentiment_breakdown?.neg_pct || 0 }}%</p>
            </div>
          </div>

          <!-- Sentiment Bar & Humas Note -->
          <div class="space-y-2">
            <div class="flex items-center justify-between text-[11px] font-bold flex-wrap gap-2 text-slate-300">
              <span>Polarisasi Sentimen Warganet:</span>
              <div class="flex items-center gap-3">
                <span class="text-emerald-400">🟢 Positif: {{ selectedCase.sentiment_breakdown?.pos_pct || 0 }}% ({{ selectedCase.sentiment_breakdown?.positif || 0 }})</span>
                <span class="text-slate-400">⚪ Netral: {{ selectedCase.sentiment_breakdown?.netral || 0 }}</span>
                <span class="text-rose-400">🔴 Negatif: {{ selectedCase.sentiment_breakdown?.neg_pct || 0 }}% ({{ selectedCase.sentiment_breakdown?.negatif || 0 }})</span>
              </div>
            </div>
            <div class="h-2 w-full bg-slate-800 rounded-full overflow-hidden flex">
              <div class="bg-emerald-500 transition-all duration-500" :style="{ width: (selectedCase.sentiment_breakdown?.pos_pct || 0) + '%' }"></div>
              <div class="bg-slate-500 transition-all duration-500" :style="{ width: Math.max(0, 100 - (selectedCase.sentiment_breakdown?.pos_pct || 0) - (selectedCase.sentiment_breakdown?.neg_pct || 0)) + '%' }"></div>
              <div class="bg-rose-500 transition-all duration-500" :style="{ width: (selectedCase.sentiment_breakdown?.neg_pct || 0) + '%' }"></div>
            </div>
            <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-xs text-amber-200 flex items-start gap-2">
              <Info class="w-4 h-4 text-amber-400 shrink-0 mt-0.5" />
              <p class="leading-relaxed text-[11px]">
                <strong>Rekomendasi Komunikasi Humas:</strong> {{ selectedCase.intelligence_notes }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- GRAFIK TREN SENTIMEN & DETEKSI LONJAKAN ISU (SPIKE DETECTION) -->
      <div class="rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 p-5 md:p-6 shadow-sm space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-3">
          <div>
            <div class="flex items-center gap-2 flex-wrap">
              <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/30">
                Spike Anomaly Detection
              </span>
              <span
                class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                :class="spikeTimeline?.summary?.spike_detected ? 'bg-rose-500/15 text-rose-600 dark:text-rose-400 border border-rose-500/30 animate-pulse' : 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30'"
              >
                {{ spikeTimeline?.summary?.spike_detected ? '🚨 Terdeteksi Lonjakan Isu' : '🟢 Stabilitas Opini Terkendali' }}
              </span>
              <span class="text-xs text-slate-500 dark:text-slate-400 font-mono">
                Indeks Stabilitas: <strong class="text-slate-900 dark:text-white">{{ spikeTimeline?.summary?.stability_score || 85 }}/100</strong>
              </span>
            </div>
            <h2 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2 mt-1">
              <Activity class="w-5 h-5 text-indigo-500" />
              <span>Grafik Fluktuasi Percakapan & Deteksi Lonjakan Isu (7 Hari)</span>
            </h2>
          </div>

          <!-- Chart Legend -->
          <div class="flex items-center gap-3 text-xs font-bold">
            <span class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
              <span class="w-3 h-3 rounded-full bg-emerald-500 inline-block"></span>
              <span>Positif</span>
            </span>
            <span class="flex items-center gap-1.5 text-slate-500 dark:text-slate-400">
              <span class="w-3 h-3 rounded-full bg-slate-400 inline-block"></span>
              <span>Netral</span>
            </span>
            <span class="flex items-center gap-1.5 text-rose-600 dark:text-rose-400">
              <span class="w-3 h-3 rounded-full bg-rose-500 inline-block"></span>
              <span>Negatif (Spike)</span>
            </span>
          </div>
        </div>

        <!-- Pure Reactive SVG Multi-Line Chart -->
        <div class="relative w-full overflow-x-auto no-scrollbar">
          <svg viewBox="0 0 650 175" class="w-full h-44 select-none">
            <defs>
              <linearGradient id="totalAreaGrad" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#6366f1" stop-opacity="0.18" />
                <stop offset="100%" stop-color="#6366f1" stop-opacity="0.0" />
              </linearGradient>
            </defs>

            <!-- Horizontal Gridlines -->
            <line x1="45" y1="35" x2="605" y2="35" stroke="currentColor" class="text-slate-200 dark:text-slate-800" stroke-dasharray="3,3" />
            <line x1="45" y1="75" x2="605" y2="75" stroke="currentColor" class="text-slate-200 dark:text-slate-800" stroke-dasharray="3,3" />
            <line x1="45" y1="115" x2="605" y2="115" stroke="currentColor" class="text-slate-200 dark:text-slate-800" stroke-dasharray="3,3" />
            <line x1="45" y1="150" x2="605" y2="150" stroke="currentColor" class="text-slate-300 dark:text-slate-700" stroke-width="1.5" />

            <!-- Soft Total Area Path -->
            <path :d="totalAreaPath" fill="url(#totalAreaGrad)" />

            <!-- Line 1: Positif (Emerald) -->
            <polyline
              :points="posPolyline"
              fill="none"
              stroke="#10b981"
              stroke-width="2.5"
              stroke-linecap="round"
              stroke-linejoin="round"
            />

            <!-- Line 2: Netral (Slate) -->
            <polyline
              :points="netPolyline"
              fill="none"
              stroke="#94a3b8"
              stroke-width="2"
              stroke-dasharray="4,2"
              stroke-linecap="round"
              stroke-linejoin="round"
            />

            <!-- Line 3: Negatif (Rose Spike) -->
            <polyline
              :points="negPolyline"
              fill="none"
              stroke="#f43f5e"
              stroke-width="3"
              stroke-linecap="round"
              stroke-linejoin="round"
            />

            <!-- Data Circle Points & Interactive Hover Columns -->
            <g v-for="(day, idx) in spikeTimelineDays" :key="idx">
              <!-- Interactive Transparent Trigger Column -->
              <rect
                :x="getSvgX(idx) - 25"
                y="10"
                width="50"
                height="150"
                fill="transparent"
                class="cursor-pointer"
                @mouseenter="activeDayIndex = idx"
              />

              <!-- Highlight Vertical Line on Hover -->
              <line
                v-if="activeDayIndex === idx"
                :x1="getSvgX(idx)"
                y1="20"
                :x2="getSvgX(idx)"
                y2="150"
                stroke="#6366f1"
                stroke-width="1.5"
                stroke-dasharray="2,2"
              />

              <!-- Positif Point -->
              <circle
                :cx="getSvgX(idx)"
                :cy="getSvgY(day.positif)"
                r="3.5"
                fill="#10b981"
                stroke="#ffffff"
                stroke-width="1.5"
              />

              <!-- Negatif Point with Alert Pulsating Ring if Spike -->
              <circle
                v-if="day.is_spike"
                :cx="getSvgX(idx)"
                :cy="getSvgY(day.negatif)"
                r="7"
                fill="#f43f5e"
                opacity="0.3"
                class="animate-ping"
              />
              <circle
                :cx="getSvgX(idx)"
                :cy="getSvgY(day.negatif)"
                r="4.5"
                :fill="day.is_spike ? '#e11d48' : '#f43f5e'"
                stroke="#ffffff"
                stroke-width="1.5"
              />

              <!-- X-Axis Date Labels -->
              <text
                :x="getSvgX(idx)"
                y="166"
                text-anchor="middle"
                class="text-[10px] font-mono font-bold"
                :fill="activeDayIndex === idx ? '#6366f1' : (day.is_spike ? '#f43f5e' : '#64748b')"
              >
                {{ day.label }}
              </text>
            </g>
          </svg>
        </div>

        <!-- Active Data Point Inspector & Early Warning Summary -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs">
          <div>
            <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider block">Waktu Analisis:</span>
            <span class="font-black text-slate-900 dark:text-white text-sm">{{ activeDayData?.label || '7 Hari Terakhir' }}</span>
            <span v-if="activeDayData?.is_spike" class="ml-2 px-1.5 py-0.2 rounded text-[9px] font-bold bg-rose-500 text-white">Spike!</span>
          </div>

          <div class="flex items-center gap-3">
            <div>
              <span class="text-[10px] text-slate-500 font-bold block">🟢 Positif:</span>
              <span class="font-black text-emerald-600 dark:text-emerald-400 text-sm">{{ activeDayData?.positif || 0 }}</span>
            </div>
            <div>
              <span class="text-[10px] text-slate-500 font-bold block">⚪ Netral:</span>
              <span class="font-black text-slate-700 dark:text-slate-300 text-sm">{{ activeDayData?.netral || 0 }}</span>
            </div>
            <div>
              <span class="text-[10px] text-slate-500 font-bold block">🔴 Negatif:</span>
              <span class="font-black text-rose-600 dark:text-rose-400 text-sm">{{ activeDayData?.negatif || 0 }}</span>
            </div>
          </div>

          <div class="md:col-span-2 flex items-center justify-between border-t md:border-t-0 md:border-l border-slate-200 dark:border-slate-800 pt-2 md:pt-0 md:pl-4">
            <div>
              <span class="text-[10px] text-slate-500 font-bold block">💡 Kesimpulan Intelijen:</span>
              <p class="text-[11px] text-slate-700 dark:text-slate-300 font-medium leading-relaxed">
                {{ activeDayData?.is_spike ? activeDayData.spike_note : (spikeTimeline?.summary?.verdict || 'Situasi percakapan publik terpantau kondusif.') }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Toolbar Filters -->
      <div class="p-4 rounded-2xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3 shadow-sm">
        <!-- Left: Quick Status (Semua / Disimpan) + Platform Selector Pills -->
        <div class="flex items-center gap-2 overflow-x-auto pb-1 lg:pb-0 flex-wrap sm:flex-nowrap">
          <!-- Quick Status: Semua Percakapan -->
          <button
            @click="selectAllPosts"
            type="button"
            class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-black transition cursor-pointer select-none border shrink-0"
            :class="(!showBookmarksOnly && selectedCaseId === null)
              ? 'bg-amber-400 text-slate-950 border-amber-300 shadow-md shadow-amber-400/20'
              : 'bg-slate-100 dark:bg-[#070d19] text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-700 hover:border-slate-500'"
            title="Tampilkan semua percakapan radar"
          >
            <Globe class="w-3.5 h-3.5" :class="(!showBookmarksOnly && selectedCaseId === null) ? 'text-slate-950' : 'text-slate-400'" />
            <span>Semua</span>
            <span
              class="px-1.5 py-0.5 rounded-full text-[10px] font-mono"
              :class="(!showBookmarksOnly && selectedCaseId === null)
                ? 'bg-slate-950 text-amber-300 font-black'
                : 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold'"
            >
              {{ totalPostsCount || metrics?.total_posts || posts?.total || 0 }}
            </span>
          </button>

          <!-- Quick Status: Disimpan -->
          <button
            @click="selectBookmarked"
            type="button"
            class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-black transition cursor-pointer select-none border shrink-0"
            :class="(showBookmarksOnly && selectedCaseId === null)
              ? 'bg-amber-400 text-slate-950 border-amber-300 shadow-md shadow-amber-400/20'
              : 'bg-slate-100 dark:bg-[#070d19] text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-700 hover:border-slate-500'"
            title="Tampilkan postingan yang disimpan / bookmark"
          >
            <Bookmark class="w-3.5 h-3.5" :class="(showBookmarksOnly && selectedCaseId === null) ? 'text-slate-950' : 'text-amber-400'" />
            <span>Disimpan</span>
            <span
              class="px-1.5 py-0.5 rounded-full text-[10px] font-mono"
              :class="(showBookmarksOnly && selectedCaseId === null)
                ? 'bg-slate-950 text-amber-300 font-black'
                : 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold'"
            >
              {{ bookmarkedCount || 0 }}
            </span>
          </button>

          <div class="hidden sm:block h-5 border-r border-slate-300 dark:border-slate-700 mx-1 shrink-0"></div>

          <!-- Platform Selector Pills -->
          <div class="flex items-center gap-1.5 shrink-0">
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

          <!-- Satwil / Polda Filter (National Divhumas & Regional Polda Views) -->
          <select
            v-if="isNational || (satwils && satwils.length > 0)"
            v-model="selectedSatwil"
            @change="handleFilter"
            class="px-3 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 outline-none focus:border-amber-400"
          >
            <option value="all">{{ isNational ? '🌐 Seluruh Wilayah Hukum' : '🏛️ Seluruh Satwil (Polres)' }}</option>
            <template v-if="isNational">
              <option v-for="p in (allPoldas || []).filter(item => item.code !== 'DIVHUMAS')" :key="p.id" :value="p.id">
                {{ p.name }}
              </option>
            </template>
            <template v-else>
              <option v-for="s in (satwils || [])" :key="s.id" :value="s.id">
                {{ s.name }}
              </option>
            </template>
          </select>

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
              <div class="flex items-center gap-1.5 flex-wrap">
                <!-- Platform Pill -->
                <span
                  class="px-2.5 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-wider border"
                  :class="getPlatformBadge(post.platform).class"
                >
                  {{ getPlatformBadge(post.platform).label }}
                </span>

                <!-- Satwil / Polda Badge (when isNational) -->
                <span
                  v-if="isNational && post.polda"
                  class="px-2 py-0.5 rounded-lg text-[9px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-300 border border-amber-500/30"
                >
                  🏛️ {{ post.polda.code || post.polda.name }}
                </span>

                <!-- Critical Spike Alert Badge -->
                <span
                  v-if="isCriticalSpike(post)"
                  class="px-2 py-0.5 rounded-lg text-[9px] font-black uppercase tracking-wider bg-rose-600 text-white animate-pulse shadow-sm flex items-center gap-1"
                  title="Potensi viralisasi tinggi! Akselerasi interaksi ekstrem di ruang digital"
                >
                  <Flame class="w-3 h-3 text-amber-300" />
                  <span>Critical Spike</span>
                </span>
              </div>

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

            <!-- Media Thumbnail with Play Overlay for Video Platforms -->
            <div v-if="post.thumbnail_url" class="relative rounded-xl overflow-hidden h-40 bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 group">
              <img
                :src="post.thumbnail_url"
                :alt="post.author_name"
                class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                loading="lazy"
              />
              <!-- Play Button Overlay for Video Platforms -->
              <div v-if="post.platform === 'youtube' || post.platform === 'tiktok'" class="absolute inset-0 flex items-center justify-center bg-black/25 group-hover:bg-black/40 transition">
                <div
                  class="w-10 h-10 rounded-full flex items-center justify-center shadow-lg transition transform group-hover:scale-110"
                  :class="post.platform === 'youtube' ? 'bg-red-600 text-white' : 'bg-black/80 text-white border border-cyan-400'"
                >
                  <svg class="w-4 h-4 fill-current ml-0.5" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                </div>
              </div>
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
                  :href="`/issues/create?polda=${selectedCode}&title=${encodeURIComponent(`[PATROLI MEDSOS] ${post.author_handle || ''}: ${(post.content || '').slice(0, 60)}...`)}&category=📱 Isu Medsos & Opini Publik (Cyber Patrol)&source_media=${encodeURIComponent(getPlatformBadge(post.platform).label)}&source_url=${encodeURIComponent(post.post_url || '')}&summary=${encodeURIComponent(post.content || '')}`"
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
        <FolderOpen v-if="selectedCase" class="w-10 h-10 text-amber-500 mx-auto" />
        <Radio v-else class="w-10 h-10 text-slate-400 mx-auto" />
        <h3 class="text-base font-black text-slate-900 dark:text-white">
          {{ selectedCase ? `Belum ada postingan tersimpan untuk topik "${selectedCase.title}"` : 'Tidak ada percakapan media sosial ditemukan' }}
        </h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto">
          {{ selectedCase ? 'Klik "Tarik Update Postingan" di atas untuk mencari dan mengunduh postingan terbaru seputar kasus ini.' : 'Coba sesuaikan kata kunci pencarian, filter platform, atau tekan tombol Sinkronisasi Radar.' }}
        </p>
        <div class="flex items-center justify-center gap-2 pt-2">
          <button
            v-if="selectedCase"
            @click="refreshCaseTab(selectedCase.id)"
            :disabled="isRefreshingCase"
            type="button"
            class="px-4 py-2 rounded-xl bg-amber-400 text-slate-950 font-black text-xs hover:bg-amber-300 transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
          >
            <RotateCw class="w-3.5 h-3.5" :class="{ 'animate-spin': isRefreshingCase }" />
            <span>{{ isRefreshingCase ? 'Menyinkronkan...' : 'Tarik Update Postingan' }}</span>
          </button>
          <button
            @click="selectAllPosts"
            type="button"
            class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 text-xs font-bold transition cursor-pointer"
          >
            Lihat Semua Percakapan
          </button>
        </div>
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

    <!-- TAB 02: EXECUTIVE SOCIAL BRIEFING & NASKAH WA PIMPINAN (SADEWA AI) -->
    <div v-if="activeTab === 'briefing'" class="space-y-5">
      <!-- Header Command Bar -->
      <div class="p-5 rounded-3xl bg-gradient-to-r from-[#070d1d] via-[#0b1426] to-indigo-950 border border-amber-500/30 shadow-2xl">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
          <div>
            <div class="flex items-center gap-2 mb-1">
              <span class="animate-pulse w-2.5 h-2.5 rounded-full bg-amber-400 inline-block"></span>
              <span class="text-xs font-black text-amber-400 uppercase tracking-widest">SADEWA Social AI Synthesis</span>
            </div>
            <h2 class="text-xl font-black text-white">Executive Social Briefing & Naskah WA Pimpinan</h2>
            <p class="text-xs text-slate-400 mt-0.5">Sintesis intelijen otomatis dari {{ metrics?.total_posts || posts?.total || 0 }} percakapan warganet lintas 5 platform media sosial</p>
          </div>

          <div class="flex flex-wrap items-center gap-2.5">
            <!-- Historical Reports Dropdown (Fitur Arsip Laporan) -->
            <div v-if="reportHistory && reportHistory.length > 0" class="flex items-center gap-1.5 bg-slate-900/90 border border-slate-700 rounded-xl px-3 py-2 text-xs">
              <Clock class="w-3.5 h-3.5 text-amber-400 shrink-0" />
              <select
                v-model="selectedReportId"
                @change="loadReport(selectedReportId)"
                :disabled="isFetchingReport"
                class="bg-transparent text-slate-200 font-bold outline-none cursor-pointer max-w-[200px]"
              >
                <option v-for="r in reportHistory" :key="r.id" :value="r.id" class="bg-slate-900 text-white">
                  {{ r.label }}
                </option>
              </select>
            </div>

            <!-- Print / Export PDF Button (Fitur Ekspor Resmi) -->
            <button
              v-if="socialAiReport"
              @click="printA4SocialReport"
              type="button"
              class="px-4 py-3 rounded-xl text-xs font-black bg-slate-800 hover:bg-slate-700 text-white border border-slate-700 flex items-center gap-2 cursor-pointer shadow-md transition select-none"
              title="Cetak atau simpan laporan intelijen medsos format A4 resmi"
            >
              <Printer class="w-4 h-4 text-amber-400" />
              <span>📥 Cetak Laporan PDF</span>
            </button>

            <!-- Main AI Synthesis CTA Button -->
            <button
              @click="analyzeAI"
              :disabled="isAnalyzingAI"
              type="button"
              class="px-5 py-3 rounded-xl text-xs font-black bg-gradient-to-r from-amber-400 to-amber-500 hover:brightness-110 text-slate-950 shadow-lg shadow-amber-400/25 flex items-center gap-2 disabled:opacity-60 cursor-pointer transition select-none"
            >
              <Sparkles class="w-4 h-4 text-slate-950" :class="{'animate-spin': isAnalyzingAI}" />
              <span>{{ isAnalyzingAI ? 'SADEWA Sedang Menganalisis...' : '⚡ Sintesis Intelijen Medsos Otomatis' }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="isAnalyzingAI" class="p-10 rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 text-center space-y-3">
        <div class="w-12 h-12 rounded-full border-4 border-amber-400 border-t-transparent animate-spin mx-auto"></div>
        <p class="text-sm font-bold text-slate-900 dark:text-white">SADEWA AI sedang menganalisis percakapan warganet & sentimen...</p>
        <p class="text-xs text-slate-500">Memproses narasi, mendeteksi polarisasi buzzer, merumuskan cooling system, dan menyusun naskah dinas...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="socialAiError" class="p-5 rounded-3xl bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900 text-rose-800 dark:text-rose-300 flex items-start gap-3">
        <AlertTriangle class="w-5 h-5 text-rose-500 shrink-0 mt-0.5" />
        <div>
          <p class="font-black text-sm">Analisis AI Belum Berhasil</p>
          <p class="text-xs mt-1">{{ socialAiError }}</p>
          <button @click="analyzeAI" class="mt-2 text-xs font-bold underline cursor-pointer">Coba Lagi</button>
        </div>
      </div>

      <!-- Empty / Intro State (before first analysis) -->
      <div v-else-if="!socialAiReport" class="p-12 rounded-3xl bg-white dark:bg-[#0b1426] border border-dashed border-slate-300 dark:border-slate-700 text-center space-y-4">
        <Sparkles class="w-12 h-12 text-amber-400 mx-auto" />
        <h3 class="text-base font-black text-slate-900 dark:text-white">Belum Ada Sintesis Intelijen Medsos</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto">
          Klik tombol <strong>"⚡ Sintesis Intelijen Medsos Otomatis"</strong> di atas untuk memproses percakapan warganet, memetakan polarisasi buzzer vs organik, dan menghasilkan rekomendasi cooling system beserta naskah WhatsApp pimpinan.
        </p>
        <button
          @click="analyzeAI"
          :disabled="isAnalyzingAI"
          type="button"
          class="px-5 py-2.5 rounded-xl text-xs font-black bg-amber-400 hover:bg-amber-300 text-slate-950 transition cursor-pointer shadow-md inline-flex items-center gap-2"
        >
          <Sparkles class="w-4 h-4" />
          <span>Mulai Analisis Sekarang</span>
        </button>
      </div>

      <!-- RESULTS SECTION (when socialAiReport is populated) -->
      <div v-else class="space-y-5">
        <!-- Sub-tabs for results navigation -->
        <div class="flex items-center gap-2 border-b border-slate-200 dark:border-slate-800 pb-2 overflow-x-auto">
          <button
            v-for="subTab in [
              { id: 'briefing', label: '📋 Executive Briefing' },
              { id: 'polarisasi', label: '⚡ Polarisasi & Buzzer' },
              { id: 'cooling', label: '🎯 Cooling System' },
              { id: 'counter', label: '🛡️ Konter Opini & Narasi' },
              { id: 'wa', label: '📱 Naskah WA Pimpinan' }
            ]"
            :key="subTab.id"
            @click="activeAiTab = subTab.id"
            type="button"
            class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer select-none whitespace-nowrap"
            :class="activeAiTab === subTab.id
              ? 'bg-amber-400 text-slate-950 font-black shadow-md'
              : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700'"
          >
            {{ subTab.label }}
          </button>
        </div>

        <!-- SUB-TAB 1: EXECUTIVE BRIEFING -->
        <div v-if="activeAiTab === 'briefing'" class="space-y-4">
          <!-- Model info banner -->
          <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
            <span>Dianalisis oleh SADEWA AI ({{ socialAiReport.model_used }})</span>
            <span>&bull; {{ socialAiReport.posts_count }} postingan diproses</span>
            <span>&bull; Waktu respons: {{ socialAiReport.latency_ms }}ms</span>
          </div>

          <!-- Executive Brief Card -->
          <div class="p-5 rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <h3 class="font-black text-slate-900 dark:text-white flex items-center gap-2 text-sm border-b border-slate-100 dark:border-slate-800 pb-3">
              <Activity class="w-4 h-4 text-amber-500" /> Ringkasan Eksekutif Situasi Medsos
            </h3>
            <p class="text-sm text-slate-700 dark:text-slate-200 leading-relaxed">{{ socialAiReport.data?.executive_brief }}</p>

            <!-- Top Concern -->
            <div class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-800">
              <span class="text-xs font-black text-rose-600 dark:text-rose-400 uppercase tracking-wider">⚠️ Isu Kritis Prioritas Pimpinan</span>
              <p class="text-sm text-rose-800 dark:text-rose-200 mt-1 leading-relaxed">{{ socialAiReport.data?.top_concern }}</p>
            </div>

            <!-- Sentiment Breakdown -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="space-y-2">
                <h4 class="text-xs font-black text-slate-700 dark:text-slate-300 uppercase tracking-wider">Komposisi Sentimen Percakapan</h4>
                <div class="flex items-center gap-2 text-xs font-bold">
                  <span class="text-emerald-600 dark:text-emerald-400">🟢 Positif: {{ socialAiReport.data?.sentiment_analysis?.positif || 0 }}%</span>
                  <span class="text-slate-500">⚪ Netral: {{ socialAiReport.data?.sentiment_analysis?.netral || 0 }}%</span>
                  <span class="text-rose-600 dark:text-rose-400">🔴 Negatif: {{ socialAiReport.data?.sentiment_analysis?.negatif || 0 }}%</span>
                </div>
                <div class="w-full h-3 rounded-full overflow-hidden flex bg-slate-200 dark:bg-slate-800">
                  <div :style="{width: (socialAiReport.data?.sentiment_analysis?.positif || 0) + '%'}" class="bg-emerald-500 h-full"></div>
                  <div :style="{width: (socialAiReport.data?.sentiment_analysis?.netral || 0) + '%'}" class="bg-slate-400 dark:bg-slate-600 h-full"></div>
                  <div :style="{width: (socialAiReport.data?.sentiment_analysis?.negatif || 0) + '%'}" class="bg-rose-500 h-full"></div>
                </div>
                <p class="text-xs font-bold" :class="getSentimentStabilityClass(socialAiReport.data?.sentiment_analysis?.stability_index || 50)">
                  Stabilitas Opini: {{ socialAiReport.data?.sentiment_analysis?.stability_label }} (Skor {{ socialAiReport.data?.sentiment_analysis?.stability_index }}/100)
                </p>
              </div>
              <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                <span class="text-[10px] font-bold text-slate-500 uppercase">Framing &amp; Persepsi Warganet</span>
                <p class="text-xs text-slate-700 dark:text-slate-300 mt-1 leading-relaxed">{{ socialAiReport.data?.sentiment_analysis?.narrative_framing }}</p>
              </div>
            </div>
          </div>

          <!-- Critical Accounts Card -->
          <div v-if="socialAiReport.data?.critical_accounts?.length" class="p-5 rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <h3 class="font-black text-slate-900 dark:text-white flex items-center gap-2 text-sm border-b border-slate-100 dark:border-slate-800 pb-3">
              <UserCheck class="w-4 h-4 text-amber-500" /> Akun Kritis &amp; Influencer Terdeteksi
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div
                v-for="(acc, idx) in socialAiReport.data.critical_accounts"
                :key="idx"
                class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-1"
              >
                <div class="flex items-center justify-between">
                  <span class="font-black text-sm text-slate-900 dark:text-white">{{ acc.name }}</span>
                  <span
                    class="text-[10px] font-bold px-2 py-0.5 rounded"
                    :class="acc.tone === 'Kritis' ? 'bg-rose-500/15 text-rose-600 dark:text-rose-400' : acc.tone === 'Suportif' ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' : 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300'"
                  >
                    {{ acc.tone }}
                  </span>
                </div>
                <p class="text-[11px] text-slate-500">{{ acc.platform }} &bull; {{ acc.category }}</p>
                <p class="text-[11px] text-slate-600 dark:text-slate-400">{{ acc.reach_note }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- SUB-TAB 2: POLARISASI & BUZZER -->
        <div v-else-if="activeAiTab === 'polarisasi'" class="space-y-4">
          <div class="p-5 rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <h3 class="font-black text-slate-900 dark:text-white flex items-center gap-2 text-sm border-b border-slate-100 dark:border-slate-800 pb-3">
              <Zap class="w-4 h-4 text-amber-500" /> Analisis Polarisasi: Netizen Organik vs Koordinasi Buzzer
            </h3>
            <div class="p-4 rounded-2xl border" :class="getBuzzerRiskClass(socialAiReport.data?.buzzer_vs_organic?.risk_level)">
              <div class="flex items-center justify-between mb-2">
                <span class="font-black text-sm">Tingkat Risiko Koordinasi Buzzer</span>
                <span class="px-3 py-1 rounded-full text-xs font-black uppercase">{{ socialAiReport.data?.buzzer_vs_organic?.risk_level || 'rendah' }}</span>
              </div>
              <p class="text-sm leading-relaxed">{{ socialAiReport.data?.buzzer_vs_organic?.assessment }}</p>
              <p class="text-xs mt-2 opacity-80">{{ socialAiReport.data?.buzzer_vs_organic?.risk_note }}</p>
            </div>
          </div>
        </div>

        <!-- SUB-TAB 3: COOLING SYSTEM -->
        <div v-else-if="activeAiTab === 'cooling'" class="space-y-4">
          <div class="p-5 rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <h3 class="font-black text-slate-900 dark:text-white flex items-center gap-2 text-sm border-b border-slate-100 dark:border-slate-800 pb-3">
              <Target class="w-4 h-4 text-amber-500" /> Rekomendasi Taktis Cooling System Humas
            </h3>
            <div class="space-y-3">
              <div
                v-for="(cs, idx) in (socialAiReport.data?.cooling_system || [])"
                :key="idx"
                class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800"
              >
                <div class="flex items-start gap-3">
                  <span class="w-7 h-7 rounded-xl bg-amber-400 text-slate-950 flex items-center justify-center font-black text-xs shrink-0 mt-0.5">{{ idx + 1 }}</span>
                  <div class="flex-1 space-y-1">
                    <span class="font-black text-sm text-slate-900 dark:text-white">{{ cs.action }}</span>
                    <div class="flex items-center gap-3 text-[11px] text-slate-500 mt-1">
                      <span>📱 Saluran: {{ cs.channel }}</span>
                      <span>🕐 Waktu: {{ cs.timing }}</span>
                      <span v-if="cs.pic">👤 PIC: {{ cs.pic }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- SUB-TAB 4: KONTER OPINI & NARASI TANDINGAN (TACTICAL PLAYBOOK) -->
        <div v-else-if="activeAiTab === 'counter'" class="space-y-4">
          <!-- Card 1: Draf Siaran Pers / Klarifikasi Resmi -->
          <div class="p-5 rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
              <h3 class="font-black text-slate-900 dark:text-white flex items-center gap-2 text-sm">
                <FileText class="w-4 h-4 text-sky-500" />
                <span>Draf Klarifikasi Resmi / Siaran Pers Objektif</span>
              </h3>
              <button
                @click="copyClarification"
                type="button"
                class="px-3.5 py-1.5 rounded-xl text-xs font-black flex items-center gap-1.5 cursor-pointer transition select-none"
                :class="showClarificationCopied ? 'bg-emerald-500 text-white' : 'bg-sky-500 hover:bg-sky-400 text-white shadow-sm'"
              >
                <Check v-if="showClarificationCopied" class="w-3.5 h-3.5" />
                <FileText v-else class="w-3.5 h-3.5" />
                {{ showClarificationCopied ? '✓ Tersalin!' : '📋 Salin Draf Klarifikasi' }}
              </button>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400">
              Digunakan juru bicara kepolisian (Kabid Humas / Kasihumas) untuk meluruskan framing berita dan membendung spekulasi:
            </p>
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-sm text-slate-800 dark:text-slate-200 leading-relaxed font-sans">
              {{ socialAiReport.data?.counter_narrative?.objective_clarification || 'Polri senantiasa mengedepankan transparansi, akuntabilitas, dan penegakan hukum berkeadilan dalam menindaklanjuti seluruh aduan masyarakat di ruang digital.' }}
            </div>
          </div>

          <!-- Card 2: Template Respon Komentar Warganet (Humanis & Edukatif) -->
          <div class="p-5 rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
              <h3 class="font-black text-slate-900 dark:text-white flex items-center gap-2 text-sm">
                <MessageSquare class="w-4 h-4 text-emerald-500" />
                <span>Template Respon Balasan Warganet (Social Reply)</span>
              </h3>
              <button
                @click="copyHumanist"
                type="button"
                class="px-3.5 py-1.5 rounded-xl text-xs font-black flex items-center gap-1.5 cursor-pointer transition select-none"
                :class="showHumanistCopied ? 'bg-emerald-500 text-white' : 'bg-emerald-500 hover:bg-emerald-400 text-white shadow-sm'"
              >
                <Check v-if="showHumanistCopied" class="w-3.5 h-3.5" />
                <MessageSquare v-else class="w-3.5 h-3.5" />
                {{ showHumanistCopied ? '✓ Tersalin!' : '📋 Salin Balasan Medsos' }}
              </button>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400">
              Digunakan admin akun medsos (@humaspolri, @poldasulbar) untuk membalas komentar warganet dengan nada empati:
            </p>
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-sm text-slate-800 dark:text-slate-200 leading-relaxed font-sans italic">
              "{{ socialAiReport.data?.counter_narrative?.humanist_reply || 'Terima kasih atas atensi dan masukannya Sobat Polri. Informasi telah kami teruskan ke satuan kerja terkait untuk pendalaman objektif.' }}"
            </div>
          </div>

          <!-- Card 3: Pesan Kunci Pamflet / Infografis Kewilayahan -->
          <div class="p-5 rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
              <h3 class="font-black text-slate-900 dark:text-white flex items-center gap-2 text-sm">
                <Sparkles class="w-4 h-4 text-amber-500" />
                <span>Pesan Kunci Materi Infografis (Key Messages Satwil)</span>
              </h3>
              <button
                @click="copyInfographic"
                type="button"
                class="px-3.5 py-1.5 rounded-xl text-xs font-black flex items-center gap-1.5 cursor-pointer transition select-none"
                :class="showInfographicCopied ? 'bg-emerald-500 text-white' : 'bg-amber-400 hover:bg-amber-300 text-slate-950 shadow-sm'"
              >
                <Check v-if="showInfographicCopied" class="w-3.5 h-3.5" />
                <Sparkles v-else class="w-3.5 h-3.5" />
                {{ showInfographicCopied ? '✓ Tersalin!' : '📋 Salin Poin Infografis' }}
              </button>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400">
              Disampaikan ke tim multimedia / Si Humas Polres jajaran sebagai materi konten pamflet visual:
            </p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
              <div
                v-for="(msg, mIdx) in (socialAiReport.data?.counter_narrative?.infographic_key_messages || [])"
                :key="mIdx"
                class="p-4 rounded-2xl bg-amber-400/10 border border-amber-400/30 text-xs text-slate-800 dark:text-slate-200 space-y-1.5"
              >
                <div class="flex items-center gap-2 font-black text-amber-600 dark:text-amber-400 text-xs">
                  <span class="w-5 h-5 rounded-full bg-amber-400 text-slate-950 flex items-center justify-center font-black text-[10px]">{{ mIdx + 1 }}</span>
                  <span>Poin Kunci {{ mIdx + 1 }}</span>
                </div>
                <p class="leading-relaxed font-semibold">{{ msg }}</p>
              </div>
            </div>
          </div>

          <!-- Card 4: Proyeksi Viralisasi 24 Jam -->
          <div class="p-5 rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
            <h3 class="font-black text-slate-900 dark:text-white flex items-center gap-2 text-sm border-b border-slate-100 dark:border-slate-800 pb-3">
              <TrendingUp class="w-4 h-4 text-rose-500" />
              <span>Proyeksi Viralisasi &amp; Estimasi Jam Puncak (24 Jam ke Depan)</span>
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
              <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                <span class="text-[10px] font-bold text-slate-500 uppercase block mb-1">Tingkat Risiko Viralisasi:</span>
                <span class="font-black px-2 py-0.5 rounded text-xs" :class="socialAiReport.data?.virality_forecast?.risk_level === 'Tinggi' ? 'bg-rose-500/20 text-rose-600 dark:text-rose-400 border border-rose-500/30' : 'bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/30'">
                  {{ socialAiReport.data?.virality_forecast?.risk_level || 'Sedang' }}
                </span>
              </div>
              <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                <span class="text-[10px] font-bold text-slate-500 uppercase block mb-1">Estimasi Jam Puncak:</span>
                <span class="font-black text-slate-900 dark:text-white text-xs">{{ socialAiReport.data?.virality_forecast?.peak_window || '19.00 - 22.00 WITA' }}</span>
              </div>
              <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 md:col-span-1">
                <span class="text-[10px] font-bold text-slate-500 uppercase block mb-1">Catatan Intelijen:</span>
                <p class="text-[11px] text-slate-700 dark:text-slate-300 font-medium leading-relaxed">{{ socialAiReport.data?.virality_forecast?.forecast_summary || 'Dinamika opini diproyeksikan stabil jika rilis konter narasi disebar tepat waktu.' }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- SUB-TAB 5: NASKAH WA PIMPINAN -->
        <div v-else-if="activeAiTab === 'wa'" class="space-y-4">
          <div class="p-5 rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
              <h3 class="font-black text-slate-900 dark:text-white flex items-center gap-2 text-sm">
                <Send class="w-4 h-4 text-emerald-500" /> Naskah WhatsApp Format Komando Pimpinan
              </h3>
              <button
                @click="copyWaScript"
                type="button"
                class="px-4 py-2 rounded-xl text-xs font-black flex items-center gap-1.5 cursor-pointer transition select-none"
                :class="showWaCopied ? 'bg-emerald-500 text-white' : 'bg-amber-400 hover:bg-amber-300 text-slate-950 shadow-md'"
              >
                <Check v-if="showWaCopied" class="w-3.5 h-3.5" />
                <Send v-else class="w-3.5 h-3.5" />
                {{ showWaCopied ? '✓ Tersalin ke Clipboard!' : '📋 Salin Naskah WA' }}
              </button>
            </div>
            <pre class="whitespace-pre-wrap text-sm text-slate-800 dark:text-slate-200 leading-relaxed font-sans bg-slate-50 dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 max-h-[600px] overflow-y-auto">{{ socialAiReport.data?.whatsapp_script }}</pre>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 03: RADAR KOL & AKUN KRITIS -->
    <div v-if="activeTab === 'kol'" class="space-y-4">
      <div class="rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-4">
        <div class="border-b border-slate-200 dark:border-slate-800 pb-3">
          <h2 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
            <UserCheck class="w-5 h-5 text-amber-500" />
            <span>{{ isNational ? 'Direktori Tokoh Kritis & Key Opinion Leader (KOL) Skala Nasional' : 'Direktori Tokoh Kritis, Pengamat Hukum & KOL Wilayah' }}</span>
          </h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
            {{ isNational
              ? 'Akun-akun nasional berpengaruh (pengawas eksternal kepolisian, LSM HAM, jurnalis investigasi, dan analis pertahanan/keamanan) yang menyoroti kebijakan dan isu strategis Polri secara luas.'
              : `Akun-akun yang memiliki daya jangkau tinggi (*high amplification*) dan secara berkala menyoroti kinerja penegakan hukum dan kamtibmas di ${selectedPolda?.name || 'wilayah jajaran'}.`
            }}
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
              <div v-if="kol.posts_count || kol.total_views" class="flex items-center gap-2 mt-1.5 text-[10px] font-mono text-slate-500 dark:text-slate-400">
                <span v-if="kol.posts_count" class="font-bold text-amber-600 dark:text-amber-400">
                  {{ kol.posts_count }} Postingan
                </span>
                <span v-if="kol.total_views">
                  &bull; {{ formatNumber(kol.total_views) }} Jangkauan
                </span>
              </div>
            </div>

            <div class="pt-2 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
              <button
                @click="searchQuery = kol.author_handle; selectedPlatform = (kol.platform || 'all'); activeTab = 'radar'; handleFilter()"
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

    <!-- TAB 04: TREN TAGAR & KERAWANAN SIBER (DUAL-MODE ARCHITECTURE) -->
    <div v-if="activeTab === 'trends'" class="space-y-5">
      <!-- DUAL-MODE LENS SWITCHER -->
      <div class="p-2 rounded-2xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 grid grid-cols-1 md:grid-cols-2 gap-2 shadow-sm">
        <!-- MODE 1: LENSA KAMTIBMAS POLRI -->
        <button
          type="button"
          @click="trendLensMode = 'kamtibmas'"
          class="flex items-start gap-3.5 p-3.5 rounded-xl transition text-left cursor-pointer border-2"
          :class="trendLensMode === 'kamtibmas'
            ? 'bg-amber-400/10 dark:bg-amber-400/10 border-amber-400 text-slate-900 dark:text-white shadow-sm'
            : 'hover:bg-slate-50 dark:hover:bg-slate-900 text-slate-600 dark:text-slate-400 border-transparent'"
        >
          <div
            class="p-2 rounded-xl shrink-0 mt-0.5"
            :class="trendLensMode === 'kamtibmas' ? 'bg-amber-400 text-slate-950 font-black shadow' : 'bg-slate-100 dark:bg-slate-800 text-slate-500'"
          >
            <Shield class="w-5 h-5" />
          </div>
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-xs font-black tracking-wider uppercase">01. Lensa Kamtibmas & Humas Polri</span>
              <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-400 text-slate-950">Fokus Dinas</span>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
              Monitoring tagar kepolisian, cyber patrol, penegakan hukum, citra Polri, dan isu viral kewilayahan/nasional.
            </p>
          </div>
        </button>

        <!-- MODE 2: LENSA TREN PUBLIK MEDIA SOSIAL INDONESIA -->
        <button
          type="button"
          @click="trendLensMode = 'public'"
          class="flex items-start gap-3.5 p-3.5 rounded-xl transition text-left cursor-pointer border-2"
          :class="trendLensMode === 'public'
            ? 'bg-rose-500/10 dark:bg-rose-500/10 border-rose-500 text-slate-900 dark:text-white shadow-sm'
            : 'hover:bg-slate-50 dark:hover:bg-slate-900 text-slate-600 dark:text-slate-400 border-transparent'"
        >
          <div
            class="p-2 rounded-xl shrink-0 mt-0.5"
            :class="trendLensMode === 'public' ? 'bg-rose-500 text-white font-black shadow' : 'bg-slate-100 dark:bg-slate-800 text-slate-500'"
          >
            <Flame class="w-5 h-5" />
          </div>
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-xs font-black tracking-wider uppercase">02. Lensa Tren Publik Medsos Indonesia</span>
              <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500 text-white animate-pulse">Live Multi-Platform</span>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
              Tren warganet Indonesia (𝕏, YouTube, Instagram, TikTok, Facebook) dengan deteksi kerawanan siber & EWS.
            </p>
          </div>
        </button>
      </div>

      <!-- VIEW 1: LENSA KAMTIBMAS & HUMAS POLRI -->
      <div v-if="trendLensMode === 'kamtibmas'" class="space-y-4">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Leaderboard Tagar Viral -->
        <div class="lg:col-span-2 rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-4">
          <div class="border-b border-slate-200 dark:border-slate-800 pb-3">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
              <div>
                <h2 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                  <Hash class="w-5 h-5 text-amber-500" />
                  <span>{{ trendingTitle }}</span>
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                  {{ trendingSubtitle }}
                </p>
              </div>

              <!-- Platform Filter Pills for Trending Tab -->
              <div class="flex items-center gap-1.5 overflow-x-auto pb-1 no-scrollbar shrink-0">
                <button
                  v-for="pTab in trendPlatformTabs"
                  :key="pTab.id"
                  type="button"
                  @click="selectedTrendPlatform = pTab.id"
                  class="px-2.5 py-1 rounded-xl text-xs font-bold transition flex items-center gap-1 whitespace-nowrap cursor-pointer select-none"
                  :class="selectedTrendPlatform === pTab.id
                    ? 'bg-amber-400 text-slate-950 font-black shadow-sm'
                    : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                >
                  <span>{{ pTab.label }}</span>
                </button>
              </div>
            </div>
          </div>

          <div class="space-y-2.5">
            <div
              v-for="(trend, idx) in currentTrendingList"
              :key="idx"
              class="flex items-center justify-between p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 hover:border-amber-400 transition"
            >
              <div class="flex items-center gap-3">
                <span class="w-6 h-6 rounded-lg bg-amber-400/20 text-amber-600 dark:text-amber-400 flex items-center justify-center font-black text-xs shrink-0">
                  {{ idx + 1 }}
                </span>
                <div>
                  <h4
                    @click="filterByTrend(trend)"
                    class="font-black text-slate-900 dark:text-white text-xs hover:text-amber-500 cursor-pointer transition"
                  >
                    {{ trend.tag }}
                  </h4>
                  <p class="text-[10px] text-slate-500 dark:text-slate-400 font-mono flex items-center gap-2 mt-0.5 flex-wrap">
                    <span>{{ formatNumber(trend.count) }} Volume/Jangkauan</span>
                    <span v-if="trend.posts_count" class="text-amber-600 dark:text-amber-400 font-bold">&bull; {{ trend.posts_count }} Postingan</span>
                    <span>&bull;</span>
                    <span
                      class="px-1.5 py-0.2 rounded text-[9px] font-bold"
                      :class="getPlatformBadge(trend.platform).class"
                    >
                      {{ getPlatformBadge(trend.platform).label }}
                    </span>
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
                  @click="filterByTrend(trend)"
                  type="button"
                  class="p-1.5 rounded-lg bg-slate-100 hover:bg-amber-400 hover:text-slate-950 dark:bg-slate-800 dark:hover:bg-amber-400 dark:hover:text-slate-950 text-slate-700 dark:text-slate-300 transition cursor-pointer"
                  title="Filter postingan dengan tagar ini di Radar"
                >
                  <Search class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>

            <!-- Empty State for current trending list -->
            <div
              v-if="!currentTrendingList || currentTrendingList.length === 0"
              class="p-8 text-center text-xs text-slate-500 dark:text-slate-400 space-y-2"
            >
              <Hash class="w-6 h-6 mx-auto text-slate-400 opacity-50" />
              <p>Belum ada aktivitas tagar khusus platform ini pada periode aktif.</p>
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
              Waktu {{ peakHours?.timezone || (isNational ? 'WIB' : 'WITA') }} paling aktif warganet {{ isNational ? 'nasional' : (selectedPolda?.name || 'wilayah') }}
            </p>
          </div>

          <div class="space-y-3 text-xs">
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
              <div class="flex items-center justify-between mb-0.5">
                <span class="text-[10px] text-slate-500 font-bold block">Puncak Siang:</span>
                <span class="text-[10px] font-mono text-amber-600 dark:text-amber-400 font-bold">{{ peakHours?.afternoon_pct || 34 }}% Trafik</span>
              </div>
              <p class="font-black text-slate-900 dark:text-white text-sm">{{ peakHours?.afternoon_window || (isNational ? '11.30 – 13.30 WIB' : '11.30 – 13.30 WITA') }}</p>
              <p class="text-[10px] text-slate-500">{{ peakHours?.afternoon_desc || 'Isu keluhan pelayanan & lalin siang hari' }}</p>
            </div>

            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
              <div class="flex items-center justify-between mb-0.5">
                <span class="text-[10px] text-slate-500 font-bold block">Puncak Malam:</span>
                <span class="text-[10px] font-mono text-amber-600 dark:text-amber-400 font-bold">{{ peakHours?.evening_pct || 48 }}% Trafik</span>
              </div>
              <p class="font-black text-amber-500 text-sm">{{ peakHours?.evening_window || (isNational ? '19.00 – 22.00 WIB' : '19.00 – 22.00 WITA') }}</p>
              <p class="text-[10px] text-slate-500">{{ peakHours?.evening_desc || 'Puncak viralisasi video TikTok & opini medsos X' }}</p>
            </div>

            <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-400/10 border border-amber-300 dark:border-amber-400/30 text-[11px] text-amber-900 dark:text-amber-200 space-y-1">
              <p class="font-bold">💡 Rekomendasi Jam Rilis Konter Narasi:</p>
              <p class="leading-relaxed">
                Rilis siaran pers atau infografis klarifikasi paling efektif didistribusikan pada pukul <strong>{{ peakHours?.recommended_release_siang || (isNational ? '12.00 WIB' : '12.00 WITA') }}</strong> atau <strong>{{ peakHours?.recommended_release_malam || (isNational ? '19.30 WIB' : '19.30 WITA') }}</strong> agar menjangkau {{ peakHours?.active_audience_pct || 70 }}% pengguna aktif.
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- VISUAL WORD CLOUD & TOPIC CLUSTERS (SEMANTIC ANALYSIS) -->
      <div class="rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-3">
          <div>
            <div class="flex items-center gap-2">
              <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-400 text-slate-950">
                Semantic NLP Engine
              </span>
              <span class="text-xs text-slate-500 dark:text-slate-400 font-mono">
                {{ (wordCloud?.words || []).length }} Topik Turunan Teridentifikasi
              </span>
            </div>
            <h3 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2 mt-1">
              <Cloud class="w-5 h-5 text-sky-500" />
              <span>Visual Word Cloud & Klaster Kata Terkait</span>
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
              Representasi visual kata/frasa yang paling sering muncul berdampingan dengan kata kunci kepolisian. Ukuran kata mencerminkan frekuensi penyebutan. <strong>Klik kata mana saja untuk memfilter percakapan di Radar secara instan.</strong>
            </p>
          </div>

          <!-- Sentiment Color Legend -->
          <div class="flex items-center gap-2 text-xs font-bold shrink-0">
            <span class="px-2 py-0.5 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 text-[10px]">
              🟢 Citra Positif
            </span>
            <span class="px-2 py-0.5 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30 text-[10px]">
              ⚪ Informasi Netral
            </span>
            <span class="px-2 py-0.5 rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/30 text-[10px]">
              🔴 Keluhan / Isu Negatif
            </span>
          </div>
        </div>

        <!-- Word Cloud Floating Tag Cloud -->
        <div class="p-6 rounded-2xl bg-slate-50/80 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 min-h-[220px] flex flex-wrap items-center justify-center gap-3 select-none">
          <button
            v-for="(w, wIdx) in (wordCloud?.words || [])"
            :key="wIdx"
            @click="filterByWord(w)"
            type="button"
            class="px-3.5 py-1.5 rounded-2xl font-black transition cursor-pointer border transform hover:scale-110 shadow-sm flex items-center gap-1.5 active:scale-95"
            :class="getWordStyle(w).classes"
            :style="{ fontSize: getWordStyle(w).fontSize }"
            :title="`Klik untuk memfilter: ${w.text} (${w.count} sebutan) — Sentimen Dominan: ${w.sentiment}`"
          >
            <span>{{ w.text }}</span>
            <span class="text-[9px] opacity-75 font-mono px-1.5 py-0.2 rounded-full bg-white/40 dark:bg-black/40">
              {{ w.count }}
            </span>
          </button>
        </div>
      </div>
      </div>

      <!-- VIEW 2: LENSA TREN PUBLIK MEDSOS INDONESIA (MULTI-PLATFORM) -->
      <div v-else-if="trendLensMode === 'public'" class="space-y-4">
        <!-- Header Tren Publik -->
        <div class="p-6 rounded-3xl bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950 text-white border border-slate-700/60 shadow-lg relative overflow-hidden">
          <div class="absolute -right-12 -top-12 w-48 h-48 bg-rose-500/10 rounded-full blur-2xl pointer-events-none"></div>
          <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 relative z-10">
            <div>
              <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-rose-500 text-white">
                  🇮🇩 WOEID: 23424846 (Indonesia)
                </span>
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-white/10 text-slate-300">
                  Lintas 5 Platform (𝕏, YouTube, Instagram, TikTok, Facebook)
                </span>
                <span class="text-[11px] text-slate-400">
                  Update: <strong class="text-amber-400">{{ publicTrends?.meta?.last_updated || 'Realtime' }}</strong>
                </span>
              </div>
              <h2 class="text-xl md:text-2xl font-black tracking-tight text-white flex items-center gap-2">
                <span>Tren Publik Media Sosial Indonesia</span>
                <Flame class="w-6 h-6 text-rose-500 shrink-0" />
              </h2>
              <p class="text-xs text-slate-300 mt-1 max-w-3xl leading-relaxed">
                Pemantauan tren warganet Indonesia secara holistik. Menangkap fenomena percakapan viral di ruang publik sekaligus menyaring potensi kejahatan siber, phishing, disinformasi, dan kerawanan kamtibmas sebelum meluas.
              </p>
            </div>

            <div class="flex items-center gap-2.5 shrink-0">
              <button
                type="button"
                @click="triggerSyncTrends"
                :disabled="isSyncingTrends"
                class="px-4 py-2.5 rounded-xl text-xs font-bold bg-amber-400 hover:bg-amber-300 text-slate-950 transition shadow flex items-center gap-2 disabled:opacity-50 cursor-pointer"
              >
                <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': isSyncingTrends }" />
                <span>{{ isSyncingTrends ? 'Memperbarui Tren...' : '⚡ Refresh Tren Publik (Apify)' }}</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="p-4 rounded-2xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3 shadow-sm">
          <!-- Platform Tabs -->
          <div class="flex items-center gap-1.5 overflow-x-auto pb-1 lg:pb-0">
            <button
              v-for="p in publicPlatformTabs"
              :key="p.id"
              @click="selectedPublicPlatform = p.id"
              type="button"
              class="px-3 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer select-none whitespace-nowrap"
              :class="selectedPublicPlatform === p.id
                ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-950 font-black shadow'
                : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
            >
              {{ p.label }}
              <span class="ml-1 opacity-70 text-[10px]">
                ({{ (publicTrends?.platforms?.[p.id] || []).length }})
              </span>
            </button>
          </div>

          <!-- Right Controls: Search & Security Only Toggle -->
          <div class="flex flex-wrap items-center gap-2">
            <!-- Search -->
            <div class="relative min-w-[220px] flex-1 sm:flex-initial">
              <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5 pointer-events-none" />
              <input
                v-model="searchTrendQuery"
                type="text"
                placeholder="Cari topik / isu / catatan..."
                class="w-full pl-8 pr-3 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-semibold text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-amber-400"
              />
            </div>

            <!-- Security Filter Toggle -->
            <button
              type="button"
              @click="securityOnly = !securityOnly"
              class="px-3 py-1.5 rounded-xl text-xs font-bold transition border cursor-pointer select-none flex items-center gap-1.5"
              :class="securityOnly
                ? 'bg-rose-500 text-white font-black border-rose-500 shadow-sm'
                : 'bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-700 hover:bg-slate-100'"
            >
              <AlertTriangle class="w-3.5 h-3.5" :class="securityOnly ? 'text-white' : 'text-rose-500'" />
              <span>🚨 Relevan Kamtibmas Saja</span>
              <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="securityOnly ? 'bg-white/20 text-white' : 'bg-rose-500/20 text-rose-500 font-bold'">
                {{ securityRelevantPublicCount }}
              </span>
            </button>
          </div>
        </div>

        <!-- Trends Grid List -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div
            v-for="(item, idx) in currentPublicTrendsList"
            :key="idx"
            class="rounded-3xl bg-white dark:bg-[#0b1426] border transition p-5 shadow-sm space-y-3 relative flex flex-col justify-between"
            :class="item.is_security_relevant
              ? 'border-rose-400/80 dark:border-rose-900/80 ring-1 ring-rose-400/20'
              : 'border-slate-200 dark:border-slate-800 hover:border-amber-400/70'"
          >
            <div>
              <!-- Top Row: Rank, Platform Badge, Category, Velocity -->
              <div class="flex items-center justify-between gap-2 mb-2 flex-wrap">
                <div class="flex items-center gap-2">
                  <!-- Rank Badge -->
                  <span
                    class="w-6 h-6 rounded-lg flex items-center justify-center font-black text-xs shrink-0"
                    :class="item.rank === 1
                      ? 'bg-amber-400 text-slate-950 shadow-sm'
                      : item.rank === 2
                      ? 'bg-slate-300 text-slate-900'
                      : item.rank === 3
                      ? 'bg-amber-700 text-white'
                      : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300'"
                  >
                    #{{ item.rank }}
                  </span>

                  <!-- Platform Pill -->
                  <span class="px-2 py-0.5 rounded text-[10px] font-black" :class="getPublicPlatformBadge(item.platform).class">
                    {{ getPublicPlatformBadge(item.platform).label }}
                  </span>

                  <!-- Category -->
                  <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400">
                    {{ item.category }}
                  </span>
                </div>

                <!-- Velocity / Speed -->
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-mono">
                  {{ item.velocity }}
                </span>
              </div>

              <!-- Trend Name / Title -->
              <div class="space-y-1">
                <h3
                  @click="filterByPublicTrend(item)"
                  class="font-black text-slate-900 dark:text-white text-sm hover:text-amber-500 cursor-pointer transition leading-snug"
                >
                  {{ item.name }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 font-mono">
                  Volume / Penayangan: <strong class="text-slate-900 dark:text-slate-200">{{ item.volume }}</strong>
                </p>
              </div>

              <!-- Security Relevance Banner (Atensi Intelijen Siber / Kamtibmas) -->
              <div
                v-if="item.is_security_relevant"
                class="mt-3 p-3 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-900 dark:text-rose-200 space-y-1 text-xs"
              >
                <div class="flex items-center gap-1.5 font-black text-rose-600 dark:text-rose-400 text-[11px]">
                  <AlertTriangle class="w-3.5 h-3.5 shrink-0" />
                  <span>ATENSI KAMTIBMAS & SIBER: {{ item.relevance_type }}</span>
                </div>
                <p class="text-[11px] leading-relaxed text-slate-700 dark:text-slate-300">
                  {{ item.relevance_note }}
                </p>
              </div>
              <div
                v-else
                class="mt-3 p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 text-[11px] leading-relaxed"
              >
                <span class="font-bold text-slate-800 dark:text-slate-200">Konteks Isu:</span> {{ item.relevance_note }}
              </div>
            </div>

            <!-- Action Buttons Footer -->
            <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between gap-2 mt-2">
              <div class="flex items-center gap-1.5">
                <!-- Direct 1-Click Escalation -->
                <Link
                  :href="getEscalationUrl(item)"
                  class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-[11px] font-black bg-rose-500 hover:bg-rose-600 text-white transition shadow-sm"
                  title="Eskalasi langsung topik tren ini ke formulir Laporan Isu Baru"
                >
                  <AlertTriangle class="w-3 h-3" />
                  <span>🚨 Eskalasi Jadi Isu</span>
                </Link>

                <!-- Filter in Radar Tab -->
                <button
                  type="button"
                  @click="filterByPublicTrend(item)"
                  class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-[11px] font-bold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition"
                  title="Filter postingan dengan kata kunci ini di Tab 01 Radar"
                >
                  <Search class="w-3 h-3 text-slate-500" />
                  <span>Pantau di Radar</span>
                </button>
              </div>

              <!-- Open Source URL -->
              <a
                v-if="item.sample_url"
                :href="item.sample_url"
                target="_blank"
                rel="noopener noreferrer"
                class="p-1.5 rounded-lg bg-slate-100 hover:bg-amber-400 hover:text-slate-950 dark:bg-slate-800 dark:hover:bg-amber-400 dark:hover:text-slate-950 text-slate-500 transition"
                title="Buka topik di platform aslinya"
              >
                <ExternalLink class="w-3.5 h-3.5" />
              </a>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div
          v-if="!currentPublicTrendsList || currentPublicTrendsList.length === 0"
          class="p-12 text-center text-xs text-slate-500 dark:text-slate-400 space-y-2 rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800"
        >
          <Flame class="w-8 h-8 mx-auto text-slate-400 opacity-50" />
          <p class="font-bold text-slate-700 dark:text-slate-300">Tidak ada tren publik yang sesuai dengan filter.</p>
          <p class="text-[11px]">Coba ubah kata kunci pencarian atau matikan filter "Relevan Kamtibmas Saja".</p>
        </div>
      </div>
    </div>

    <!-- TAB 04: KONFIGURASI APIFY & KATA KUNCI (ADMIN ONLY - HIDDEN FROM OPERATOR) -->
    <div v-if="false" class="space-y-4">
      <div class="max-w-3xl rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 p-6 md:p-8 shadow-sm space-y-6">
        <div class="border-b border-slate-200 dark:border-slate-800 pb-4">
          <div class="flex items-center gap-2">
            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-500 text-white">
              Apify Free Tier Ready
            </span>
            <h2 class="text-base font-black text-slate-900 dark:text-white">
              {{ isNational ? 'Pengaturan Integrasi Apify Scraper Nasional (Divhumas Polri)' : 'Pengaturan Integrasi Apify Scraper & Kata Kunci Pelacakan' }}
            </h2>
          </div>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
            {{ isNational
              ? 'Konfigurasi crawler skala nasional untuk pemantauan citra Polri, akun kedinasan pimpinan, dan narasi kamtibmas kewilayahan.'
              : 'Gunakan paket gratis Apify (gratis $5/bulan) untuk scraping data X, TikTok, Instagram, dan YouTube.'
            }}
          </p>
        </div>

        <form @submit.prevent="saveSettings" class="space-y-5 text-xs">
          <!-- Banner Pengelolaan Kredensial Apify Terpusat (Admin Governance) -->
          <div class="p-4 rounded-2xl bg-slate-100 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 space-y-3">
            <div class="flex items-start justify-between gap-3 flex-wrap">
              <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-amber-400/10 flex items-center justify-center shrink-0 border border-amber-400/30">
                  <Shield class="w-4 h-4 text-amber-500" />
                </div>
                <div>
                  <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-wider">
                    Kredensial API Apify Dikelola Tersentralisasi
                  </h3>
                  <p class="text-[11px] text-slate-500 dark:text-slate-400">
                    Untuk keamanan kredensial dinas dan rotasi kuota, Personal API Token Apify dikelola khusus oleh Administrator pada menu:
                    <strong class="text-slate-800 dark:text-slate-200"> Keamanan &amp; Pemeliharaan &rarr; Mesin Intelijen AI</strong>.
                  </p>
                </div>
              </div>

              <!-- Status Badge -->
              <span
                class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider shrink-0 border flex items-center gap-1.5"
                :class="(settings?.apify_api_token || settingsForm.apify_api_token) ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30' : 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/30'"
              >
                <span class="w-1.5 h-1.5 rounded-full" :class="(settings?.apify_api_token || settingsForm.apify_api_token) ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500'"></span>
                <span>{{ (settings?.apify_api_token || settingsForm.apify_api_token) ? 'Token Aktif & Terhubung' : 'Token Belum Diatur' }}</span>
              </span>
            </div>

            <div class="flex items-center justify-between text-[11px] pt-2 border-t border-slate-200 dark:border-slate-800 flex-wrap gap-2">
              <span class="text-slate-500 dark:text-slate-400 font-mono">
                Status Integrasi: <strong class="text-slate-700 dark:text-slate-300">{{ (settings?.apify_api_token || settingsForm.apify_api_token) ? 'Multi-API Failover Pool Siaga' : 'Memerlukan Konfigurasi Admin' }}</strong>
              </span>

              <Link
                href="/settings/ai"
                class="text-amber-600 dark:text-amber-400 font-bold hover:underline inline-flex items-center gap-1"
              >
                <span>Kelola Kredensial di Admin</span>
                <ExternalLink class="w-3 h-3" />
              </Link>
            </div>
          </div>

          <!-- Kata Kunci Pelacakan -->
          <div>
            <label class="block font-black text-slate-800 dark:text-slate-200 mb-1.5 uppercase tracking-wider text-[11px]">
              {{ isNational ? 'KATA KUNCI PEMANTAUAN NASIONAL (PISAHKAN DENGAN KOMA)' : 'KATA KUNCI PEMANTAUAN WILAYAH (PISAHKAN DENGAN KOMA)' }}
            </label>
            <textarea
              v-model="settingsForm.keywords_tracked"
              rows="2"
              class="w-full p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white font-medium placeholder-slate-400 focus:outline-none focus:border-amber-400 leading-relaxed"
            ></textarea>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
              {{ isNational
                ? 'Contoh: Divhumas Polri, Kapolri, Mabes Polri, Humas Polri, Bareskrim, Korlantas Polri, Propam Polri, Polisi Presisi, Dumas Presisi'
                : 'Contoh: Polda Sulbar, Polresta Mamuju, Polisi Sulbar, Polres Majene, Polres Polman, Lantas Sulbar'
              }}
            </p>
          </div>

          <!-- Akun Target Pantauan Khusus -->
          <div>
            <label class="block font-black text-slate-800 dark:text-slate-200 mb-1.5 uppercase tracking-wider text-[11px]">
              {{ isNational ? 'AKUN TARGET PANTAUAN NASIONAL (KANAL RESMI POLRI & PIMPINAN)' : 'AKUN TARGET PANTAUAN KHUSUS (PROFIL PUBLIK / INSTAGRAM / 𝕏 / TIKTOK / YOUTUBE)' }}
            </label>
            <textarea
              v-model="settingsForm.accounts_tracked"
              rows="2"
              :placeholder="isNational ? '@divisihumaspri, @humaspolri, @polripresisi, @halo_polisi, @ccicpolri, @bareskrim_polri' : '@poldasulbar, @polresta_mamuju_official, @tribratanews_sulbar, @sulbar_update'"
              class="w-full p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white font-mono placeholder-slate-400 focus:outline-none focus:border-amber-400 leading-relaxed"
            ></textarea>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
              {{ isNational
                ? 'Masukkan username akun publik nasional yang wajib dipantau secara berkala oleh mesin pencari crawler Divhumas Polri.'
                : 'Masukkan username akun publik yang wajib dipantau (pisahkan dengan koma). Crawler akan memprioritaskan penarikan dari akun-akun ini dan menandainya sebagai pantauan prioritas.'
              }}
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

      <!-- KATALOG FORMAT TREN & REKOMENDASI ACTOR APIFY PER PLATFORM -->
      <div class="max-w-4xl rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 p-6 md:p-8 shadow-sm space-y-6">
        <div class="border-b border-slate-200 dark:border-slate-800 pb-4">
          <div class="flex items-center gap-2">
            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-indigo-500 text-white">
              Arsitektur Multi-Platform
            </span>
            <h2 class="text-base font-black text-slate-900 dark:text-white">
              Format Tren yang Didukung & Rekomendasi Actor Apify per Platform
            </h2>
          </div>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
            Masing-masing media sosial memiliki format tren dan struktur data yang berbeda. Gunakan panduan resmi ini untuk memahami cara scraper Apify mengekstrak data tren dari masing-masing platform secara legal dan efisien.
          </p>
        </div>

        <div class="space-y-4">
          <div
            v-for="(actor, aIdx) in (supportedActors || [])"
            :key="aIdx"
            class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 space-y-3"
          >
            <!-- Platform Header -->
            <div class="flex items-center justify-between gap-2 flex-wrap">
              <div class="flex items-center gap-2.5">
                <span class="px-2.5 py-1 rounded-xl text-xs font-black" :class="actor.badge_class">
                  {{ actor.platform_label }}
                </span>
                <span class="text-xs font-black text-slate-900 dark:text-white">
                  {{ actor.trend_format }}
                </span>
              </div>
              <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                ✓ {{ actor.status_label }}
              </span>
            </div>

            <!-- Actors Recommendations -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 pt-1">
              <div
                v-for="(rec, rIdx) in actor.recommended_actors"
                :key="rIdx"
                class="p-3 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex flex-col justify-between"
              >
                <div>
                  <div class="flex items-center justify-between gap-1 mb-1">
                    <span class="font-mono font-bold text-xs text-amber-600 dark:text-amber-400">
                      {{ rec.id }}
                    </span>
                    <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                      {{ rec.badge }}
                    </span>
                  </div>
                  <p class="font-bold text-slate-900 dark:text-white text-xs">{{ rec.name }}</p>
                  <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                    {{ rec.description }}
                  </p>
                </div>
                <div class="pt-2 mt-2 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                  <span class="text-[10px] text-slate-400">Creator: @{{ rec.author }}</span>
                  <a
                    :href="rec.apify_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-[10px] font-bold text-sky-600 hover:text-sky-500 dark:text-sky-400 inline-flex items-center gap-1"
                  >
                    <span>Apify Store</span>
                    <ExternalLink class="w-3 h-3" />
                  </a>
                </div>
              </div>
            </div>

            <!-- Output Data Schema -->
            <div class="p-2.5 rounded-xl bg-slate-100 dark:bg-slate-800/60 text-[11px] text-slate-600 dark:text-slate-300 font-mono flex items-start gap-2">
              <span class="font-bold text-slate-900 dark:text-white shrink-0">Output Data:</span>
              <span class="break-words">{{ actor.output_data }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- MODAL ON-DEMAND KEYWORD TRACKER (CRAWL TOPIK BARU INSTAN) -->
    <div
      v-if="showTrackModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm animate-fade-in"
    >
      <div
        class="w-full max-w-lg rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 p-6 md:p-8 shadow-2xl space-y-6 relative"
      >
        <!-- Modal Header -->
        <div class="flex items-start justify-between gap-4 border-b border-slate-100 dark:border-slate-800 pb-4">
          <div>
            <div class="flex items-center gap-2">
              <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-gradient-to-r from-sky-500 to-indigo-600 text-white">
                Apify On-Demand Engine
              </span>
              <span class="text-xs text-slate-500 dark:text-slate-400 font-bold">Non-Blocking</span>
            </div>
            <h2 class="text-lg font-black text-slate-900 dark:text-white flex items-center gap-2 mt-1">
              <Sparkles class="w-5 h-5 text-amber-400" />
              <span>Lacak Kata Kunci / Topik Baru</span>
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 leading-relaxed">
              Tarik percakapan warganet secara langsung dari internet berdasarkan topik atau nama kasus mendesak yang ingin Anda amati.
            </p>
          </div>
          <button
            @click="showTrackModal = false"
            type="button"
            class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition"
          >
            <X class="w-4 h-4" />
          </button>
        </div>

        <form @submit.prevent="submitTrackKeyword" class="space-y-4 text-xs">
          <!-- Input Kata Kunci -->
          <div>
            <label class="block font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider text-[11px] mb-1.5">
              KATA KUNCI / TOPIK / NAMA KASUS
            </label>
            <div class="relative">
              <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-3 pointer-events-none" />
              <input
                v-model="trackForm.keyword"
                type="text"
                required
                placeholder="Contoh: razia knalpot brong, kebijakan tarif, demo mahasiswa"
                class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white font-bold placeholder-slate-400 focus:outline-none focus:border-amber-400 text-sm"
              />
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
              Crawler akan melacak percakapan publik, komentar, dan video seputar kata kunci ini.
            </p>
          </div>

          <!-- Platform Checkbox Pills -->
          <div>
            <label class="block font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider text-[11px] mb-1.5">
              PILIH PLATFORM TARGET
            </label>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="plat in [
                  { id: 'x', label: '𝕏 (Twitter)' },
                  { id: 'tiktok', label: 'TikTok' },
                  { id: 'instagram', label: 'Instagram' },
                  { id: 'youtube', label: 'YouTube' },
                  { id: 'facebook', label: 'Facebook' },
                ]"
                :key="plat.id"
                type="button"
                @click="toggleTrackPlatform(plat.id)"
                class="px-3 py-1.5 rounded-xl font-bold transition flex items-center gap-1.5 cursor-pointer text-xs"
                :class="trackForm.platforms.includes(plat.id)
                  ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-950 font-black shadow'
                  : 'bg-slate-100 dark:bg-slate-800 text-slate-500 border border-slate-200 dark:border-slate-700'"
              >
                <span>{{ plat.label }}</span>
                <Check v-if="trackForm.platforms.includes(plat.id)" class="w-3.5 h-3.5 text-amber-400 dark:text-amber-600" />
              </button>
            </div>
          </div>

          <!-- Target Kedalaman Scraping -->
          <div>
            <label class="block font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider text-[11px] mb-1.5">
              KEDALAMAN DATA TARGET
            </label>
            <div class="grid grid-cols-3 gap-2">
              <button
                v-for="opt in [
                  { val: 20, label: '20 Postingan', desc: 'Kilat (< 10 detik)' },
                  { val: 50, label: '50 Postingan', desc: 'Standar' },
                  { val: 100, label: '100 Postingan', desc: 'Komprehensif' },
                ]"
                :key="opt.val"
                type="button"
                @click="trackForm.max_items = opt.val"
                class="p-2.5 rounded-xl text-center transition border cursor-pointer"
                :class="trackForm.max_items === opt.val
                  ? 'bg-amber-400/15 border-amber-400 text-slate-950 dark:text-white font-black'
                  : 'bg-slate-50 dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400'"
              >
                <p class="font-bold text-xs">{{ opt.label }}</p>
                <p class="text-[10px] text-slate-400">{{ opt.desc }}</p>
              </button>
            </div>
          </div>

          <!-- Modal Action Buttons -->
          <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
            <button
              type="button"
              @click="showTrackModal = false"
              class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="isTrackingKeyword || !trackForm.keyword.trim()"
              class="px-6 py-2.5 rounded-xl text-xs font-black bg-gradient-to-r from-sky-500 to-indigo-600 hover:from-sky-400 hover:to-indigo-500 text-white shadow-lg shadow-sky-500/25 flex items-center gap-2 disabled:opacity-50 cursor-pointer"
            >
              <RefreshCw v-if="isTrackingKeyword" class="w-4 h-4 animate-spin text-amber-300" />
              <Sparkles v-else class="w-4 h-4 text-amber-300" />
              <span>{{ isTrackingKeyword ? 'Sedang Menghimpun Data...' : 'Mulai Lacak Topik Ini' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- DEDICATED PRINT CONTAINER UNTUK LAPORAN EKSEKUTIF MEDSOS A4               -->
    <!-- ========================================================================= -->
    <div id="print-a4-social" class="hidden print:block text-slate-900 bg-white p-6 font-sans">
      <!-- Kop Dokumen Resmi Polri -->
      <div class="flex items-center justify-between border-b-2 border-slate-900 pb-2 mb-3">
        <div class="flex items-center gap-3">
          <div class="w-14 h-14 rounded-full bg-slate-100 border border-slate-400 flex items-center justify-center p-1 shrink-0">
            <Shield class="w-8 h-8 text-amber-600 fill-amber-500" />
          </div>
          <div>
            <h4 class="text-[10px] font-black tracking-widest text-slate-800 uppercase">Kepolisian Negara Republik Indonesia</h4>
            <h2 class="text-xs font-black tracking-wider text-slate-950 uppercase">{{ isNational ? 'Divisi Humas Polri' : 'Bidang Humas Polda ' + (props.selectedPolda?.name || '').toUpperCase() }}</h2>
            <p class="text-[9px] text-slate-600 italic">Pusat Komando Analisis Media Sosial, Cyber Patrol &amp; Social Listening</p>
          </div>
        </div>
        <div class="text-right border-l-2 border-slate-900 pl-3">
          <span class="inline-block px-1.5 py-0.5 text-[8.5px] font-black uppercase tracking-wider bg-red-600 text-white rounded">
            RAHASIA / SEGERA
          </span>
          <p class="text-[8.5px] text-slate-500 mt-0.5 font-mono">DOC: SOC-INTEL/24H</p>
          <p class="text-[8.5px] text-slate-700 font-bold">{{ metrics?.server_wita || new Date().toLocaleDateString('id-ID') }}</p>
        </div>
      </div>

      <!-- Judul Lembar Laporan -->
      <div class="text-center py-1 bg-slate-100 border border-slate-300 rounded mb-3">
        <h1 class="text-[11px] font-black text-slate-950 uppercase tracking-widest">
          Laporan Eksekutif Intelijen Media Sosial &amp; Opini Warganet (SADEWA AI)
        </h1>
      </div>

      <!-- Seksi I: Ringkasan Eksekutif & Atensi Kritis -->
      <div class="mb-3 space-y-1">
        <div class="text-[10px] font-black uppercase text-slate-900 border-b border-slate-300 pb-0.5">
          I. Ringkasan Eksekutif Situasi Medsos &amp; Atensi Kritis Pimpinan
        </div>
        <p class="text-[9.5px] leading-relaxed text-slate-800 text-justify">
          {{ socialAiReport?.data?.executive_brief || 'Pemantauan komprehensif percakapan warganet lintas 5 platform media sosial (𝕏, TikTok, Instagram, YouTube, Facebook) menunjukkan arus opini publik berada pada koridor kondusif.' }}
        </p>
        <div class="p-2 rounded border border-rose-300 bg-rose-50 text-[9px] text-rose-900 font-medium">
          <strong class="text-rose-700 font-black">⚠️ ISU KRITIS PRIORITAS PIMPINAN:</strong> {{ socialAiReport?.data?.top_concern || 'Tidak ada anomali isu kritis mendesak.' }}
        </div>
      </div>

      <!-- Seksi II: Polarisasi Sentimen & Risiko Buzzer -->
      <div class="grid grid-cols-12 gap-2 mb-3">
        <div class="col-span-6 border border-slate-300 rounded p-2 bg-slate-50">
          <span class="text-[9.5px] font-black uppercase text-slate-900 block mb-1">Distribusi Sentimen Percakapan Medsos</span>
          <div class="space-y-1 text-[9px]">
            <div class="flex justify-between items-center font-bold">
              <span class="text-emerald-700">Positif:</span>
              <span class="font-black text-emerald-800">{{ socialAiReport?.data?.sentiment_analysis?.positif || metrics?.positive_pct || 0 }}%</span>
            </div>
            <div class="w-full bg-slate-200 h-1.5 rounded-full overflow-hidden">
              <div class="bg-emerald-600 h-full" :style="{ width: (socialAiReport?.data?.sentiment_analysis?.positif || metrics?.positive_pct || 0) + '%' }"></div>
            </div>
            <div class="flex justify-between items-center font-bold">
              <span class="text-slate-600">Netral:</span>
              <span class="font-black text-slate-700">{{ socialAiReport?.data?.sentiment_analysis?.netral || metrics?.neutral_pct || 0 }}%</span>
            </div>
            <div class="w-full bg-slate-200 h-1.5 rounded-full overflow-hidden">
              <div class="bg-slate-500 h-full" :style="{ width: (socialAiReport?.data?.sentiment_analysis?.netral || metrics?.neutral_pct || 0) + '%' }"></div>
            </div>
            <div class="flex justify-between items-center font-bold">
              <span class="text-red-700">Negatif:</span>
              <span class="font-black text-red-800">{{ socialAiReport?.data?.sentiment_analysis?.negatif || metrics?.negative_pct || 0 }}%</span>
            </div>
            <div class="w-full bg-slate-200 h-1.5 rounded-full overflow-hidden">
              <div class="bg-red-600 h-full" :style="{ width: (socialAiReport?.data?.sentiment_analysis?.negatif || metrics?.negative_pct || 0) + '%' }"></div>
            </div>
          </div>
        </div>

        <div class="col-span-6 border border-slate-300 rounded p-2 bg-slate-50 text-[9px] space-y-1">
          <div class="flex items-center justify-between font-bold text-slate-900 border-b border-slate-200 pb-1">
            <span>Deteksi Polarisasi Buzzer:</span>
            <span class="px-1.5 py-0.2 rounded font-black text-white" :class="socialAiReport?.data?.buzzer_vs_organic?.risk_level === 'tinggi' ? 'bg-red-600' : 'bg-emerald-600'">
              {{ (socialAiReport?.data?.buzzer_vs_organic?.risk_level || 'Rendah').toUpperCase() }}
            </span>
          </div>
          <p class="text-slate-800 leading-snug">{{ socialAiReport?.data?.buzzer_vs_organic?.assessment || 'Percakapan didominasi netizen organik masyarakat.' }}</p>
          <p class="text-slate-600 italic">{{ socialAiReport?.data?.buzzer_vs_organic?.risk_note || 'Tidak terdeteksi koordinasi terpusat tagar serempak.' }}</p>
        </div>
      </div>

      <!-- Seksi III: Rekomendasi Cooling System Humas -->
      <div class="mb-3 space-y-0.5">
        <div class="text-[10px] font-black uppercase text-slate-900 border-b border-slate-300 pb-0.5">
          II. Rekomendasi Cooling System &amp; Orkestrasi Tindakan Humas
        </div>
        <table class="w-full text-left text-[9px] border-collapse border border-slate-300">
          <thead>
            <tr class="bg-slate-100 text-slate-800">
              <th class="border border-slate-300 px-1.5 py-0.5 w-6 text-center">No</th>
              <th class="border border-slate-300 px-1.5 py-0.5">Langkah Taktis Komunikasi</th>
              <th class="border border-slate-300 px-1.5 py-0.5 w-24">Saluran Target</th>
              <th class="border border-slate-300 px-1.5 py-0.5 w-24">Waktu Eksekusi</th>
              <th class="border border-slate-300 px-1.5 py-0.5 w-28">PIC Penanggung Jawab</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(cs, idx) in (socialAiReport?.data?.cooling_system || [])" :key="'print-cs-' + idx" class="even:bg-slate-50">
              <td class="border border-slate-300 px-1.5 py-0.5 text-center font-bold">{{ idx + 1 }}</td>
              <td class="border border-slate-300 px-1.5 py-0.5 font-semibold text-slate-900">{{ cs.action }}</td>
              <td class="border border-slate-300 px-1.5 py-0.5 text-slate-600">{{ cs.channel }}</td>
              <td class="border border-slate-300 px-1.5 py-0.5 text-slate-600">{{ cs.timing }}</td>
              <td class="border border-slate-300 px-1.5 py-0.5 font-bold text-slate-800">{{ cs.pic || 'Tim Humas' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Seksi IV: Konter Opini & Narasi Tandingan -->
      <div class="mb-4 space-y-1">
        <div class="text-[10px] font-black uppercase text-slate-900 border-b border-slate-300 pb-0.5">
          III. Draf Konter Opini &amp; Pesan Kunci Materi Edukasi Publik
        </div>
        <div class="p-2 border border-slate-300 rounded bg-slate-50 text-[9px] text-slate-800 leading-snug">
          <strong class="text-slate-950 font-black block mb-0.5">DRAF KLARIFIKASI RESMI:</strong>
          {{ socialAiReport?.data?.counter_narrative?.objective_clarification || 'Polri mengedepankan transparansi dan penegakan hukum profesional dalam menjaga ketertiban ruang siber.' }}
        </div>
      </div>

      <!-- Kolom Tanda Tangan Resmi Pejabat Kehumasan -->
      <div class="grid grid-cols-2 pt-2 text-[9.5px]">
        <div class="text-slate-700">
          <p class="font-bold">Catatan Pimpinan / Disposisi:</p>
          <div class="h-14 border border-dashed border-slate-400 rounded mt-1 p-1 text-[8.5px] text-slate-400 italic">
            [Ruang paraf &amp; arahan tindak lanjut Kapolda / PJU]
          </div>
        </div>
        <div class="text-center">
          <p class="text-slate-600">{{ (isNational ? 'Jakarta' : 'Mamuju') }}, {{ new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) }}</p>
          <p class="font-black text-slate-950 uppercase mt-0.5">{{ isNational ? 'KEPALA DIVISI HUMAS POLRI' : 'KEPALA BIDANG HUMAS POLDA' }}</p>
          <div class="h-12 flex items-center justify-center">
            <span class="text-[8px] text-slate-400 italic">[Tertanda Sah Secara Digital Melalui SADEWA SIMOD]</span>
          </div>
          <p class="font-bold text-slate-900 underline">IRJEN POL. PEJABAT HUMAS POLRI</p>
        </div>
      </div>
    </div>
  </EnterpriseLayout>
</template>

