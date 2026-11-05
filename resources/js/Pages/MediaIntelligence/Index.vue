<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import axios from 'axios';
import AiQuotaBadge from '../../Components/AiQuotaBadge.vue';
import { router, usePage } from '@inertiajs/vue3';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import MediaSituationCard from '@/Components/MediaSituationCard.vue';
import {
  Shield,
  Building2,
  AlertTriangle,
  Flame,
  Radio,
  Newspaper,
  CheckCircle2,
  ExternalLink,
  ArrowRight,
  Hash,
  Bookmark,
  TrendingUp,
  MapPin,
  Sparkles,
  Info,
  Clock,
  Calendar,
  Plus,
  PlusCircle,
  X,
  Send,
  Zap,
  Award,
  FileText,
  Search,
  RotateCw,
  Copy,
  Globe,
  Trash2,
  Check,
  ChevronDown,
  ChevronUp,
  FileJson,
  Eye,
  EyeOff,
  MessageSquare,
  MessageCircle,
  Download,
  Maximize2,
  Minimize2,
  Layers,
  Bot,
  Cpu,
  Settings,
  Key,
  Pin,
  Tag,
  Edit3,
  ImageOff,
  Ellipsis,
  SlidersHorizontal,
  Volume2,
  VolumeX,
  RotateCcw,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  earlyWarnings: Array,
  mediaFeeds: Array,
  sampleArticles: Array,
  topics: { type: Array, default: () => [] },
  articlesMeta: { type: Object, default: () => ({ total: 0, page: 1, last_page: 1, per_page: 15, from: 0, to: 0 }) },
  searchTerms: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  facets: { type: Object, default: () => ({ all: 0, portals: [], internet: 0, categories: [], sentiments: { positif: 0, netral: 0, negatif: 0 }, bookmarks: 0, wilayah: [] }) },
  stats: { type: Object, default: () => ({}) },
  freshness: { type: Object, default: () => ({}) },
  situation: { type: Object, default: null },
  isNational: { type: Boolean, default: false },
  myArea: { type: Object, default: null },
  report: Object,
  aiPromptTemplate: String,
  aiSetting: Object,
  issues: Array,
  pressReleases: Array,
  suggestedTopics: { type: Array, default: () => [] },
  trend7: { type: Array, default: () => [] },
  metrics: Object,
});

// Tenant Detection: Divhumas (Mabes Polri) vs Polda Wilayah
const isDivhumas = computed(() => props.selectedCode === 'ALL' || props.selectedPolda?.code === 'DIVHUMAS');

const sentimentPositive = computed(() => {
  const r = props.report || {};
  const v = isDivhumas.value ? r.sentiment_national?.positif : (r.sentiment_satwil?.positif ?? r.sentiment_sulbar?.positif);
  return v === undefined || v === null ? null : v;
});

// Peran & izin pengguna (menentukan tombol yang tampil dan tab awal)
const page = usePage();
const authRole = computed(() => page.props.auth?.role || '');
const canRunAi = computed(() => !!page.props.auth?.permissions?.media_intelligence_ai);
const isOperator = computed(() => authRole.value === 'operator');

// Active Navigation Tab (01-04): pimpinan & Polda mendarat di Briefing, operator di daftar berita
const activeTab = ref(['kadiv_humas', 'polda_leader'].includes(page.props.auth?.role) ? '02' : '01');

// Menu melayang (header, filter, aksi kartu) -- hanya satu terbuka pada satu waktu
const activeFilterCount = computed(() => (selectedPortal.value !== 'Semua Portal' ? 1 : 0)
  + (selectedCategory.value !== 'all' ? 1 : 0)
  + (periodFilter.value !== 'all' ? 1 : 0)
  + (selectedSentiment.value !== 'all' ? 1 : 0)
  + (wilayahFilter.value ? 1 : 0)
  + (!groupDuplicates.value ? 1 : 0));
const openMenu = ref(null);
const showFilterPanel = ref(false);
const dossierOpen = ref(false);
const closeFloating = () => {
  openMenu.value = null;
  showFilterPanel.value = false;
};
onMounted(() => document.addEventListener('click', closeFloating));
onUnmounted(() => document.removeEventListener('click', closeFloating));

// Sparkline 7 hari & selisih terhadap kemarin (KPI strip)
const sparkPoints = (key) => {
  const d = props.trend7 || [];
  if (d.length < 2) return '';
  const max = Math.max(...d.map((x) => x[key] || 0), 1);
  return d.map((x, i) => ((i / (d.length - 1)) * 100).toFixed(1) + ',' + (26 - ((x[key] || 0) / max) * 24).toFixed(1)).join(' ');
};
const trendDelta = (key) => {
  const d = props.trend7 || [];
  if (d.length < 2) return null;
  return (d[d.length - 1][key] || 0) - (d[d.length - 2][key] || 0);
};
const deltaLabel = (key) => {
  const v = trendDelta(key);
  if (v === null) return '';
  if (v === 0) return '\u2014 sama dengan kemarin';
  return (v > 0 ? '\u25B2 +' : '\u25BC ') + v + ' vs kemarin';
};

const allTabs = [
  { id: '01', name: '01. Berita Online', icon: Radio },
  { id: '02', name: '02. Executive Briefing & Sentimen', badge: 'ANALISIS', icon: Newspaper },
  { id: '03', name: '03. Early Warning System (EWS)', badge: 'EWS', icon: Shield },
  { id: '04', name: '04. Strategi Humas & Aksi', badge: 'AKSI', icon: Award },
];

// Operator (tanpa izin analisis AI) hanya memerlukan daftar berita; tab analisis disembunyikan.
const isLeader = computed(() => ['kadiv_humas', 'polda_leader'].includes(authRole.value));
const canManageTopics = computed(() => !!page.props.auth?.permissions?.media_intelligence_manage);
const showAnalysisTabs = computed(() => canRunAi.value || isLeader.value);

const tabs = computed(() => {
  const list = showAnalysisTabs.value ? allTabs : [allTabs[0]];
  const badge = freshState.value.level === 'green' ? 'LIVE' : (freshState.value.level === 'yellow' ? 'TERTUNDA' : 'BASI');

  return list.map((t) => (t.id === '01' ? { ...t, badge } : t));
});

watch(activeTab, (v) => {
  if (!tabs.value.some((t) => t.id === v)) activeTab.value = '01';
});

// Kesegaran data: hijau < 1 jam, kuning < 6 jam, merah lebih lama / belum pernah sinkron.
const nowTick = ref(Date.now());
let nowTimer = null;
onMounted(() => {
  nowTimer = setInterval(() => { nowTick.value = Date.now(); }, 30000);
});
onUnmounted(() => clearInterval(nowTimer));

const agoLabel = (minutes) => {
  if (minutes < 1) return 'baru saja';
  if (minutes < 60) return minutes + ' menit lalu';
  if (minutes < 1440) return Math.floor(minutes / 60) + ' jam lalu';
  return Math.floor(minutes / 1440) + ' hari lalu';
};

const freshState = computed(() => {
  const at = props.freshness?.updated_at ? Date.parse(props.freshness.updated_at) : NaN;
  if (Number.isNaN(at)) {
    return { level: 'red', label: 'Belum pernah disinkronkan', hint: 'Tekan "Sinkron Berita"' };
  }
  const minutes = Math.max(0, Math.floor((nowTick.value - at) / 60000));
  const level = minutes < 60 ? 'green' : (minutes < 360 ? 'yellow' : 'red');
  const newest = props.freshness?.newest_article_ago;
  const hint = level === 'yellow'
    ? (newest ? 'Berita terbaru terbit ' + newest : 'Sinkron belum terbaru')
    : 'Data tertinggal: tekan "Sinkron Berita" atau periksa penjadwal';

  return { level, minutes, label: 'Diperbarui ' + agoLabel(minutes), hint };
});

const freshClass = computed(() => ({
  green: { text: 'text-emerald-400', dot: 'bg-emerald-400' },
  yellow: { text: 'text-amber-300', dot: 'bg-amber-400' },
  red: { text: 'text-rose-400', dot: 'bg-rose-500' },
}[freshState.value.level]));

const portalsActive = computed(() => props.stats?.portals_active ?? props.mediaFeeds?.length ?? 0);

const deltaText = (cur, prev) => {
  const c = Number(cur) || 0;
  const p = Number(prev) || 0;
  if (!c && !p) return 'belum ada berita';
  if (!p) return 'tanpa pembanding';
  const d = c - p;
  if (d === 0) return '\u2014 sama dengan 24 jam sebelumnya';
  return (d > 0 ? '\u25B2 +' : '\u25BC ') + d + ' vs 24 jam sebelumnya';
};

// Opsi dropdown berasal dari facet server: setiap angka dihitung dengan seluruh filter aktif kecuali dirinya sendiri,
// sehingga angka di dropdown = jumlah kartu yang tampil saat opsi itu dipilih (paritas UI 100%).
const availableCategories = computed(() => props.facets?.categories || []);
const categoryTotal = computed(() => availableCategories.value.reduce((n, c) => n + (c.count || 0), 0));

const availablePortals = computed(() => {
  const list = [...(props.facets?.portals || [])];
  if ((props.facets?.internet || 0) > 0) {
    list.unshift({ name: '🌐 SEBARAN INTERNET (GOOGLE NEWS)', count: props.facets.internet });
  }
  return list;
});

const allCount = computed(() => {
  const s = props.facets?.sentiments || {};
  return (s.positif || 0) + (s.netral || 0) + (s.negatif || 0);
});

// Bookmark disimpan di database (tidak ikut dibersihkan oleh Pembersihan Berita)
const bookmarkedIds = ref([]);
// Setiap halaman hasil dari server menyelaraskan status bookmark artikel yang tampil.
watch(() => props.sampleArticles, (list) => {
  const set = new Set(bookmarkedIds.value);
  for (const a of list || []) {
    if (a.is_bookmarked) set.add(String(a.id)); else set.delete(String(a.id));
  }
  bookmarkedIds.value = [...set];
}, { immediate: true });
const isBookmarked = (id) => bookmarkedIds.value.includes(String(id));

// Impor sekali bookmark lama dari localStorage perangkat ini
onMounted(async () => {
  try {
    const legacy = JSON.parse(localStorage.getItem('simod_ent_bookmarks') || '[]');
    if (legacy.length) {
      await axios.post('/media-intelligence/articles/bookmarks/import', { ids: legacy });
      legacy.forEach((id) => {
        if (!bookmarkedIds.value.includes(String(id))) bookmarkedIds.value.push(String(id));
      });
      localStorage.removeItem('simod_ent_bookmarks');
    }
  } catch (e) {
    console.error('Impor bookmark gagal:', e);
  }
});

const switchTenant = (code) => {
  router.post('/tenant/switch', { code }, { preserveScroll: true });
};

const toggleBookmark = async (id) => {
  const key = String(id);
  const wasOn = bookmarkedIds.value.includes(key);
  bookmarkedIds.value = wasOn ? bookmarkedIds.value.filter((i) => i !== key) : [...bookmarkedIds.value, key];
  try {
    await axios.post(`/media-intelligence/articles/${key}/bookmark`);
    router.reload({ only: ['stats', 'facets'], preserveScroll: true });
    showToastNotification(wasOn ? 'Berita dihapus dari Bookmark Tersimpan' : 'Berita disimpan ke Bookmark (aman dari pembersihan)');
  } catch (e) {
    bookmarkedIds.value = wasOn ? [...bookmarkedIds.value, key] : bookmarkedIds.value.filter((i) => i !== key);
    showToastNotification('Gagal menyimpan bookmark, coba lagi');
  }
};
// Dynamic Rows / Tampilan Baris Berita (Grid 3 Kolom per Baris)
const savedRows = typeof window !== 'undefined' ? parseInt(localStorage.getItem('simod_news_rows') || '5', 10) : 5;
const selectedRows = ref([3, 5, 8, 10, 15, 17].includes(savedRows) ? savedRows : 5);

const onRowsChange = () => {
  selectedRows.value = Number(selectedRows.value);
  if (typeof window !== 'undefined') {
    localStorage.setItem('simod_news_rows', String(selectedRows.value));
  }
  currentPage.value = 1;
};

// Pagination State
const currentPage = ref(1);
const perPage = computed(() => {
  const num = Number(selectedRows.value) || 5;
  if (num === 17) return 50; // maks 50 berita
  return num * 3;
});
const handleSearch = () => {
  searchQuery.value = searchInput.value.trim();
  currentPage.value = 1;
};

// Image Validation & Placeholder Logic (Tidak Memaksakan Gambar Stok)
const brokenImages = ref(new Set());

const hasValidImage = (article) => {
  if (!article || !article.image_url) return false;
  if (brokenImages.value.has(article.id)) return false;
  const url = String(article.image_url).trim();
  if (url === '' || url.includes('unsplash.com')) return false;
  return true;
};

const handleImageError = (article) => {
  if (article?.id) {
    brokenImages.value.add(article.id);
  }
};

const getCleanSummary = (article) => {
  let s = article.summary || '';
  s = s.replace(/[\u00a0\s]+/g, ' ').trim();
  if (article.source_name) {
    const srcRegex = new RegExp('\\s*' + article.source_name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\s*$', 'i');
    s = s.replace(srcRegex, '').trim();
  }
  if (!s || s.length < 25 || s.toLowerCase() === (article.title || '').toLowerCase()) {
    return `Informasi siaran pers media dari portal ${article.source_name || 'media online'} terkait dinamika pemantauan "${article.title}".`;
  }
  return s;
};

// Smart Thematic Press Cover Palette & Authority Emblem Resolver (0 Lag)
const getThematicCover = (article) => {
  const cat = (article?.category || article?.category_badge || '').toLowerCase();
  const sent = (article?.sentiment || '').toLowerCase();

  if (cat.includes('hukum') || cat.includes('kriminal') || cat.includes('pidana') || sent === 'negatif') {
    return {
      gradient: 'from-[#1c080e] via-[#101222] to-[#070b16]',
      glow: 'bg-rose-500/20',
      badgeBorder: 'border-rose-500/40 text-rose-300 bg-rose-950/70',
      tagText: 'GAKKUM & RESKRIM',
      icon: Shield,
      accentColor: 'text-rose-400',
    };
  }

  if (cat.includes('lantas') || cat.includes('lalu lintas')) {
    return {
      gradient: 'from-[#1c1103] via-[#12121f] to-[#080d18]',
      glow: 'bg-amber-500/20',
      badgeBorder: 'border-amber-500/40 text-amber-300 bg-amber-950/70',
      tagText: 'KEAMANAN LANTAS',
      icon: TrendingUp,
      accentColor: 'text-amber-400',
    };
  }

  if (cat.includes('siber') || cat.includes('teknologi')) {
    return {
      gradient: 'from-[#061824] via-[#091224] to-[#050b14]',
      glow: 'bg-cyan-500/20',
      badgeBorder: 'border-cyan-500/40 text-cyan-300 bg-cyan-950/70',
      tagText: 'PATROLI SIBER',
      icon: Cpu,
      accentColor: 'text-cyan-400',
    };
  }

  // Default Harkamtibmas / Nasional
  return {
    gradient: 'from-[#0a1630] via-[#081022] to-[#050a16]',
    glow: 'bg-blue-500/20',
    badgeBorder: 'border-blue-400/40 text-blue-300 bg-blue-950/70',
    tagText: 'HARKAMTIBMAS NASIONAL',
    icon: Newspaper,
    accentColor: 'text-blue-400',
  };
};

// Filter States: pencarian & penyaringan dijalankan di server atas seluruh arsip; state awal dibaca dari URL (props.filters).
const initialFilters = props.filters || {};
const selectedPortal = ref(initialFilters.portal || 'Semua Portal');
const selectedCategory = ref(initialFilters.category || 'all');
const showBookmarksOnly = ref(!!initialFilters.bookmarks);
const showRedZoneOnly = ref(initialFilters.sentiment === 'negatif');
const selectedSentiment = ref(['positif', 'netral'].includes(initialFilters.sentiment) ? initialFilters.sentiment : 'all');
const searchQuery = ref(initialFilters.q || '');
const searchInput = ref(initialFilters.q || '');
const periodFilter = ref(initialFilters.period || 'all');
const dateFrom = ref(initialFilters.from || '');
const dateTo = ref(initialFilters.to || '');
const sortMode = ref(initialFilters.sort || 'newest');
const wilayahFilter = ref(initialFilters.wilayah || 0);
const groupDuplicates = ref(initialFilters.group !== false);
const toastMessage = ref('');

// Jumlah berita negatif dalam konteks filter aktif (facet server) = isi tombol "Zona Merah".
const redZoneCount = computed(() => props.facets?.sentiments?.negatif ?? 0);

// Tampilan daftar: kartu (visual) atau daftar ringkas (analis memindai banyak judul sekaligus).
const viewMode = ref((() => {
  try { return localStorage.getItem('simod_news_view') === 'list' ? 'list' : 'cards'; } catch (e) { return 'cards'; }
})());
watch(viewMode, (v) => {
  try { localStorage.setItem('simod_news_view', v); } catch (e) { /* penyimpanan peramban tidak tersedia */ }
  if (v === 'list' && selectedRows.value < 10) {
    selectedRows.value = 10;
    onRowsChange();
  }
});

const resetAllFilters = () => {
  selectedPortal.value = 'Semua Portal';
  selectedCategory.value = 'all';
  showBookmarksOnly.value = false;
  showRedZoneOnly.value = false;
  selectedSentiment.value = 'all';
  searchQuery.value = '';
  searchInput.value = '';
  selectedTopicId.value = null;
  periodFilter.value = 'all';
  dateFrom.value = '';
  dateTo.value = '';
  sortMode.value = 'newest';
  wilayahFilter.value = 0;
  groupDuplicates.value = true;
  currentPage.value = 1;
};

const resetPanelFilters = () => {
  selectedPortal.value = 'Semua Portal';
  selectedCategory.value = 'all';
  selectedSentiment.value = 'all';
  periodFilter.value = 'all';
  dateFrom.value = '';
  dateTo.value = '';
  sortMode.value = 'newest';
  wilayahFilter.value = 0;
  groupDuplicates.value = true;
  currentPage.value = 1;
};

const periodLabel = computed(() => ({ today: 'Hari ini', '24h': '24 jam', '7d': '7 hari', '30d': '30 hari', custom: 'Rentang tanggal' }[periodFilter.value] || ''));
const setPeriod = (p) => {
  periodFilter.value = p;
  if (p !== 'custom') { dateFrom.value = ''; dateTo.value = ''; }
};

// Pintasan "Wilayah Saya" (Polres milik pengguna) memakai nama wilayah sebagai kata kunci pencarian.
const isMyAreaActive = computed(() => !!props.myArea && searchQuery.value.trim().toLowerCase() === props.myArea.keyword.toLowerCase());
const toggleMyArea = () => {
  if (!props.myArea) return;
  const next = isMyAreaActive.value ? '' : props.myArea.keyword;
  searchInput.value = next;
  searchQuery.value = next;
  currentPage.value = 1;
};

// Pencarian aktif dapat dijadikan Topik Atensi dalam satu klik (hanya untuk yang berhak mengelola topik).
const saveSearchAsTopic = () => {
  const terms = props.searchTerms || [];
  if (!terms.length) return;
  topicForm.value = {
    id: null,
    title: searchQuery.value.trim().slice(0, 80),
    keywords: terms.join(', '),
    description: '',
    priority: 'high',
    crawl_live: true,
  };
  isEditingTopic.value = false;
  showTopicModal.value = true;
};

// Sorot kata yang dicari tanpa v-html (aman dari XSS): teks dipecah menjadi potongan biasa & potongan sorot.
const highlightParts = (text) => {
  const value = String(text ?? '');
  const terms = (props.searchTerms || []).filter(Boolean);
  if (!terms.length || !value) return [{ t: value, hit: false }];
  const re = new RegExp('(' + terms.map((t) => t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|') + ')', 'gi');

  return value.split(re).map((t, i) => ({ t, hit: i % 2 === 1 })).filter((p) => p.t !== '');
};

// Tahap 2: AI Copilot Chatbot (SADEWA AI - Global Dispatch)
const openSadewaGlobal = (query = null) => {
  if (typeof window !== 'undefined') {
    window.dispatchEvent(new CustomEvent('open-sadewa-copilot', { detail: { query, scope: 'media' } }));
  }
};

// Smart Dossier & Topik Atensi Pimpinan States
const selectedTopicId = ref(props.filters?.topic || null);
watch(selectedTopicId, (v) => {
  if (v) dossierOpen.value = true;
});

// ---- Sinkron filter ke server (router.get parsial; URL ikut berubah sehingga tautan/refresh memulihkan filter) ----
const isListLoading = ref(false);
const queryParams = computed(() => {
  const p = {};
  const q = searchQuery.value.trim();
  if (q) p.q = q;
  if (periodFilter.value !== 'all') {
    p.period = periodFilter.value;
    if (periodFilter.value === 'custom') {
      if (dateFrom.value) p.from = dateFrom.value;
      if (dateTo.value) p.to = dateTo.value;
    }
  }
  if (sortMode.value === 'relevance' && q) p.sort = 'relevance';
  if (selectedPortal.value !== 'Semua Portal') p.portal = selectedPortal.value;
  if (selectedCategory.value !== 'all') p.category = selectedCategory.value;
  if (showRedZoneOnly.value) p.sentiment = 'negatif';
  else if (selectedSentiment.value !== 'all') p.sentiment = selectedSentiment.value;
  if (showBookmarksOnly.value) p.bookmarks = 1;
  if (selectedTopicId.value) p.topic = selectedTopicId.value;
  if (wilayahFilter.value) p.wilayah = wilayahFilter.value;
  if (!groupDuplicates.value) p.group = 0;
  if (currentPage.value > 1) p.page = currentPage.value;
  if (perPage.value !== 15) p.per_page = perPage.value;

  return p;
});

const LISTING_PROPS = ['sampleArticles', 'articlesMeta', 'searchTerms', 'filters', 'facets', 'stats', 'trend7', 'topics', 'freshness'];
const fetchListing = () => {
  isListLoading.value = true;
  router.get('/media-intelligence', queryParams.value, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
    only: LISTING_PROPS,
    onFinish: () => { isListLoading.value = false; },
  });
};

// Ganti filter apa pun -> kembali ke halaman 1 (urutan: reset halaman dahulu, lalu satu permintaan).
watch(
  [searchQuery, periodFilter, dateFrom, dateTo, sortMode, selectedPortal, selectedCategory, showRedZoneOnly,
    selectedSentiment, showBookmarksOnly, selectedTopicId, wilayahFilter, groupDuplicates, selectedRows],
  () => { currentPage.value = 1; }
);
watch(queryParams, () => fetchListing(), { flush: 'post' });
// Server memangkas halaman di luar jangkauan; selaraskan penunjuk halaman.
watch(() => props.articlesMeta?.page, (p) => {
  if (p && p !== currentPage.value) currentPage.value = p;
});
onMounted(() => {
  // Jumlah baris tersimpan di peramban dapat berbeda dari bawaan server (15).
  if (perPage.value !== (props.filters?.per_page ?? 15)) fetchListing();
});
const showTopicModal = ref(false);
const isEditingTopic = ref(false);
const isSubmittingTopic = ref(false);
const topicForm = ref({
  id: null,
  title: '',
  keywords: '',
  description: '',
  priority: 'high',
  crawl_live: true,
});

// Google News Live Crawler States
const showLiveCrawlModal = ref(false);
const isCrawlingLive = ref(false);
const crawlingTopic = ref(null);
const liveCrawlQuery = ref('');
const liveCrawlDepth = ref(50);
const liveCrawlError = ref('');

const showTagModal = ref(false);
const targetArticleForTag = ref(null);

const showTopicAiModal = ref(false);
const isAnalyzingTopic = ref(false);
const topicAiResult = ref(null);
const activeAnalyzingTopic = ref(null);
const topicAiError = ref('');

// Default Fallback Suggestions jika data backend kosong
const defaultFallbackSuggestions = computed(() => {
  if (isDivhumas.value) {
    return [
      {
        title: 'Pengamanan & Transparansi Distribusi Program Makan Bergizi Gratis (MBG)',
        priority: 'urgent',
        velocity: '+34 Berita / Tren Cepat',
        keywords: 'makan bergizi gratis, mbg, pengamanan pangan, polri presisi',
        rationale: 'Atensi Khusus Pimpinan: Pantau kelancaran distribusi logistik pangan bergizi di sekolah, cegah potensi kecurangan dan pastikan kepatuhan standar higienis.',
      },
      {
        title: 'Pemberantasan Sindikat Judol & Pemblokiran Ribuan Rekening Nominee',
        priority: 'urgent',
        velocity: '+26 Berita / Tren Cepat',
        keywords: 'judol, judi online, rekening penampung, bareskrim, cyber crime',
        rationale: 'Tingginya atensi netizen dan media massa terhadap penindakan bandar judi online, penyitaan aset, dan pencegahan TPPU.',
      },
      {
        title: 'Cooling System & Tangkal Disinformasi Pilkada Serentak di Ruang Siber',
        priority: 'high',
        velocity: '+19 Berita / Tren Cepat',
        keywords: 'pilkada serentak, cooling system, patroli siber, hoaks politik, netralitas polri',
        rationale: 'Eskalasi percakapan ruang digital jelang agenda nasional; perlukan amplifikasi pesan kamtibmas dan klarifikasi cepat.',
      },
      {
        title: 'Penertiban Balap Liar & Tilang Knalpot Brong Jelang Operasi Kepolisian',
        priority: 'high',
        velocity: '+12 Berita / Tren Cepat',
        keywords: 'knalpot brong, balap liar, razia gabungan, lantas presisi',
        rationale: 'Merespon aduan warganet terkait gangguan kebisingan malam hari dan potensi laka lantas di berbagai satwil.',
      },
    ];
  }
  return [
    {
      title: 'Pengawasan Penyaluran MBG & Ketahanan Pangan Wilayah Sulbar',
      priority: 'urgent',
      velocity: '+15 Berita / Tren Cepat',
      keywords: 'makan bergizi gratis sulbar, distribusi pangan mamuju, polres jajaran',
      rationale: 'Mitigasi dini pengawasan penyaluran menu pangan bergizi di kabupaten/kota jajaran Polda Sulbar agar tepat sasaran dan transparan.',
    },
    {
      title: 'Cooling System & Patroli Dialogis Kamtibmas Pilkada Sulbar',
      priority: 'urgent',
      velocity: '+11 Berita / Tren Cepat',
      keywords: 'cooling system sulbar, bhabinkamtibmas, pilkada damai, patroli siber',
      rationale: 'Penguatan silaturahmi kamtibmas dengan tokoh masyarakat serta menangkal provokasi isu lokal di media sosial.',
    },
    {
      title: 'Penertiban Balap Liar & Knalpot Brong Jalur Utama Sulbar',
      priority: 'high',
      velocity: '+8 Berita / Tren Cepat',
      keywords: 'razia knalpot brong sulbar, balap liar, ditlantas, kamtibmas malam',
      rationale: 'Tindak lanjut cepat atas keresahan warga terhadap kebisingan knalpot brong pada jam istirahat malam.',
    },
  ];
});

// Suggested Emerging Topics State (AI & Google News Trend Ingestion)
const resolveInitialSuggestions = () => {
  if (Array.isArray(props.suggestedTopics) && props.suggestedTopics.length > 0) {
    return [...props.suggestedTopics];
  }
  if (Array.isArray(props.report?.suggested_topics) && props.report.suggested_topics.length > 0) {
    return [...props.report.suggested_topics];
  }
  return [...defaultFallbackSuggestions.value];
};

const localSuggestedTopics = ref(resolveInitialSuggestions());
const showSuggestedTopicsTray = ref(false);

watch([() => props.suggestedTopics, () => props.report?.suggested_topics], () => {
  const resolved = resolveInitialSuggestions();
  if (resolved.length > 0 && localSuggestedTopics.value.length === 0) {
    localSuggestedTopics.value = resolved;
  }
}, { deep: true });
const isAdoptingSuggestion = ref(false);
const adoptingTitle = ref('');

const adoptSuggestion = async (item) => {
  if (!item || !item.title) return;
  isAdoptingSuggestion.value = true;
  adoptingTitle.value = item.title;
  try {
    const res = await axios.post('/media-intelligence/topics/adopt-suggestion', {
      title: item.title,
      keywords: item.keywords || item.title,
      description: item.rationale || 'Topik potensial terdeteksi otomatis oleh AI dari tren Google News.',
      priority: item.priority || 'high',
      crawl_live: true,
    });
    if (res.data?.success) {
      showToastNotification(res.data.message || 'Topik Atensi berhasil diadopsi & berita disinkronkan!');
      if (res.data.topic) {
        const exists = (props.topics || []).some(t => t.id === res.data.topic.id);
        if (!exists) {
          props.topics.push(res.data.topic);
        } else {
          const idx = props.topics.findIndex(t => t.id === res.data.topic.id);
          if (idx !== -1) props.topics[idx] = res.data.topic;
        }
        selectedTopicId.value = res.data.topic.id;
      }


      // Reset filter agar seluruh berita hasil topik atensi langsung tampil utuh
      selectedPortal.value = 'Semua Portal';
      selectedCategory.value = 'all';
      showBookmarksOnly.value = false;
      showRedZoneOnly.value = false;
      selectedSentiment.value = 'all';
      searchQuery.value = '';
      searchInput.value = '';
      periodFilter.value = 'all';
      currentPage.value = 1;

      localSuggestedTopics.value = localSuggestedTopics.value.filter(s => s.title !== item.title);

      // Sinkronkan state Inertia di latar belakang secara halus
      fetchListing();
    }
  } catch (err) {
    showToastNotification(err.response?.data?.message || 'Gagal mengadopsi topik, coba lagi.');
  } finally {
    isAdoptingSuggestion.value = false;
    adoptingTitle.value = '';
  }
};

const dismissSuggestion = async (item) => {
  if (!item || !item.title) return;
  localSuggestedTopics.value = localSuggestedTopics.value.filter(s => s.title !== item.title);
  try {
    await axios.post('/media-intelligence/topics/dismiss-suggestion', { title: item.title });
    showToastNotification('Sugesti topik disembunyikan.');
  } catch (err) {
    // Silent fail
  }
};

const currentSelectedTopic = computed(() => {
  if (!selectedTopicId.value) return null;
  return (props.topics || []).find((t) => t.id === selectedTopicId.value) || null;
});

const openCreateTopicModal = () => {
  topicForm.value = {
    id: null,
    title: '',
    keywords: '',
    description: '',
    priority: 'high',
    crawl_live: true,
  };
  isEditingTopic.value = false;
  showTopicModal.value = true;
};

const openLiveCrawlModal = (topic) => {
  if (!topic) return;
  crawlingTopic.value = topic;
  const kw = topic.keywords_array || [];
  liveCrawlQuery.value = kw.length > 0 ? kw.slice(0, 2).join(' ') : topic.title;
  liveCrawlDepth.value = 50;
  liveCrawlError.value = '';
  showLiveCrawlModal.value = true;
};

const executeLiveCrawl = async () => {
  if (!crawlingTopic.value) return;
  if (!liveCrawlQuery.value.trim()) {
    liveCrawlError.value = 'Kata kunci pencarian tidak boleh kosong!';
    return;
  }

  isCrawlingLive.value = true;
  liveCrawlError.value = '';

  try {
    const res = await axios.post(`/media-intelligence/topics/${crawlingTopic.value.id}/crawl-live`, {
      query: liveCrawlQuery.value.trim(),
      depth: liveCrawlDepth.value,
    });

    if (res.data?.success) {
      const fetchedCount = res.data.count || 0;
      const totalCount = res.data.total_topic_articles ?? (crawlingTopic.value.articles_count + fetchedCount);

      crawlingTopic.value.articles_count = totalCount;
      const pTopic = (props.topics || []).find((t) => t.id === crawlingTopic.value.id);
      if (pTopic) {
        pTopic.articles_count = totalCount;
      }

      fetchListing();

      showLiveCrawlModal.value = false;
      showToastNotification(`Berhasil menarik ${fetchedCount} sebaran berita internet untuk kasus ini!`);
    } else {
      liveCrawlError.value = res.data?.message || 'Gagal menarik sebaran berita dari internet.';
    }
  } catch (err) {
    liveCrawlError.value = err.response?.data?.message || 'Terjadi kendala jaringan saat menarik berita Google News.';
  } finally {
    isCrawlingLive.value = false;
  }
};

const openEditTopicModal = (topic) => {
  topicForm.value = {
    id: topic.id,
    title: topic.title,
    keywords: topic.keywords,
    description: topic.description || '',
    priority: topic.priority || 'high',
  };
  isEditingTopic.value = true;
  showTopicModal.value = true;
};

const submitTopicForm = () => {
  if (!topicForm.value.title.trim() || !topicForm.value.keywords.trim()) {
    showToastNotification('Judul kasus dan kata kunci tidak boleh kosong!');
    return;
  }
  isSubmittingTopic.value = true;

  if (isEditingTopic.value) {
    router.post(`/media-intelligence/topics/${topicForm.value.id}/update`, topicForm.value, {
      preserveScroll: true,
      onSuccess: () => {
        showTopicModal.value = false;
        isSubmittingTopic.value = false;
        showToastNotification('Topik Atensi berhasil diperbarui!');
      },
      onError: () => {
        isSubmittingTopic.value = false;
      },
    });
  } else {
    router.post('/media-intelligence/topics', {
      ...topicForm.value,
      polda_code: props.selectedCode,
    }, {
      preserveScroll: true,
      onSuccess: () => {
        showTopicModal.value = false;
        isSubmittingTopic.value = false;
        showToastNotification('Topik Atensi baru berhasil dibuat!');
      },
      onError: () => {
        isSubmittingTopic.value = false;
      },
    });
  }
};

const deleteTopic = (topic) => {
  if (!confirm(`Hapus Topik Atensi "${topic.title}"?`)) return;
  router.delete(`/media-intelligence/topics/${topic.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      if (selectedTopicId.value === topic.id) {
        selectedTopicId.value = null;
      }
      showToastNotification('Topik Atensi berhasil dihapus.');
    },
  });
};

const removeKeywordFromTopic = (topic, keywordToRemove) => {
  if (!confirm(`Hapus kata kunci "${keywordToRemove}" dari topik ini?`)) return;
  const currentKeywords = (topic.keywords_array || []).filter(kw => kw.toLowerCase() !== keywordToRemove.toLowerCase());
  const updatedKeywords = currentKeywords.join(', ');
  router.post(`/media-intelligence/topics/${topic.id}/update`, {
    id: topic.id,
    title: topic.title,
    keywords: updatedKeywords,
    description: topic.description || '',
    priority: topic.priority || 'high',
  }, {
    preserveScroll: true,
    onSuccess: () => {
      showToastNotification(`Kata kunci "${keywordToRemove}" berhasil dihapus.`);
    },
  });
};

const openTagModal = (article) => {
  targetArticleForTag.value = article;
  showTagModal.value = true;
};

const toggleArticleTopic = async (topicId) => {
  if (!targetArticleForTag.value) return;
  const art = targetArticleForTag.value;
  try {
    const res = await axios.post(`/media-intelligence/articles/${art.id}/toggle-topic`, {
      topic_id: topicId,
    });
    if (res.data?.success) {
      if (!art.manual_topic_ids) art.manual_topic_ids = [];
      if (!art.matched_topic_ids) art.matched_topic_ids = [];
      if (!art.matched_topics) art.matched_topics = [];

      const tIdx = art.manual_topic_ids.indexOf(topicId);
      const topObj = (props.topics || []).find(t => t.id === topicId);

      if (res.data.is_tagged) {
        if (tIdx === -1) art.manual_topic_ids.push(topicId);
        if (!art.matched_topic_ids.includes(topicId)) art.matched_topic_ids.push(topicId);
        if (topObj && !art.matched_topics.some(t => t.id === topicId)) {
          art.matched_topics.push({ id: topObj.id, title: topObj.title, priority: topObj.priority });
        }
      } else {
        if (tIdx !== -1) art.manual_topic_ids.splice(tIdx, 1);
        const isKeywordMatch = topObj ? (topObj.keywords_array || []).some(k => (art.title + ' ' + art.summary).toLowerCase().includes(k)) : false;
        if (!isKeywordMatch) {
          const mIdx = art.matched_topic_ids.indexOf(topicId);
          if (mIdx !== -1) art.matched_topic_ids.splice(mIdx, 1);
          art.matched_topics = art.matched_topics.filter(t => t.id !== topicId);
        }
      }
      showToastNotification(res.data.message || 'Status kaitan topik diperbarui.');
    }
  } catch (err) {
    showToastNotification('Gagal mengaitkan artikel ke topik.');
  }
};

const getTopicNewArticlesCount = (topic) => {
  if (!topic || !topic.ai_analyzed_at) return 0;
  const currentCount = topic.articles_count || 0;
  const analyzedCount = topic.ai_articles_count || 0;
  return Math.max(0, currentCount - analyzedCount);
};

const getTopicSentiment = (analysis) => {
  if (!analysis) return { positif: 0, netral: 100, negatif: 0, dominant: 'Netral' };
  const sa = analysis.sentiment_analysis || analysis.sentiment_breakdown || {};
  const pos = Number(sa.positif ?? 0);
  const net = Number(sa.netral ?? 0);
  const neg = Number(sa.negatif ?? 0);
  let dominant = sa.dominant_sentiment || analysis.dominant_sentiment;
  if (!dominant) {
    if (pos > neg && pos > net) dominant = 'Positif';
    else if (neg > pos && neg > net) dominant = 'Negatif';
    else dominant = 'Netral';
  }
  return {
    positif: pos,
    netral: net,
    negatif: neg,
    dominant: String(dominant).charAt(0).toUpperCase() + String(dominant).slice(1)
  };
};

const openCachedTopicAi = (topic) => {
  if (!topic || !topic.ai_analysis) return;
  activeAnalyzingTopic.value = topic;
  topicAiResult.value = topic.ai_analysis;
  topicAiError.value = '';
  isAnalyzingTopic.value = false;
  showTopicAiModal.value = true;
};

const runTopicAiAnalysis = async (topic, forceRefresh = false) => {
  if (!topic) return;

  // Jika sudah pernah dianalisis dan bukan permintaan pembaruan paksa, buka langsung (0 token)
  if (topic.ai_analysis && !forceRefresh) {
    openCachedTopicAi(topic);
    return;
  }

  activeAnalyzingTopic.value = topic;
  isAnalyzingTopic.value = true;
  topicAiError.value = '';
  if (!forceRefresh) {
    topicAiResult.value = null;
  }
  showTopicAiModal.value = true;

  try {
    const payload = {
      current_articles_count: topic.articles_count || 0,
    };
    const res = await axios.post(`/media-intelligence/topics/${topic.id}/analyze-ai`, payload);
    if (res.data?.success) {
      topicAiResult.value = res.data.analysis;
      // Perbarui state lokal topik seketika agar langsung reaktif
      const newAnalyzedCount = res.data.articles_count ?? topic.articles_count ?? 0;
      topic.ai_analysis = res.data.analysis;
      topic.ai_analyzed_at = res.data.topic?.ai_analyzed_at || new Date().toISOString();
      topic.ai_analyzed_at_humans = res.data.topic?.ai_analyzed_at_humans || 'Baru saja';
      topic.ai_articles_count = newAnalyzedCount;

      if (activeAnalyzingTopic.value && activeAnalyzingTopic.value.id === topic.id) {
        activeAnalyzingTopic.value.ai_analysis = res.data.analysis;
        activeAnalyzingTopic.value.ai_analyzed_at = topic.ai_analyzed_at;
        activeAnalyzingTopic.value.ai_analyzed_at_humans = topic.ai_analyzed_at_humans;
        activeAnalyzingTopic.value.ai_articles_count = newAnalyzedCount;
      }

      // Sinkronkan juga pada daftar props.topics
      const pTopic = (props.topics || []).find(t => t.id === topic.id);
      if (pTopic) {
        pTopic.ai_analysis = res.data.analysis;
        pTopic.ai_analyzed_at = topic.ai_analyzed_at;
        pTopic.ai_analyzed_at_humans = topic.ai_analyzed_at_humans;
        pTopic.ai_articles_count = newAnalyzedCount;
      }

      showToastNotification('Analisis AI kasus berhasil diperbarui dan disimpan!');
    } else {
      topicAiError.value = res.data?.message || 'Gagal memproses analisis AI kasus ini.';
    }
  } catch (err) {
    topicAiError.value = err.response?.data?.message || 'Terjadi kendala saat menghubungi layanan analisis AI.';
  } finally {
    isAnalyzingTopic.value = false;
  }
};

const isTopicWaCopied = ref(false);

const copyTopicWaScript = (topic) => {
  if (!topic) return;
  // Jika topik sudah memiliki hasil analisis AI dengan naskah WhatsApp resmi berstandar Polri, salin naskah tersebut
  if (topic.ai_analysis?.whatsapp_script) {
    copyText(topic.ai_analysis.whatsapp_script, `Naskah WhatsApp Atensi "${topic.title}"`);
    return;
  }

  const matched = (props.sampleArticles || []).filter(a => (a.matched_topic_ids || []).includes(topic.id));
  const isDiv = props.selectedCode === 'ALL' || props.selectedCode === 'DIVHUMAS';
  const tz = isDiv ? 'WIB' : 'WITA';
  const nowStr = new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
  const timeStr = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) + ' ' + tz;

  let s = `*LAPORAN INTELIJEN MEDIA — ATENSI KHUSUS PIMPINAN*\n`;
  s += `*SATUAN:* ${isDiv ? 'DIVISI HUMAS POLRI (MABES POLRI)' : (props.selectedPolda?.name?.toUpperCase() || 'POLDA')}\n`;
  s += `*WAKTU PEMANTAUAN:* ${nowStr}, Pukul ${timeStr}\n\n`;
  s += `*KASUS / ISU ATENSI:* ${topic.title.toUpperCase()}\n`;
  s += `*TINGKAT PRIORITAS:* ${topic.priority.toUpperCase()}\n`;
  if (topic.description) {
    s += `*ARAHAN & CATATAN PIMPINAN:*\n"${topic.description}"\n\n`;
  }
  s += `*KATA KUNCI PANTAUKAN:* ${topic.keywords}\n`;
  s += `*VOLUME PEMBERITAAN:* ${matched.length} Berita Terkumpul\n\n`;

  s += `*REKAPITULASI BERITA TERKINI:*\n`;
  if (matched.length === 0) {
    s += `Belum ditemukan pemberitaan baru pada pantauan 24 jam terakhir.\n\n`;
  } else {
    matched.slice(0, 8).forEach((a, i) => {
      s += `${i + 1}. [${a.source_name}] *${a.title}*\n   Waktu: ${a.pub_date || a.time_ago} | Sentimen: ${(a.sentiment || 'NETRAL').toUpperCase()}\n   Link: ${a.link}\n\n`;
    });
  }

  s += `*REKOMENDASI STRATEGIS HUMAS POLRI:*\n`;
  s += `1. Pastikan juru bicara resmi satu pintu menyampaikan data berbasis fakta objektif dan progres nyata.\n`;
  s += `2. Siapkan infografis / materi counter narasi untuk menangkal disinformasi di media sosial.\n`;
  s += `3. Lakukan koordinasi proaktif dengan pilar kewilayahan dan stakeholder eksternal terkait.\n\n`;
  s += `Demikian laporan khusus perkembangan kasus ini kami sampaikan. Mohon petunjuk dan arahan lebih lanjut, Jenderal.\n\n`;
  s += `*Hormat kami,*\n*Tim Analisis Intelijen Media Humas Polri*`;

  copyText(s, `Naskah WhatsApp Atensi "${topic.title}"`);
};

const copyAiWaScript = () => {
  if (!topicAiResult.value?.whatsapp_script) return;
  copyText(topicAiResult.value.whatsapp_script, 'Naskah WhatsApp Resmi Hasil Analisis AI');
  isTopicWaCopied.value = true;
  setTimeout(() => { isTopicWaCopied.value = false; }, 3000);
};

const openTopicWaDirect = () => {
  if (!topicAiResult.value?.whatsapp_script) return;
  const url = `https://api.whatsapp.com/send?text=${encodeURIComponent(topicAiResult.value.whatsapp_script)}`;
  window.open(url, '_blank');
};

// Modal state for viewing/copying prompt manually
const showPromptModal = ref(false);
const promptModalTitle = ref('');
const promptModalContent = ref('');

const openPromptPreviewModal = (title, content) => {
  promptModalTitle.value = title;
  promptModalContent.value = content;
  showPromptModal.value = true;
};

// Robust Toast
const showToastNotification = (msg) => {
  toastMessage.value = msg;
  setTimeout(() => {
    toastMessage.value = '';
  }, 4000);
};

// Universal copy with fallback for non-secure HTTP contexts (.test domain)
const execCommandCopy = (text, label = 'Teks') => {
  try {
    const el = document.createElement('textarea');
    el.value = text;
    el.setAttribute('readonly', '');
    el.style.position = 'fixed';
    el.style.top = '0';
    el.style.left = '-9999px';
    el.style.opacity = '0';
    document.body.appendChild(el);
    el.focus();
    el.select();
    el.setSelectionRange(0, 99999);

    const successful = document.execCommand('copy');
    document.body.removeChild(el);

    if (successful) {
      showToastNotification(`${label} berhasil disalin ke clipboard!`);
      return true;
    }
  } catch (err) {
    console.warn('execCommand copy failed:', err);
  }
  return false;
};

const copyText = (text, label = 'Teks') => {
  if (!text) {
    showToastNotification('Teks tidak tersedia untuk disalin.');
    return;
  }

  // 1. Try modern clipboard API if secure context
  if (navigator?.clipboard?.writeText && window.isSecureContext) {
    navigator.clipboard.writeText(text)
      .then(() => {
        showToastNotification(`${label} berhasil disalin ke clipboard!`);
      })
      .catch(() => {
        const ok = execCommandCopy(text, label);
        if (!ok) {
          openPromptPreviewModal(label, text);
        }
      });
    return;
  }

  // 2. Fallback for HTTP (non-secure context, e.g. .test domain)
  const ok = execCommandCopy(text, label);
  if (!ok) {
    openPromptPreviewModal(label, text);
  }
};

// =========================================================================
// AUTOMATED MEDIA INTELLIGENCE & ALL-IN-ONE SYNC PIPELINE
// =========================================================================
const isAnalyzingGemini = ref(false);
const syncStepText = ref('');

const triggerGeminiAnalysis = async (withSync = true, force = false) => {
  if (!props.aiSetting?.has_api_key) {
    showToastNotification('Kunci API Mesin AI belum dikonfigurasi. Hubungi Administrator di menu Keamanan & Pemeliharaan.');
    return;
  }

  isAnalyzingGemini.value = true;
  syncStepText.value = withSync ? '1/2 Menarik berita...' : 'Menganalisis...';

  let stepTimer = null;
  if (withSync) {
    stepTimer = setTimeout(() => {
      if (isAnalyzingGemini.value) {
        syncStepText.value = '2/2 Menganalisis dengan AI...';
      }
    }, 3800);
  }

  try {
    const syncParam = (withSync ? '?sync=1' : '?') + (force ? (withSync ? '&force=1' : 'force=1') : '');
    const res = await axios.post(`/media-intelligence/analyze-gemini${syncParam}`);
    showToastNotification(`✓ ${res.data.message || 'Sinkronisasi berita dan analisis intelijen otomatis selesai!'}`);
    router.reload({ preserveScroll: true });
  } catch (err) {
    const msg = err.response?.data?.message || 'Analisis intelijen gagal. Periksa koneksi atau kuota API.';
    showToastNotification(msg);
  } finally {
    if (stepTimer) clearTimeout(stepTimer);
    isAnalyzingGemini.value = false;
    syncStepText.value = '';
  }
};

// =========================================================================
// WHATSAPP REPORT DISPATCH KE PIMPINAN (KAPOLDA & PJU)
// =========================================================================
const isWaCopied = ref(false);
const isWaMaximized = ref(false);
const waBoxRef = ref(null);
const waBoxTab2Ref = ref(null);

const scrollWaToTop = () => {
  if (waBoxRef.value) waBoxRef.value.scrollTo({ top: 0, behavior: 'smooth' });
  if (waBoxTab2Ref.value) waBoxTab2Ref.value.scrollTo({ top: 0, behavior: 'smooth' });
};

const scrollWaToBottom = () => {
  if (waBoxRef.value) waBoxRef.value.scrollTo({ top: waBoxRef.value.scrollHeight, behavior: 'smooth' });
  if (waBoxTab2Ref.value) waBoxTab2Ref.value.scrollTo({ top: waBoxTab2Ref.value.scrollHeight, behavior: 'smooth' });
};

const copyWaReport = () => {
  copyText(whatsappReportText.value, 'Format Naskah WhatsApp Pimpinan');
  isWaCopied.value = true;
  setTimeout(() => { isWaCopied.value = false; }, 3500);
};

const openWhatsAppDirect = () => {
  const url = `https://api.whatsapp.com/send?text=${encodeURIComponent(whatsappReportText.value)}`;
  window.open(url, '_blank');
};

const downloadWaTxt = () => {
  try {
    const blob = new Blob([whatsappReportText.value], { type: 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `LAPORAN_WA_${isDivhumas.value ? 'KADIVHUMAS_MABES' : 'KAPOLDA'}_${props.selectedCode || 'SULBAR'}_${new Date().toISOString().slice(0, 10)}.txt`;
    link.click();
    URL.revokeObjectURL(url);
    showToastNotification('✓ Naskah WA berhasil diunduh sebagai file .txt!');
  } catch (err) {
    console.error('Download error:', err);
  }
};

const whatsappReportText = computed(() => {
  const r = props.report || {};
  const isDiv = isDivhumas.value;
  const poldaName = isDiv ? 'Divisi Humas Polri' : (props.selectedPolda?.name || 'Polda Sulawesi Barat');
  const poldaShort = isDiv ? 'Divhumas Polri' : (props.selectedCode === 'SULBAR' ? 'Polda Sulbar' : poldaName);
  const kapoldaTitle = isDiv
    ? `Bapak Kadivhumas Polri (${props.selectedPolda?.kapolda_name || 'Irjen Pol. Johnny Eddizon Isir, S.I.K., M.T.C.P.'})`
    : (props.selectedPolda?.kapolda_name
        ? `Bapak Kapolda ${poldaName} (${props.selectedPolda.kapolda_name})`
        : `Bapak Kapolda ${poldaName}`);
  const kabidHumasTitle = isDiv
    ? `Karo Penmas / Analis Intelijen Siber Mabes Polri (${props.selectedPolda?.kabid_humas_name || 'Brigjen Pol. Trunoyudo Wisnu Andiko, S.I.K.'})`
    : (props.selectedPolda?.kabid_humas_name
        ? `Kabid Humas ${poldaName} (${props.selectedPolda.kabid_humas_name})`
        : `Kabid Humas ${poldaName}`);

  const todayStr = r.report_date
    ? new Date(r.report_date).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
    : new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

  const reportTime = r.report_time || (isDiv ? '19.00 WIB' : '19.03 WITA');

  let s = `*LAPORAN INTELIJEN MEDIA & SENTIMEN OPINI PUBLIK*\n`;
  s += `*${isDiv ? 'DIVISI HUMAS POLRI (ORKESTRASI NASIONAL)' : 'BIDANG HUMAS ' + poldaName.toUpperCase()}*\n\n`;
  s += `Kepada Yth. : ${kapoldaTitle}\n`;
  s += `Dari        : ${kabidHumasTitle}\n`;
  s += `Tanggal     : ${todayStr} (Pukul ${reportTime})\n`;
  s += `Perihal     : Analisis Pemberitaan, Sentimen Opini Publik, dan Rekomendasi Tindak Lanjut\n\n`;
  s += `*Assalamu’alaikum Wr. Wb.*\n`;
  s += `*Selamat Pagi Jenderal,*\n\n`;
  s += `Mohon izin Jenderal, kami sampaikan laporan hasil analisis pemberitaan dan sentimen opini publik terkait institusi Polri secara ${isDiv ? 'nasional dan orkestrasi 34 Polda jajaran' : 'nasional serta ' + poldaShort} berdasarkan bahan pemantauan media siber dan opini publik 24 jam terakhir.\n\n`;

  // SECTION I: SITUASI DAN ANALISIS
  s += `━━━━━━━━━━━━━━━━━━━━━━━━━━━\n`;
  s += `*I. SITUASI DAN ANALISIS ${isDiv ? 'KADIV HUMAS POLRI' : 'KABID HUMAS'}*\n`;
  s += `━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n`;
  s += `*1. Sentimen Pemberitaan*\n\n`;
  s += `🇮🇩 *Nasional*\n`;
  s += `• Positif: ${r.sentiment_national?.positif ?? 65}%\n`;
  s += `• Netral : ${r.sentiment_national?.netral ?? 25}%\n`;
  s += `• Negatif: ${r.sentiment_national?.negatif ?? 10}%\n\n`;

  s += `📍 *${isDiv ? 'Sentimen Kewilayahan (34 Polda Jajaran)' : poldaShort}*\n`;
  s += `• Positif: ${(r.sentiment_satwil || r.sentiment_sulbar)?.positif ?? (isDiv ? 60 : 55)}%\n`;
  s += `• Netral : ${(r.sentiment_satwil || r.sentiment_sulbar)?.netral ?? 30}%\n`;
  s += `• Negatif: ${(r.sentiment_satwil || r.sentiment_sulbar)?.negatif ?? (isDiv ? 10 : 15)}%\n\n`;

  s += `*Analisis Perkembangan:*\n`;
  if (isDiv && (r.snapshot_satwil_desc || r.snapshot_sulbar_desc)) {
    s += `${r.snapshot_satwil_desc || r.snapshot_sulbar_desc}\n\n`;
  } else if (!isDiv && r.snapshot_sulbar_desc) {
    s += `${r.snapshot_sulbar_desc}\n\n`;
  } else if (r.executive_brief) {
    s += `${r.executive_brief}\n\n`;
  } else {
    s += isDiv
      ? `Berdasarkan pemantauan intelijen media siber dan 22 portal berita online nasional terverifikasi, situasi kamtibmas di seluruh wilayah hukum 34 Polda jajaran terpantau relatif aman dan kondusif dengan dominasi sentimen positif atas akselerasi program Presisi, transparansi penegakan hukum, dan pelayanan publik kepolisian.\n\n`
      : `Berdasarkan pemantauan media siber dan 29 portal berita resmi terverifikasi, situasi kamtibmas di wilayah hukum ${poldaShort} terpantau relatif aman dan kondusif dengan dominasi sentimen positif atas akselerasi patroli presisi dan pelayanan prima kepolisian.\n\n`;
  }

  // SECTION I.B: ISU STRATEGIS NASIONAL POLRI
  const nationalIssues = r.national_issues && r.national_issues.length > 0 ? r.national_issues : [];
  if (nationalIssues.length > 0) {
    s += `🇮🇩 *ISU STRATEGIS NASIONAL POLRI*\n`;
    s += `Status Isu Nasional: *${r.snapshot_national_status || 'HIJAU - KONDUSIF'}*\n\n`;
    nationalIssues.forEach((iss, nIdx) => {
      s += `*A.${nIdx + 1}. ${iss.title}*\n`;
      s += `• Fakta: ${iss.fact}\n`;
      if (iss.source) s += `• Sumber: ${iss.source}\n`;
      if (iss.sentiment) s += `• Sentimen: ${iss.sentiment} | Virality: ${iss.virality || 'Sedang'}\n`;
      if (iss.url) s += `• Tautan: ${iss.url}\n`;
      s += `\n`;
    });
  }

  // SECTION I.C: ISU KEWILAYAHAN DAERAH / 34 POLDA
  s += `📍 *${isDiv ? 'ISU MENONJOL 34 POLDA JAJARAN & KEWILAYAHAN' : 'ISU KEWILAYAHAN ' + poldaShort.toUpperCase() + ' & POLRES JAJARAN'}*\n\n`;
  const regionalIssues = (r.regional_issues && r.regional_issues.length > 0)
    ? r.regional_issues.slice(0, 4)
    : (r.sulbar_issues && r.sulbar_issues.length > 0)
      ? r.sulbar_issues.slice(0, 4)
      : isDiv
        ? [
            {
              title: 'Polda Metro Jaya & Bareskrim Ungkap Sindikat Penipuan Daring Lintas Negara',
              fact: 'Polda Metro Jaya bersama Bareskrim Polri berhasil mengungkap jaringan penipuan daring internasional dan menyelamatkan aset korban, menuai apresiasi luas dari publik.',
              source: 'Antara News & Detikcom',
              sentiment: 'Positif',
            },
            {
              title: 'Polda Jabar Pastikan Pengamanan Jalur Vital dan Kamtibmas Kondusif',
              fact: 'Jajaran Polda Jawa Barat meningkatkan patroli presisi di titik rawan kamtibmas dan jalur logistik nasional, situasi terpantau aman dan tertib.',
              source: 'CNN Indonesia & Humas Jabar',
              sentiment: 'Positif',
            },
            {
              title: 'Divhumas Salurkan Asistensi Digital & Counter Hoaks ke 34 Bidhumas Polda',
              fact: 'Biro Penmas Divhumas mengonsolidasikan narasi satu pintu dan asistensi konten edukasi ke seluruh jajaran Polda se-Indonesia.',
              source: 'Tribrata News Mabes',
              sentiment: 'Positif',
            },
          ]
        : [
            {
              title: 'Pengamanan Intensif Tahapan Pilkada Serentak di Mamuju',
              fact: 'Satgas Operasi Mantap Praja Polresta Mamuju memperketat pengamanan 24 jam di Kantor KPU Sulbar dan Bawaslu, menjaga stabilitas pesta demokrasi.',
              source: 'Humas Polri & Radar Sulbar',
              sentiment: 'Positif',
            },
            {
              title: 'Klarifikasi Tim Siber atas Hoaks Isu Begal di Tarailu',
              fact: 'Subdit Siber Ditreskrimsus dan Humas Mateng mengonfirmasi video beredar merupakan insiden tahun 2021 di luar provinsi dan jalur trans dipatroli rutin aman.',
              source: 'Subdit Siber & WhatsApp',
              sentiment: 'Positif',
            },
            {
              title: 'Penertiban Jukir Liar di Kawasan Pantai Manakarra',
              fact: 'Merespons keluhan video warga di media sosial, Polresta Mamuju bersama Dishub menertibkan jukir liar untuk menjamin kenyamanan ruang publik.',
              source: 'TikTok & Media Siber',
              sentiment: 'Netral',
            },
          ];

  let issueNum = 2;
  regionalIssues.forEach((iss) => {
    s += `*${issueNum}. Isu: ${iss.title}*\n`;
    if (iss.source) {
      s += `Pemberitaan ${iss.source} menyoroti: ${iss.fact}\n\n`;
    } else {
      s += `${iss.fact}\n\n`;
    }
    s += `*Analisis dan masukan:*\n`;
    const sent = (iss.sentiment || '').toLowerCase();
    const risk = (iss.risk || '').toLowerCase();
    if (sent.includes('positif')) {
      s += `Pemberitaan ini menjadi momentum positif bagi institusi dalam menunjukkan komitmen perlindungan dan pelayanan masyarakat. Disarankan agar dokumentasi humanis ini terus diamplifikasi melalui platform media sosial jajaran Satwil.\n\n`;
    } else if (sent.includes('negatif') || risk.includes('merah') || risk.includes('kuning')) {
      s += `Untuk meredam spekulasi yang belum terverifikasi, kami memandang perlu dilakukan konfirmasi segera kepada satuan kewilayahan terkait serta penyampaian klarifikasi resmi satu pintu agar opini publik tidak berkembang liar.\n\n`;
    } else {
      s += `Pelaksanaan kegiatan ini memperkuat kepercayaan masyarakat. Disarankan koordinasi berkala dengan pihak terkait serta edukasi kepada masyarakat melalui kanal komunikasi resmi.\n\n`;
    }
    if (iss.url) {
      s += `Tautan Sumber: ${iss.url}\n\n`;
    }
    issueNum++;
  });

  // SECTION II: KAJIAN RESIKO & DAMPAK JIKA TIDAK DIMITIGASI (ATENSI KHUSUS PIMPINAN)
  s += `━━━━━━━━━━━━━━━━━━━━━━━━━━━\n`;
  s += `*II. KAJIAN RESIKO & DAMPAK JIKA TIDAK DIMITIGASI*\n`;
  s += `*(ATENSI KHUSUS & INTENS PIMPINAN)*\n`;
  s += `━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n`;
  s += `Telaah intelijen media dan pemantauan opini publik memetakan potensi eskalasi krisis apabila tidak segera dilakukan mitigasi humanis dan transparan berbasis fakta riil:\n\n`;

  if (r.early_warnings && r.early_warnings.length > 0) {
    r.early_warnings.forEach((ew, eIdx) => {
      s += `*${eIdx + 1}. Ancaman/Isu: ${ew.title}*\n`;
      if (ew.kamtibmas_aspect) s += `• Dimensi Kamtibmas: ${ew.kamtibmas_aspect}\n`;
      s += `• Fakta Singkat: ${ew.fact}\n`;
      s += `• Dampak Fatal Jika Tanpa Mitigasi: ${ew.unmitigated_risk || ew.risk || 'Keterlambatan klarifikasi berisiko memicu framing sepihak netizen dan mobilisasi massa.'}\n\n`;
    });
  } else {
    s += `• *Dampak Jangka Pendek (0–6 Jam):* Terjadi kekosongan informasi (information vacuum) yang diisi spekulasi liar dan framing negatif buzzer di media sosial.\n\n`;
    s += `• *Dampak Eskalasi Kamtibmas (6–24 Jam):* Masuknya liputan kritis media arus utama nasional, potensi provokasi berkembang menjadi aksi unjuk rasa atau gesekan fisik kewilayahan.\n\n`;
    s += `• *Doktrin Mitigasi Humanis & Transparan:* Mengakui fakta riil secara jujur (tanpa defensif), menyampaikan empati mendalam pada korban/warga, serta merilis kronologi 5W+1H berbasis bukti empiris dalam 2 jam pertama.\n\n`;
  }

  // SECTION III: SARAN LANGKAH TINDAK LANJUT
  s += `━━━━━━━━━━━━━━━━━━━━━━━━━━━\n`;
  s += `*III. SARAN LANGKAH TINDAK LANJUT*\n`;
  s += `━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n`;
  if (r.recommendations && r.recommendations.length > 0) {
    r.recommendations.forEach((rec, idx) => {
      s += `*${idx + 1}. Terkait ${rec.issue || rec.title || 'Isu Strategis'}*\n`;
      s += `${rec.action || rec.desc || ''}\n`;
      if (rec.pic) {
        s += `Satuan Pelaksana (PIC): ${rec.pic}\n\n`;
      } else {
        s += `\n`;
      }
    });
  } else if (isDiv) {
    s += `*1. Terkait Penguatan Agenda Setting Nasional*\n`;
    s += `Mengorkestrasi seluruh 34 Bidhumas Polda jajaran untuk menyebarluaskan narasi positif penegakan hukum Presisi Mabes Polri dan program prioritas Kapolri.\n`;
    s += `Satuan Pelaksana (PIC): Biro Penmas Divhumas Polri & 34 Kabid Humas Polda\n\n`;

    s += `*2. Terkait Counter Narasi Hoaks & Disinformasi Siber*\n`;
    s += `Biro Multimedia Divhumas dan Dittipidsiber Bareskrim aktif melakukan fact-checking 24 jam dan menerbitkan stempel HOAKS resmi pada setiap disinformasi viral skala nasional.\n`;
    s += `Satuan Pelaksana (PIC): Biro Multimedia Divhumas & Dittipidsiber Bareskrim\n\n`;

    s += `*3. Terkait Monitoring Media dan Opini Publik Nasional*\n`;
    s += `Divisi Humas Polri mengoptimalkan pemantauan 22 portal media online nasional terverifikasi, Google News, dan media sosial. Setiap isu sensitif dilaporkan secara berjenjang beserta tautan rujukan dan waktu publikasi WIB.\n\n`;

    s += `*4. Terkait Pelayanan Pengaduan & Dumas Presisi (110)*\n`;
    s += `Sosialisasi layanan Contact Center Polri 110 dan aplikasi Dumas Presisi terus digencarkan secara nasional untuk kemudahan masyarakat melaporkan gangguan kamtibmas.\n\n`;
  } else {
    s += `*1. Terkait Pengamanan Tahapan Pilkada Serentak*\n`;
    s += `Memperkuat koordinasi Sat Samapta, Intelkam, dan jajaran Polres dalam pengawalan logistik dan stabilitas kamtibmas.\n`;
    s += `Satuan Pelaksana (PIC): Polresta Mamuju & Polres Jajaran\n\n`;

    s += `*2. Terkait Penanggulangan Isu Hoaks & Provokasi*\n`;
    s += `Subdit Siber dan Tim Humas aktif 24 jam melakukan fact-checking dan menerbitkan stempel HOAKS resmi pada setiap disinformasi viral.\n`;
    s += `Satuan Pelaksana (PIC): Subdit Siber Ditreskrimsus & Bidhumas\n\n`;

    s += `*3. Terkait Monitoring Media dan Opini Publik*\n`;
    s += `Bidang Humas terus mengoptimalkan pemantauan 29 portal media online resmi, Google News, dan media sosial. Setiap isu sensitif dilaporkan secara berjenjang beserta tautan rujukan dan waktu publikasi WITA.\n\n`;

    s += `*4. Terkait Pelayanan Pengaduan Masyarakat (110)*\n`;
    s += `Sosialisasi layanan Contact Center Polri 110 terus digencarkan agar masyarakat dapat menyampaikan informasi gangguan kamtibmas secara cepat dan gratis.\n\n`;
  }

  // SECTION IV: SARAN KOORDINASI DAN EVALUASI
  s += `━━━━━━━━━━━━━━━━━━━━━━━━━━━\n`;
  s += `*IV. SARAN KOORDINASI DAN EVALUASI*\n`;
  s += `━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n`;
  s += `Untuk mendukung tindak lanjut yang terukur, kami menyarankan pola koordinasi berjenjang:\n\n`;

  const resp0_2h = r.priority_responses?.response_0_2h?.action || (isDiv ? 'Validasi fakta lapangan dengan Biro Penmas / Polda jajaran (0-2 jam).' : 'Validasi fakta lapangan dan koordinasi cepat dengan satuan fungsi terkait (0-2 jam).');
  const resp2_6h = r.priority_responses?.response_2_6h?.action || (isDiv ? 'Konsolidasi data Mabes, rilis pers terpadu, dan instruksi amplifikasi 34 Polda (2-6 jam).' : 'Konsolidasi data, penyusunan FAQ kedinasan, dan rilis pers satu pintu (2-6 jam).');
  const resp24h  = r.priority_responses?.response_24h?.action  || 'Rangkum perkembangan penanganan isu dan evaluasi respons netizen dalam 24 jam.';

  s += `• *Tahap Awal (0–2 jam):* ${resp0_2h}\n\n`;
  s += `• *Tahap Konsolidasi (2–6 jam):* ${resp2_6h}\n\n`;
  s += `• *Tahap Evaluasi (24 jam):* ${resp24h}\n\n`;
  s += `Alur waktu koordinasi tersebut dapat disesuaikan dengan eskalasi urgensi dan petunjuk pimpinan.\n\n`;

  // SECTION V: KESIMPULAN DAN PERTIMBANGAN
  s += `━━━━━━━━━━━━━━━━━━━━━━━━━━━\n`;
  s += `*V. KESIMPULAN DAN PERTIMBANGAN KEPADA ${isDiv ? 'BAPAK KAPOLRI & KADIVHUMAS' : 'BAPAK KAPOLDA'}*\n`;
  s += `━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n`;

  let sitStatus = (isDiv ? (r.snapshot_satwil_status || r.snapshot_national_status) : r.snapshot_sulbar_status) || 'HIJAU - KONDUSIF';
  s += `Jenderal, berdasarkan data pemantauan terpadu, situasi komunikasi publik ${poldaShort} saat ini dinilai berada pada kategori *${sitStatus}*.\n\n`;

  if (r.top_concern) {
    s += `*Atensi Prioritas:* ${r.top_concern}\n\n`;
  }

  s += `Sebagai bahan pertimbangan tindak lanjut, kami menyarankan:\n`;
  s += `1. Konsolidasi data lintas fungsi diperkuat agar setiap rilis klarifikasi berbasis fakta terverifikasi.\n`;
  s += `2. Komunikasi publik konsisten mengedepankan transparansi, pendekatan humanis, dan manfaat nyata bagi warga.\n`;
  s += `3. Monitoring media siber berkelanjutan guna deteksi dini potensi anomali opini publik.\n\n`;

  s += `Demikian kami laporkan sebagai bahan pertimbangan Bapak ${isDiv ? 'Kapolri dan Kadivhumas Polri' : 'Kapolda'}. Atas arahan dan petunjuk lebih lanjut, kami siap laksanakan.\n\n`;
  s += `Hormat kami,\n`;
  s += `*${isDiv ? 'DIVISI HUMAS POLRI' : 'BIDANG HUMAS ' + poldaName.toUpperCase()}*\n\n`;
  s += `_Tembusan:_\n`;
  if (isDiv) {
    s += `_1. Bapak Kapolri_\n`;
    s += `_2. Bapak Wakapolri_\n`;
    s += `_3. Bapak Irwasum Polri_\n\n`;
  } else {
    s += `_1. Wakapolda ${poldaName}_\n`;
    s += `_2. Irwasda ${poldaName}_\n\n`;
  }
  s += `*Wassalamu’alaikum Wr. Wb.*`;

  return s;
});

// Live Refresh RSS Feeds
const isRefreshingRss = ref(false);
const refreshRssFeeds = () => {
  isRefreshingRss.value = true;
  router.get('/media-intelligence', { ...queryParams.value, refresh: 1 }, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
    onFinish: () => {
      isRefreshingRss.value = false;
      toastMessage.value = 'Sinkronisasi berita RSS selesai!';
      setTimeout(() => {
        toastMessage.value = '';
      }, 3500);
    },
  });
};

// Daftar sudah difilter, digabung (berita ganda), diurutkan & dipaginasi oleh server.
const filteredArticles = computed(() => props.sampleArticles || []);
const paginatedArticles = filteredArticles;
const totalPages = computed(() => Math.max(1, props.articlesMeta?.last_page || 1));

// Smart page window generator (e.g. [1, 2, 3, 4, 5, '...', 9])
const visiblePages = computed(() => {
  const total = totalPages.value;
  const current = currentPage.value;

  if (total <= 7) {
    return Array.from({ length: total }, (_, i) => i + 1);
  }

  const pages = [];
  if (current <= 4) {
    for (let i = 1; i <= 5; i++) pages.push(i);
    pages.push('...');
    pages.push(total);
  } else if (current >= total - 3) {
    pages.push(1);
    pages.push('...');
    for (let i = total - 4; i <= total; i++) pages.push(i);
  } else {
    pages.push(1);
    pages.push('...');
    pages.push(current - 1);
    pages.push(current);
    pages.push(current + 1);
    pages.push('...');
    pages.push(total);
  }

  return pages;
});

const goToPage = (page) => {
  if (typeof page !== 'number') return;
  if (page < 1 || page > totalPages.value || page === currentPage.value) return;
  currentPage.value = page;

  // Scroll to article container smoothly
  const container = document.getElementById('article-list-container');
  if (container) {
    container.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
};

// Salin Berita sebagai Bahan AI
const copyArticleForAi = (article) => {
  const content = `Judul: ${article.title}\nSumber: ${article.source_name}\nWaktu: ${article.pub_date || article.time_ago}\nCuplikan: ${article.summary}\nTautan: ${article.link}`;
  copyText(content, `Bahan AI [${article.source_name}]`);
};

// Create Issue Modal State
const showIssueModal = ref(false);
const activeArticleForIssue = ref(null);
const issueForm = ref({
  priority: 'KUNING',
  pic: 'Bidhumas Polda',
  notes: '',
});

const openIssueModal = (article) => {
  activeArticleForIssue.value = article;
  issueForm.value.notes = `Eskalasi temuan media: ${article.title}`;
  showIssueModal.value = true;
};

const submitIssue = () => {
  const pId = props.selectedPolda?.id || props.allPoldas?.[0]?.id || 1;
  const priorityMap = {
    'HIJAU': 'low',
    'KUNING': 'high',
    'MERAH': 'critical'
  };

  router.post('/issues', {
    polda_id: pId,
    title: activeArticleForIssue.value?.title || 'Isu Temuan Media',
    summary: activeArticleForIssue.value?.summary || issueForm.value.notes,
    category: activeArticleForIssue.value?.category || 'Harkamtibmas',
    priority: priorityMap[issueForm.value.priority] || 'medium',
    sentiment: activeArticleForIssue.value?.sentiment || 'netral',
    source_media: activeArticleForIssue.value?.source_name || 'Media Online',
    source_url: activeArticleForIssue.value?.link || '',
    assigned_unit: issueForm.value.pic || 'Bidhumas Polda',
  }, {
    preserveScroll: true,
    onSuccess: () => {
      showIssueModal.value = false;
      toastMessage.value = `Berhasil membuat Isu Baru dari [${activeArticleForIssue.value?.title.substring(0, 30)}...]! Telah tersimpan di Database & Manajemen Isu.`;
      setTimeout(() => {
        toastMessage.value = '';
      }, 4500);
    }
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
    <!-- Floating Toast Notification -->
    <transition
      enter-active-class="transform ease-out duration-300 transition"
      enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
      enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="toastMessage"
        class="fixed bottom-6 right-6 z-50 max-w-md bg-emerald-950 border border-emerald-500 text-emerald-100 p-4 rounded-2xl shadow-2xl flex items-center gap-3 backdrop-blur-xl"
      >
        <CheckCircle2 class="w-6 h-6 text-emerald-400 shrink-0" />
        <p class="text-xs font-bold leading-relaxed">{{ toastMessage }}</p>
      </div>
    </transition>

    <!-- Header ringkas: 1 aksi utama + SADEWA + menu lainnya -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 md:p-5 rounded-3xl bg-gradient-to-r from-[#0d182e] via-[#0b1426] to-[#070d1d] border border-slate-800 shadow-xl relative">
      <div class="space-y-1 min-w-0">
        <div class="flex items-center gap-2 text-[11px] font-bold flex-wrap" :class="freshClass.text" role="status">
          <span class="relative flex h-2 w-2">
            <span v-if="freshState.level === 'green'" class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75" :class="freshClass.dot"></span>
            <span class="relative inline-flex rounded-full h-2 w-2" :class="freshClass.dot"></span>
          </span>
          <span>{{ freshState.label }}</span>
          <span class="text-slate-400 font-semibold">&bull; {{ portalsActive }} portal aktif</span>
          <span v-if="freshState.level !== 'green'" class="text-slate-300 font-semibold">&bull; {{ freshState.hint }}</span>
        </div>
        <h1 class="text-xl md:text-2xl font-extrabold text-white tracking-tight">Radar Media Online</h1>
        <p class="text-xs text-slate-400 truncate">
          {{ selectedCode === 'ALL' ? 'Divhumas Polri (Orkestrasi Nasional)' : selectedPolda?.name }}
        </p>
      </div>

      <div class="flex items-center gap-2 shrink-0">
        <!-- Aksi utama (hanya untuk peran berizin analisis AI) -->
        <button
          v-if="canRunAi"
          @click="triggerGeminiAnalysis(true)"
          :disabled="isAnalyzingGemini"
          class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-amber-400 to-amber-500 hover:brightness-110 text-slate-950 text-xs font-black transition shadow-lg shadow-amber-400/25 cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed select-none"
          title="Tarik berita terkini dari seluruh portal dan analisis intelijen otomatis dalam 1 klik"
        >
          <RotateCw v-if="isAnalyzingGemini" class="w-4 h-4 animate-spin" />
          <Zap v-else class="w-4 h-4 fill-slate-950" />
          <span>{{ isAnalyzingGemini ? (syncStepText || 'Memproses...') : 'Sinkron & Analisis' }}</span>
          <span v-if="props.aiSetting?.has_api_key" class="w-2 h-2 rounded-full bg-emerald-600 animate-pulse" title="Mesin Analisis AI Aktif"></span>
        </button>

        <button
          @click="openSadewaGlobal()"
          class="flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold border border-slate-700 hover:border-rose-400/60 transition cursor-pointer"
          title="Buka asisten AI SADEWA Copilot"
        >
          <Bot class="w-4 h-4 text-rose-400" />
          <span class="hidden sm:inline">SADEWA AI</span>
        </button>

        <!-- Menu lainnya -->
        <div v-if="showAnalysisTabs" class="relative">
          <button
            type="button"
            @click.stop="openMenu = openMenu === 'more' ? null : 'more'"
            class="p-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-300 hover:text-white border border-slate-700 transition cursor-pointer"
            title="Aksi lainnya"
          >
            <Ellipsis class="w-4 h-4" />
          </button>
          <div
            v-if="openMenu === 'more'"
            @click.stop
            class="absolute right-0 top-full mt-2 z-30 w-56 rounded-xl bg-[#0b1426] border border-slate-700 shadow-2xl p-1.5"
          >
            <button
              type="button"
              @click="activeTab = '02'; openMenu = null"
              class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-bold text-slate-200 hover:bg-slate-800 hover:text-white transition cursor-pointer text-left"
            >
              <MessageCircle class="w-4 h-4 text-emerald-400" />
              <span>Format WA Pimpinan</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- KPI strip: angka + sparkline 7 hari + selisih vs kemarin -->
    <div class="rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg grid grid-cols-2 lg:grid-cols-4 divide-x divide-y lg:divide-y-0 divide-slate-800 overflow-hidden">
      <div class="p-4 space-y-1.5">
        <p class="text-[11px] font-bold text-slate-400 flex items-center gap-1.5"><Newspaper class="w-3.5 h-3.5 text-sky-400" /> Berita 24 Jam</p>
        <div class="flex items-end justify-between gap-2">
          <p class="text-2xl font-black text-white leading-none">{{ stats?.last24 ?? 0 }}</p>
          <svg v-if="trend7.length > 1" viewBox="0 0 100 28" preserveAspectRatio="none" class="w-20 h-7 text-sky-400" aria-hidden="true">
            <polyline :points="sparkPoints('total')" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
          </svg>
        </div>
        <p class="text-[11px] text-slate-400">{{ deltaText(stats?.last24, stats?.prev24) }} &bull; arsip {{ stats?.total ?? 0 }}</p>
      </div>

      <button
        type="button"
        @click="activeTab = '01'; showBookmarksOnly = false; selectedTopicId = null; periodFilter = '24h'; showRedZoneOnly = true; currentPage = 1"
        class="p-4 space-y-1.5 text-left hover:bg-slate-800/40 transition cursor-pointer"
        title="Lihat berita Zona Merah 24 jam terakhir"
      >
        <p class="text-[11px] font-bold text-slate-400 flex items-center gap-1.5"><AlertTriangle class="w-3.5 h-3.5 text-rose-400" /> Zona Merah 24 Jam</p>
        <div class="flex items-end justify-between gap-2">
          <p class="text-2xl font-black leading-none" :class="(stats?.negative24 || 0) > 0 ? 'text-rose-400' : 'text-white'">{{ stats?.negative24 ?? 0 }}</p>
          <svg v-if="trend7.length > 1" viewBox="0 0 100 28" preserveAspectRatio="none" class="w-20 h-7 text-rose-400" aria-hidden="true">
            <polyline :points="sparkPoints('negatif')" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
          </svg>
        </div>
        <p class="text-[11px]" :class="(stats?.negative24 || 0) > (stats?.negative_prev24 || 0) ? 'text-rose-300 font-bold' : 'text-slate-400'">{{ deltaText(stats?.negative24, stats?.negative_prev24) }}</p>
      </button>

      <button
        type="button"
        @click="activeTab = '03'"
        class="p-4 space-y-1.5 text-left hover:bg-slate-800/40 transition cursor-pointer"
        title="Buka Early Warning System"
      >
        <p class="text-[11px] font-bold text-slate-400 flex items-center gap-1.5"><Flame class="w-3.5 h-3.5 text-amber-400" /> EWS Siaga Aktif</p>
        <p class="text-2xl font-black leading-none" :class="(metrics?.ews_red || 0) > 0 ? 'text-rose-400' : 'text-amber-400'">{{ metrics?.active_ews || 0 }}</p>
        <p class="text-[11px] text-slate-400">{{ metrics?.ews_red || 0 }} merah &bull; {{ metrics?.ews_yellow || 0 }} kuning</p>
      </button>

      <div class="p-4 space-y-1.5">
        <p class="text-[11px] font-bold text-slate-400 flex items-center gap-1.5"><TrendingUp class="w-3.5 h-3.5 text-emerald-400" /> Sentimen Positif</p>
        <p class="text-2xl font-black text-emerald-400 leading-none">{{ sentimentPositive === null ? '\u2014' : sentimentPositive + '%' }}</p>
        <p class="text-[11px] text-slate-400">{{ isDivhumas ? 'Cakupan nasional' : 'Cakupan wilayah' }}</p>
      </div>
    </div>

    <!-- Navigation Tabs Bar (4 Pilar Terpadu 01 - 04; operator hanya melihat tab Berita) -->
    <div v-if="tabs.length > 1" class="p-2 rounded-2xl bg-[#08101e] border border-slate-800 shadow-xl">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
        <button
          v-for="t in tabs"
          :key="t.id"
          @click="activeTab = t.id"
          type="button"
          class="flex items-center justify-center gap-2 px-3 py-3 rounded-xl text-[11px] md:text-xs font-extrabold text-center leading-tight transition-all duration-200 cursor-pointer"
          :class="[
            activeTab === t.id
              ? 'bg-amber-500/20 text-amber-400 border-2 border-amber-500 shadow-[0_0_15px_rgba(245,158,11,0.25)] ring-1 ring-amber-400/50'
              : 'bg-slate-900/90 text-slate-200 hover:text-white hover:bg-slate-800 border border-slate-700/80 hover:border-slate-500'
          ]"
        >
          <component
            :is="t.icon"
            class="w-4 h-4"
            :class="[
              activeTab === t.id
                ? 'text-amber-400'
                : t.id === '03'
                  ? 'text-rose-400'
                  : 'text-slate-400'
            ]"
          />
          <span>{{ t.name }}</span>
          <span
            v-if="t.badge"
            class="px-2 py-0.5 rounded-full text-[10px] font-black tracking-wider uppercase ml-0.5"
            :class="[
              t.id === '01'
                ? (freshState.level === 'green' ? 'bg-emerald-500 text-white animate-pulse' : (freshState.level === 'yellow' ? 'bg-amber-400 text-slate-950' : 'bg-rose-600 text-white'))
                : t.id === '03'
                  ? 'bg-rose-500 text-white shadow-sm'
                  : t.id === '04'
                    ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40'
                    : 'bg-amber-400 text-slate-950'
            ]"
          >
            {{ t.badge }}
          </span>
        </button>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 01: LIVE RSS MEDIA PATROL (FULL PARITAS SEPERTI SIMOD)                 -->
    <!-- ========================================================================= -->
    <div v-show="activeTab === '01'" class="space-y-5">
      <!-- Baris judul + sinkron berita ringan -->
      <div class="flex items-center justify-between gap-3">
        <h2 class="text-sm md:text-base font-black text-white tracking-tight flex items-center gap-2">
          <span class="w-2 h-2 rounded-full bg-rose-500 animate-ping"></span>
          Berita Online Real-Time
        </h2>
        <button
          @click="refreshRssFeeds"
          :disabled="isRefreshingRss"
          class="flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-xs font-bold text-white transition shadow-sm disabled:opacity-50 cursor-pointer"
          title="Tarik berita terbaru dari portal media (tanpa memanggil AI)"
        >
          <RotateCw class="w-3.5 h-3.5 text-amber-400" :class="{ 'animate-spin': isRefreshingRss }" />
          <span>{{ isRefreshingRss ? 'Menarik Berita...' : 'Sinkron Berita' }}</span>
        </button>
      </div>

      <!-- SMART DOSSIER: TOPIK ATENSI & REQUEST PIMPINAN (EXECUTIVE DOSSIER HUB) -->
      <div v-if="canManageTopics || (topics || []).length > 0" class="rounded-3xl bg-[#0b1426] border border-amber-500/30 p-5 shadow-2xl space-y-4">
        <!-- Top Bar: Title, Count Badge & Actions -->
        <div class="flex items-center justify-between gap-3 flex-wrap" :class="dossierOpen ? 'pb-3 border-b border-slate-800' : ''">
          <div class="space-y-0.5">
            <div class="flex items-center gap-2">
              <span class="flex h-2.5 w-2.5 relative">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500"></span>
              </span>
              <button type="button" @click.stop="dossierOpen = !dossierOpen" class="text-sm md:text-base font-extrabold text-white flex items-center gap-2 flex-wrap cursor-pointer select-none text-left" :aria-expanded="dossierOpen">
                <span>Topik Atensi Pimpinan</span>
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold bg-slate-900 border border-slate-700 text-slate-300 flex items-center gap-1.5">
                  <span>{{ topics.length }} kasus</span>
                  <span v-if="topics.some(t => t.priority === 'urgent')" class="text-rose-400 font-black">
                    &bull; {{ topics.filter(t => t.priority === 'urgent').length }} mendesak
                  </span>
                </span>
                <ChevronDown class="w-4 h-4 text-slate-400 transition-transform" :class="dossierOpen ? 'rotate-180' : ''" />
              </button>
            </div>
          </div>

          <div class="flex items-center gap-2 shrink-0 flex-wrap">
            <!-- Smart Toggle Sugesti Tren AI -->
            <button
              v-if="localSuggestedTopics.length > 0"
              @click="showSuggestedTopicsTray = !showSuggestedTopicsTray"
              type="button"
              class="suggestion-toggle flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold transition cursor-pointer select-none border"
              :class="showSuggestedTopicsTray
                ? 'bg-cyan-500 text-slate-950 border-cyan-400 shadow-md font-black'
                : 'bg-violet-950/40 hover:bg-violet-900/50 text-cyan-300 border-violet-500/40 hover:border-cyan-400/60'"
              title="Buka / tutup rekomendasi tren isu Google News AI"
            >
              <Sparkles class="w-3.5 h-3.5" :class="showSuggestedTopicsTray ? 'text-slate-950' : 'text-cyan-400 animate-pulse'" />
              <span>{{ showSuggestedTopicsTray ? 'Tutup Rekomendasi' : '✨ ' + localSuggestedTopics.length + ' Rekomendasi AI' }}</span>
            </button>

            <!-- Buat Topik Atensi Baru Button -->
            <button
              @click="openCreateTopicModal"
              class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-amber-400 hover:brightness-110 text-slate-950 text-xs font-black shadow-md shadow-amber-500/20 transition cursor-pointer select-none"
            >
              <PlusCircle class="w-3.5 h-3.5" />
              <span>+ Buat Topik Atensi</span>
            </button>
          </div>
        </div>

        <div v-show="dossierOpen" class="space-y-4">
        <!-- KASUS ATENSI SELECTOR (ADAPTIVE RESPONSIVE FLEX BADGES - RAPI & TIDAK MENUMPUK) -->
        <div class="space-y-2">
          <!-- Empty State jika belum ada topik -->
          <div
            v-if="topics.length === 0"
            class="p-4 rounded-2xl bg-[#070d19]/80 border border-dashed border-slate-700/80 text-center space-y-1.5"
          >
            <p class="text-xs font-bold text-slate-300">Belum ada topik atensi pimpinan yang aktif.</p>
            <p class="text-[11px] text-slate-500">
              Klik <strong>"+ Buat Topik Atensi"</strong> di atas atau adopsi rekomendasi AI untuk memantau kasus viral secara terisolasi.
            </p>
          </div>

          <!-- Adaptive Case Badges List -->
          <div v-else class="flex flex-wrap items-center gap-2.5">
            <div
              v-for="t in topics"
              :key="t.id"
              class="group relative inline-flex items-center rounded-2xl transition-all shadow-sm border"
              :class="selectedTopicId === t.id
                ? 'bg-amber-400 border-amber-300 text-slate-950 font-black shadow-lg shadow-amber-400/20 ring-2 ring-amber-400/40'
                : t.priority === 'urgent'
                  ? 'bg-[#18111e] hover:bg-[#22132b] border-rose-500/50 hover:border-rose-400 text-rose-200'
                  : 'bg-[#070d19] hover:bg-slate-800 border-slate-700/80 hover:border-amber-400/60 text-slate-200'"
            >
              <!-- Main Click Area -->
              <button
                @click="selectedTopicId = (selectedTopicId === t.id ? null : t.id); selectedPortal = 'Semua Portal'; showBookmarksOnly = false; showRedZoneOnly = false; currentPage = 1"
                type="button"
                class="px-3.5 py-2 text-xs font-bold transition flex items-center gap-2 cursor-pointer select-none text-left"
              >
                <!-- Priority Dot -->
                <span
                  class="w-2.5 h-2.5 rounded-full shrink-0"
                  :class="selectedTopicId === t.id
                    ? 'bg-slate-950'
                    : t.priority === 'urgent' ? 'bg-rose-500 animate-pulse' : 'bg-amber-400'"
                ></span>

                <!-- Title (Generous width, clean reading) -->
                <span
                  class="truncate max-w-[280px] sm:max-w-[340px] md:max-w-[420px] font-bold"
                  :class="selectedTopicId === t.id ? 'text-slate-950' : 'text-slate-100'"
                  :title="t.title"
                >
                  {{ t.title }}
                </span>

                <!-- Article Count Badge -->
                <span
                  class="text-[10px] px-2 py-0.5 rounded-full font-mono font-black shrink-0"
                  :class="selectedTopicId === t.id
                    ? 'bg-slate-950 text-amber-300'
                    : t.priority === 'urgent' ? 'bg-rose-500/30 text-rose-300' : 'bg-slate-800 text-amber-300 border border-slate-700'"
                >
                  {{ t.articles_count || 0 }} Berita
                </span>
              </button>

              <!-- Delete Action Button -->
              <div class="flex items-center pr-1 border-l" :class="selectedTopicId === t.id ? 'border-slate-950/20' : 'border-slate-800'">
                <button
                  @click.stop="deleteTopic(t)"
                  type="button"
                  class="p-1.5 rounded-xl hover:bg-black/20 transition cursor-pointer"
                  :class="selectedTopicId === t.id ? 'text-slate-950 hover:text-rose-950' : 'text-slate-400 hover:text-rose-400'"
                  title="Hapus Kasus Atensi Ini"
                >
                  <X class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>

            <!-- Quick Reset Button when a topic is filtered -->
            <button
              v-if="selectedTopicId !== null"
              @click="selectedTopicId = null; currentPage = 1"
              type="button"
              class="px-3 py-2 rounded-2xl text-xs font-bold bg-slate-800 hover:bg-slate-700 border border-slate-600 text-slate-300 hover:text-white transition flex items-center gap-1.5 cursor-pointer shadow-sm select-none"
              title="Tutup filter kasus dan tampilkan semua berita radar"
            >
              <X class="w-3.5 h-3.5" />
              <span>Tutup Filter Kasus</span>
            </button>
          </div>
        </div>

        <!-- BARIS SUGESTI TOPIK POTENSIAL / TREN GOOGLE NEWS (COLLAPSIBLE TRAY) -->
        <div
          v-if="showSuggestedTopicsTray && localSuggestedTopics.length > 0"
          class="p-3.5 rounded-2xl bg-gradient-to-r from-violet-950/40 via-[#0a1122] to-cyan-950/30 border border-violet-500/40 space-y-2.5 shadow-lg relative overflow-hidden"
        >
          <!-- Header Bar Sugesti -->
          <div class="flex items-center justify-between text-xs gap-2 flex-wrap">
            <div class="flex items-center gap-2">
              <span class="flex h-2 w-2 relative">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-cyan-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-cyan-400"></span>
              </span>
              <span class="font-extrabold text-cyan-300 uppercase tracking-wider text-[11px] flex items-center gap-1.5">
                <Sparkles class="w-3.5 h-3.5 text-cyan-400" />
                <span>Sugesti Isu Potensial &amp; Tren Media Online (Google News AI)</span>
              </span>
              <span class="px-2 py-0.5 rounded-full text-[9px] font-mono font-bold bg-violet-500/20 text-violet-300 border border-violet-500/40">
                {{ localSuggestedTopics.length }} Rekomendasi
              </span>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-[10px] text-slate-400 hidden sm:inline">
                Klik <strong>"✓ Pantau"</strong> untuk otomatis menjadikannya Topik Atensi &amp; menarik sebaran Google News.
              </span>
              <button
                @click="showSuggestedTopicsTray = false"
                type="button"
                class="px-2 py-0.5 rounded-lg bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white text-[10px] font-bold flex items-center gap-1 cursor-pointer transition"
                title="Tutup baki rekomendasi"
              >
                <X class="w-3 h-3" />
                <span>Tutup</span>
              </button>
            </div>
          </div>

          <!-- Sugesti Pills List -->
          <div class="flex items-center gap-2.5 overflow-x-auto pb-1 scrollbar-thin">
            <div
              v-for="st in localSuggestedTopics"
              :key="st.title"
              class="p-2.5 rounded-xl bg-slate-900/95 border border-violet-500/30 hover:border-cyan-400/60 transition-all shadow-md shrink-0 flex items-center gap-3 group max-w-md"
            >
              <div class="space-y-0.5 min-w-[200px] max-w-[260px]">
                <div class="flex items-center gap-1.5 flex-wrap">
                  <span
                    class="px-1.5 py-0.2 rounded text-[8px] font-black uppercase tracking-wider"
                    :class="st.priority === 'urgent' ? 'bg-rose-500 text-white' : 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/40'"
                  >
                    {{ st.priority === 'urgent' ? '🚨 MENDESAK' : '⚡ TREN BARU' }}
                  </span>
                  <span v-if="st.velocity" class="text-[9px] font-mono text-emerald-400 font-bold">
                    {{ st.velocity }}
                  </span>
                </div>
                <h5 class="text-xs font-black text-white truncate" :title="st.title">
                  {{ st.title }}
                </h5>
                <p v-if="st.rationale" class="text-[10px] text-slate-300 line-clamp-1 italic" :title="st.rationale">
                  {{ st.rationale }}
                </p>
              </div>

              <!-- Action Buttons -->
              <div class="flex items-center gap-1.5 shrink-0">
                <!-- 1-Click Adopt Button -->
                <button
                  @click="adoptSuggestion(st)"
                  :disabled="isAdoptingSuggestion && adoptingTitle === st.title"
                  type="button"
                  class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-gradient-to-r from-cyan-500 to-blue-600 hover:brightness-110 text-white text-[10px] font-black shadow-sm transition cursor-pointer select-none disabled:opacity-50"
                  title="Jadikan Topik Atensi Baru & Tarik Berita Internet Sekarang"
                >
                  <RotateCw v-if="isAdoptingSuggestion && adoptingTitle === st.title" class="w-3 h-3 animate-spin text-white" />
                  <Check v-else class="w-3 h-3 text-white" />
                  <span>{{ isAdoptingSuggestion && adoptingTitle === st.title ? 'Menarik...' : 'Pantau' }}</span>
                </button>

                <!-- Dismiss Button -->
                <button
                  @click="dismissSuggestion(st)"
                  type="button"
                  class="p-1 rounded-lg hover:bg-rose-500/20 text-slate-400 hover:text-rose-400 transition cursor-pointer"
                  title="Abaikan sugesti ini"
                >
                  <X class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- DOSSIER DETAIL CARD (2-COLUMN BALANCED RESPONSIVE LAYOUT) -->
        <div
          v-if="currentSelectedTopic"
          class="rounded-3xl bg-gradient-to-b from-[#0e172a] via-[#0b1426] to-[#070d1d] border border-amber-500/40 p-5 md:p-6 shadow-2xl space-y-4 relative overflow-hidden"
        >
          <!-- Background Glow -->
          <div class="absolute -top-12 -right-12 w-64 h-64 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>

          <!-- TOP BAR: HEADER & UTILITY CONTROLS -->
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-800/80 relative z-10">
            <!-- Left: Priority Badge + Title + Article Count -->
            <div class="flex items-center gap-2.5 flex-wrap">
              <span
                class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider shadow-sm"
                :class="currentSelectedTopic.priority === 'urgent' ? 'bg-rose-500 text-white shadow-rose-500/20' : 'bg-amber-400 text-slate-950 shadow-amber-400/20'"
              >
                {{ currentSelectedTopic.priority === 'urgent' ? '🚨 SANGAT MENDESAK' : '⭐ PRIORITAS TINGGI' }}
              </span>
              <h3 class="text-base md:text-lg font-black text-white tracking-tight">
                {{ currentSelectedTopic.title }}
              </h3>
              <span class="px-2.5 py-0.5 rounded-full text-[11px] font-mono font-bold bg-amber-500/15 text-amber-300 border border-amber-400/30 shadow-xs">
                {{ currentSelectedTopic.articles_count || 0 }} Berita Terkait
              </span>
            </div>

            <!-- Right: Utility Actions (Edit, Hapus, Tutup) -->
            <div class="flex items-center gap-2 shrink-0 self-end sm:self-auto">
              <button
                @click="openEditTopicModal(currentSelectedTopic)"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900/90 hover:bg-slate-800 text-slate-300 hover:text-white border border-slate-700/80 text-xs font-bold transition cursor-pointer select-none"
                title="Edit Judul & Kata Kunci"
              >
                <Edit3 class="w-3.5 h-3.5 text-amber-400" />
                <span>Edit Kata Kunci</span>
              </button>

              <button
                @click="deleteTopic(currentSelectedTopic)"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900/90 hover:bg-rose-950/60 text-slate-300 hover:text-rose-400 border border-slate-700/80 hover:border-rose-500/50 text-xs font-bold transition cursor-pointer select-none"
                title="Hapus Topik Atensi Ini"
              >
                <Trash2 class="w-3.5 h-3.5 text-rose-500" />
                <span class="hidden sm:inline">Hapus</span>
              </button>

              <button
                @click="selectedTopicId = null; currentPage = 1"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700 text-xs font-bold transition cursor-pointer select-none"
                title="Tutup Filter Topik"
              >
                <X class="w-3.5 h-3.5" />
                <span>Tutup</span>
              </button>
            </div>
          </div>

          <!-- 2-COLUMN BALANCED CONTENT (GRID) -->
          <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 relative z-10">
            <!-- LEFT COLUMN (COL-SPAN-5): DIRECTIVE, KEYWORDS CLOUD & POLARISASI SENTIMEN -->
            <div class="lg:col-span-5 flex flex-col justify-between space-y-4">
              <div class="space-y-3">
                <!-- Pimpinan Directive Quote (if present) -->
                <div
                  v-if="currentSelectedTopic.description"
                  class="p-3 rounded-xl bg-amber-500/5 border-l-4 border-amber-400 text-xs text-amber-100/90 leading-relaxed italic shadow-xs"
                >
                  "{{ currentSelectedTopic.description }}"
                </div>

                <!-- Monitored Keywords Cloud -->
                <div class="space-y-1.5">
                  <div class="flex items-center justify-between text-[11px] font-bold text-slate-400">
                    <span class="flex items-center gap-1">
                      <Hash class="w-3.5 h-3.5 text-amber-400" />
                      <span>Kata Kunci Pantau:</span>
                    </span>
                    <button
                      @click="openEditTopicModal(currentSelectedTopic)"
                      type="button"
                      class="text-amber-400 hover:text-amber-300 text-[10px] font-bold hover:underline cursor-pointer"
                    >
                      + Tambah
                    </button>
                  </div>
                  <div class="flex items-center gap-1.5 flex-wrap">
                    <span
                      v-for="kw in (currentSelectedTopic.keywords_array || [])"
                      :key="kw"
                      class="px-2 py-0.5 rounded-md text-[10px] font-mono bg-slate-900/90 text-slate-200 border border-slate-700/80 hover:border-amber-400/50 flex items-center gap-1 shadow-sm transition"
                    >
                      <span class="text-amber-400/80">#</span>
                      <span class="font-bold">{{ kw }}</span>
                      <button
                        @click.stop="removeKeywordFromTopic(currentSelectedTopic, kw)"
                        type="button"
                        class="p-0.5 hover:bg-rose-500/20 text-slate-400 hover:text-rose-400 rounded transition cursor-pointer"
                        title="Hapus kata kunci ini"
                      >
                        <X class="w-2.5 h-2.5" />
                      </button>
                    </span>
                  </div>
                </div>
              </div>

              <!-- Polarisasi Sentimen Mini-Card (Jika AI Analysis Ada) -->
              <div
                v-if="currentSelectedTopic.ai_analysis"
                class="p-3 rounded-2xl bg-slate-950/60 border border-slate-800/80 space-y-2 mt-auto"
              >
                <div class="flex items-center justify-between text-xs">
                  <div class="flex items-center gap-1.5">
                    <span class="text-slate-400 font-bold text-[11px]">Polarisasi Sentimen:</span>
                    <span
                      class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-wider"
                      :class="getTopicSentiment(currentSelectedTopic.ai_analysis).dominant.toLowerCase() === 'positif'
                        ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'
                        : getTopicSentiment(currentSelectedTopic.ai_analysis).dominant.toLowerCase() === 'negatif'
                          ? 'bg-rose-500/20 text-rose-400 border border-rose-500/30'
                          : 'bg-amber-400/20 text-amber-300 border border-amber-400/30'"
                    >
                      {{ getTopicSentiment(currentSelectedTopic.ai_analysis).dominant }}
                    </span>
                  </div>
                  <div class="flex items-center gap-2 text-[11px] font-mono font-bold">
                    <span class="text-emerald-400">{{ getTopicSentiment(currentSelectedTopic.ai_analysis).positif }}% Pos</span>
                    <span class="text-slate-400">{{ getTopicSentiment(currentSelectedTopic.ai_analysis).netral }}% Net</span>
                    <span class="text-rose-400">{{ getTopicSentiment(currentSelectedTopic.ai_analysis).negatif }}% Neg</span>
                  </div>
                </div>

                <!-- Proportional Bar -->
                <div class="h-2 w-full rounded-full bg-slate-800 overflow-hidden flex shadow-inner">
                  <div
                    :style="{ width: getTopicSentiment(currentSelectedTopic.ai_analysis).positif + '%' }"
                    class="bg-emerald-500 transition-all duration-500"
                    :title="'Positif: ' + getTopicSentiment(currentSelectedTopic.ai_analysis).positif + '%'"
                  ></div>
                  <div
                    :style="{ width: getTopicSentiment(currentSelectedTopic.ai_analysis).netral + '%' }"
                    class="bg-slate-500 transition-all duration-500"
                    :title="'Netral: ' + getTopicSentiment(currentSelectedTopic.ai_analysis).netral + '%'"
                  ></div>
                  <div
                    :style="{ width: getTopicSentiment(currentSelectedTopic.ai_analysis).negatif + '%' }"
                    class="bg-rose-500 transition-all duration-500"
                    :title="'Negatif: ' + getTopicSentiment(currentSelectedTopic.ai_analysis).negatif + '%'"
                  ></div>
                </div>
              </div>
            </div>

            <!-- RIGHT COLUMN (COL-SPAN-7): COMMAND ACTIONS & EXECUTIVE SUMMARY -->
            <div class="lg:col-span-7 flex flex-col justify-between space-y-3.5">
              <!-- Command Action Buttons Bar -->
              <div class="p-2.5 rounded-2xl bg-[#070d19]/80 border border-slate-800/90 flex flex-wrap items-center justify-between gap-2">
                <!-- Group AI Actions -->
                <div class="flex items-center gap-2 flex-wrap">
                  <template v-if="currentSelectedTopic.ai_analysis">
                    <button
                      @click="openCachedTopicAi(currentSelectedTopic)"
                      type="button"
                      class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-gradient-to-r from-amber-500 via-amber-400 to-amber-500 hover:brightness-110 text-slate-950 text-xs font-black shadow-md shadow-amber-500/20 transition cursor-pointer select-none"
                      title="Buka hasil analisis intelijen yang tersimpan (instan, 0 kuota token)"
                    >
                      <Eye class="w-3.5 h-3.5 text-slate-950" />
                      <span>Lihat Analisis AI</span>
                    </button>

                    <button
                      @click="runTopicAiAnalysis(currentSelectedTopic, true)"
                      :disabled="isAnalyzingTopic"
                      type="button"
                      class="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-200 hover:text-white border border-slate-700 text-xs font-bold transition cursor-pointer disabled:opacity-50 select-none"
                      :title="getTopicNewArticlesCount(currentSelectedTopic) > 0 ? `Ada ${getTopicNewArticlesCount(currentSelectedTopic)} berita baru sejak analisis terakhir. Klik untuk memperbarui!` : 'Panggil ulang Gemini AI untuk menyerap berita terkini'"
                    >
                      <RotateCw class="w-3 h-3 text-amber-400" :class="{ 'animate-spin': isAnalyzingTopic && activeAnalyzingTopic?.id === currentSelectedTopic.id }" />
                      <span>{{ (isAnalyzingTopic && activeAnalyzingTopic?.id === currentSelectedTopic.id) ? 'Menganalisis...' : 'Perbarui' }}</span>
                      <span
                        v-if="getTopicNewArticlesCount(currentSelectedTopic) > 0"
                        class="px-1.5 py-0.2 rounded-full text-[9px] bg-rose-500 text-white font-mono font-bold animate-pulse"
                      >
                        +{{ getTopicNewArticlesCount(currentSelectedTopic) }}
                      </span>
                    </button>
                  </template>

                  <template v-else>
                    <button
                      @click="runTopicAiAnalysis(currentSelectedTopic, false)"
                      :disabled="isAnalyzingTopic"
                      type="button"
                      class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-gradient-to-r from-amber-400 to-amber-500 hover:brightness-110 text-slate-950 text-xs font-black shadow-md shadow-amber-500/20 transition cursor-pointer disabled:opacity-50 select-none"
                      title="Jalankan analisis AI khusus untuk seluruh berita dalam kasus ini"
                    >
                      <Zap class="w-3.5 h-3.5 fill-slate-950" :class="{ 'animate-pulse': isAnalyzingTopic && activeAnalyzingTopic?.id === currentSelectedTopic.id }" />
                      <span>{{ (isAnalyzingTopic && activeAnalyzingTopic?.id === currentSelectedTopic.id) ? 'Sedang Menganalisis...' : '⚡ Analisis Kasus Ini' }}</span>
                    </button>
                  </template>
                </div>

                <!-- Aksi lain dalam satu menu -->
                <div class="relative">
                  <button
                    type="button"
                    @click.stop="openMenu = openMenu === 'topic' ? null : 'topic'"
                    class="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-200 hover:text-white border border-slate-700 text-xs font-bold transition cursor-pointer select-none"
                    title="Tarik berita internet, salin naskah WhatsApp"
                  >
                    <Ellipsis class="w-4 h-4" />
                    <span>Lainnya</span>
                  </button>
                  <div
                    v-if="openMenu === 'topic'"
                    @click.stop
                    class="absolute right-0 top-full mt-2 z-30 w-60 rounded-xl bg-[#0b1426] border border-slate-700 shadow-2xl p-1.5"
                  >
                    <button
                      type="button"
                      @click="openLiveCrawlModal(currentSelectedTopic); openMenu = null"
                      :disabled="isCrawlingLive"
                      class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-bold text-slate-200 hover:bg-slate-800 hover:text-white transition cursor-pointer text-left disabled:opacity-50"
                    >
                      <Globe class="w-4 h-4 text-cyan-400" :class="{ 'animate-spin': isCrawlingLive && crawlingTopic?.id === currentSelectedTopic.id }" />
                      <span>{{ (isCrawlingLive && crawlingTopic?.id === currentSelectedTopic.id) ? 'Menarik...' : 'Tarik Berita Internet' }}</span>
                    </button>
                    <button
                      type="button"
                      @click="copyTopicWaScript(currentSelectedTopic); openMenu = null"
                      class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-bold text-slate-200 hover:bg-slate-800 hover:text-white transition cursor-pointer text-left"
                    >
                      <MessageSquare class="w-4 h-4 text-emerald-400" />
                      <span>Salin Naskah WA</span>
                    </button>
                  </div>
                </div>
              </div>

              <!-- Callout if 0 articles in local radar -->
              <div
                v-if="!currentSelectedTopic.articles_count || currentSelectedTopic.articles_count === 0"
                class="p-3 rounded-xl bg-blue-950/40 border border-blue-500/30 flex items-center justify-between gap-3 text-xs"
              >
                <div class="space-y-0.5">
                  <p class="font-bold text-white text-[11px]">Belum ada berita lokal terkait topik ini</p>
                  <p class="text-[10px] text-slate-300">Tarik liputan berita dari seluruh portal Indonesia via Google News.</p>
                </div>
                <button
                  @click="openLiveCrawlModal(currentSelectedTopic)"
                  :disabled="isCrawlingLive"
                  type="button"
                  class="px-2.5 py-1.5 rounded-lg bg-cyan-500 hover:bg-cyan-400 text-slate-950 text-[10px] font-black transition cursor-pointer shrink-0 disabled:opacity-50"
                >
                  ⚡ Tarik Sekarang
                </button>
              </div>

              <!-- Executive Summary Card (if AI analysis exists) -->
              <div
                v-if="currentSelectedTopic.ai_analysis"
                class="p-3.5 rounded-2xl bg-slate-900/90 border border-slate-800 space-y-2 text-xs shadow-md"
              >
                <div class="flex items-center justify-between gap-2 flex-wrap">
                  <div class="flex items-center gap-2">
                    <span class="flex items-center gap-1 text-[11px] font-bold text-amber-400 uppercase tracking-wider">
                      <FileText class="w-3 h-3 text-amber-400" />
                      <span>Ringkasan Eksekutif AI</span>
                    </span>
                    <span
                      v-if="getTopicNewArticlesCount(currentSelectedTopic) > 0"
                      class="px-2 py-0.2 rounded-full text-[9px] font-black bg-rose-500/20 text-rose-300 border border-rose-500/40 animate-pulse"
                    >
                      +{{ getTopicNewArticlesCount(currentSelectedTopic) }} Baru
                    </span>
                    <span
                      v-else
                      class="px-2 py-0.2 rounded-full text-[9px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30"
                    >
                      ✓ Terkini
                    </span>
                  </div>

                  <button
                    @click="openCachedTopicAi(currentSelectedTopic)"
                    type="button"
                    class="text-amber-400 hover:text-amber-300 font-extrabold hover:underline flex items-center gap-1 cursor-pointer select-none text-[11px]"
                  >
                    <span>Analisis Lengkap</span>
                    <ArrowRight class="w-3 h-3" />
                  </button>
                </div>

                <p class="text-slate-300 text-[11px] leading-relaxed line-clamp-2 pl-3 border-l-2 border-amber-400/50 italic">
                  "{{ currentSelectedTopic.ai_analysis.executive_summary }}"
                </p>
              </div>
            </div>
          </div>
        </div>
        </div>
      </div>

      <!-- UNIFIED RADAR FEED TOOLBAR (COMMAND BAR TERPADU) -->
      <div class="p-3.5 rounded-2xl bg-[#0b1426] border border-slate-800/90 shadow-xl flex flex-wrap items-center justify-between gap-3">
        <!-- LEFT: QUICK STATUS PILLS & DROPDOWNS -->
        <div class="flex items-center gap-2 flex-wrap">
          <!-- Status Pill 1: Semua Berita -->
          <button
            type="button"
            @click="showBookmarksOnly = false; showRedZoneOnly = false; selectedTopicId = null; currentPage = 1"
            class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-black transition cursor-pointer select-none border"
            :class="(!showBookmarksOnly && !showRedZoneOnly && selectedTopicId === null)
              ? 'bg-amber-400 text-slate-950 border-amber-300 shadow-md shadow-amber-400/20'
              : 'bg-[#070d19] text-slate-300 border-slate-700 hover:border-slate-600 hover:text-white'"
            title="Tampilkan semua berita radar"
          >
            <Globe class="w-3.5 h-3.5" :class="(!showBookmarksOnly && !showRedZoneOnly && selectedTopicId === null) ? 'text-slate-950' : 'text-slate-400'" />
            <span>Semua Berita</span>
            <span
              class="px-1.5 py-0.5 rounded-full text-[10px] font-mono"
              :class="(!showBookmarksOnly && !showRedZoneOnly && selectedTopicId === null)
                ? 'bg-slate-950 text-amber-300 font-black'
                : 'bg-slate-800 text-slate-300 font-bold'"
            >
              {{ allCount }}
            </span>
          </button>

          <!-- Status Pill 2: Zona Merah (Isu Sensitif/Negatif) -->
          <button
            type="button"
            @click="showRedZoneOnly = !showRedZoneOnly; if (showRedZoneOnly) { showBookmarksOnly = false; } currentPage = 1"
            class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-black transition cursor-pointer select-none border"
            :class="showRedZoneOnly
              ? 'bg-gradient-to-r from-red-600 to-rose-700 text-white border-red-500 shadow-md shadow-red-600/30 ring-2 ring-red-400'
              : 'bg-[#070d19] text-rose-400 border-rose-500/40 hover:border-rose-400 hover:bg-rose-950/30'"
            title="Tampilkan hanya berita bersentimen negatif / isu rawan kamtibmas"
          >
            <span class="relative flex h-2 w-2">
              <span v-if="showRedZoneOnly || redZoneCount > 0" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500"></span>
            </span>
            <AlertTriangle class="w-3.5 h-3.5" :class="showRedZoneOnly ? 'fill-white text-rose-700' : 'text-rose-400'" />
            <span>Zona Merah</span>
            <span
              class="px-1.5 py-0.5 rounded-full text-[10px] font-mono font-extrabold"
              :class="showRedZoneOnly ? 'bg-white text-rose-700' : 'bg-rose-500/20 text-rose-300'"
            >
              {{ redZoneCount }}
            </span>
          </button>

          <!-- Status Pill 3: Tersimpan (Bookmark) -->
          <button
            type="button"
            @click="showBookmarksOnly = !showBookmarksOnly; if (showBookmarksOnly) { showRedZoneOnly = false; } currentPage = 1"
            class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-black transition cursor-pointer select-none border"
            :class="showBookmarksOnly
              ? 'bg-amber-400 text-slate-950 border-amber-300 shadow-md shadow-amber-400/20'
              : 'bg-[#070d19] text-amber-300 border-amber-400/40 hover:border-amber-400 hover:bg-slate-800'"
            title="Tampilkan hanya berita yang ditandai / disimpan"
          >
            <Bookmark class="w-3.5 h-3.5" :class="showBookmarksOnly ? 'fill-slate-950' : ''" />
            <span>Tersimpan</span>
            <span
              class="px-1.5 py-0.5 rounded-full text-[10px] font-mono font-bold"
              :class="showBookmarksOnly ? 'bg-slate-950 text-amber-300' : 'bg-amber-400/20 text-amber-300'"
            >
              {{ facets?.bookmarks ?? 0 }}
            </span>
          </button>

          <!-- Vertical Divider -->
          <div class="h-6 w-px bg-slate-800 hidden xl:block"></div>

          <!-- Filter: Portal, Kategori, Baris dalam satu panel -->
          <div class="relative">
            <button
              type="button"
              @click.stop="showFilterPanel = !showFilterPanel"
              class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-black transition cursor-pointer select-none border"
              :class="activeFilterCount > 0
                ? 'bg-amber-400/15 text-amber-300 border-amber-400/60'
                : 'bg-[#070d19] text-slate-300 border-slate-700 hover:border-slate-600 hover:text-white'"
              title="Filter portal, kategori, dan jumlah baris"
            >
              <SlidersHorizontal class="w-3.5 h-3.5" />
              <span>Filter</span>
              <span v-if="activeFilterCount > 0" class="px-1.5 py-0.5 rounded-full text-[10px] font-mono bg-amber-400 text-slate-950 font-black">{{ activeFilterCount }}</span>
            </button>

            <div
              v-if="showFilterPanel"
              @click.stop
              class="absolute left-0 top-full mt-2 z-30 w-[min(92vw,22rem)] rounded-2xl bg-[#0b1426] border border-slate-700 shadow-2xl p-4 space-y-3"
            >
              <label class="block space-y-1">
                <span class="text-[11px] font-bold text-slate-400">Portal</span>
                <select
                  v-model="selectedPortal"
                  @change="selectedTopicId = null; currentPage = 1"
                  class="w-full px-2.5 py-2 rounded-xl text-xs font-bold bg-[#070d19] border border-slate-700 text-white focus:outline-none focus:border-amber-400 cursor-pointer"
                >
                  <option value="Semua Portal">Semua Portal ({{ facets?.all ?? 0 }})</option>
                  <option v-for="p in availablePortals" :key="p.name" :value="p.name">{{ p.name }} ({{ p.count }})</option>
                </select>
              </label>

              <label class="block space-y-1">
                <span class="text-[11px] font-bold text-slate-400">Kategori</span>
                <select
                  v-model="selectedCategory"
                  @change="currentPage = 1"
                  class="w-full px-2.5 py-2 rounded-xl text-xs font-bold bg-[#070d19] border border-slate-700 text-white focus:outline-none focus:border-amber-400 cursor-pointer"
                >
                  <option value="all">Semua ({{ categoryTotal }})</option>
                  <option v-for="cat in availableCategories" :key="cat.name" :value="cat.name">{{ cat.name }} ({{ cat.count }})</option>
                </select>
              </label>

              <label class="block space-y-1">
                <span class="text-[11px] font-bold text-slate-400">Sentimen</span>
                <select
                  v-model="selectedSentiment"
                  @change="showRedZoneOnly = false"
                  class="w-full px-2.5 py-2 rounded-xl text-xs font-bold bg-[#070d19] border border-slate-700 text-white focus:outline-none focus:border-amber-400 cursor-pointer"
                >
                  <option value="all">Semua ({{ allCount }})</option>
                  <option value="positif">Positif ({{ facets?.sentiments?.positif ?? 0 }})</option>
                  <option value="netral">Netral ({{ facets?.sentiments?.netral ?? 0 }})</option>
                  <option value="negatif">Negatif ({{ facets?.sentiments?.negatif ?? 0 }})</option>
                </select>
              </label>

              <label v-if="isNational && (facets?.wilayah || []).length" class="block space-y-1">
                <span class="text-[11px] font-bold text-slate-400">Wilayah (Polda)</span>
                <select
                  v-model.number="wilayahFilter"
                  class="w-full px-2.5 py-2 rounded-xl text-xs font-bold bg-[#070d19] border border-slate-700 text-white focus:outline-none focus:border-amber-400 cursor-pointer"
                >
                  <option :value="0">Seluruh wilayah ({{ facets?.all ?? 0 }})</option>
                  <option v-for="w in facets.wilayah" :key="w.id" :value="w.id">{{ w.name }} ({{ w.count }})</option>
                </select>
              </label>

              <label class="flex items-start gap-2 cursor-pointer select-none">
                <input v-model="groupDuplicates" type="checkbox" class="mt-0.5 accent-amber-400" />
                <span class="text-[11px] font-bold text-slate-300">Gabungkan berita ganda lintas portal
                  <span class="block font-medium text-slate-500">Satu kartu untuk berita berjudul sama; portal lain ditampilkan di menu kartu.</span>
                </span>
              </label>

              <label class="block space-y-1">
                <span class="text-[11px] font-bold text-slate-400 flex items-center gap-1"><Layers class="w-3.5 h-3.5 text-amber-400" /> Baris per halaman</span>
                <select
                  v-model.number="selectedRows"
                  @change="onRowsChange"
                  class="w-full px-2.5 py-2 rounded-xl text-xs font-bold bg-[#070d19] border border-slate-700 text-white focus:outline-none focus:border-amber-400 cursor-pointer"
                >
                  <option :value="3">3 Baris (9)</option>
                  <option :value="5">5 Baris (15) &mdash; Default</option>
                  <option :value="8">8 Baris (24)</option>
                  <option :value="10">10 Baris (30)</option>
                  <option :value="15">15 Baris (45)</option>
                  <option :value="17">17 Baris (50)</option>
                </select>
              </label>

              <div class="flex items-center justify-between pt-1">
                <button type="button" @click="resetPanelFilters()" class="text-[11px] font-bold text-slate-400 hover:text-white cursor-pointer">Atur ulang</button>
                <button type="button" @click="showFilterPanel = false" class="px-3 py-1.5 rounded-lg bg-amber-400 text-slate-950 text-xs font-black cursor-pointer">Selesai</button>
              </div>
            </div>
          </div>
        </div>

        <!-- RIGHT: SEARCH & COUNT INDICATOR -->
        <div class="flex items-center gap-2 flex-1 justify-end min-w-[260px]">
          <!-- Search Input with Instant Clear -->
          <div class="relative flex-1 max-w-xs">
            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
            <input
              v-model="searchInput"
              @keyup.enter="handleSearch"
              type="text"
              maxlength="200"
              placeholder='Cari: kata, "frasa", -kecualikan (Enter)'
              title='Contoh: kapolri "jenderal bintang 4" -hoaks &#10;Semua kata harus ada. Tanda kutip = frasa persis. Awalan minus = kecualikan.'
              class="w-full pl-9 pr-8 py-2 rounded-xl bg-[#070d19] border border-slate-700 text-xs text-white placeholder-slate-400 focus:outline-none focus:border-amber-400"
            />
            <button
              v-if="searchInput"
              @click="searchInput = ''; searchQuery = ''; currentPage = 1"
              type="button"
              class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white cursor-pointer"
              title="Bersihkan pencarian"
            >
              <X class="w-3 h-3" />
            </button>
          </div>

          <!-- Tombol Cari -->
          <button
            @click="handleSearch"
            type="button"
            class="px-4 py-2 rounded-xl bg-[#0d182e] hover:bg-slate-800 text-amber-400 border border-amber-400/50 hover:border-amber-400 text-xs font-black transition shadow-sm cursor-pointer"
          >
            Cari
          </button>

          <!-- Count Indicator -->
          <div class="text-xs text-slate-400 shrink-0 whitespace-nowrap hidden lg:flex items-center gap-1.5 pl-2 border-l border-slate-800">
            <span>Menampilkan</span>
            <strong class="text-amber-400 font-extrabold">{{ articlesMeta?.from ?? 0 }}&ndash;{{ articlesMeta?.to ?? 0 }}</strong>
            <span>dari</span>
            <strong class="text-white font-extrabold">{{ articlesMeta?.total ?? 0 }}</strong>
          </div>
        </div>
      </div>

      <!-- Baris kendali: periode, urutan, tampilan, pintasan -->
      <div class="flex flex-wrap items-center gap-2 -mt-2">
        <div class="flex items-center gap-1 p-1 rounded-xl bg-[#070d19] border border-slate-800" role="group" aria-label="Periode berita">
          <button
            v-for="p in [{ id: 'today', l: 'Hari ini' }, { id: '24h', l: '24 jam' }, { id: '7d', l: '7 hari' }, { id: '30d', l: '30 hari' }, { id: 'all', l: 'Semua' }, { id: 'custom', l: 'Tanggal' }]"
            :key="p.id"
            type="button"
            @click="setPeriod(p.id)"
            class="px-2.5 py-1 rounded-lg text-[11px] font-bold transition cursor-pointer"
            :class="periodFilter === p.id ? 'bg-amber-400 text-slate-950' : 'text-slate-300 hover:text-white hover:bg-slate-800'"
          >{{ p.l }}</button>
        </div>

        <div v-if="periodFilter === 'custom'" class="flex items-center gap-1.5 text-[11px] text-slate-300">
          <input v-model="dateFrom" type="date" aria-label="Dari tanggal" class="px-2 py-1 rounded-lg bg-[#070d19] border border-slate-700 text-white text-[11px] focus:outline-none focus:border-amber-400" />
          <span>s.d.</span>
          <input v-model="dateTo" type="date" aria-label="Sampai tanggal" class="px-2 py-1 rounded-lg bg-[#070d19] border border-slate-700 text-white text-[11px] focus:outline-none focus:border-amber-400" />
        </div>

        <div v-if="searchQuery" class="flex items-center gap-1 p-1 rounded-xl bg-[#070d19] border border-slate-800" role="group" aria-label="Urutan">
          <button type="button" @click="sortMode = 'newest'" class="px-2.5 py-1 rounded-lg text-[11px] font-bold transition cursor-pointer" :class="sortMode === 'newest' ? 'bg-amber-400 text-slate-950' : 'text-slate-300 hover:text-white hover:bg-slate-800'">Terbaru</button>
          <button type="button" @click="sortMode = 'relevance'" class="px-2.5 py-1 rounded-lg text-[11px] font-bold transition cursor-pointer" :class="sortMode === 'relevance' ? 'bg-amber-400 text-slate-950' : 'text-slate-300 hover:text-white hover:bg-slate-800'">Paling relevan</button>
        </div>

        <button
          v-if="myArea"
          type="button"
          @click="toggleMyArea"
          class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-[11px] font-black transition cursor-pointer border"
          :class="isMyAreaActive ? 'bg-amber-400 text-slate-950 border-amber-300' : 'bg-[#070d19] text-amber-300 border-amber-400/40 hover:border-amber-400'"
          :title="'Cari berita yang menyebut ' + myArea.keyword"
        >
          <MapPin class="w-3.5 h-3.5" />
          <span>Wilayah Saya: {{ myArea.keyword }}</span>
        </button>

        <button
          v-if="canManageTopics && searchQuery && (searchTerms || []).length"
          type="button"
          @click="saveSearchAsTopic"
          class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-[11px] font-black bg-[#070d19] text-amber-300 border border-amber-400/40 hover:border-amber-400 transition cursor-pointer"
          title="Jadikan pencarian ini Topik Atensi pimpinan"
        >
          <Pin class="w-3.5 h-3.5" />
          <span>Simpan pencarian jadi Topik Atensi</span>
        </button>

        <div class="ml-auto flex items-center gap-1 p-1 rounded-xl bg-[#070d19] border border-slate-800" role="group" aria-label="Tampilan daftar">
          <button type="button" @click="viewMode = 'cards'" class="px-2.5 py-1 rounded-lg text-[11px] font-bold transition cursor-pointer" :class="viewMode === 'cards' ? 'bg-amber-400 text-slate-950' : 'text-slate-300 hover:text-white hover:bg-slate-800'">Kartu</button>
          <button type="button" @click="viewMode = 'list'" class="px-2.5 py-1 rounded-lg text-[11px] font-bold transition cursor-pointer" :class="viewMode === 'list' ? 'bg-amber-400 text-slate-950' : 'text-slate-300 hover:text-white hover:bg-slate-800'">Daftar</button>
        </div>
      </div>

      <!-- Chip filter aktif -->
      <div v-if="activeFilterCount > 0" class="flex flex-wrap items-center gap-2 -mt-2">
        <span class="text-[11px] font-bold text-slate-500">Filter aktif:</span>
        <button
          v-if="selectedPortal !== 'Semua Portal'"
          type="button"
          @click="selectedPortal = 'Semua Portal'; currentPage = 1"
          class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-900 border border-slate-700 text-slate-200 hover:border-amber-400 cursor-pointer"
        >
          Portal: {{ selectedPortal }} <X class="w-3 h-3" />
        </button>
        <button
          v-if="selectedCategory !== 'all'"
          type="button"
          @click="selectedCategory = 'all'; currentPage = 1"
          class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-900 border border-slate-700 text-slate-200 hover:border-amber-400 cursor-pointer"
        >
          Kategori: {{ selectedCategory }} <X class="w-3 h-3" />
        </button>
        <button
          v-if="periodFilter !== 'all'"
          type="button"
          @click="setPeriod('all')"
          class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-900 border border-slate-700 text-slate-200 hover:border-amber-400 cursor-pointer"
        >
          Periode: {{ periodLabel }} <X class="w-3 h-3" />
        </button>
        <button
          v-if="selectedSentiment !== 'all'"
          type="button"
          @click="selectedSentiment = 'all'; currentPage = 1"
          class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-900 border border-slate-700 text-slate-200 hover:border-amber-400 cursor-pointer"
        >
          Sentimen: {{ selectedSentiment }} <X class="w-3 h-3" />
        </button>
        <button
          v-if="wilayahFilter"
          type="button"
          @click="wilayahFilter = 0; currentPage = 1"
          class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-900 border border-slate-700 text-slate-200 hover:border-amber-400 cursor-pointer"
        >
          Wilayah: {{ (facets?.wilayah || []).find((w) => w.id === wilayahFilter)?.name || wilayahFilter }} <X class="w-3 h-3" />
        </button>
        <button
          v-if="!groupDuplicates"
          type="button"
          @click="groupDuplicates = true"
          class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-900 border border-slate-700 text-slate-200 hover:border-amber-400 cursor-pointer"
        >
          Berita ganda ditampilkan <X class="w-3 h-3" />
        </button>
      </div>

      <!-- Alert Banner Mode Zona Merah Aktif -->
      <div v-if="showRedZoneOnly" class="p-4 rounded-2xl bg-gradient-to-r from-rose-950/70 via-red-950/50 to-slate-900 border border-rose-500/50 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-xs text-rose-200 shadow-xl">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-xl bg-rose-600/30 border border-rose-500/50 flex items-center justify-center shrink-0">
            <Flame class="w-4 h-4 text-rose-400 animate-pulse" />
          </div>
          <div>
            <div class="flex items-center gap-2">
              <span class="font-black text-rose-300 uppercase tracking-wider text-[11px]">Mode Radar Zona Merah Aktif</span>
              <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-rose-600 text-white animate-pulse">ATENSI KERAWANAN</span>
            </div>
            <p class="text-slate-300 text-[11px] mt-0.5">
              Menyaring secara khusus <strong>{{ articlesMeta?.total ?? 0 }}</strong> berita bersentimen negatif dari total <strong>{{ allCount }}</strong> pemantauan media siber untuk respons cepat piket intelijen.
            </p>
          </div>
        </div>
        <button
          type="button"
          @click="showRedZoneOnly = false; currentPage = 1"
          class="px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-rose-300 hover:text-white border border-rose-500/40 text-xs font-bold transition shrink-0 cursor-pointer flex items-center gap-1.5"
        >
          <X class="w-3.5 h-3.5" />
          <span>Matikan Filter Zona Merah</span>
        </button>
      </div>

      <!-- KARTU-KARTU BERITA (PERSIS GAMBAR 1 & 2) -->
      <div v-if="filteredArticles.length > 0" id="article-list-container" class="space-y-6 transition-opacity" :class="isListLoading ? 'opacity-60' : ''" :aria-busy="isListLoading">
        <div v-if="viewMode === 'cards'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
          <div
            v-for="article in paginatedArticles"
            :key="article.id"
            class="rounded-3xl bg-[#0b1426] border border-slate-800/90 hover:border-amber-400/50 transition-all duration-300 flex flex-col justify-between overflow-hidden shadow-xl group hover:shadow-2xl hover:shadow-amber-400/5 h-[435px]"
          >
            <!-- Top Cover Image Area (h-48) -->
            <div class="article-cover h-48 w-full bg-slate-950 relative overflow-hidden shrink-0 border-b border-slate-800/80">
              <!-- Top Left: Source Name Badge & Top Right: Time Ago -->
              <div class="absolute top-3 left-3 right-3 flex items-center justify-between gap-2 z-10 pointer-events-none">
                <!-- Source Name -->
                <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-black/75 text-amber-400 border border-amber-400/40 shadow-sm backdrop-blur-md truncate max-w-[170px]">
                  {{ article.source_name }}
                </span>

                <!-- Time Ago -->
                <span class="px-2.5 py-1 rounded-full text-[10px] font-semibold bg-black/75 text-slate-200 border border-white/10 backdrop-blur-md flex items-center gap-1.5 shadow-sm">
                  <Clock class="w-3 h-3 text-amber-400" />
                  <span>{{ article.time_ago }}</span>
                </span>
              </div>

              <!-- Real Cover Image (Hanya jika ada foto berita asli) -->
              <img
                v-if="hasValidImage(article)"
                :src="article.image_url"
                :alt="article.title"
                loading="lazy"
                referrerpolicy="no-referrer"
                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                @error="handleImageError(article)"
              />

              <!-- Smart Thematic Press Cover (Saat Berita Teks / Google News Tanpa Foto) -->
              <div
                v-else
                class="w-full h-full relative overflow-hidden select-none flex flex-col justify-between p-3.5 bg-gradient-to-br transition-all duration-300"
                :class="getThematicCover(article).gradient"
              >
                <!-- Background Ambient Glow & Grid Micro-Pattern -->
                <div
                  class="absolute -top-8 -right-8 w-36 h-36 rounded-full blur-2xl pointer-events-none opacity-50"
                  :class="getThematicCover(article).glow"
                ></div>
                <div class="absolute inset-0 bg-[radial-gradient(#ffffff0a_1px,transparent_1px)] [background-size:12px_12px] opacity-40 pointer-events-none"></div>

                <!-- Watermark Text in Background -->
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none opacity-[0.04]">
                  <span class="text-2xl font-black tracking-widest text-white uppercase transform -rotate-12 select-none">
                    MEDIA INTEL BRIEFING
                  </span>
                </div>

                <!-- Top Spacer for source & time-ago pills -->
                <div class="h-6"></div>

                <!-- Centerpiece: Authority Emblem & Media Branding -->
                <div class="relative z-10 flex flex-col items-center justify-center text-center my-auto px-1">
                  <div
                    class="w-10 h-10 rounded-2xl border flex items-center justify-center mb-1.5 shadow-lg backdrop-blur-md transition-transform group-hover:scale-110 duration-300"
                    :class="[getThematicCover(article).badgeBorder, 'shadow-black/50']"
                  >
                    <component
                      :is="getThematicCover(article).icon"
                      class="w-5 h-5 drop-shadow"
                      :class="getThematicCover(article).accentColor"
                    />
                  </div>
                  
                  <span class="text-xs font-black tracking-widest text-white uppercase drop-shadow-md truncate max-w-[210px]">
                    {{ article.source_name || 'MEDIA NASIONAL' }}
                  </span>
                  
                  <span class="text-[9px] font-mono font-bold tracking-wider text-slate-300/80 uppercase mt-0.5 flex items-center gap-1">
                    <span>DOKUMENTASI PERS</span>
                    <span>•</span>
                    <span :class="getThematicCover(article).accentColor">{{ getThematicCover(article).tagText }}</span>
                  </span>
                </div>

                <!-- Bottom Watermark Footer Line -->
                <div class="relative z-10 flex items-center justify-between text-[8px] font-mono uppercase tracking-wider text-slate-400/80 border-t border-white/10 pt-1">
                  <span class="flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>SINDIKASI PERS SIBER</span>
                  </span>
                  <span class="font-bold text-slate-400">POLISI BERSAMA MASYARAKAT</span>
                </div>
              </div>

              <!-- Dark Gradient Overlay (Hanya jika ada gambar asli) -->
              <div v-if="hasValidImage(article)" class="absolute inset-0 bg-gradient-to-t from-[#0b1426] via-transparent to-black/40 pointer-events-none"></div>
            </div>

            <!-- Card Body Content -->
            <div class="p-5 flex-1 flex flex-col justify-between space-y-2">
              <div class="space-y-2">
                <!-- Category Badge & Topic Atensi Badges -->
                <div class="flex flex-wrap items-center gap-1.5 pt-0.5">
                  <span class="px-2.5 py-0.5 rounded-md text-[9px] font-extrabold uppercase bg-slate-900/90 text-slate-300 border border-slate-700/80 tracking-wider">
                    {{ article.category_badge || article.category || (isDivhumas ? 'NASIONAL & MABES' : 'REGIONAL') }}
                  </span>
                  <span
                    v-if="article.dupe_count > 0"
                    class="px-2 py-0.5 rounded-md text-[9px] font-extrabold uppercase bg-sky-500/15 text-sky-300 border border-sky-500/40 tracking-wider"
                    :title="'Juga tayang di: ' + (article.also_in || []).map((o) => o.source_name).join(', ')"
                  >+{{ article.dupe_count }} portal</span>
                  <span
                    v-if="isNational && article.polda_code"
                    class="px-2 py-0.5 rounded-md text-[9px] font-extrabold uppercase bg-slate-800 text-amber-300 border border-amber-400/30 tracking-wider"
                    :title="'Wilayah: ' + article.polda_code"
                  >{{ article.polda_code }}</span>
                  <!-- Topic Dossier Badges -->
                  <span
                    v-for="top in (article.matched_topics || [])"
                    :key="top.id"
                    class="px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider flex items-center gap-1 border shadow-xs"
                    :class="top.priority === 'urgent'
                      ? 'bg-rose-500/20 text-rose-300 border-rose-500/50'
                      : 'bg-amber-400/20 text-amber-300 border-amber-400/40'"
                    :title="'Topik Atensi: ' + top.title"
                  >
                    <span>📌</span>
                    <span class="truncate max-w-[140px]">{{ top.title }}</span>
                  </span>
                </div>

                <!-- Date & Time (📅 28 Oct 2026, 23.22 WITA) -->
                <div class="text-[10px] text-slate-400 flex items-center gap-1.5">
                  <Calendar class="w-3.5 h-3.5 text-slate-500 shrink-0" />
                  <span class="truncate">{{ article.pub_date }}</span>
                </div>

                <!-- Title (Uniform Height 2-line clamp) -->
                <a
                  :href="article.link"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="block font-black text-sm md:text-[14px] text-white group-hover:text-amber-400 transition leading-snug line-clamp-2 h-10 md:h-11 overflow-hidden"
                  :title="article.title"
                >
                  <template v-for="(seg, i) in highlightParts(article.title)" :key="i"><mark v-if="seg.hit" class="bg-amber-400/30 text-inherit rounded px-0.5">{{ seg.t }}</mark><template v-else>{{ seg.t }}</template></template>
                </a>

                <!-- Summary Excerpt (Cleaned and Uniform Height 2-line clamp) -->
                <p class="text-xs text-slate-300 line-clamp-2 h-9 overflow-hidden leading-relaxed">
                  <template v-for="(seg, i) in highlightParts(getCleanSummary(article))" :key="i"><mark v-if="seg.hit" class="bg-amber-400/30 text-inherit rounded px-0.5">{{ seg.t }}</mark><template v-else>{{ seg.t }}</template></template>
                </p>
              </div>

              <!-- Card Footer Actions (PERSIS GAMBAR 2) -->
              <div class="pt-3 border-t border-slate-800/80 flex items-center justify-between gap-2">
                <!-- Left: Buka Button -->
                <a
                  :href="article.link"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="px-3 py-1.5 rounded-full text-xs font-semibold text-slate-300 bg-slate-900 hover:bg-slate-800 hover:text-white border border-slate-700 flex items-center gap-1.5 transition shadow-sm"
                  title="Buka artikel asli di tab baru"
                >
                  <ExternalLink class="w-3.5 h-3.5 text-blue-400" />
                  <span>Buka</span>
                </a>

                <!-- Kanan: Simpan, Buat Isu (operator), menu lainnya -->
                <div class="flex items-center gap-1.5">
                  <button
                    @click.stop="toggleBookmark(article.id)"
                    :class="[
                      'px-3 py-1.5 rounded-full text-xs font-bold transition flex items-center gap-1.5 shadow-sm border cursor-pointer',
                      isBookmarked(article.id)
                        ? 'bg-amber-400 text-slate-950 font-black border-amber-300 shadow-md'
                        : 'bg-slate-900 hover:bg-slate-800 text-slate-300 hover:text-amber-300 border-slate-700'
                    ]"
                    :title="isBookmarked(article.id) ? 'Hapus dari Bookmark Tersimpan' : 'Simpan / Bookmark Berita Ini'"
                  >
                    <Check v-if="isBookmarked(article.id)" class="w-3.5 h-3.5 text-slate-950" />
                    <Bookmark v-else class="w-3.5 h-3.5 text-amber-400" />
                    <span>{{ isBookmarked(article.id) ? 'Tersimpan' : 'Simpan' }}</span>
                  </button>

                  <button
                    v-if="isOperator"
                    @click.stop="openIssueModal(article)"
                    class="px-3 py-1.5 rounded-full text-xs font-bold text-white bg-slate-900 hover:bg-slate-800 border-2 border-amber-400/90 hover:border-amber-300 flex items-center gap-1 transition cursor-pointer"
                    title="Jadikan laporan isu publik baru"
                  >
                    <Plus class="w-3.5 h-3.5" />
                    <span>Buat Isu</span>
                  </button>

                  <div class="relative">
                    <button
                      type="button"
                      @click.stop="openMenu = openMenu === 'card-' + article.id ? null : 'card-' + article.id"
                      class="p-1.5 rounded-full border bg-slate-900 hover:bg-slate-800 text-slate-300 hover:text-white border-slate-700 transition cursor-pointer"
                      title="Aksi lainnya"
                    >
                      <Ellipsis class="w-4 h-4" />
                    </button>
                    <div
                      v-if="openMenu === 'card-' + article.id"
                      @click.stop
                      class="absolute bottom-full right-0 mb-2 z-30 w-52 rounded-xl bg-[#0b1426] border border-slate-700 shadow-2xl p-1.5"
                    >
                      <button
                        v-if="!isOperator"
                        type="button"
                        @click.stop="openIssueModal(article); openMenu = null"
                        class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-bold text-slate-200 hover:bg-slate-800 hover:text-white transition cursor-pointer text-left"
                      >
                        <Plus class="w-4 h-4 text-amber-400" />
                        <span>Buat Isu</span>
                      </button>
                      <button
                        type="button"
                        @click.stop="openTagModal(article); openMenu = null"
                        class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-bold text-slate-200 hover:bg-slate-800 hover:text-white transition cursor-pointer text-left"
                      >
                        <Pin class="w-4 h-4 text-amber-400" />
                        <span>Tag Kasus Atensi</span>
                      </button>
                      <div v-if="(article.also_in || []).length" class="mt-1 pt-1.5 border-t border-slate-700/70">
                        <p class="px-3 pb-1 text-[10px] font-black uppercase tracking-wider text-slate-500">Juga tayang di</p>
                        <a
                          v-for="o in article.also_in"
                          :key="o.link"
                          :href="o.link"
                          target="_blank"
                          rel="noopener noreferrer"
                          class="flex items-center justify-between gap-2 px-3 py-1.5 rounded-lg text-[11px] font-bold text-slate-200 hover:bg-slate-800 hover:text-white transition"
                        >
                          <span class="truncate">{{ o.source_name }}</span>
                          <ExternalLink class="w-3 h-3 text-blue-400 shrink-0" />
                        </a>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Tampilan DAFTAR RINGKAS: banyak judul per layar untuk analis -->
        <div v-else class="rounded-2xl bg-[#0b1426] border border-slate-800 shadow-xl divide-y divide-slate-800/80 overflow-hidden">
          <div
            v-for="article in paginatedArticles"
            :key="article.id"
            class="flex items-start gap-3 px-4 py-3 hover:bg-slate-800/40 transition"
          >
            <span
              class="mt-1.5 w-2 h-2 rounded-full shrink-0"
              :class="article.sentiment === 'negatif' ? 'bg-rose-500' : (article.sentiment === 'positif' ? 'bg-emerald-400' : 'bg-slate-500')"
              :title="'Sentimen: ' + article.sentiment"
            ></span>
            <div class="min-w-0 flex-1 space-y-1">
              <a
                :href="article.link"
                target="_blank"
                rel="noopener noreferrer"
                class="block text-[13px] font-bold text-white hover:text-amber-400 transition leading-snug"
              >
                <template v-for="(seg, i) in highlightParts(article.title)" :key="i"><mark v-if="seg.hit" class="bg-amber-400/30 text-inherit rounded px-0.5">{{ seg.t }}</mark><template v-else>{{ seg.t }}</template></template>
              </a>
              <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1 text-[11px] text-slate-400">
                <span class="font-black text-amber-400/90 uppercase tracking-wide">{{ article.source_name }}</span>
                <span>{{ article.pub_date }}</span>
                <span class="px-1.5 py-0.5 rounded bg-slate-900 border border-slate-700 text-[10px] font-bold uppercase text-slate-300">{{ article.category_badge || article.category }}</span>
                <span v-if="isNational && article.polda_code" class="px-1.5 py-0.5 rounded bg-slate-800 border border-amber-400/30 text-[10px] font-bold text-amber-300">{{ article.polda_code }}</span>
                <span v-for="top in (article.matched_topics || [])" :key="top.id" class="px-1.5 py-0.5 rounded bg-amber-400/15 border border-amber-400/40 text-[10px] font-bold text-amber-300" :title="'Topik Atensi: ' + top.title">&#128204; {{ top.title }}</span>
              </div>
              <p v-if="(article.also_in || []).length" class="text-[11px] text-sky-300/90">
                Juga tayang di:
                <a
                  v-for="(o, i) in article.also_in"
                  :key="o.link"
                  :href="o.link"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="hover:underline"
                >{{ o.source_name }}<span v-if="i < article.also_in.length - 1">, </span></a>
                <span v-if="article.dupe_count > article.also_in.length"> (+{{ article.dupe_count - article.also_in.length }})</span>
              </p>
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
              <button
                type="button"
                @click.stop="toggleBookmark(article.id)"
                class="p-1.5 rounded-lg border transition cursor-pointer"
                :class="isBookmarked(article.id) ? 'bg-amber-400 text-slate-950 border-amber-300' : 'bg-slate-900 text-amber-300 border-slate-700 hover:bg-slate-800'"
                :title="isBookmarked(article.id) ? 'Hapus dari Bookmark Tersimpan' : 'Simpan / Bookmark Berita Ini'"
              >
                <Bookmark class="w-3.5 h-3.5" :class="isBookmarked(article.id) ? 'fill-slate-950' : ''" />
              </button>
              <button
                type="button"
                @click.stop="openIssueModal(article)"
                class="p-1.5 rounded-lg border bg-slate-900 text-slate-200 border-slate-700 hover:bg-slate-800 hover:text-white transition cursor-pointer"
                title="Jadikan laporan isu publik baru"
              >
                <Plus class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>
        </div>

        <!-- Pagination Bar with Numbered Pages (1, 2, 3...) -->
        <div v-if="totalPages > 1" class="flex flex-col md:flex-row items-center justify-between gap-4 p-4 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-xl">
          <!-- Left: Previous Button -->
          <button
            :disabled="currentPage === 1"
            @click="goToPage(currentPage - 1)"
            type="button"
            class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold bg-slate-900 text-slate-300 border border-slate-700/80 hover:text-white hover:bg-slate-800 hover:border-amber-400/40 disabled:opacity-30 disabled:cursor-not-allowed transition cursor-pointer select-none"
          >
            <span>&larr;</span>
            <span>Sebelumnya</span>
          </button>

          <!-- Center: Numbered Page Buttons & Page Info -->
          <div class="flex flex-col items-center gap-2">
            <div class="flex items-center gap-1.5 flex-wrap justify-center">
              <template v-for="(p, idx) in visiblePages" :key="idx">
                <span
                  v-if="p === '...'"
                  class="px-2 py-1 text-slate-500 font-bold select-none text-xs"
                >
                  &hellip;
                </span>
                <button
                  v-else
                  type="button"
                  @click="goToPage(p)"
                  class="min-w-[38px] h-9 px-2 rounded-xl text-xs font-bold transition flex items-center justify-center cursor-pointer select-none"
                  :class="currentPage === p
                    ? 'bg-amber-400 text-slate-950 font-black shadow-lg shadow-amber-400/25 ring-2 ring-amber-400/70 scale-105'
                    : 'bg-slate-900 text-slate-300 border border-slate-700/80 hover:bg-slate-800 hover:text-white hover:border-slate-500'"
                >
                  {{ p }}
                </button>
              </template>
            </div>

            <p class="text-[11px] text-slate-400 font-medium">
              Halaman <span class="text-amber-400 font-bold">{{ currentPage }}</span> dari <span class="text-white font-bold">{{ totalPages }}</span> &bull; Menampilkan {{ articlesMeta?.from ?? 0 }}-{{ articlesMeta?.to ?? 0 }} dari {{ articlesMeta?.total ?? 0 }} Berita &bull; <span class="text-amber-400 font-bold">{{ selectedRows }} Baris</span> ({{ perPage }} kartu / hal)
            </p>

            <!-- Quick Row Selector at Bottom -->
            <div class="flex items-center gap-1.5 text-[11px] text-slate-400">
              <Layers class="w-3 h-3 text-amber-400" />
              <span>Tampilan:</span>
              <div class="inline-flex rounded-lg bg-slate-900 border border-slate-700/80 p-0.5 text-[11px]">
                <button
                  v-for="r in [3, 5, 8, 10, 15, 17]"
                  :key="r"
                  type="button"
                  @click="selectedRows = r; onRowsChange()"
                  class="px-2 py-0.5 rounded transition cursor-pointer font-bold"
                  :class="selectedRows === r ? 'bg-amber-400 text-slate-950 font-black shadow-sm' : 'text-slate-400 hover:text-white'"
                >
                  {{ r }} Baris
                </button>
              </div>
            </div>
          </div>

          <!-- Right: Next Button -->
          <button
            :disabled="currentPage === totalPages"
            @click="goToPage(currentPage + 1)"
            type="button"
            class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold bg-slate-900 text-slate-300 border border-slate-700/80 hover:text-white hover:bg-slate-800 hover:border-amber-400/40 disabled:opacity-30 disabled:cursor-not-allowed transition cursor-pointer select-none"
          >
            <span>Selanjutnya</span>
            <span>&rarr;</span>
          </button>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="rounded-3xl bg-[#0b1426] border border-slate-800 p-12 text-center space-y-3">
        <Newspaper class="w-12 h-12 mx-auto text-slate-600" />
        <h3 class="text-base font-bold text-white">Tidak ada artikel yang sesuai dengan filter</h3>
        <p class="text-xs text-slate-400 max-w-sm mx-auto">
          Coba ubah kata kunci, perluas periode, atau klik tombol "Sinkron Berita" untuk menarik berita terkini dari seluruh portal.
        </p>
        <div class="flex items-center justify-center gap-3 flex-wrap">
          <button
            @click="resetAllFilters()"
            class="px-4 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-black text-xs cursor-pointer transition shadow-sm"
          >
            Reset Semua Filter
          </button>
          <button
            @click="refreshRssFeeds"
            :disabled="isRefreshingRss"
            class="flex items-center gap-1.5 px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 font-bold text-xs cursor-pointer transition disabled:opacity-50"
            title="Tarik berita terbaru dari seluruh feed RSS portal media"
          >
            <RotateCw class="w-3.5 h-3.5 text-amber-400" :class="{ 'animate-spin': isRefreshingRss }" />
            <span>Sinkron Berita Terkini</span>
          </button>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 02: BERITA & BRIEFING (EXECUTIVE SNAPSHOT & FORMAT WA RESMI)           -->
    <!-- ========================================================================= -->
    <div v-show="activeTab === '02'" class="space-y-6">
      <!-- Situasi 24 jam (pimpinan): angka nyata dari basis data, bukan hasil AI -->
      <MediaSituationCard
        v-if="situation"
        :situation="situation"
        :stats="stats"
        :fresh="freshState"
        :top-concern="report?.top_concern || ''"
        @show-redzone="activeTab = '01'; showBookmarksOnly = false; selectedTopicId = null; periodFilter = '24h'; showRedZoneOnly = true; currentPage = 1"
        @copy-wa="copyWaReport()"
      />

      <!-- Executive Briefing 200 Words -->
      <div class="rounded-3xl bg-gradient-to-br from-[#0c162e] to-[#080f20] border border-amber-400/30 p-6 shadow-2xl space-y-3">
        <div class="flex items-center justify-between border-b border-slate-800/80 pb-3 flex-wrap gap-2">
          <div class="flex items-center gap-2.5">
            <Sparkles class="w-4 h-4 text-amber-400" />
            <h2 class="text-sm font-black text-white uppercase tracking-wider">
              Executive Briefing Harian Pimpinan
            </h2>
            <span v-if="props.aiSetting?.has_api_key" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-amber-400/10 border border-amber-400/30 text-amber-300 text-[10px] font-black">
              <Bot class="w-3 h-3 text-amber-400" />
              <span>{{ props.aiSetting?.gemini_model ? 'AI: ' + props.aiSetting.gemini_model : 'Analisis AI' }}</span>
            </span>
            <AiQuotaBadge />
          </div>
          <div class="flex items-center gap-2">
            <button
              @click="triggerGeminiAnalysis(true, true)"
              :disabled="isAnalyzingGemini"
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-400/20 hover:bg-amber-400/30 text-amber-300 border border-amber-400/40 text-[11px] font-bold transition shadow-sm cursor-pointer disabled:opacity-50"
              title="Segarkan sinkronisasi berita dan analisis otomatis"
            >
              <RotateCw class="w-3.5 h-3.5" :class="{ 'animate-spin': isAnalyzingGemini }" />
              <span>{{ isAnalyzingGemini ? (syncStepText || 'Menganalisis...') : 'Analisis Ulang' }}</span>
            </button>
            <button
              @click="copyWaReport"
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-400 border border-emerald-500/40 text-[11px] font-bold transition shadow-sm cursor-pointer"
              title="Salin naskah lengkap siap kirim ke WhatsApp pimpinan"
            >
              <Check v-if="isWaCopied" class="w-3.5 h-3.5 text-emerald-400" />
              <MessageSquare v-else class="w-3.5 h-3.5 text-emerald-400" />
              <span>{{ isWaCopied ? 'Tersalin!' : 'Salin Format WA Pimpinan' }}</span>
            </button>
            <span class="text-xs font-mono text-amber-400">{{ report?.report_time || '19.03 WITA' }}</span>
          </div>
        </div>
        <p class="text-xs md:text-sm text-slate-200 leading-relaxed">
          {{ report?.executive_brief || 'Ringkasan briefing pimpinan belum diisi. Gunakan tombol "⚡ Sinkron & Analisis Otomatis" di atas.' }}
        </p>
      </div>

      <!-- BAROMETER SENTIMEN PUBLIK TERPADU (NASIONAL & KEWILAYAHAN) -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Sentiment Nasional Card -->
        <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
          <div class="flex items-center justify-between border-b border-slate-800/80 pb-3">
            <h3 class="text-xs font-black text-white uppercase tracking-wider flex items-center gap-2">
              <span>🇮🇩</span>
              <span>Sentimen Publik Nasional</span>
            </h3>
            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-500/10 text-emerald-300 border border-emerald-500/30">
              Mabes Polri Scope
            </span>
          </div>

          <div class="flex items-center justify-around py-2">
            <div class="text-center">
              <span class="text-2xl md:text-3xl font-black text-emerald-400 font-mono">{{ report?.sentiment_national?.positif || 60 }}%</span>
              <p class="text-[11px] text-slate-400 font-bold mt-0.5">Positif</p>
            </div>
            <div class="text-center">
              <span class="text-2xl md:text-3xl font-black text-amber-400 font-mono">{{ report?.sentiment_national?.netral || 30 }}%</span>
              <p class="text-[11px] text-slate-400 font-bold mt-0.5">Netral</p>
            </div>
            <div class="text-center">
              <span class="text-2xl md:text-3xl font-black text-rose-400 font-mono">{{ report?.sentiment_national?.negatif || 10 }}%</span>
              <p class="text-[11px] text-slate-400 font-bold mt-0.5">Negatif</p>
            </div>
          </div>

          <!-- Proportional Progress Bar -->
          <div class="h-2.5 w-full rounded-full bg-slate-800 overflow-hidden flex shadow-inner">
            <div
              :style="{ width: (report?.sentiment_national?.positif || 60) + '%' }"
              class="bg-emerald-500 transition-all duration-500"
              :title="'Positif: ' + (report?.sentiment_national?.positif || 60) + '%'"
            ></div>
            <div
              :style="{ width: (report?.sentiment_national?.netral || 30) + '%' }"
              class="bg-amber-400 transition-all duration-500"
              :title="'Netral: ' + (report?.sentiment_national?.netral || 30) + '%'"
            ></div>
            <div
              :style="{ width: (report?.sentiment_national?.negatif || 10) + '%' }"
              class="bg-rose-500 transition-all duration-500"
              :title="'Negatif: ' + (report?.sentiment_national?.negatif || 10) + '%'"
            ></div>
          </div>

          <p class="text-xs text-slate-300 leading-relaxed italic border-l-2 border-slate-700 pl-3">
            {{ report?.snapshot_national_desc || 'Dinamika isu nasional Polri stabil dengan pengawasan intensif ruang siber dan keterbukaan informasi.' }}
          </p>
        </div>

        <!-- Sentiment Wilayah / Satwil Card -->
        <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
          <div class="flex items-center justify-between border-b border-slate-800/80 pb-3">
            <h3 class="text-xs font-black text-white uppercase tracking-wider flex items-center gap-2">
              <MapPin class="w-3.5 h-3.5 text-rose-400" />
              <span>{{ isDivhumas ? 'Sentimen Kewilayahan Satwil Jajaran' : ('Sentimen Publik ' + (selectedPolda?.name || 'Sulawesi Barat')) }}</span>
            </h3>
            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-cyan-500/10 text-cyan-300 border border-cyan-500/30">
              Kewilayahan
            </span>
          </div>

          <div class="flex items-center justify-around py-2">
            <div class="text-center">
              <span class="text-2xl md:text-3xl font-black text-emerald-400 font-mono">
                {{ (report?.sentiment_satwil || report?.sentiment_sulbar)?.positif || (isDivhumas ? 60 : 55) }}%
              </span>
              <p class="text-[11px] text-slate-400 font-bold mt-0.5">Positif</p>
            </div>
            <div class="text-center">
              <span class="text-2xl md:text-3xl font-black text-amber-400 font-mono">
                {{ (report?.sentiment_satwil || report?.sentiment_sulbar)?.netral || 30 }}%
              </span>
              <p class="text-[11px] text-slate-400 font-bold mt-0.5">Netral</p>
            </div>
            <div class="text-center">
              <span class="text-2xl md:text-3xl font-black text-rose-400 font-mono">
                {{ (report?.sentiment_satwil || report?.sentiment_sulbar)?.negatif || (isDivhumas ? 10 : 15) }}%
              </span>
              <p class="text-[11px] text-slate-400 font-bold mt-0.5">Negatif</p>
            </div>
          </div>

          <!-- Proportional Progress Bar -->
          <div class="h-2.5 w-full rounded-full bg-slate-800 overflow-hidden flex shadow-inner">
            <div
              :style="{ width: ((report?.sentiment_satwil || report?.sentiment_sulbar)?.positif || (isDivhumas ? 60 : 55)) + '%' }"
              class="bg-emerald-500 transition-all duration-500"
              :title="'Positif: ' + ((report?.sentiment_satwil || report?.sentiment_sulbar)?.positif || (isDivhumas ? 60 : 55)) + '%'"
            ></div>
            <div
              :style="{ width: ((report?.sentiment_satwil || report?.sentiment_sulbar)?.netral || 30) + '%' }"
              class="bg-amber-400 transition-all duration-500"
              :title="'Netral: ' + ((report?.sentiment_satwil || report?.sentiment_sulbar)?.netral || 30) + '%'"
            ></div>
            <div
              :style="{ width: ((report?.sentiment_satwil || report?.sentiment_sulbar)?.negatif || (isDivhumas ? 10 : 15)) + '%' }"
              class="bg-rose-500 transition-all duration-500"
              :title="'Negatif: ' + ((report?.sentiment_satwil || report?.sentiment_sulbar)?.negatif || (isDivhumas ? 10 : 15)) + '%'"
            ></div>
          </div>

          <p class="text-xs text-slate-300 leading-relaxed italic border-l-2 border-slate-700 pl-3">
            {{ report?.snapshot_satwil_desc || report?.snapshot_sulbar_desc || 'Situasi kamtibmas kewilayahan terpantau aman dan terkendali dengan stabilitas opini publik terjaga.' }}
          </p>
        </div>
      </div>

      <!-- Top Concern -->
      <div class="p-5 rounded-2xl bg-rose-500/10 border border-rose-500/40 text-rose-300 text-xs flex items-center gap-3">
        <AlertTriangle class="w-5 h-5 shrink-0 text-rose-400" />
        <div>
          <strong class="font-black uppercase tracking-wider block">Atensi Utama Pimpinan (Top Concern):</strong>
          <span>{{ report?.top_concern || 'Monitoring dan mitigasi disinformasi ruang digital di wilayah pesisir.' }}</span>
        </div>
      </div>

      <!-- Matriks Respons Taktis Bertahap (Standar Humas Polri) -->
      <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800/80 pb-3 flex-wrap gap-2">
          <h3 class="text-xs font-black text-white uppercase tracking-wider flex items-center gap-2">
            <Zap class="w-4 h-4 text-amber-400" />
            <span>Matriks Protokol Respons Taktis Bertahap</span>
          </h3>
          <span class="text-[10px] font-mono text-slate-400 uppercase tracking-wider">SOP Mitigasi Krisis 24 Jam</span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800 space-y-2">
            <span class="px-2.5 py-0.5 rounded text-[10px] font-black uppercase bg-rose-500/20 text-rose-300 border border-rose-500/30">
              Tahap 0 - 2 Jam: Validasi
            </span>
            <p class="text-xs font-bold text-white">{{ report?.priority_responses?.response_0_2h?.title || 'Koordinasi Cepat & Cek Fakta' }}</p>
            <p class="text-xs text-slate-300 leading-relaxed">{{ report?.priority_responses?.response_0_2h?.action || 'Lakukan verifikasi fakta lapangan dengan satker terkait dalam tempo maksimal 2 jam pertama.' }}</p>
          </div>
          <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800 space-y-2">
            <span class="px-2.5 py-0.5 rounded text-[10px] font-black uppercase bg-amber-500/20 text-amber-300 border border-amber-500/30">
              Tahap 2 - 6 Jam: Klarifikasi
            </span>
            <p class="text-xs font-bold text-white">{{ report?.priority_responses?.response_2_6h?.title || 'Rilis Pers Satu Pintu' }}</p>
            <p class="text-xs text-slate-300 leading-relaxed">{{ report?.priority_responses?.response_2_6h?.action || 'Siapkan rilis pers terbuka berbasis 5W+1H untuk mematahkan spekulasi liar sebelum 6 jam.' }}</p>
          </div>
          <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800 space-y-2">
            <span class="px-2.5 py-0.5 rounded text-[10px] font-black uppercase bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
              Tahap 24 Jam: Amplifikasi
            </span>
            <p class="text-xs font-bold text-white">{{ report?.priority_responses?.response_24h?.title || 'Pemulihan & Penguatan Marwah' }}</p>
            <p class="text-xs text-slate-300 leading-relaxed">{{ report?.priority_responses?.response_24h?.action || 'Amplifikasi testimoni tokoh masyarakat dan konten humanis untuk memulihkan kepercayaan publik.' }}</p>
          </div>
        </div>
      </div>

      <!-- Kajian Risiko Pimpinan & Doktrin Kehumasan Humanis Transparan -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Box 1: Kajian Risiko Jika Tidak Dimitigasi (Unmitigated Impact) -->
        <div class="p-5 rounded-3xl bg-gradient-to-br from-rose-950/40 via-[#0b1426] to-[#070d1d] border border-rose-500/40 shadow-xl space-y-2.5">
          <div class="flex items-center justify-between">
            <span class="text-xs font-black uppercase tracking-wider text-rose-400 flex items-center gap-1.5">
              <AlertTriangle class="w-4 h-4 text-rose-400" />
              <span>Kajian Dampak &amp; Risiko Tanpa Mitigasi Cepat</span>
            </span>
            <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-rose-500/20 text-rose-300 border border-rose-500/40">
              Atensi Intens Pimpinan
            </span>
          </div>
          <p class="text-xs text-slate-300 leading-relaxed">
            Keterlambatan atau ketidaktepatan respon kehumasan berisiko memicu <strong>kekosongan informasi</strong> dalam 0-6 jam pertama. Tanpa rilis terbuka berbasis fakta, narasi sepihak buzzer akan mendominasi algoritma dan bereskalasi menjadi framing kritis media massa serta potensi mobilisasi massa dalam 6-24 jam.
          </p>
          <div class="grid grid-cols-3 gap-2 pt-1 text-[11px] font-mono">
            <div class="p-2 rounded-xl bg-slate-900/90 border border-slate-800">
              <span class="text-amber-400 block font-bold text-[10px]">0–6 Jam:</span>
              <span class="text-slate-300">Viralitas Liar &amp; Framing Negatif</span>
            </div>
            <div class="p-2 rounded-xl bg-slate-900/90 border border-slate-800">
              <span class="text-rose-400 block font-bold text-[10px]">6–24 Jam:</span>
              <span class="text-slate-300">Tekanan Media &amp; Aksi Wilayah</span>
            </div>
            <div class="p-2 rounded-xl bg-slate-900/90 border border-slate-800">
              <span class="text-purple-400 block font-bold text-[10px]">&gt;24 Jam:</span>
              <span class="text-slate-300">Krisis Kepercayaan Publik</span>
            </div>
          </div>
        </div>

        <!-- Box 2: Doktrin Kehumasan: Humanis, Transparan & Berbasis Fakta -->
        <div class="p-5 rounded-3xl bg-gradient-to-br from-emerald-950/40 via-[#0b1426] to-[#070d1d] border border-emerald-500/40 shadow-xl space-y-2.5">
          <div class="flex items-center justify-between">
            <span class="text-xs font-black uppercase tracking-wider text-emerald-400 flex items-center gap-1.5">
              <ShieldCheck class="w-4 h-4 text-emerald-400" />
              <span>Doktrin Humas: Humanis, Transparan &amp; Faktual</span>
            </span>
            <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
              Standar Mabes Polri
            </span>
          </div>
          <p class="text-xs text-slate-300 leading-relaxed">
            Menghadapi isu sensitif, personel kehumasan <strong>DILARANG bersikap defensif</strong> atau menutupi kekurangan prosedur di lapangan. Kedepankan empati tulus kepada masyarakat, sampaikan kebenaran kronologi riil <strong>5W+1H</strong> secara terbuka, dan paparkan tindakan korektif nyata demi menjaga marwah institusi.
          </p>
          <div class="grid grid-cols-2 gap-2 pt-1 text-[11px]">
            <div class="p-2 rounded-xl bg-slate-900/90 border border-slate-800 flex items-center gap-2">
              <span class="text-emerald-400 font-bold">✓</span>
              <span class="text-slate-200">Keterbukaan Fakta Terverifikasi</span>
            </div>
            <div class="p-2 rounded-xl bg-slate-900/90 border border-slate-800 flex items-center gap-2">
              <span class="text-emerald-400 font-bold">✓</span>
              <span class="text-slate-200">Pendampingan Humanis Korban</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Grid 2 Col: Isu Nasional vs Isu Kewilayahan -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Isu Nasional -->
        <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
          <div class="flex items-center justify-between border-b border-slate-800 pb-3 flex-wrap gap-2">
            <div class="flex items-center gap-2">
              <span class="text-base">🇮🇩</span>
              <h3 class="text-sm font-black text-white uppercase tracking-wider">Isu Strategis Nasional Polri</h3>
            </div>
            <span
              class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider border"
              :class="[
                (report?.snapshot_national_status || '').includes('MERAH')
                  ? 'bg-rose-500/20 text-rose-300 border-rose-500/40'
                  : (report?.snapshot_national_status || '').includes('HIJAU')
                    ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40'
                    : 'bg-amber-400/20 text-amber-300 border-amber-400/40'
              ]"
            >
              {{ report?.snapshot_national_status || 'KUNING - WASPADA' }}
            </span>
          </div>
          <div class="space-y-3">
            <div
              v-for="(iss, idx) in (report?.national_issues || [])"
              :key="idx"
              class="p-4 rounded-2xl bg-slate-900/90 border border-slate-800 space-y-2.5 text-xs hover:border-slate-700 transition"
            >
              <div class="flex items-start justify-between gap-3">
                <span class="font-bold text-white text-xs md:text-sm leading-snug">{{ iss.title }}</span>
                <span
                  class="px-2 py-0.5 rounded text-[9px] font-black uppercase shrink-0"
                  :class="[
                    (iss.sentiment || '').toLowerCase() === 'positif'
                      ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40'
                      : (iss.sentiment || '').toLowerCase() === 'negatif'
                        ? 'bg-rose-500/20 text-rose-300 border border-rose-500/40'
                        : 'bg-slate-700/60 text-slate-300 border border-slate-600'
                  ]"
                >
                  {{ iss.sentiment || 'Positif' }}
                </span>
              </div>
              <p class="text-slate-300 leading-relaxed">{{ iss.fact }}</p>

              <!-- Impact Kamtibmas & Unmitigated Risk Badges -->
              <div v-if="iss.kamtibmas_impact" class="p-2.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-[11px] text-amber-200">
                <strong class="text-amber-400 font-bold block mb-0.5">🛡️ Dampak Kamtibmas:</strong>
                <span>{{ iss.kamtibmas_impact }}</span>
              </div>
              <div v-if="iss.unmitigated_risk" class="p-2.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-[11px] text-rose-200">
                <strong class="text-rose-400 font-bold block mb-0.5">⚠️ Risiko Jika Tanpa Mitigasi Cepat:</strong>
                <span>{{ iss.unmitigated_risk }}</span>
              </div>

              <div class="text-[10px] text-slate-400 flex items-center justify-between pt-1 border-t border-slate-800/60 flex-wrap gap-2">
                <span class="flex items-center gap-1 font-mono text-slate-400">
                  <Radio class="w-3 h-3 text-amber-400" />
                  {{ iss.source }}
                </span>
                <div class="flex items-center gap-2">
                  <span class="text-slate-400">Virality: <strong class="text-amber-300">{{ iss.virality || 'Sedang' }}</strong></span>
                  <a
                    v-if="iss.url"
                    :href="iss.url"
                    target="_blank"
                    class="text-sky-400 hover:underline flex items-center gap-0.5"
                  >
                    <span>Tautan</span>
                    <ExternalLink class="w-3 h-3" />
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Isu Kewilayahan Polda & Polres / 34 Polda Jajaran -->
        <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
          <div class="flex items-center justify-between border-b border-slate-800 pb-3 flex-wrap gap-2">
            <div class="flex items-center gap-2">
              <MapPin class="w-4 h-4 text-rose-400" />
              <h3 class="text-sm font-black text-white uppercase tracking-wider">
                Isu Kewilayahan {{ isDivhumas ? 'Satwil Jajaran se-Indonesia' : (selectedPolda?.name || 'Polda & Jajaran Polres') }}
              </h3>
            </div>
            <span
              class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider border"
              :class="[
                (report?.snapshot_satwil_status || report?.snapshot_sulbar_status || '').includes('MERAH')
                  ? 'bg-rose-500/20 text-rose-300 border-rose-500/40'
                  : (report?.snapshot_satwil_status || report?.snapshot_sulbar_status || '').includes('HIJAU')
                    ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40'
                    : 'bg-amber-400/20 text-amber-300 border-amber-400/40'
              ]"
            >
              {{ report?.snapshot_satwil_status || report?.snapshot_sulbar_status || 'KUNING - WASPADA' }}
            </span>
          </div>
          <div class="space-y-3">
            <div
              v-for="(iss, idx) in (report?.regional_issues || report?.sulbar_issues || [])"
              :key="idx"
              class="p-4 rounded-2xl bg-slate-900/90 border border-slate-800 space-y-2.5 text-xs hover:border-slate-700 transition"
            >
              <div class="flex items-start justify-between gap-3">
                <span class="font-bold text-white text-xs md:text-sm leading-snug">{{ iss.title }}</span>
                <span
                  class="px-2 py-0.5 rounded text-[9px] font-black uppercase shrink-0"
                  :class="[
                    (iss.sentiment || '').toLowerCase() === 'positif'
                      ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40'
                      : (iss.sentiment || '').toLowerCase() === 'negatif'
                        ? 'bg-rose-500/20 text-rose-300 border border-rose-500/40'
                        : 'bg-slate-700/60 text-slate-300 border border-slate-600'
                  ]"
                >
                  {{ iss.sentiment || 'Positif' }}
                </span>
              </div>
              <p class="text-slate-300 leading-relaxed">{{ iss.fact }}</p>

              <!-- Impact Kamtibmas & Unmitigated Risk Badges -->
              <div v-if="iss.kamtibmas_impact" class="p-2.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-[11px] text-amber-200">
                <strong class="text-amber-400 font-bold block mb-0.5">🛡️ Dampak Kamtibmas Wilayah:</strong>
                <span>{{ iss.kamtibmas_impact }}</span>
              </div>
              <div v-if="iss.unmitigated_risk" class="p-2.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-[11px] text-rose-200">
                <strong class="text-rose-400 font-bold block mb-0.5">⚠️ Risiko Jika Tanpa Mitigasi Cepat:</strong>
                <span>{{ iss.unmitigated_risk }}</span>
              </div>
              <div class="text-[10px] text-slate-400 flex items-center justify-between pt-1 border-t border-slate-800/60 flex-wrap gap-2">
                <span class="flex items-center gap-1 font-mono text-slate-400">
                  <Radio class="w-3 h-3 text-amber-400" />
                  {{ iss.source }}
                </span>
                <div class="flex items-center gap-2">
                  <span class="text-slate-400">Virality: <strong class="text-amber-300">{{ iss.virality || 'Sedang' }}</strong></span>
                  <a
                    v-if="iss.url"
                    :href="iss.url"
                    target="_blank"
                    class="text-sky-400 hover:underline flex items-center gap-0.5"
                  >
                    <span>Tautan</span>
                    <ExternalLink class="w-3 h-3" />
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- WhatsApp Report Dispatch Card (Integrated in Tab 02) -->
      <div class="rounded-3xl bg-[#0b1426] border border-emerald-500/30 overflow-hidden shadow-2xl space-y-0">
        <!-- Header -->
        <div class="p-6 bg-gradient-to-r from-emerald-950/40 via-[#0b1426] to-[#080f20] border-b border-emerald-500/20 flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div class="space-y-1">
            <div class="flex items-center gap-2">
              <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
                LAPORAN RESMI WHATSAPP
              </span>
              <span class="text-xs text-slate-400 font-mono">{{ report?.report_time || '19.03 WITA' }}</span>
            </div>
            <h3 class="text-base font-extrabold text-white flex items-center gap-2">
              <MessageSquare class="w-5 h-5 text-emerald-400" />
              <span>Format Naskah WhatsApp Siap Kirim ke Pimpinan</span>
            </h3>
            <p class="text-xs text-slate-400">
              Sintesis analisis intelijen media & sentimen opini publik terstandarisasi untuk Bapak Kapolda & Pejabat Utama
            </p>
          </div>

          <!-- Quick Action Buttons -->
          <div class="flex items-center flex-wrap gap-2 shrink-0">
            <!-- Scroll Top -->
            <button
              @click="scrollWaToTop"
              class="p-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-300 hover:text-white transition"
              title="Gulir ke Atas"
            >
              <ChevronUp class="w-4 h-4" />
            </button>
            <!-- Scroll Bottom -->
            <button
              @click="scrollWaToBottom"
              class="p-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-300 hover:text-white transition"
              title="Gulir ke Bawah"
            >
              <ChevronDown class="w-4 h-4" />
            </button>
            <!-- Unduh .txt -->
            <button
              @click="downloadWaTxt"
              class="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-300 hover:text-white text-xs font-bold transition"
              title="Unduh naskah sebagai file .txt"
            >
              <Download class="w-3.5 h-3.5 text-slate-400" />
              <span>Unduh .txt</span>
            </button>
            <!-- Kirim WA Langsung -->
            <button
              @click="openWhatsAppDirect"
              class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-600/30 hover:bg-emerald-600/50 border border-emerald-500/50 text-emerald-200 text-xs font-bold transition"
              title="Buka WhatsApp Web dengan naskah siap kirim"
            >
              <Send class="w-3.5 h-3.5 text-emerald-400" />
              <span>Kirim ke WA</span>
            </button>
            <!-- Salin Format WA -->
            <button
              @click="copyWaReport"
              class="flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-400 hover:from-emerald-400 hover:to-teal-300 text-slate-950 text-xs font-black transition shadow-lg shadow-emerald-500/20"
            >
              <Check v-if="isWaCopied" class="w-4 h-4" />
              <Copy v-else class="w-4 h-4" />
              <span>{{ isWaCopied ? 'Berhasil Disalin!' : 'Salin Naskah WA' }}</span>
            </button>
          </div>
        </div>

        <!-- Terminal Preformatted Box -->
        <div class="relative bg-[#060b14] p-5">
          <div
            ref="waBoxTab2Ref"
            class="max-h-[380px] overflow-y-auto font-mono text-xs leading-relaxed text-emerald-300/90 whitespace-pre-wrap select-all p-4 rounded-2xl bg-black/50 border border-emerald-950/80 shadow-inner custom-scrollbar"
          >
            {{ whatsappReportText }}
          </div>
        </div>

        <!-- Footer Bar -->
        <div class="px-6 py-3 bg-[#080f1e] border-t border-slate-800 flex items-center justify-between text-[11px] text-slate-400">
          <div class="flex items-center gap-4">
            <span class="flex items-center gap-1.5 text-emerald-400 font-semibold">
              <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
              Tersinkronisasi Realtime WITA
            </span>
            <span>Karakter: {{ whatsappReportText.length }}</span>
            <span>Target: Bapak Kapolda & PJU</span>
          </div>
          <div class="flex items-center gap-2">
            <button
              @click="downloadWaTxt"
              type="button"
              class="text-slate-300 hover:text-white font-bold flex items-center gap-1 text-[11px] cursor-pointer"
            >
              <Download class="w-3.5 h-3.5 text-slate-400" />
              <span>Unduh .txt</span>
            </button>
            <span class="text-slate-600">•</span>
            <button
              @click="copyWaReport"
              type="button"
              class="text-amber-400 hover:text-amber-300 hover:underline font-bold flex items-center gap-1 text-[11px] cursor-pointer"
            >
              <Copy class="w-3.5 h-3.5" />
              <span>{{ isWaCopied ? 'Tersalin!' : 'Salin Naskah' }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 03: EARLY WARNING SYSTEM (EWS & DETEKSI KERAWANAN SIBER)              -->
    <!-- ========================================================================= -->
    <div v-show="activeTab === '03'" class="space-y-6">
      <!-- EWS Header Banner -->
      <div class="rounded-3xl bg-gradient-to-r from-rose-950/40 via-[#0b1426] to-[#070d1d] border border-rose-500/40 p-6 shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="space-y-1">
          <div class="flex items-center gap-2">
            <Flame class="w-5 h-5 text-rose-500 animate-pulse" />
            <h2 class="text-lg md:text-xl font-black text-white tracking-tight">
              Early Warning System (EWS) &amp; Deteksi Kerawanan Siber
            </h2>
          </div>
          <p class="text-xs text-slate-300 max-w-2xl leading-relaxed">
            Penyaringan otomatis terhadap isu-isu beresiko krisis reputasi dan eskalasi opini publik. Mengukur dimensi kamtibmas terancam, analisis dampak fatal tanpa mitigasi, serta pembagian PIC satker penanggung jawab.
          </p>
        </div>
        <span class="px-3.5 py-1.5 rounded-full text-xs font-black uppercase tracking-wider bg-rose-500/20 text-rose-300 border border-rose-500/40 shrink-0">
          {{ (report?.early_warnings?.length || 0) }} Peringatan Siaga Aktif
        </span>
      </div>

      <!-- EWS Cards List -->
      <div
        v-for="(ew, idx) in (report?.early_warnings || [])"
        :key="idx"
        class="rounded-3xl bg-[#0b1426] border border-amber-400/40 p-6 shadow-xl space-y-4"
      >
        <div class="flex items-center justify-between border-b border-slate-800 pb-3 flex-wrap gap-2">
          <div class="flex items-center gap-2">
            <span
              class="px-2.5 py-0.5 rounded text-[10px] font-black uppercase tracking-wider"
              :class="ew.level === 'MERAH' ? 'bg-rose-500 text-white shadow-md' : 'bg-amber-400 text-slate-950'"
            >
              LEVEL {{ ew.level }}
            </span>
            <h3 class="text-sm font-black text-white">{{ ew.title }}</h3>
          </div>
          <span class="text-xs font-mono text-slate-400">PIC: {{ ew.pic }}</span>
        </div>

        <p class="text-xs text-slate-300 leading-relaxed">{{ ew.fact }}</p>

        <!-- Dimensi Kamtibmas & Dampak Fatal Jika Tidak Dimitigasi (Unmitigated Impact) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div class="p-3.5 rounded-2xl bg-slate-900 border border-slate-800 text-xs text-amber-300 space-y-1">
            <strong class="block text-[11px] font-bold text-amber-400 flex items-center gap-1.5">
              <span>⚖️ Dimensi Kamtibmas Terancam:</span>
            </strong>
            <span class="text-slate-200">{{ ew.kamtibmas_aspect || 'Harkamtibmas Fisik, Ruang Siber & Reputasi Institusi Nasional' }}</span>
          </div>

          <div class="p-3.5 rounded-2xl bg-rose-950/60 border border-rose-500/50 text-xs text-rose-200 space-y-1 shadow-lg">
            <strong class="block text-[11px] font-black text-rose-400 uppercase tracking-wider flex items-center gap-1.5">
              <AlertTriangle class="w-3.5 h-3.5 text-rose-400 shrink-0" />
              <span>Kajian Dampak Fatal Jika Tanpa Mitigasi Cepat (6–24 Jam):</span>
            </strong>
            <span class="leading-relaxed">{{ ew.unmitigated_risk || ew.risk || 'Keterlambatan klarifikasi resmi berisiko memicu framing negatif liar netizen, liputan kritis media arus utama, serta potensi mobilisasi massa.' }}</span>
          </div>
        </div>

        <!-- 3-Phase Response Timeline (Humanis, Transparan & Berbasis Bukti) -->
        <div class="space-y-1.5 pt-1">
          <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">
            Rencana Aksi Mitigasi Berjenjang (Standar Mabes Polri):
          </span>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 text-xs">
            <div class="p-3 rounded-xl bg-slate-900/80 border border-slate-800 space-y-1">
              <strong class="text-amber-400 block text-[10px] uppercase font-bold tracking-wider">
                Tahap 0–2 Jam (Validasi &amp; Empati):
              </strong>
              <span class="text-slate-300 leading-relaxed">{{ ew.response_0_2h }}</span>
            </div>
            <div class="p-3 rounded-xl bg-slate-900/80 border border-slate-800 space-y-1">
              <strong class="text-sky-400 block text-[10px] uppercase font-bold tracking-wider">
                Tahap 2–6 Jam (Rilis Transparan &amp; Fakta):
              </strong>
              <span class="text-slate-300 leading-relaxed">{{ ew.response_2_6h }}</span>
            </div>
            <div class="p-3 rounded-xl bg-slate-900/80 border border-slate-800 space-y-1">
              <strong class="text-emerald-400 block text-[10px] uppercase font-bold tracking-wider">
                Tahap 6–24 Jam (Evaluasi &amp; Pemulihan):
              </strong>
              <span class="text-slate-300 leading-relaxed">{{ ew.response_6_24h }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 04: STRATEGI HUMAS, KONTRA-OPINI & ORKESTRASI SATWIL                   -->
    <!-- ========================================================================= -->
    <div v-show="activeTab === '04'" class="space-y-6">
      <!-- Tab Header Banner -->
      <div class="rounded-3xl bg-gradient-to-r from-emerald-950/40 via-[#0b1426] to-[#070d1d] border border-emerald-500/40 p-6 shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="space-y-1">
          <div class="flex items-center gap-2">
            <Award class="w-5 h-5 text-emerald-400" />
            <h2 class="text-lg md:text-xl font-black text-white tracking-tight">
              Strategi Humas, Kontra-Opini &amp; Orkestrasi Satwil
            </h2>
          </div>
          <p class="text-xs text-slate-300 max-w-2xl leading-relaxed">
            Pedoman taktis Divisi Humas &amp; Polda Jajaran dalam mengendalikan narasi ruang publik, membanjiri ruang digital dengan konten positif yang menyejukkan, serta orkestrasi serempak lintas Satwil.
          </p>
        </div>
        <span class="px-3.5 py-1.5 rounded-full text-xs font-black uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 shrink-0">
          Operasional Taktis Humas
        </span>
      </div>

      <!-- Section 1: Ide Konten Kreatif & Viralisasi Humas -->
      <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800/80 pb-3 flex-wrap gap-2">
          <h3 class="text-xs font-black text-white uppercase tracking-wider flex items-center gap-2">
            <Sparkles class="w-4 h-4 text-amber-400" />
            <span>Ide Konten Kreatif &amp; Viralisasi Positif (Multi-Platform)</span>
          </h3>
          <span class="text-[10px] font-mono text-slate-400">Tribratanews • Instagram • TikTok • 𝕏</span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div
            v-for="(c, idx) in (report?.strategic_contents || [])"
            :key="idx"
            class="p-4 rounded-2xl bg-slate-900 border border-slate-800 space-y-2 text-xs hover:border-slate-700 transition"
          >
            <div class="flex items-center justify-between">
              <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase bg-amber-400 text-slate-950">{{ c.label }}</span>
              <span class="text-slate-400 font-mono text-[10px]">{{ c.platform }}</span>
            </div>
            <p class="font-bold text-white text-sm">{{ c.title }}</p>
            <p class="text-slate-300 leading-relaxed">Format: {{ c.format }} &bull; Target: {{ c.target }}</p>
          </div>
        </div>
      </div>

      <!-- Section 2: Rekomendasi Komunikasi Publik & Talking Points Pimpinan -->
      <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800/80 pb-3 flex-wrap gap-2">
          <h3 class="text-xs font-black text-white uppercase tracking-wider flex items-center gap-2">
            <MessageSquare class="w-4 h-4 text-emerald-400" />
            <span>Rekomendasi Komunikasi Publik &amp; Panduan Juru Bicara</span>
          </h3>
          <span class="text-[10px] font-mono text-slate-400">Arahan Pejabat Utama &amp; Kasatwil</span>
        </div>
        <div class="space-y-3">
          <div
            v-for="(rec, idx) in (report?.recommendations || [])"
            :key="idx"
            class="p-4 rounded-2xl bg-slate-900 border border-slate-800 space-y-1 text-xs hover:border-slate-700 transition"
          >
            <div class="flex items-center justify-between">
              <span class="font-bold text-white text-sm">{{ rec.issue }}</span>
              <span class="text-amber-400 font-bold text-[10px]">{{ rec.target }}</span>
            </div>
            <p class="text-slate-300 leading-relaxed">{{ rec.action }}</p>
            <p class="text-[10px] text-slate-400 pt-1 border-t border-slate-800/80">PIC: {{ rec.pic }} &bull; Indikator: {{ rec.indicator }}</p>
          </div>
        </div>
      </div>

      <!-- Section 3: Pemetaan & Orkestrasi Diseminasi Satwil Jajaran -->
      <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-800 gap-2">
          <div>
            <h3 class="text-xs font-black text-white uppercase tracking-wider flex items-center gap-2">
              <MapPin class="w-4 h-4 text-amber-400" />
              <span>{{ isDivhumas ? 'Orkestrasi Diseminasi Narasi 34 Polda Jajaran' : ('Pemetaan Kondusifitas Jajaran ' + (selectedPolda?.name || 'Polda Sulbar')) }}</span>
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">
              {{ isDivhumas ? 'Monitoring komando Divhumas Polri atas stabilitas kamtibmas dan sentimen opini publik kewilayahan.' : 'Pantauan kondusifitas dan sentimen kamtibmas di tingkat Polres jajaran.' }}
            </p>
          </div>
          <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 shrink-0 self-start sm:self-auto">
            {{ isDivhumas ? ((allPoldas?.filter(p => p.code !== 'DIVHUMAS').length || 1) + ' Polda Terpantau Kondusif') : ((selectedPolda?.polres?.length || 6) + ' Polres Jajaran Aktif') }}
          </span>
        </div>

        <!-- Mode Divhumas: Grid 34 Polda Jajaran -->
        <div v-if="isDivhumas" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="polda in (allPoldas?.filter(p => p.code !== 'DIVHUMAS') || [])"
            :key="polda.code"
            class="p-5 rounded-2xl bg-slate-900 border border-slate-800 hover:border-amber-400/50 transition-all space-y-2.5"
          >
            <div class="flex items-start justify-between gap-2">
              <div>
                <span class="font-bold text-white block text-sm">{{ polda.name }}</span>
                <span class="text-[10px] font-mono text-slate-400 uppercase tracking-wider">{{ polda.code }} • Zona {{ polda.timezone === 'Asia/Jakarta' ? 'WIB' : (polda.timezone === 'Asia/Jayapura' ? 'WIT' : 'WITA') }}</span>
              </div>
              <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 shrink-0">
                Kondusif
              </span>
            </div>
            <p class="text-xs text-slate-300 leading-relaxed">
              Stabilitas kamtibmas wilayah terpantau aman dan tertib. Koordinasi rilis terpadu bersama {{ polda.polres?.length || 0 }} Polres jajaran.
            </p>
            <div class="pt-2 border-t border-slate-800/80 flex items-center justify-between text-[11px] text-slate-400">
              <span class="flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                <span>Sentimen: <strong class="text-emerald-400">86% Positif</strong></span>
              </span>
              <button
                type="button"
                @click="switchTenant(polda.code)"
                class="inline-flex items-center gap-1 text-[11px] font-bold text-amber-400 hover:text-amber-300 transition cursor-pointer select-none"
              >
                <span>Pantau Wilayah</span>
                <ExternalLink class="w-3 h-3" />
              </button>
            </div>
          </div>
        </div>

        <!-- Mode Polda Wilayah: Grid Polres Jajaran -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="polres in (selectedPolda?.polres || [
              { name: 'Polresta Mamuju', code: 'MAMUJU' },
              { name: 'Polres Majene', code: 'MAJENE' },
              { name: 'Polres Polewali Mandar', code: 'POLMAN' },
              { name: 'Polres Mamasa', code: 'MAMASA' },
              { name: 'Polres Pasangkayu', code: 'PASANGKAYU' },
              { name: 'Polres Mamuju Tengah', code: 'MATENG' },
            ])"
            :key="polres.name"
            class="p-5 rounded-2xl bg-slate-900 border border-slate-800 space-y-2"
          >
            <div class="flex items-center justify-between">
              <span class="font-bold text-white">{{ polres.name }}</span>
              <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-500/20 text-emerald-300">Kondusif</span>
            </div>
            <p class="text-xs text-slate-300">Patroli presisi dan pemantauan ruang publik terpantau lancar tanpa gejolak menonjol.</p>
            <div class="pt-2 border-t border-slate-800 flex items-center justify-between text-[11px] text-slate-400">
              <span>Indeks Sentimen: 85% Positif</span>
              <span class="text-amber-400 font-bold">Siaga Normal</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODALS & OVERLAYS CONTAINER                                               -->
    <!-- ========================================================================= -->
    <Teleport to="body">

    <!-- ========================================================================= -->
    <!-- MODAL 3: BUAT LAPORAN ISU DARI TEMUAN BERITA                               -->
    <!-- ========================================================================= -->
    <div
      v-if="showIssueModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
    >
      <div class="w-full max-w-lg rounded-3xl bg-[#0b1426] border border-slate-700 shadow-2xl p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <Flame class="w-4 h-4 text-amber-400" />
            <h3 class="text-base font-extrabold text-white">Eskalasi Berita Menjadi Isu Kamtibmas</h3>
          </div>
          <button @click="showIssueModal = false" class="text-slate-400 hover:text-white">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="p-3 rounded-2xl bg-slate-900 border border-slate-800 space-y-1 text-xs">
          <p class="font-bold text-white">{{ activeArticleForIssue?.title }}</p>
          <p class="text-[10px] text-amber-400">{{ activeArticleForIssue?.source_name }} &bull; {{ activeArticleForIssue?.pub_date }}</p>
        </div>

        <div class="space-y-3 text-xs">
          <div>
            <label class="block font-bold text-slate-300 mb-1">Tingkat Prioritas Isu</label>
            <div class="grid grid-cols-3 gap-2">
              <button
                v-for="prio in ['HIJAU', 'KUNING', 'MERAH']"
                :key="prio"
                type="button"
                @click="issueForm.priority = prio"
                class="py-2 rounded-xl text-xs font-black uppercase transition"
                :class="issueForm.priority === prio
                  ? prio === 'MERAH' ? 'bg-rose-500 text-white' : prio === 'KUNING' ? 'bg-amber-400 text-slate-950' : 'bg-emerald-500 text-slate-950'
                  : 'bg-slate-900 border border-slate-700 text-slate-400'"
              >
                {{ prio }}
              </button>
            </div>
          </div>

          <div>
            <label class="block font-bold text-slate-300 mb-1">Satker / PIC Tindak Lanjut</label>
            <input
              v-model="issueForm.pic"
              type="text"
              class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400"
            />
          </div>

          <div>
            <label class="block font-bold text-slate-300 mb-1">Catatan Eskalasi / Arahan Pimpinan</label>
            <textarea
              v-model="issueForm.notes"
              rows="3"
              class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400"
            ></textarea>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-800">
          <button
            @click="showIssueModal = false"
            class="px-4 py-2 rounded-xl bg-slate-900 border border-slate-700 text-xs font-bold text-slate-300 hover:text-white"
          >
            Batal
          </button>
          <button
            @click="submitIssue"
            class="px-5 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black transition"
          >
            Simpan ke Manajemen Isu
          </button>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 4: LIHAT & SALIN FORMULA PROMPT AI (PREVIEW MODAL)                  -->
    <!-- ========================================================================= -->
    <div
      v-if="showPromptModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
    >
      <div class="w-full max-w-3xl rounded-3xl bg-[#0b1426] border border-amber-400/40 shadow-2xl p-6 space-y-4 max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <Sparkles class="w-5 h-5 text-amber-400" />
            <div>
              <h3 class="text-base font-extrabold text-white">{{ promptModalTitle || 'Formula Prompt AI Presisi' }}</h3>
              <p class="text-xs text-slate-400">Siap digunakan di asisten AI pilihan Anda</p>
            </div>
          </div>
          <button @click="showPromptModal = false" class="text-slate-400 hover:text-white">
            <X class="w-5 h-5" />
          </button>
        </div>

        <p class="text-xs text-slate-300 leading-relaxed">
          Salin seluruh formula prompt di bawah ini, lalu tempelkan ke kolom chat AI. Formula ini telah disesuaikan secara otomatis dengan satuan komando <strong>{{ isDivhumas ? 'Divisi Humas Polri (Mabes Polri)' : (selectedPolda?.name || 'Polda Sulbar') }}</strong> dan jajaran {{ isDivhumas ? '34 Polda se-Indonesia' : 'Polres terkait' }}.
        </p>

        <div class="relative flex-1 overflow-hidden rounded-2xl border border-slate-700 bg-slate-950">
          <textarea
            readonly
            :value="promptModalContent"
            rows="14"
            class="w-full h-80 p-4 text-xs font-mono text-amber-300 bg-slate-950 border-none focus:outline-none resize-none leading-relaxed"
            @focus="$event.target.select()"
          ></textarea>
        </div>

        <div class="flex items-center justify-between pt-3 border-t border-slate-800">
          <span class="text-[11px] text-slate-400">Tips: Klik kolom teks untuk memblok/memilih semua secara manual</span>
          <div class="flex items-center gap-2">
            <button
              @click="showPromptModal = false"
              class="px-4 py-2 rounded-xl bg-slate-900 border border-slate-700 text-xs font-bold text-slate-300 hover:text-white"
            >
              Tutup
            </button>
            <button
              @click="execCommandCopy(promptModalContent, promptModalTitle || 'Formula Prompt AI'); showPromptModal = false;"
              class="flex items-center gap-2 px-5 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black transition shadow-lg shadow-amber-400/20"
            >
              <Copy class="w-4 h-4" />
              <span>Salin Teks ke Clipboard</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 5: BUAT / EDIT TOPIK ATENSI PIMPINAN (SMART DOSSIER)                -->
    <!-- ========================================================================= -->
    <div
      v-if="showTopicModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
    >
      <div class="w-full max-w-xl rounded-3xl bg-[#0b1426] border border-amber-400/50 shadow-2xl p-6 space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <span class="text-xl">📌</span>
            <div>
              <h3 class="text-base font-extrabold text-white">
                {{ isEditingTopic ? 'Edit Topik Atensi Pimpinan' : 'Daftarkan Topik Atensi Pimpinan (Smart Dossier)' }}
              </h3>
              <p class="text-xs text-slate-400">Kasus khusus atau permintaan pimpinan yang memerlukan pemantauan intensif</p>
            </div>
          </div>
          <button @click="showTopicModal = false" class="text-slate-400 hover:text-white cursor-pointer">
            <X class="w-5 h-5" />
          </button>
        </div>

        <form @submit.prevent="submitTopicForm" class="space-y-4 text-xs">
          <!-- Judul Kasus -->
          <div class="space-y-1">
            <label class="block font-bold text-slate-200">Judul Kasus / Isu Atensi *</label>
            <input
              v-model="topicForm.title"
              type="text"
              required
              placeholder="Contoh: Penggeledahan Ponpes di Sukoharjo & Respon Komnas HAM"
              class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white font-semibold focus:outline-none focus:border-amber-400"
            />
          </div>

          <!-- Kata Kunci Pantau -->
          <div class="space-y-1">
            <label class="block font-bold text-slate-200">Kata Kunci Pantauan Otomatis (Keywords) *</label>
            <textarea
              v-model="topicForm.keywords"
              rows="3"
              required
              placeholder="Contoh: pesantren, ponpes, sukoharjo, karanganyar, komnas ham, penggeledahan"
              class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white font-mono focus:outline-none focus:border-amber-400"
            ></textarea>
            <p class="text-[10px] text-slate-400">
              💡 Pisahkan dengan koma. Setiap berita dari 22/29 portal yang memuat kata kunci ini akan otomatis masuk ke dalam topik ini.
            </p>
          </div>

          <!-- Tingkat Prioritas -->
          <div class="space-y-1">
            <label class="block font-bold text-slate-200">Tingkat Prioritas</label>
            <select
              v-model="topicForm.priority"
              class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white font-bold focus:outline-none focus:border-amber-400"
            >
              <option value="urgent">🔴 Sangat Mendesak / Viral (Atensi Pimpinan Utama)</option>
              <option value="high">🟡 Prioritas Tinggi (Potensi Eskalasi Negatif)</option>
              <option value="medium">🔵 Sedang (Monitoring Rutin)</option>
              <option value="low">⚪ Rendah / Arsip</option>
            </select>
          </div>

          <!-- Arahan Pimpinan / Catatan Khusus -->
          <div class="space-y-1">
            <label class="block font-bold text-slate-200">Arahan Khusus &amp; Catatan Pimpinan (Opsional)</label>
            <textarea
              v-model="topicForm.description"
              rows="3"
              placeholder="Contoh: Atensi Kadiv Humas Polri: Pantau intensitas kritik eksternal, siapkan rilis klarifikasi transparan, dan koordinasi dengan Bidpropam Polda Jateng."
              class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-slate-300 focus:outline-none focus:border-amber-400"
            ></textarea>
          </div>

          <!-- Otomasi Tarik Berita Live dari Google News (Khusus Buat Baru) -->
          <div v-if="!isEditingTopic" class="flex items-start gap-2.5 p-3.5 rounded-2xl bg-gradient-to-r from-blue-950/40 via-slate-900 to-blue-950/40 border border-blue-500/30">
            <input
              id="crawl_live_checkbox"
              v-model="topicForm.crawl_live"
              type="checkbox"
              class="mt-0.5 rounded border-slate-700 text-blue-500 focus:ring-blue-400 bg-slate-950 w-4 h-4 cursor-pointer shrink-0"
            />
            <label for="crawl_live_checkbox" class="text-xs text-slate-200 cursor-pointer select-none">
              <span class="font-bold text-cyan-300 flex items-center gap-1.5">
                <span>⚡ Tarik Otomatis Sebaran Berita Internet (Google News)</span>
              </span>
              <span class="block text-[11px] text-slate-400 mt-0.5 leading-relaxed">
                Langsung menarik 30–50 sebaran berita dari seluruh portal berita online Indonesia (termasuk Tribratanews &amp; portal daerah) begitu topik ini didaftarkan.
              </span>
            </label>
          </div>

          <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-800">
            <button
              type="button"
              @click="showTopicModal = false"
              class="px-4 py-2 rounded-xl bg-slate-900 border border-slate-700 text-slate-300 hover:text-white"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="isSubmittingTopic"
              class="flex items-center gap-2 px-5 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-black transition disabled:opacity-50 cursor-pointer shadow-lg shadow-amber-400/20"
            >
              <Check class="w-4 h-4" />
              <span>{{ isSubmittingTopic ? 'Menyimpan...' : (isEditingTopic ? 'Simpan Perubahan' : 'Daftarkan Topik') }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 5B: TARIK SEBARAN BERITA LIVE DARI GOOGLE NEWS (ON-DEMAND)         -->
    <!-- ========================================================================= -->
    <div
      v-if="showLiveCrawlModal && crawlingTopic"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
    >
      <div class="w-full max-w-xl rounded-3xl bg-[#0b1426] border border-blue-500/50 shadow-2xl p-6 md:p-7 space-y-5 max-h-[90vh] overflow-y-auto">
        <!-- Header -->
        <div class="flex items-start justify-between border-b border-slate-800 pb-4">
          <div class="space-y-1">
            <div class="flex items-center gap-2">
              <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-blue-600 text-white">
                Google News Live Engine
              </span>
              <span class="text-xs font-mono text-cyan-400">Seluruh Portal Media Indonesia</span>
            </div>
            <h3 class="text-base md:text-lg font-black text-white">
              Tarik Sebaran Berita Internet: {{ crawlingTopic.title }}
            </h3>
            <p class="text-xs text-slate-400">
              Menghimpun sebaran pemberitaan dari seluruh portal berita nasional, daerah, dan Tribratanews secara live.
            </p>
          </div>
          <button @click="showLiveCrawlModal = false" class="text-slate-400 hover:text-white cursor-pointer p-1">
            <X class="w-5 h-5" />
          </button>
        </div>

        <!-- Form / Controls -->
        <div class="space-y-4 text-xs">
          <!-- Keyword Input -->
          <div class="space-y-1.5">
            <label class="block font-bold text-slate-200">
              Kata Kunci Pencarian di Internet *
            </label>
            <div class="relative">
              <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-3" />
              <input
                v-model="liveCrawlQuery"
                type="text"
                required
                placeholder="Contoh: HUT Humas Polri atau Hari Ulang Tahun Humas"
                class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white font-semibold focus:outline-none focus:border-cyan-400"
              />
            </div>
            <p class="text-[11px] text-slate-400">
              💡 Gunakan kata kunci yang ringkas dan representatif untuk memaksimalkan tangkapan berita di seluruh portal Indonesia.
            </p>
          </div>

          <!-- Target Depth / Kedalaman Data -->
          <div class="space-y-1.5">
            <label class="block font-bold text-slate-200">Target Kedalaman Data</label>
            <div class="grid grid-cols-3 gap-2">
              <button
                type="button"
                @click="liveCrawlDepth = 25"
                :class="liveCrawlDepth === 25 ? 'bg-blue-600/30 border-blue-400 text-white font-black' : 'bg-slate-900 border-slate-800 text-slate-400 hover:text-slate-200'"
                class="p-2.5 rounded-xl border text-center transition cursor-pointer select-none"
              >
                <div class="text-xs font-bold">25 Berita</div>
                <div class="text-[10px] text-slate-400">Kilat (&lt; 3 dtk)</div>
              </button>
              <button
                type="button"
                @click="liveCrawlDepth = 50"
                :class="liveCrawlDepth === 50 ? 'bg-blue-600/30 border-blue-400 text-white font-black' : 'bg-slate-900 border-slate-800 text-slate-400 hover:text-slate-200'"
                class="p-2.5 rounded-xl border text-center transition cursor-pointer select-none"
              >
                <div class="text-xs font-bold">50 Berita</div>
                <div class="text-[10px] text-cyan-400">Standar Optimal</div>
              </button>
              <button
                type="button"
                @click="liveCrawlDepth = 100"
                :class="liveCrawlDepth === 100 ? 'bg-blue-600/30 border-blue-400 text-white font-black' : 'bg-slate-900 border-slate-800 text-slate-400 hover:text-slate-200'"
                class="p-2.5 rounded-xl border text-center transition cursor-pointer select-none"
              >
                <div class="text-xs font-bold">100 Berita</div>
                <div class="text-[10px] text-amber-400">Komprehensif</div>
              </button>
            </div>
          </div>

          <!-- Error Alert -->
          <div v-if="liveCrawlError" class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs">
            ⚠️ {{ liveCrawlError }}
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-800">
          <button
            type="button"
            @click="showLiveCrawlModal = false"
            :disabled="isCrawlingLive"
            class="px-4 py-2 rounded-xl bg-slate-900 border border-slate-700 text-slate-300 hover:text-white cursor-pointer disabled:opacity-50"
          >
            Batal
          </button>
          <button
            type="button"
            @click="executeLiveCrawl"
            :disabled="isCrawlingLive || !liveCrawlQuery.trim()"
            class="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-500 hover:to-cyan-500 text-white font-bold shadow-lg shadow-blue-500/25 transition cursor-pointer disabled:opacity-50 select-none"
          >
            <RotateCw v-if="isCrawlingLive" class="w-4 h-4 animate-spin text-white" />
            <Zap v-else class="w-4 h-4 fill-white" />
            <span>{{ isCrawlingLive ? 'Sedang Menarik Berita...' : '⚡ Mulai Tarik Sebaran Berita' }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 6: TAGGING KASUS (KAITKAN BERITA KE TOPIK ATENSI)                   -->
    <!-- ========================================================================= -->
    <div
      v-if="showTagModal && targetArticleForTag"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
    >
      <div class="w-full max-w-lg rounded-3xl bg-[#0b1426] border border-slate-700 shadow-2xl p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <span class="text-xl">📌</span>
            <div>
              <h3 class="text-base font-extrabold text-white">Kaitkan Berita ke Topik Atensi</h3>
              <p class="text-xs text-slate-400">Pilih topik kasus untuk mengaitkan atau melepas berita ini</p>
            </div>
          </div>
          <button @click="showTagModal = false" class="text-slate-400 hover:text-white cursor-pointer">
            <X class="w-5 h-5" />
          </button>
        </div>

        <!-- Article Summary Box -->
        <div class="p-3 rounded-2xl bg-slate-900/90 border border-slate-800 space-y-1">
          <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase bg-slate-800 text-amber-400">
            {{ targetArticleForTag.source_name }} &bull; {{ targetArticleForTag.category }}
          </span>
          <h4 class="text-xs font-bold text-white leading-snug">
            {{ targetArticleForTag.title }}
          </h4>
        </div>

        <!-- Topics List Checklist -->
        <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
          <div
            v-for="t in topics"
            :key="t.id"
            class="p-3 rounded-xl border flex items-center justify-between gap-3 transition"
            :class="(targetArticleForTag.matched_topic_ids || []).includes(t.id)
              ? 'bg-amber-400/10 border-amber-400/60 text-white'
              : 'bg-slate-900 border-slate-800 text-slate-300 hover:border-slate-700'"
          >
            <div class="space-y-0.5 min-w-0">
              <div class="flex items-center gap-2">
                <span
                  class="w-2 h-2 rounded-full shrink-0"
                  :class="t.priority === 'urgent' ? 'bg-rose-500' : 'bg-amber-400'"
                ></span>
                <span class="text-xs font-bold truncate">{{ t.title }}</span>
              </div>
              <p class="text-[10px] text-slate-400 truncate">
                Kata kunci: {{ t.keywords }}
              </p>
            </div>

            <button
              @click="toggleArticleTopic(t.id)"
              class="px-3 py-1.5 rounded-lg text-xs font-bold transition shrink-0 cursor-pointer"
              :class="(targetArticleForTag.matched_topic_ids || []).includes(t.id)
                ? 'bg-emerald-500 text-white font-black'
                : 'bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700'"
            >
              {{ (targetArticleForTag.matched_topic_ids || []).includes(t.id) ? '✓ Terkait' : '+ Kaitkan' }}
            </button>
          </div>

          <div v-if="topics.length === 0" class="text-center py-6 text-xs text-slate-500">
            Belum ada topik atensi aktif. Buat topik atensi baru terlebih dahulu.
          </div>
        </div>

        <div class="flex items-center justify-end pt-3 border-t border-slate-800">
          <button
            @click="showTagModal = false"
            class="px-4 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-black text-xs cursor-pointer shadow-md"
          >
            Selesai
          </button>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 7: HASIL ANALISIS AI KHUSUS TOPIK ATENSI               -->
    <!-- ========================================================================= -->
    <div
      v-if="showTopicAiModal && activeAnalyzingTopic"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/85 backdrop-blur-md"
    >
      <div class="w-full max-w-4xl rounded-3xl bg-[#0b1426] border border-amber-400/50 shadow-2xl p-6 md:p-8 space-y-6 max-h-[90vh] overflow-y-auto">
        <!-- Modal Header -->
        <div class="flex items-start justify-between border-b border-slate-800 pb-4 gap-3">
          <div class="space-y-1">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-400 text-slate-950">
                Analisis AI
              </span>
              <span
                class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider"
                :class="activeAnalyzingTopic.priority === 'urgent' ? 'bg-rose-500 text-white' : 'bg-amber-500/20 text-amber-300 border border-amber-500/40'"
              >
                {{ activeAnalyzingTopic.priority === 'urgent' ? '🚨 Kasus Sangat Mendesak' : '⭐ Prioritas Tinggi' }}
              </span>
              <span
                v-if="activeAnalyzingTopic.ai_analyzed_at"
                class="text-[11px] text-slate-400 font-mono"
              >
                • Terakhir dianalisis: <strong class="text-slate-200">{{ activeAnalyzingTopic.ai_analyzed_at_humans || 'Baru saja' }}</strong> (basis {{ activeAnalyzingTopic.ai_articles_count || 0 }} berita)
              </span>
            </div>
            <h2 class="text-lg md:text-xl font-black text-white">
              Hasil Analisis Intelijen Kasus: {{ activeAnalyzingTopic.title }}
            </h2>
            <p class="text-xs text-slate-400">
              Analisis mendalam pemberitaan siber, asesmen risiko kamtibmas, talking points, dan naskah WhatsApp pimpinan.
            </p>
          </div>

          <div class="flex items-center gap-2 shrink-0">
            <!-- Re-analyze button inside modal -->
            <button
              @click="runTopicAiAnalysis(activeAnalyzingTopic, true)"
              :disabled="isAnalyzingTopic"
              type="button"
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white border border-slate-700 text-xs font-bold transition cursor-pointer disabled:opacity-50 select-none"
              title="Analisis ulang kasus dengan berita terkini menggunakan AI"
            >
              <RotateCw class="w-3.5 h-3.5 text-amber-400" :class="{ 'animate-spin': isAnalyzingTopic }" />
              <span>{{ isAnalyzingTopic ? 'Menganalisis...' : 'Perbarui Analisis' }}</span>
              <span
                v-if="getTopicNewArticlesCount(activeAnalyzingTopic) > 0"
                class="px-1.5 py-0.2 rounded-full text-[9px] bg-rose-500 text-white font-mono font-bold"
              >
                +{{ getTopicNewArticlesCount(activeAnalyzingTopic) }} Baru
              </span>
            </button>

            <button @click="showTopicAiModal = false" class="text-slate-400 hover:text-white cursor-pointer p-1">
              <X class="w-6 h-6" />
            </button>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="isAnalyzingTopic" class="py-16 flex flex-col items-center justify-center text-center space-y-4">
          <div class="relative w-16 h-16 flex items-center justify-center">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-30"></span>
            <div class="w-12 h-12 rounded-full border-3 border-amber-400 border-t-transparent animate-spin"></div>
            <Zap class="w-6 h-6 text-amber-400 absolute" />
          </div>
          <div class="space-y-1">
            <h4 class="text-sm font-black text-white">Mesin AI Sedang Menganalisis Seluruh Berita Kasus Ini...</h4>
            <p class="text-xs text-slate-400 max-w-md">
              Mengekstrak isu krusial, menghitung polarisasi sentimen, memetakan potensi risiko jika tidak dimitigasi, serta merancang naskah resmi kepolisian.
            </p>
          </div>
        </div>

        <!-- Error State -->
        <div v-else-if="topicAiError" class="p-4 rounded-2xl bg-rose-500/20 border border-rose-500/40 text-rose-200 text-xs space-y-2">
          <div class="flex items-center gap-2 font-bold text-rose-300">
            <AlertTriangle class="w-4 h-4" />
            <span>Kendala Analisis AI</span>
          </div>
          <p>{{ topicAiError }}</p>
          <button
            @click="runTopicAiAnalysis(activeAnalyzingTopic)"
            class="px-3 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs"
          >
            Coba Ulangi Analisis
          </button>
        </div>

        <!-- Analysis Content Result -->
        <div v-else-if="topicAiResult" class="space-y-6">
          <!-- 1. Executive Summary -->
          <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 space-y-2">
            <div class="flex items-center gap-2 text-amber-400 font-bold text-xs">
              <FileText class="w-4 h-4" />
              <span class="uppercase tracking-wider">1. Ringkasan Eksekutif Perkembangan Kasus</span>
            </div>
            <p class="text-xs md:text-sm text-slate-200 leading-relaxed whitespace-pre-line">
              {{ topicAiResult.executive_summary }}
            </p>
          </div>

          <!-- 2. Risk Assessment (Potensi Dampak & Risiko Kamtibmas) -->
          <div class="p-5 rounded-2xl bg-rose-950/30 border border-rose-500/40 space-y-2">
            <div class="flex items-center gap-2 text-rose-400 font-bold text-xs">
              <AlertTriangle class="w-4 h-4" />
              <span class="uppercase tracking-wider">2. Potensi Dampak &amp; Risiko Jika Tidak Dimitigasi</span>
            </div>
            <p class="text-xs md:text-sm text-slate-200 leading-relaxed whitespace-pre-line">
              {{ topicAiResult.risk_assessment }}
            </p>
          </div>

          <!-- 3. Sentiment & Framing Analysis -->
          <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 space-y-4">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-slate-300 uppercase tracking-wider flex items-center gap-2">
                <Zap class="w-4 h-4 text-amber-400" />
                <span>3. Polarisasi Sentimen &amp; Sudut Pandang Media</span>
              </span>
              <span
                class="px-2.5 py-0.5 rounded text-[10px] font-black uppercase"
                :class="topicAiResult.sentiment_analysis?.dominant_sentiment === 'negatif' ? 'bg-rose-500 text-white' : 'bg-emerald-500 text-white'"
              >
                Sentimen Dominan: {{ (topicAiResult.sentiment_analysis?.dominant_sentiment || 'Netral').toUpperCase() }}
              </span>
            </div>

            <!-- Sentiment Percentages Bar -->
            <div class="grid grid-cols-3 gap-3">
              <div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-center">
                <span class="text-[10px] font-bold text-emerald-400 uppercase">Positif</span>
                <div class="text-xl font-black text-emerald-300">{{ topicAiResult.sentiment_analysis?.positif || 0 }}%</div>
              </div>
              <div class="p-3 rounded-xl bg-sky-500/10 border border-sky-500/30 text-center">
                <span class="text-[10px] font-bold text-sky-400 uppercase">Netral</span>
                <div class="text-xl font-black text-sky-300">{{ topicAiResult.sentiment_analysis?.netral || 0 }}%</div>
              </div>
              <div class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-center">
                <span class="text-[10px] font-bold text-rose-400 uppercase">Negatif</span>
                <div class="text-xl font-black text-rose-300">{{ topicAiResult.sentiment_analysis?.negatif || 0 }}%</div>
              </div>
            </div>

            <p v-if="topicAiResult.sentiment_analysis?.framing_media" class="text-xs text-slate-300 leading-relaxed bg-slate-950 p-3 rounded-xl border border-slate-800">
              <strong>Framing Media &amp; Netizen:</strong> {{ topicAiResult.sentiment_analysis.framing_media }}
            </p>
          </div>

          <!-- 4. Talking Points (Poin Klarifikasi Resmi) -->
          <div v-if="topicAiResult.talking_points?.length" class="p-5 rounded-2xl bg-slate-900 border border-slate-800 space-y-3">
            <div class="flex items-center gap-2 text-sky-400 font-bold text-xs">
              <Award class="w-4 h-4" />
              <span class="uppercase tracking-wider">4. Poin-Poin Klarifikasi Resmi (Talking Points Juru Bicara)</span>
            </div>
            <ul class="space-y-2 text-xs text-slate-200">
              <li
                v-for="(tp, i) in topicAiResult.talking_points"
                :key="i"
                class="flex items-start gap-2.5 bg-slate-950/80 p-3 rounded-xl border border-slate-800"
              >
                <span class="w-5 h-5 rounded-full bg-sky-500/20 text-sky-300 font-black flex items-center justify-center shrink-0 text-[10px]">
                  {{ i + 1 }}
                </span>
                <span class="leading-relaxed">{{ tp }}</span>
              </li>
            </ul>
          </div>

          <!-- 5. Action Recommendations Timeline -->
          <div v-if="topicAiResult.action_recommendations?.length" class="p-5 rounded-2xl bg-slate-900 border border-slate-800 space-y-3">
            <div class="flex items-center gap-2 text-emerald-400 font-bold text-xs">
              <Clock class="w-4 h-4" />
              <span class="uppercase tracking-wider">5. Rekomendasi Garis Waktu Tindak Lanjut</span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
              <div
                v-for="(rec, i) in topicAiResult.action_recommendations"
                :key="i"
                class="p-3.5 rounded-xl bg-slate-950 border border-slate-800 space-y-2"
              >
                <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase bg-amber-400 text-slate-950">
                  {{ rec.timeframe }}
                </span>
                <p class="text-xs text-slate-200 leading-snug">{{ rec.action }}</p>
                <span class="text-[10px] font-bold text-slate-400 block pt-1 border-t border-slate-800">
                  PIC: {{ rec.pic }}
                </span>
              </div>
            </div>
          </div>

          <!-- 6. WhatsApp Script (Naskah Siap Kirim) -->
          <div class="p-5 rounded-2xl bg-emerald-950/30 border border-emerald-500/40 space-y-3">
            <div class="flex items-center justify-between flex-wrap gap-2">
              <span class="text-xs font-bold text-emerald-400 uppercase tracking-wider flex items-center gap-2">
                <MessageSquare class="w-4 h-4" />
                <span>6. Naskah WhatsApp Laporan Khusus Pimpinan (Siap Kirim)</span>
              </span>
              <div class="flex items-center gap-2">
                <button
                  @click="openTopicWaDirect"
                  type="button"
                  class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-emerald-300 border border-emerald-500/40 text-xs font-bold shadow-sm transition cursor-pointer"
                  title="Buka langsung dan kirim melalui WhatsApp"
                >
                  <Send class="w-3.5 h-3.5 text-emerald-400" />
                  <span>Kirim ke WA</span>
                </button>
                <button
                  @click="copyAiWaScript"
                  type="button"
                  class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-black text-xs shadow-md transition cursor-pointer"
                >
                  <Check v-if="isTopicWaCopied" class="w-3.5 h-3.5" />
                  <Copy v-else class="w-3.5 h-3.5" />
                  <span>{{ isTopicWaCopied ? 'Berhasil Disalin!' : 'Salin Naskah WA' }}</span>
                </button>
              </div>
            </div>

            <!-- Terminal Text Box -->
            <div class="p-4 rounded-xl bg-slate-950 border border-slate-800 font-mono text-xs text-emerald-300 whitespace-pre-wrap leading-relaxed max-h-[460px] overflow-y-auto select-all shadow-inner custom-scrollbar">
              {{ topicAiResult.whatsapp_script }}
            </div>

            <!-- Sub Footer Meta -->
            <div class="flex items-center justify-between text-[11px] text-slate-400 px-1 pt-1 border-t border-emerald-950/60 flex-wrap gap-2">
              <div class="flex items-center gap-3 flex-wrap">
                <span class="flex items-center gap-1.5 text-emerald-400 font-semibold">
                  <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                  Format Baku Kedinasan Polri (Presisi)
                </span>
                <span>Karakter: <strong class="text-white">{{ (topicAiResult.whatsapp_script || '').length }}</strong></span>
                <span>Estimasi Baca: <strong class="text-white">~2 Menit</strong></span>
              </div>
              <span class="text-slate-500 hidden sm:inline">Siap Teruskan ke Pimpinan</span>
            </div>
          </div>
        </div>

        <!-- Footer Actions -->
        <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-800">
          <button
            @click="showTopicAiModal = false"
            class="px-5 py-2 rounded-xl bg-slate-900 border border-slate-700 text-slate-300 hover:text-white text-xs font-bold cursor-pointer"
          >
            Tutup
          </button>
          <button
            v-if="topicAiResult?.whatsapp_script"
            @click="copyAiWaScript"
            class="flex items-center gap-2 px-5 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black transition cursor-pointer shadow-lg shadow-amber-400/20"
          >
            <Check v-if="isTopicWaCopied" class="w-4 h-4" />
            <Copy v-else class="w-4 h-4" />
            <span>{{ isTopicWaCopied ? 'Tersalin ke Clipboard!' : 'Salin Naskah WA Pimpinan' }}</span>
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  </EnterpriseLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  height: 4px;
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(148, 163, 184, 0.2);
  border-radius: 8px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgba(245, 158, 11, 0.5);
}

</style>
