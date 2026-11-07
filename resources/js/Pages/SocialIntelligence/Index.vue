<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AiQuotaBadge from '../../Components/AiQuotaBadge.vue';
import SentimentInfo from '@/Components/SentimentInfo.vue';
import { renderSafeMarkdown } from '@/utils/safeMarkdown';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import {
  Bot,
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
  Ellipsis,
  SlidersHorizontal,
  ChevronDown,
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
  sentimentLexicon: { type: Object, default: null },
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
  monitoredAccounts: { type: Array, default: () => [] },
  commentDigests: { type: Object, default: () => ({}) },
  autoIntervalHours: { type: Number, default: null },
  trendingHashtags: Array,
  trendingByPlatform: Object,
  publicTrends: Object,
  spikeTimeline: Object,
  wordCloud: Object,
  peakHours: Object,
  settings: Object,
  instagramReady: { type: Boolean, default: false },
  latestReport: { type: Object, default: null },
  reportHistory: { type: Array, default: () => [] },
  satwils: { type: Array, default: () => [] },
  filters: Object,
});

// Peran & izin pengguna (menentukan tombol yang tampil dan tab awal)
const page = usePage();
const permissions = computed(() => page.props.auth?.permissions || {});
const canSync = computed(() => !!permissions.value.social_intelligence_sync);
const canCreateIssue = computed(() => !!permissions.value.issues_create);
// Pantau Akun lintas platform (Apify, failover token di server).
const ACCOUNT_PLATFORMS = [
  { id: 'instagram', label: 'Instagram', placeholder: 'Username Instagram, mis. mohmahfudmd', hint: 'Akun publik Instagram.' },
  { id: 'tiktok', label: 'TikTok', placeholder: 'Username TikTok, mis. divisihumaspolri', hint: 'Akun TikTok publik; akun privat tidak dapat diambil.' },
  { id: 'x', label: 'X (Twitter)', placeholder: 'Username X, mis. DivHumas_Polri', hint: 'Retweet tidak dihitung sebagai unggahan akun.' },
  { id: 'youtube', label: 'YouTube', placeholder: 'Handle kanal tanpa @, mis. KompasTV', hint: 'Handle kanal (youtube.com/@handle). Hanya video, tanpa Shorts/siaran langsung.' },
  { id: 'facebook', label: 'Facebook (Halaman)', placeholder: 'Nama Halaman di URL, mis. KompasTV', hint: 'Hanya Halaman (Page) publik; profil pribadi tidak dapat dipantau.' },
];
const accountPlatform = ref('instagram');
const accountUsername = ref('');
const accountBusy = ref(false);
const accountMessage = ref('');
const accountError = ref(false);
const accountPlatformMeta = computed(() => ACCOUNT_PLATFORMS.find((x) => x.id === accountPlatform.value) || ACCOUNT_PLATFORMS[0]);
const reloadAccounts = () => router.reload({ only: ['monitoredAccounts', 'commentDigests'], preserveScroll: true });
const syncAccount = async (account = null) => {
  if (accountBusy.value || (!account && !accountUsername.value.trim())) return;
  accountBusy.value = true;
  accountMessage.value = '';
  try {
    const response = account
      ? await axios.post(`/social-intelligence/accounts/${account.id}/sync`)
      : await axios.post('/social-intelligence/accounts', { platform: accountPlatform.value, username: accountUsername.value });
    accountMessage.value = response.data.message;
    accountError.value = false;
    if (!account) accountUsername.value = '';
  } catch (error) {
    accountError.value = true;
    accountMessage.value = error.response?.data?.errors?.username?.[0] || error.response?.data?.message || 'Sinkronisasi gagal. Silakan coba lagi.';
  } finally {
    reloadAccounts();
    accountBusy.value = false;
  }
};
const removeAccount = async (account) => {
  if (!confirm(`Hapus ${account.username} (${getPlatformBadge(account.platform).label}) dari daftar pantauan?`)) return;
  try {
    await axios.delete(`/social-intelligence/accounts/${account.id}`);
    reloadAccounts();
  } catch {
    accountError.value = true;
    accountMessage.value = 'Akun tidak dapat dihapus.';
  }
};
const autoIntervalHours = computed(() => props.autoIntervalHours ?? 6);
const toggleAuto = async (account, enabled) => {
  try {
    const { data } = await axios.post(`/social-intelligence/accounts/${account.id}/auto`, { enabled });
    account.auto_monitor = data.auto_monitor;
    accountError.value = false;
    accountMessage.value = data.message;
  } catch (error) {
    accountError.value = true;
    accountMessage.value = error.response?.data?.message || 'Pengaturan pantau otomatis gagal disimpan.';
    reloadAccounts();
  }
};
const syncAllAccounts = async () => {
  if (accountBusy.value) return;
  accountBusy.value = true;
  accountMessage.value = '';
  let ok = 0;
  let failed = 0;
  for (const account of props.monitoredAccounts) {
    try {
      await axios.post(`/social-intelligence/accounts/${account.id}/sync`);
      ok++;
    } catch {
      failed++;
    }
  }
  accountError.value = failed > 0 && ok === 0;
  accountMessage.value = `${ok} akun berhasil disinkronkan${failed ? `, ${failed} gagal (lihat keterangan pada tiap akun)` : ''}.`;
  reloadAccounts();
  accountBusy.value = false;
};

// Ringkasan Komentar per unggahan: komentar asli + sentimen aturan; ringkasan SADEWA atas permintaan.
const digests = ref({ ...(props.commentDigests || {}) });
watch(() => props.commentDigests, (v) => { digests.value = { ...digests.value, ...(v || {}) }; });
const commentOpen = ref(new Set());
const commentBusy = ref({});
const commentNotice = ref({});
const toggleComments = (post) => {
  const next = new Set(commentOpen.value);
  next.has(post.url) ? next.delete(post.url) : next.add(post.url);
  commentOpen.value = next;
};
const fetchComments = async (account, post) => {
  if (commentBusy.value[post.url]) return;
  commentBusy.value = { ...commentBusy.value, [post.url]: 'fetch' };
  commentNotice.value = { ...commentNotice.value, [post.url]: null };
  try {
    const { data } = await axios.post('/social-intelligence/comments', { account_id: account.id, post_url: post.url });
    digests.value = { ...digests.value, [post.url]: data.digest };
    commentNotice.value = { ...commentNotice.value, [post.url]: { error: false, text: data.message } };
    commentOpen.value = new Set([...commentOpen.value, post.url]);
  } catch (error) {
    commentNotice.value = { ...commentNotice.value, [post.url]: { error: true, text: error.response?.data?.message || 'Komentar gagal diambil.' } };
  } finally {
    commentBusy.value = { ...commentBusy.value, [post.url]: null };
  }
};
const summarizeDigest = async (post, force = false) => {
  const digest = digests.value[post.url];
  if (!digest || commentBusy.value[post.url]) return;
  commentBusy.value = { ...commentBusy.value, [post.url]: 'ai' };
  try {
    const { data } = await axios.post(`/social-intelligence/comments/${digest.id}/summarize`, { force: force ? 1 : 0 });
    digests.value = { ...digests.value, [post.url]: data.digest };
    commentNotice.value = { ...commentNotice.value, [post.url]: { error: false, text: data.message } };
  } catch (error) {
    commentNotice.value = { ...commentNotice.value, [post.url]: { error: true, text: error.response?.data?.message || 'Ringkasan gagal.' } };
  } finally {
    commentBusy.value = { ...commentBusy.value, [post.url]: null };
  }
};
const digestComments = (d) => [...(d?.comments || [])].sort((a, b) => ({ negatif: 0, positif: 1, netral: 2 }[a.sentiment] - { negatif: 0, positif: 1, netral: 2 }[b.sentiment]) || ((b.likes ?? 0) - (a.likes ?? 0)));
const pct = (n, total) => (total ? Math.round((n / total) * 100) : 0);

// Ringkasan dihitung dari unggahan tersimpan; metrik yang tidak tersedia tidak ikut dirata-rata.
const instagramStats = (account) => {
  const avg = (key) => {
    const vals = (account.posts || []).map((p) => p[key]).filter((v) => typeof v === 'number');
    return vals.length ? Math.round(vals.reduce((x, y) => x + y, 0) / vals.length) : null;
  };
  const latest = (account.posts || []).find((p) => p.posted_at)?.posted_at;
  return { avgLikes: avg('likes'), avgComments: avg('comments'), latest: latest ? new Date(latest).toLocaleDateString('id-ID', { dateStyle: 'medium' }) : '—' };
};
const monitorKol = (kol) => {
  if (ACCOUNT_PLATFORMS.some((x) => x.id === kol.platform)) accountPlatform.value = kol.platform;
  accountUsername.value = String(kol.author_handle || '').replace(/^@/, '');
  activeTab.value = 'kol';
  document.getElementById('account-monitor-username')?.focus();
};
const instagramDate = (date) => date ? new Date(date).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : 'Waktu tidak tersedia';

// Daftar akun ringkas: banyak akun tidak membuat halaman memanjang. Satu akun → langsung terbuka.
// Direktori KOL: saring per platform; jumlah dihitung dari data yang dimuat (paritas angka = kartu).
const kolPlatform = ref('all');
const kolPlatformTabs = computed(() => {
  const counts = {};
  for (const k of props.kolProfiles || []) counts[k.platform] = (counts[k.platform] || 0) + 1;
  return [{ id: 'all', label: 'Semua', count: (props.kolProfiles || []).length },
    ...Object.keys(counts).sort().map((id) => ({ id, label: getPlatformBadge(id).label, count: counts[id] }))];
});
const visibleKols = computed(() => (props.kolProfiles || []).filter((k) => kolPlatform.value === 'all' || k.platform === kolPlatform.value));

const igSearch = ref('');
const igPlatform = ref('all');
const igPlatformTabs = computed(() => {
  const counts = {};
  for (const a of props.monitoredAccounts) counts[a.platform] = (counts[a.platform] || 0) + 1;
  return Object.keys(counts).map((id) => ({ id, label: getPlatformBadge(id).label, count: counts[id] }));
});
const igSort = ref('latest');
const igHighlightOnly = ref(false);
const igExpanded = ref(new Set());
const igShowAll = ref(new Set());
const IG_PREVIEW = 4;
const DAY_MS = 86400000;
const igLatestTime = (account) => Math.max(0, ...(account.posts || []).map((p) => (p.posted_at ? new Date(p.posted_at).getTime() : 0)));
const igEngagement = (account) => {
  const vals = (account.posts || []).map((p) => (p.likes ?? 0) + (p.comments ?? 0)).filter((v) => v > 0);
  return vals.length ? vals.reduce((a, b) => a + b, 0) / vals.length : 0;
};
const igHighlights = (account) => (account.posts || []).filter((p) => p.highlight);
const igIsNew = (account) => igLatestTime(account) > 0 && Date.now() - igLatestTime(account) < DAY_MS;
const igAccounts = computed(() => {
  const q = igSearch.value.trim().toLowerCase().replace(/^@/, '');
  const list = props.monitoredAccounts.filter((a) => {
    if (igHighlightOnly.value && !igHighlights(a).length) return false;
    if (igPlatform.value !== 'all' && a.platform !== igPlatform.value) return false;
    return !q || a.username.toLowerCase().includes(q) || (a.display_name || '').toLowerCase().includes(q);
  });
  const sorters = {
    latest: (a, b) => igLatestTime(b) - igLatestTime(a),
    engagement: (a, b) => igEngagement(b) - igEngagement(a),
    highlights: (a, b) => igHighlights(b).length - igHighlights(a).length || igLatestTime(b) - igLatestTime(a),
    name: (a, b) => (a.display_name || a.username).localeCompare(b.display_name || b.username, 'id'),
  };
  return [...list].sort(sorters[igSort.value] || sorters.latest);
});
const igSummary = computed(() => {
  const accounts = props.monitoredAccounts;
  const weekAgo = Date.now() - 7 * DAY_MS;
  const top = [...accounts].filter((a) => igEngagement(a) > 0).sort((a, b) => igEngagement(b) - igEngagement(a))[0];
  return {
    accounts: accounts.length,
    week: accounts.reduce((n, a) => n + (a.posts || []).filter((p) => p.posted_at && new Date(p.posted_at).getTime() >= weekAgo).length, 0),
    highlights: accounts.reduce((n, a) => n + igHighlights(a).length, 0),
    errors: accounts.filter((a) => a.last_error).length,
    top: top ? `${top.username} (${getPlatformBadge(top.platform).label})` : null,
  };
});
const igIsOpen = (account) => props.monitoredAccounts.length === 1 || igExpanded.value.has(account.id);
const igToggle = (account) => {
  const next = new Set(igExpanded.value);
  next.has(account.id) ? next.delete(account.id) : next.add(account.id);
  igExpanded.value = next;
};
const igToggleAll = (account) => {
  const next = new Set(igShowAll.value);
  next.has(account.id) ? next.delete(account.id) : next.add(account.id);
  igShowAll.value = next;
};
// Unggahan menonjol di urutan teratas, lalu sesuai waktu; dibatasi IG_PREVIEW kecuali "lihat semua".
const igVisiblePosts = (account) => {
  const posts = [...(account.posts || [])].sort((a, b) => (!!b.highlight - !!a.highlight) || (new Date(b.posted_at || 0) - new Date(a.posted_at || 0)));
  return igShowAll.value.has(account.id) ? posts : posts.slice(0, IG_PREVIEW);
};
const igHighlightText = (h) => [
  h?.ratio ? `Keterlibatan ${String(h.ratio).replace('.', ',')}× median akun` : null,
  h?.keywords?.length ? `Menyebut: ${h.keywords.join(', ')}` : null,
].filter(Boolean).join(' · ');

// Pimpinan & Polda mendarat di Briefing; peran lain di Radar Percakapan
const activeTab = ref(props.filters?.tab || (['kadiv_humas', 'polda_leader'].includes(page.props.auth?.role) ? 'briefing' : 'radar'));

// Menu melayang & panel lipat -- hanya satu menu terbuka pada satu waktu
const openMenu = ref(null);
const showFilterPanel = ref(false);
const dossierOpen = ref(false);
const closeFloating = () => {
  openMenu.value = null;
  showFilterPanel.value = false;
};
onMounted(() => document.addEventListener('click', closeFloating));
onUnmounted(() => document.removeEventListener('click', closeFloating));
const selectedPlatform = ref(props.filters?.platform || 'all');
const selectedSentiment = ref(props.filters?.sentiment || 'all');
const kolOnly = ref(Boolean(props.filters?.kol));
const selectedSort = ref(props.filters?.sort || 'latest');
const searchQuery = ref(props.filters?.q || '');
const selectedSatwil = ref(props.filters?.satwil || 'all');
const selectedPostDate = ref(props.filters?.post_date || '');
const activeFilterCount = computed(() =>
  (selectedSentiment.value !== 'all' ? 1 : 0) + (kolOnly.value ? 1 : 0) + (selectedSatwil.value !== 'all' ? 1 : 0) + (selectedPostDate.value ? 1 : 0)
);
const clearAdvancedFilters = () => {
  selectedSentiment.value = 'all';
  kolOnly.value = false;
  selectedSatwil.value = 'all';
  handleFilter();
};

// Smart Dossier & Topik Atensi Selection States
const selectedCaseId = ref(props.filters?.case_id ? Number(props.filters.case_id) : (props.selectedCase?.id || null));
const showBookmarksOnly = ref(Boolean(props.filters?.bookmarked));
watch(selectedCaseId, (v) => {
  if (v) dossierOpen.value = true;
}, { immediate: true });

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
    tab: activeTab.value,
    case_id: selectedCaseId.value || undefined,
    bookmarked: showBookmarksOnly.value ? 1 : undefined,
    platform: selectedPlatform.value,
    sentiment: selectedSentiment.value,
    kol: kolOnly.value ? 1 : 0,
    sort: selectedSort.value,
    q: searchQuery.value,
    satwil: selectedSatwil.value,
    post_date: selectedPostDate.value || undefined,
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
    title: `[Medsos Atensi] ${c.title}`,
    category: '📱 Isu Medsos & Opini Publik (Cyber Patrol)',
    priority: c.priority === 'urgent' ? 'urgent' : 'high',
    summary: `Hasil pemantauan berkas kasus intelijen siber "${c.title}". Total himpunan ${c.posts_count || 0} percakapan warganet dengan estimasi jangkauan ${formatNumber(c.total_views)} views. Polarisasi sentimen: ${c.sentiment_breakdown?.neg_pct || 0}% negatif dan ${c.sentiment_breakdown?.pos_pct || 0}% positif. Rekomendasi Humas: ${c.intelligence_notes || ''}`,
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
  if (!props.settings?.apify_token_set) {
    testConnectionResult.value = {
      success: false,
      message: 'Token Apify belum diatur. Administrator mengisinya di Keamanan & Pemeliharaan → Mesin Intelijen AI.',
    };
    return;
  }

  isTestingConnection.value = true;
  testConnectionResult.value = null;

  try {
    const res = await axios.post('/social-intelligence/test-connection');
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

// null/undefined = metrik tidak diketahui dari sumber → '—' (bukan 0).
const formatNumber = (num) => {
  if (num === null || num === undefined) return '\u2014';
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

// Tab Tren: tren pencarian Google Indonesia (nyata, nasional) + jam ramai dari unggahan tersimpan.
const trendSearch = ref('');
const trendKamtibmasOnly = ref(false);
const isSyncingTrends = ref(false);
const googleTrends = computed(() => props.publicTrends?.items || []);
const googleTrendsKamtibmasCount = computed(() => googleTrends.value.filter((t) => t.kamtibmas?.length).length);
const filteredGoogleTrends = computed(() => {
  const q = trendSearch.value.trim().toLowerCase();
  return googleTrends.value.filter((t) => {
    if (trendKamtibmasOnly.value && !t.kamtibmas?.length) return false;
    if (!q) return true;
    return t.title.toLowerCase().includes(q) || (t.news || []).some((n) => n.title?.toLowerCase().includes(q));
  });
});
const formatTrendTime = (iso) => {
  if (!iso) return '—';
  const d = new Date(iso);
  return Number.isNaN(d.getTime()) ? '—' : d.toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
};

const triggerSyncTrends = () => {
  isSyncingTrends.value = true;
  router.post('/social-intelligence/sync-trends', {}, {
    preserveScroll: true,
    only: ['publicTrends', 'flash'],
    onFinish: () => {
      isSyncingTrends.value = false;
    },
  });
};

const filterByGoogleTrend = (item) => {
  searchQuery.value = item.title;
  selectedPlatform.value = 'all';
  activeTab.value = 'radar';
  handleFilter();
};

const getEscalationUrl = (item) => {
  const news = (item.news || [])[0];
  const params = new URLSearchParams({
    title: `Atensi tren pencarian: ${item.title}`,
    category: '📱 Isu Medsos & Opini Publik',
    summary: `Topik "${item.title}" sedang naik di pencarian Google Indonesia${item.traffic ? ` (sekitar ${item.traffic} pencarian)` : ''}.`
      + (item.kamtibmas?.length ? ` Memuat kata kunci kamtibmas: ${item.kamtibmas.join(', ')}.` : '')
      + (news ? ` Berita terkait: ${news.title}${news.source ? ` (${news.source})` : ''}.` : ''),
    source_media: 'Google Trends',
    source_url: news?.url || item.explore_url || '',
    priority: item.kamtibmas?.length ? 'high' : 'medium',
  });
  return `/issues/create?${params.toString()}`;
};

// Jam ramai: batang 24 jam dari data nyata; jendela ramai disorot.
const peakHourMax = computed(() => Math.max(1, ...(props.peakHours?.hours || [0])));
const isPeakHour = (h) => {
  const start = props.peakHours?.peak?.start;
  return start !== undefined && start !== null && (h === start || h === (start + 1) % 24);
};

// --- PILIHAN C: MULTI-LINE SPIKE CHART REACTIVE MATH ---
const spikeTimelineDays = computed(() => props.spikeTimeline?.days || []);

// Sparkline & selisih vs hari sebelumnya (strip KPI)
const sparkPoints = (key) => {
  const d = spikeTimelineDays.value;
  if (d.length < 2) return '';
  const max = Math.max(...d.map((x) => x[key] || 0), 1);
  return d.map((x, i) => ((i / (d.length - 1)) * 100).toFixed(1) + ',' + (26 - ((x[key] || 0) / max) * 24).toFixed(1)).join(' ');
};
const trendDelta = (key) => {
  const d = spikeTimelineDays.value;
  if (d.length < 2) return null;
  return (d[d.length - 1][key] || 0) - (d[d.length - 2][key] || 0);
};
const deltaLabel = (key) => {
  const v = trendDelta(key);
  if (v === null) return '';
  if (v === 0) return '— sama dengan kemarin';
  return (v > 0 ? '▲ +' : '▼ ') + v + ' vs kemarin';
};

// Grafik kolom bertumpuk 7 hari: negatif di dasar (mudah dibandingkan), lalu netral, lalu positif
const chartOpen = ref(true);
const chartMax = computed(() => Math.max(...spikeTimelineDays.value.map(d => d.total || 0), 1));
const barGeom = computed(() => {
  const days = spikeTimelineDays.value;
  const max = Math.max(...days.map((d) => d.total || 0), 1);
  const slot = 560 / (days.length || 1);
  const bw = Math.min(46, slot * 0.6);
  const base = 150;
  const h = (v) => ((v || 0) / max) * 115;
  return days.map((d, i) => {
    const x = 45 + i * slot + (slot - bw) / 2;
    const hn = h(d.negatif);
    const hu = h(d.netral);
    const hp = h(d.positif);
    const hx = h(d.unknown);
    return { i, d, x, bw, hn, hu, hp, hx, yn: base - hn, yu: base - hn - hu, yp: base - hn - hu - hp, yx: base - hn - hu - hp - hx, cx: x + bw / 2, base };
  });
});

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
const dayPercent = (value) => activeDayData.value?.total ? Math.round(100 * value / activeDayData.value.total) : 0;
const showDayPosts = (sentiment = null) => {
  if (!activeDayData.value?.total) return;
  selectedPostDate.value = activeDayData.value.date;
  if (sentiment) selectedSentiment.value = sentiment;
  activeTab.value = 'radar';
  handleFilter();
};

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

const openSadewa = (subject = '') => window.dispatchEvent(new CustomEvent('open-sadewa-copilot', { detail: { scope: 'social', subject } }));

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
  if (score === null || score === undefined) return 'text-slate-600 dark:text-slate-400';
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
    <!-- Header ringkas: 1 aksi utama + Lacak Topik + menu lainnya -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div class="min-w-0">
        <div class="flex items-center gap-2 flex-wrap text-[11px] font-bold text-emerald-600 dark:text-emerald-400">
          <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse inline-block"></span>
          <span>Multi-Tier Engine aktif</span>
          <span class="text-slate-500 dark:text-slate-400 font-semibold">&bull; {{ isNational ? 'Divhumas Polri (Nasional)' : selectedPolda?.name }}</span>
        </div>
        <h1 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white tracking-tight mt-1">Radar Media Sosial</h1>
      </div>

      <div class="flex items-center gap-2 shrink-0">
        <!-- Aksi utama (peran berizin sinkron medsos) -->
        <button
          v-if="canSync"
          @click="triggerSyncAndAnalyze"
          type="button"
          :disabled="isSyncing || isAnalyzingAI"
          class="px-4 py-2.5 rounded-xl text-xs font-black bg-gradient-to-r from-amber-400 to-amber-500 hover:brightness-110 text-slate-950 shadow-lg shadow-amber-400/25 flex items-center gap-2 cursor-pointer transition select-none disabled:opacity-60"
          title="Tarik percakapan terbaru dan susun sintesis intelijen SADEWA"
        >
          <Sparkles class="w-4 h-4" :class="{ 'animate-spin': isSyncing || isAnalyzingAI }" />
          <span>{{ (isSyncing || isAnalyzingAI) ? 'SADEWA Bekerja...' : 'Sinkron & Analisis' }}</span>
        </button>

        <button
          v-if="canSync"
          @click="openTrackKeywordModal"
          type="button"
          class="px-3.5 py-2.5 rounded-xl text-xs font-bold bg-white hover:bg-slate-100 text-slate-800 dark:bg-slate-900 dark:hover:bg-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700 flex items-center gap-2 cursor-pointer transition select-none"
          title="Lacak topik atau kasus baru secara on-demand"
        >
          <Sparkles class="w-4 h-4 text-sky-500" />
          <span class="hidden sm:inline">Lacak Topik</span>
        </button>

        <button
          type="button"
          @click="openSadewa()"
          class="px-3.5 py-2.5 rounded-xl text-xs font-bold bg-slate-900 hover:bg-slate-800 text-white border border-slate-700 hover:border-amber-400/60 flex items-center gap-2 cursor-pointer transition select-none"
          title="Tanya SADEWA AI tentang percakapan media sosial"
        >
          <Bot class="w-4 h-4 text-amber-400" />
          <span class="hidden sm:inline">SADEWA AI</span>
        </button>

        <div v-if="canSync || canCreateIssue" class="relative">
          <button
            type="button"
            @click.stop="openMenu = openMenu === 'more' ? null : 'more'"
            class="p-2.5 rounded-xl bg-white hover:bg-slate-100 text-slate-600 hover:text-slate-900 dark:bg-slate-900 dark:hover:bg-slate-800 dark:text-slate-300 dark:hover:text-white border border-slate-300 dark:border-slate-700 transition cursor-pointer"
            title="Aksi lainnya"
          >
            <Ellipsis class="w-4 h-4" />
          </button>
          <div
            v-if="openMenu === 'more'"
            @click.stop
            class="absolute right-0 top-full mt-2 z-30 w-56 rounded-xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-700 shadow-2xl p-1.5"
          >
            <button
              v-if="canSync"
              type="button"
              @click="triggerSync(); openMenu = null"
              :disabled="isSyncing || isAnalyzingAI"
              class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-bold text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800 transition cursor-pointer text-left disabled:opacity-50"
            >
              <RefreshCw class="w-4 h-4 text-sky-500" :class="{ 'animate-spin': isSyncing }" />
              <span>{{ isSyncing ? 'Memperbarui...' : 'Perbarui Data (tanpa AI)' }}</span>
            </button>
            <Link
              v-if="canCreateIssue"
              href="/issues/create"
              class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-bold text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800 transition cursor-pointer text-left"
            >
              <AlertTriangle class="w-4 h-4 text-rose-500" />
              <span>Buat Laporan Isu</span>
            </Link>
          </div>
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

    <!-- KPI strip: angka + sparkline 7 hari + selisih vs kemarin -->
    <div class="rounded-2xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 shadow-sm grid grid-cols-2 lg:grid-cols-4 divide-x divide-y lg:divide-y-0 divide-slate-200 dark:divide-slate-800 overflow-hidden">
      <div class="p-4 space-y-1.5">
        <p class="text-[11px] font-bold text-slate-500 dark:text-slate-400 flex items-center gap-1.5"><MessageSquare class="w-3.5 h-3.5 text-sky-500" /> Percakapan Terpantau</p>
        <div class="flex items-end justify-between gap-2">
          <p class="text-2xl font-black text-slate-900 dark:text-white leading-none">{{ metrics?.total_posts || 0 }}</p>
          <svg v-if="spikeTimelineDays.length > 1" viewBox="0 0 100 28" preserveAspectRatio="none" class="w-20 h-7 text-sky-500" aria-hidden="true">
            <polyline :points="sparkPoints('total')" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
          </svg>
        </div>
        <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ formatNumber(metrics?.total_views) }} views &bull; {{ deltaLabel('total') || '7 hari terakhir' }}</p>
      </div>

      <div class="p-4 space-y-1.5">
        <p class="text-[11px] font-bold text-slate-500 dark:text-slate-400 flex items-center gap-1.5"><Activity class="w-3.5 h-3.5 text-emerald-500" /> Sentimen Publik <SentimentInfo variant="icon" kind="social" :lexicon="sentimentLexicon" /></p>
        <div class="flex items-end justify-between gap-2">
          <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400 leading-none">{{ metrics?.positive_pct ?? 0 }}%<span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 ml-1">positif</span></p>
          <svg v-if="spikeTimelineDays.length > 1" viewBox="0 0 100 28" preserveAspectRatio="none" class="w-20 h-7 text-rose-500" aria-hidden="true" title="Tren negatif 7 hari">
            <polyline :points="sparkPoints('negatif')" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
          </svg>
        </div>
        <div class="w-full h-1.5 rounded-full overflow-hidden flex bg-slate-200 dark:bg-slate-800">
          <div :style="{ width: (metrics?.positive_pct || 0) + '%' }" class="bg-emerald-500 h-full" title="Positif"></div>
          <div :style="{ width: (metrics?.neutral_pct || 0) + '%' }" class="bg-slate-400 dark:bg-slate-600 h-full" title="Netral"></div>
          <div :style="{ width: (metrics?.negative_pct || 0) + '%' }" class="bg-rose-500 h-full" title="Negatif"></div>
        </div>
        <p class="text-[11px] text-slate-500 dark:text-slate-400">Netral {{ metrics?.neutral_pct ?? 0 }}% &bull; <span :class="trendDelta('negatif') > 0 ? 'text-rose-600 dark:text-rose-400 font-bold' : ''">Negatif {{ metrics?.negative_pct ?? 0 }}%</span></p>
      </div>

      <button
        type="button"
        @click="switchTab('kol')"
        class="p-4 space-y-1.5 text-left hover:bg-slate-50 dark:hover:bg-slate-800/40 transition cursor-pointer"
        title="Buka Radar KOL & Akun Kritis"
      >
        <p class="text-[11px] font-bold text-slate-500 dark:text-slate-400 flex items-center gap-1.5"><UserCheck class="w-3.5 h-3.5 text-amber-500" /> Tokoh Kritis &amp; KOL</p>
        <p class="text-2xl font-black text-amber-600 dark:text-amber-400 leading-none">{{ metrics?.kol_count || 0 }}</p>
        <p class="text-[11px] text-slate-500 dark:text-slate-400">Unggahan dari akun berpengaruh</p>
      </button>

      <button
        type="button"
        @click="selectedSort = 'viral'; switchTab('radar')"
        class="p-4 space-y-1.5 text-left hover:bg-slate-50 dark:hover:bg-slate-800/40 transition cursor-pointer"
        title="Urutkan feed menurut paling viral"
      >
        <p class="text-[11px] font-bold text-rose-600 dark:text-rose-400 flex items-center gap-1.5"><Flame class="w-3.5 h-3.5 text-rose-500" /> Potensi Viral</p>
        <p class="text-2xl font-black text-rose-600 dark:text-rose-400 leading-none">{{ metrics?.viral_count || 0 }}</p>
        <p class="text-[11px] text-slate-500 dark:text-slate-400">Skor viralisasi &gt; 75 / 100</p>
      </button>
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
                  <span>{{ investigationCases.length }} kasus</span>
                  <span v-if="investigationCases.some(c => c.priority === 'urgent')" class="text-rose-400 font-black">
                    &bull; {{ investigationCases.filter(c => c.priority === 'urgent').length }} mendesak
                  </span>
                </span>
                <ChevronDown class="w-4 h-4 text-slate-400 transition-transform" :class="dossierOpen ? 'rotate-180' : ''" />
              </button>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <button
              v-if="canSync"
              @click="openTrackKeywordModal"
              type="button"
              class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-amber-400 hover:brightness-110 text-slate-950 text-xs font-black shadow-md shadow-amber-500/20 transition cursor-pointer select-none"
            >
              <PlusCircle class="w-3.5 h-3.5" />
              <span>Lacak Topik</span>
            </button>
          </div>
        </div>

        <div v-show="dossierOpen" class="space-y-4">
        <!-- KASUS ATENSI SELECTOR (ADAPTIVE RESPONSIVE FLEX BADGES - RAPI & BEBAS OVERFLOW) -->
        <div class="space-y-2">
          <!-- Empty State jika belum ada kasus -->
          <div
            v-if="investigationCases.length === 0"
            class="p-4 rounded-2xl bg-[#070d19]/80 border border-dashed border-slate-700/80 text-center space-y-1.5"
          >
            <p class="text-xs font-bold text-slate-300">Belum ada topik atensi pimpinan yang aktif di media sosial.</p>
            <p class="text-[11px] text-slate-500">
              Klik <strong>"Lacak Topik"</strong> di atas untuk memantau tagar atau kasus spesifik secara terisolasi.
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
              <p class="text-xl font-black text-indigo-400 mt-0.5">{{ formatNumber(selectedCase.total_views) }}</p>
            </div>
            <div class="p-3 rounded-xl bg-slate-900/80 border border-slate-800">
              <span class="text-[10px] font-bold text-slate-400">Total Interaksi Warganet</span>
              <p class="text-xl font-black text-emerald-400 mt-0.5">{{ formatNumber(selectedCase.total_engagements) }}</p>
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
      </div>

      <!-- TREN 7 HARI: kolom bertumpuk (negatif di dasar), lonjakan diberi label teks -->
      <div class="rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 p-4 md:p-5 shadow-sm space-y-4">
        <button type="button" @click.stop="chartOpen = !chartOpen" class="w-full flex items-center justify-between gap-3 text-left cursor-pointer" :aria-expanded="chartOpen">
          <span class="flex items-center gap-2 flex-wrap">
            <Activity class="w-4 h-4 text-indigo-500" />
            <span class="text-sm font-black text-slate-900 dark:text-white">Tren Percakapan 7 Hari</span>
            <span
              class="px-2 py-0.5 rounded-full text-[10px] font-bold"
              :class="spikeTimeline?.summary?.spike_detected ? 'bg-rose-500/15 text-rose-600 dark:text-rose-400 border border-rose-500/30' : 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30'"
            >
              {{ spikeTimeline?.summary?.spike_detected ? 'Lonjakan negatif terdeteksi' : (spikeTimeline?.summary?.total_7d ? 'Tidak ada lonjakan terdeteksi' : 'Belum ada data') }}
            </span>
          </span>
          <ChevronDown class="w-4 h-4 text-slate-400 transition-transform" :class="chartOpen ? 'rotate-180' : ''" />
        </button>

        <div v-show="chartOpen" class="space-y-4">
          <div class="flex items-center gap-4 text-xs font-bold">
            <span class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400"><span class="w-3 h-3 rounded-sm bg-emerald-500 inline-block"></span>Positif</span>
            <span class="flex items-center gap-1.5 text-slate-500 dark:text-slate-400"><span class="w-3 h-3 rounded-sm bg-slate-400 dark:bg-slate-600 inline-block"></span>Netral</span>
            <span class="flex items-center gap-1.5 text-rose-600 dark:text-rose-400"><span class="w-3 h-3 rounded-sm bg-rose-500 inline-block"></span>Negatif</span>
            <span v-if="spikeTimeline?.summary?.unknown_7d" class="flex items-center gap-1.5 text-amber-700 dark:text-amber-400"><span class="w-3 h-3 rounded-sm bg-amber-500 inline-block"></span>Belum diklasifikasi</span>
          </div>
          <p class="text-xs text-slate-600 dark:text-slate-400">Jumlah unggahan tersimpan sesuai filter aktif, berdasarkan tanggal unggahan. Dihitung {{ spikeTimeline?.summary?.calculated_at }}. Nol berarti tidak ada data tersimpan, bukan bukti tidak ada percakapan.</p>
          <p class="text-xs text-slate-600 dark:text-slate-400">Lonjakan: minimal 5 unggahan negatif dan 1,5 kali rata-rata 6 hari sebelumnya. Sentimen adalah klasifikasi, bukan jumlah aduan, dan perlu ditinjau. {{ spikeTimeline?.summary?.synthetic_identified || 0 }} data simulasi teridentifikasi; asal data lain belum selalu dapat diverifikasi.</p>

          <div class="relative w-full overflow-x-auto no-scrollbar">
            <svg viewBox="0 0 650 185" class="w-full h-48 select-none" role="img" aria-label="Kolom bertumpuk percakapan positif, netral, dan negatif selama 7 hari">
              <g v-for="step in [0, 0.5, 1]" :key="step">
                <line x1="45" :y1="150 - step * 115" x2="605" :y2="150 - step * 115" stroke="currentColor" class="text-slate-200 dark:text-slate-700" />
                <text x="39" :y="154 - step * 115" text-anchor="end" font-size="10" class="fill-slate-600 dark:fill-slate-400">{{ Math.round(chartMax * step) }}</text>
              </g>
              <g v-for="g in barGeom" :key="g.i" class="cursor-pointer" role="button" tabindex="0" :aria-label="`${g.d.label}, ${g.d.total} unggahan`" @focus="activeDayIndex = g.i" @keydown.enter.prevent="activeDayIndex = g.i" @keydown.space.prevent="activeDayIndex = g.i" @mouseenter="activeDayIndex = g.i" @click="activeDayIndex = g.i">
                <rect :x="g.x - 8" y="8" :width="g.bw + 16" height="170" fill="transparent" />
                <rect :x="g.x" :y="g.yn" :width="g.bw" :height="g.hn" class="fill-rose-500" rx="2" />
                <rect :x="g.x" :y="g.yu" :width="g.bw" :height="g.hu" class="fill-slate-400 dark:fill-slate-600" />
                <rect :x="g.x" :y="g.yp" :width="g.bw" :height="g.hp" class="fill-emerald-500" rx="2" />
                <rect :x="g.x" :y="g.yx" :width="g.bw" :height="g.hx" class="fill-amber-500" />
                <rect v-if="activeDayIndex === g.i" :x="g.x - 3" :y="g.yx - 3" :width="g.bw + 6" :height="g.base - g.yx + 3" fill="none" stroke="#6366f1" stroke-width="2" rx="4" />
                <text :x="g.cx" :y="g.yx - 6" text-anchor="middle" font-size="11" font-weight="700" class="fill-slate-600 dark:fill-slate-300">{{ g.d.total }}</text>
                <text v-if="g.d.is_spike" :x="g.cx" :y="g.yx - 19" text-anchor="middle" font-size="10" font-weight="800" class="fill-rose-600 dark:fill-rose-400">▲ Lonjakan</text>
                <text :x="g.cx" y="168" text-anchor="middle" font-size="10" class="fill-slate-500 dark:fill-slate-400">{{ g.d.label }}</text>
              </g>
            </svg>
          </div>

          <!-- Inspektur hari terpilih -->
          <div class="grid grid-cols-1 md:grid-cols-4 gap-3 p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs">
            <div>
              <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider block">Hari terpilih</span>
              <span class="font-black text-slate-900 dark:text-white text-sm">{{ activeDayData?.label || '7 Hari Terakhir' }}</span>
              <span v-if="activeDayData?.is_spike" class="ml-2 px-1.5 py-0.5 rounded text-[9px] font-bold bg-rose-500 text-white">Lonjakan</span>
            </div>
            <div class="flex items-center gap-4">
              <button v-for="sent in ['positif', 'netral', 'negatif']" :key="sent" type="button" :disabled="!activeDayData?.[sent]" @click="showDayPosts(sent)" class="text-left disabled:opacity-60" :aria-label="`Lihat unggahan ${sent} pada ${activeDayData?.label}`"><span class="text-[10px] text-slate-500 font-bold block capitalize">{{ sent }}</span><span class="font-black text-slate-900 dark:text-white text-sm">{{ activeDayData?.[sent] || 0 }} <span class="text-[10px]">({{ dayPercent(activeDayData?.[sent] || 0) }}%)</span></span></button>
            </div>
            <div class="md:col-span-2 border-t md:border-t-0 md:border-l border-slate-200 dark:border-slate-800 pt-2 md:pt-0 md:pl-4">
              <span class="text-[10px] text-slate-500 font-bold block">Kesimpulan</span>
              <p class="text-[11px] text-slate-700 dark:text-slate-300 font-medium leading-relaxed">
                {{ activeDayData?.is_spike ? activeDayData.spike_note : (activeDayData?.total ? 'Tidak memenuhi ambang lonjakan negatif pada hari ini.' : 'Tidak ada unggahan tersimpan pada hari ini.') }}
              </p>
              <button v-if="activeDayData?.total" type="button" @click="showDayPosts()" class="mt-1 font-bold text-indigo-700 dark:text-indigo-300 hover:underline">Lihat {{ activeDayData.total }} unggahan hari ini →</button>
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

        <!-- Kanan: cari, Filter lanjutan (panel), urutan -->
        <div class="flex flex-wrap items-center gap-2">
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

          <div class="relative">
            <button
              type="button"
              @click.stop="showFilterPanel = !showFilterPanel"
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold transition border cursor-pointer select-none"
              :class="activeFilterCount > 0
                ? 'bg-amber-400/15 text-amber-700 dark:text-amber-300 border-amber-400/60'
                : 'bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-700 hover:border-slate-500'"
              title="Filter sentimen, KOL, dan wilayah"
            >
              <SlidersHorizontal class="w-3.5 h-3.5" />
              <span>Filter</span>
              <span v-if="activeFilterCount > 0" class="px-1.5 py-0.5 rounded-full text-[10px] font-mono bg-amber-400 text-slate-950 font-black">{{ activeFilterCount }}</span>
            </button>

            <div
              v-if="showFilterPanel"
              @click.stop
              class="absolute right-0 top-full mt-2 z-30 w-[min(92vw,22rem)] rounded-2xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-700 shadow-2xl p-4 space-y-3"
            >
              <label class="block space-y-1">
                <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400">Sentimen</span>
                <select
                  v-model="selectedSentiment"
                  @change="handleFilter"
                  class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 outline-none focus:border-amber-400"
                >
                  <option value="all">Semua Sentimen</option>
                  <option value="positif">Positif</option>
                  <option value="netral">Netral</option>
                  <option value="negatif">Negatif</option>
                </select>
              </label>

              <label v-if="isNational || (satwils && satwils.length > 0)" class="block space-y-1">
                <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400">{{ isNational ? 'Wilayah hukum' : 'Satuan wilayah' }}</span>
                <select
                  v-model="selectedSatwil"
                  @change="handleFilter"
                  class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 outline-none focus:border-amber-400"
                >
                  <option value="all">{{ isNational ? 'Seluruh Wilayah Hukum' : 'Seluruh Satwil (Polres)' }}</option>
                  <template v-if="isNational">
                    <option v-for="p in (allPoldas || []).filter(item => item.code !== 'DIVHUMAS')" :key="p.id" :value="p.id">{{ p.name }}</option>
                  </template>
                  <template v-else>
                    <option v-for="sw in (satwils || [])" :key="sw.id" :value="sw.id">{{ sw.name }}</option>
                  </template>
                </select>
              </label>

              <button
                @click="kolOnly = !kolOnly; handleFilter()"
                type="button"
                class="w-full px-3 py-2 rounded-xl text-xs font-bold transition border cursor-pointer select-none flex items-center justify-between gap-2"
                :class="kolOnly
                  ? 'bg-amber-400 text-slate-950 font-black border-amber-400 shadow-sm'
                  : 'bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-700'"
              >
                <span class="flex items-center gap-1.5"><UserCheck class="w-3.5 h-3.5" /> Hanya tokoh kritis / KOL</span>
                <span>{{ kolOnly ? 'Aktif' : 'Nonaktif' }}</span>
              </button>

              <div class="flex items-center justify-between pt-1">
                <button type="button" @click="clearAdvancedFilters" class="text-[11px] font-bold text-slate-500 hover:text-slate-900 dark:hover:text-white cursor-pointer">Atur ulang</button>
                <button type="button" @click="showFilterPanel = false" class="px-3 py-1.5 rounded-lg bg-amber-400 text-slate-950 text-xs font-black cursor-pointer">Selesai</button>
              </div>
            </div>
          </div>

          <select
            v-model="selectedSort"
            @change="handleFilter"
            class="px-3 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 outline-none focus:border-amber-400"
            title="Urutkan"
          >
            <option value="latest">Terkini</option>
            <option value="viral">Paling Viral</option>
            <option value="engagement">Interaksi Terbanyak</option>
            <option value="views">Paling Banyak Dilihat</option>
          </select>
        </div>
      </div>

      <!-- Chip filter aktif -->
      <div v-if="activeFilterCount > 0" class="flex flex-wrap items-center gap-2 -mt-2">
        <span class="text-[11px] font-bold text-slate-500">Filter aktif:</span>
        <button v-if="selectedPostDate" type="button" @click="selectedPostDate = ''; handleFilter()" class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-100 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200">Tanggal: {{ selectedPostDate }} <X class="w-3 h-3" /></button>
        <button v-if="selectedSentiment !== 'all'" type="button" @click="selectedSentiment = 'all'; handleFilter()" class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-100 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:border-amber-400 cursor-pointer">
          Sentimen: {{ selectedSentiment }} <X class="w-3 h-3" />
        </button>
        <button v-if="kolOnly" type="button" @click="kolOnly = false; handleFilter()" class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-100 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:border-amber-400 cursor-pointer">
          Hanya KOL <X class="w-3 h-3" />
        </button>
        <button v-if="selectedSatwil !== 'all'" type="button" @click="selectedSatwil = 'all'; handleFilter()" class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-100 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:border-amber-400 cursor-pointer">
          Wilayah dipilih <X class="w-3 h-3" />
        </button>
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

              <!-- Sentiment Pill: klik untuk alasan & metodologi -->
              <SentimentInfo :sentiment="post.sentiment" :reason="post.sentiment_reason" kind="social" :lexicon="sentimentLexicon" />
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

            <p v-if="post.views_count == null && post.likes_count == null && post.shares_count == null && post.comments_count == null" class="text-[10px] text-slate-500 dark:text-slate-400">
              Metrik keterlibatan tidak tersedia dari sumber ini (hasil pencarian/sindikasi berita).
            </p>
            <!-- Virality Progress Bar -->
            <p v-if="post.virality_score == null" class="text-[10px] text-slate-500 dark:text-slate-400"><span class="font-bold">Skor Viralisasi:</span> belum diketahui</p>
            <div v-else>
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
                  :href="`/issues/create?title=${encodeURIComponent(`[PATROLI MEDSOS] ${post.author_handle || ''}: ${(post.content || '').slice(0, 60)}...`)}&category=📱 Isu Medsos & Opini Publik (Cyber Patrol)&source_media=${encodeURIComponent(getPlatformBadge(post.platform).label)}&source_url=${encodeURIComponent(post.post_url || '')}&summary=${encodeURIComponent(post.content || '')}`"
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
            <AiQuotaBadge class="mt-2 ml-1" />
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
            <span>Dianalisis oleh SADEWA AI<template v-if="socialAiReport.model_used"> ({{ socialAiReport.model_used }})</template></span>
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
                <p class="text-xs font-bold" :class="getSentimentStabilityClass(socialAiReport.data?.sentiment_analysis?.stability_index ?? null)">
                  Stabilitas Opini: {{ socialAiReport.data?.sentiment_analysis?.stability_label }} (Skor {{ socialAiReport.data?.sentiment_analysis?.stability_index ?? '—' }}/100)
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
      <section class="rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 p-5 md:p-6 shadow-sm space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0">
            <h2 class="text-base font-black text-slate-900 dark:text-white">Pantau Akun Lintas Platform</h2>
            <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">Unggahan publik terbaru akun tokoh, KOL, atau institusi pilihan di <b>Instagram, TikTok, X, YouTube, dan Halaman Facebook</b> (hingga 12 unggahan per sinkronisasi, lewat Apify, dilakukan manual). Satu tokoh dapat dipantau di beberapa platform. Metrik yang tidak disediakan platform ditampilkan "—".</p>
          </div>
          <button v-if="canSync && instagramReady && monitoredAccounts.length > 1" type="button" :disabled="accountBusy" @click="syncAllAccounts" class="shrink-0 rounded-xl border border-slate-300 dark:border-slate-700 px-3.5 py-2 text-xs font-bold text-slate-800 dark:text-slate-200 hover:border-amber-400 disabled:opacity-50 cursor-pointer">
            {{ accountBusy ? 'Memproses...' : `Sinkronkan semua (${monitoredAccounts.length})` }}
          </button>
        </div>

        <div v-if="!instagramReady" class="rounded-xl border border-amber-300/60 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/10 p-3.5 text-xs text-amber-900 dark:text-amber-200">
          <strong>Token Apify belum diatur.</strong> Sinkronisasi akun belum dapat dijalankan. Administrator mengisinya di <em>Keamanan &amp; Pemeliharaan → Mesin Intelijen AI</em>.
        </div>

        <form v-if="canSync" class="space-y-1.5" @submit.prevent="syncAccount()">
          <div class="flex flex-col sm:flex-row gap-2">
            <label for="account-monitor-platform" class="sr-only">Platform</label>
            <select id="account-monitor-platform" v-model="accountPlatform" class="sm:w-48 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm font-semibold text-slate-900 dark:text-white focus:outline-none focus:border-amber-400">
              <option v-for="pl in ACCOUNT_PLATFORMS" :key="pl.id" :value="pl.id">{{ pl.label }}</option>
            </select>
            <label for="account-monitor-username" class="sr-only">Username</label>
            <input id="account-monitor-username" v-model="accountUsername" maxlength="100" autocomplete="off" :placeholder="accountPlatformMeta.placeholder" class="flex-1 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-amber-400" />
            <button type="submit" :disabled="accountBusy || !instagramReady || !accountUsername.trim()" class="rounded-xl bg-amber-400 hover:bg-amber-300 px-4 py-2 text-sm font-bold text-slate-950 disabled:opacity-50 cursor-pointer">{{ accountBusy ? 'Menyinkronkan...' : 'Tambah & sinkronkan' }}</button>
          </div>
          <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ accountPlatformMeta.hint }} Boleh menempel tautan profil utuh.</p>
        </form>
        <p v-else class="text-xs text-slate-500 dark:text-slate-400">Menambah atau menyinkronkan akun memerlukan izin sinkron Radar Media Sosial. Anda dapat melihat hasil yang sudah ada.</p>

        <p v-if="accountMessage" role="status" class="text-xs font-semibold" :class="accountError ? 'text-rose-700 dark:text-rose-400' : 'text-emerald-700 dark:text-emerald-400'">{{ accountMessage }}</p>
        <p v-if="!monitoredAccounts.length" class="rounded-xl bg-slate-50 dark:bg-slate-900 p-4 text-sm text-slate-600 dark:text-slate-400">Belum ada akun dalam daftar pantauan wilayah ini. Pilih platform lalu masukkan username publik, mis. Instagram <span class="font-mono">mohmahfudmd</span> atau X <span class="font-mono">DivHumas_Polri</span>.</p>

        <!-- Ringkasan & alat bantu: muncul bila sudah ada akun -->
        <div v-if="monitoredAccounts.length" class="grid grid-cols-2 lg:grid-cols-4 gap-2 text-center">
          <div class="rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-2.5"><p class="text-[10px] font-bold uppercase text-slate-500 dark:text-slate-400">Akun dipantau</p><p class="text-base font-black text-slate-900 dark:text-white">{{ igSummary.accounts }}</p></div>
          <div class="rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-2.5"><p class="text-[10px] font-bold uppercase text-slate-500 dark:text-slate-400">Unggahan 7 hari</p><p class="text-base font-black text-slate-900 dark:text-white">{{ igSummary.week }}</p></div>
          <div class="rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-2.5"><p class="text-[10px] font-bold uppercase text-slate-500 dark:text-slate-400">Unggahan menonjol</p><p class="text-base font-black" :class="igSummary.highlights ? 'text-rose-600 dark:text-rose-400' : 'text-slate-900 dark:text-white'">{{ igSummary.highlights }}</p></div>
          <div class="rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-2.5"><p class="text-[10px] font-bold uppercase text-slate-500 dark:text-slate-400">Keterlibatan tertinggi</p><p class="text-sm font-black text-slate-900 dark:text-white truncate">{{ igSummary.top ?? '—' }}</p></div>
        </div>
        <p v-if="monitoredAccounts.length" class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
          <strong class="text-slate-700 dark:text-slate-300">Menonjol</strong> = keterlibatan (suka + komentar) minimal 2× median unggahan akun itu sendiri (perlu ≥ 4 unggahan bermetrik), atau keterangan unggahan memuat kata kunci kamtibmas. Bukan penilaian AI; tetap perlu ditinjau.
        </p>

        <div v-if="monitoredAccounts.length > 1" class="flex flex-wrap items-center gap-2">
          <div class="relative flex-1 min-w-[180px]">
            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5 pointer-events-none" />
            <input v-model="igSearch" type="search" placeholder="Cari akun…" aria-label="Cari akun pantauan" class="w-full pl-8 pr-3 py-1.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-amber-400" />
          </div>
          <label class="sr-only" for="ig-sort">Urutkan akun</label>
          <select id="ig-sort" v-model="igSort" class="py-1.5 px-2.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-semibold text-slate-800 dark:text-slate-200 focus:outline-none focus:border-amber-400">
            <option value="latest">Unggahan terbaru</option>
            <option value="highlights">Paling banyak menonjol</option>
            <option value="engagement">Keterlibatan tertinggi</option>
            <option value="name">Nama (A–Z)</option>
          </select>
          <select v-if="igPlatformTabs.length > 1" v-model="igPlatform" aria-label="Saring platform" class="py-1.5 px-2.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-semibold text-slate-800 dark:text-slate-200 focus:outline-none focus:border-amber-400">
            <option value="all">Semua platform ({{ monitoredAccounts.length }})</option>
            <option v-for="t in igPlatformTabs" :key="t.id" :value="t.id">{{ t.label }} ({{ t.count }})</option>
          </select>
          <button type="button" @click="igHighlightOnly = !igHighlightOnly" :aria-pressed="igHighlightOnly" class="px-3 py-1.5 rounded-xl text-xs font-bold border transition cursor-pointer" :class="igHighlightOnly ? 'bg-rose-500 text-white border-rose-500' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-700 hover:border-rose-400'">
            Hanya yang menonjol
          </button>
          <span class="text-[11px] text-slate-500 dark:text-slate-400">{{ igAccounts.length }} dari {{ monitoredAccounts.length }} akun</span>
        </div>
        <p v-if="monitoredAccounts.length && !igAccounts.length" class="text-xs text-slate-500 dark:text-slate-400 p-3">Tidak ada akun yang cocok dengan filter.</p>

        <article v-for="account in igAccounts" :key="account.id" class="rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
          <!-- Baris ringkas: klik untuk membuka -->
          <div class="flex flex-wrap items-center gap-3 p-3.5" :class="igIsOpen(account) ? 'border-b border-slate-200 dark:border-slate-800' : ''">
            <button type="button" @click="igToggle(account)" :aria-expanded="igIsOpen(account)" :disabled="monitoredAccounts.length === 1" class="flex items-center gap-3 min-w-0 flex-1 text-left cursor-pointer disabled:cursor-default">
              <div class="w-10 h-10 rounded-full bg-linear-to-tr from-amber-400 to-rose-400 text-slate-950 font-black flex items-center justify-center shrink-0">{{ (account.display_name || account.username).charAt(0).toUpperCase() }}</div>
              <div class="min-w-0 flex-1">
                <p class="flex items-center gap-1.5 flex-wrap">
                  <span class="font-bold text-sm text-slate-900 dark:text-white truncate">{{ account.display_name || account.username }}</span>
                  <span class="px-1.5 py-0.5 rounded text-[10px] font-black" :class="getPlatformBadge(account.platform).class">{{ getPlatformBadge(account.platform).label }}</span>
                  <span class="text-[11px] font-mono text-slate-500 dark:text-slate-400">{{ account.platform === 'facebook' ? '' : '@' }}{{ account.username }}</span>
                  <span v-if="account.profile?.followers != null" class="text-[11px] text-slate-500 dark:text-slate-400">· {{ formatNumber(account.profile.followers) }} {{ account.platform === 'youtube' ? 'pelanggan' : 'pengikut' }}</span>
                  <span v-if="igIsNew(account)" class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300">Baru 24 jam</span>
                  <span v-if="igHighlights(account).length" class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-300">{{ igHighlights(account).length }} menonjol</span>
                  <span v-if="account.last_error" class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">Sinkron gagal</span>
                </p>
                <p class="text-[11px] text-slate-600 dark:text-slate-400 mt-0.5">
                  {{ account.posts?.length ?? 0 }} unggahan
                  · rata-rata suka {{ instagramStats(account).avgLikes === null ? '—' : formatNumber(instagramStats(account).avgLikes) }}
                  · komentar {{ instagramStats(account).avgComments === null ? '—' : formatNumber(instagramStats(account).avgComments) }}
                  · terbaru {{ instagramStats(account).latest }}
                </p>
              </div>
              <ChevronDown v-if="monitoredAccounts.length > 1" class="w-4 h-4 text-slate-400 shrink-0 transition-transform" :class="igIsOpen(account) ? 'rotate-180' : ''" />
            </button>
            <div class="flex items-center gap-2">
              <a :href="account.profile_url" target="_blank" rel="noopener noreferrer" class="rounded-lg border border-slate-300 dark:border-slate-700 px-2.5 py-1.5 text-xs font-bold text-slate-700 dark:text-slate-300 hover:border-amber-400" :aria-label="`Buka profil ${account.username} di ${getPlatformBadge(account.platform).label}`">Profil ↗</a>
              <label v-if="canSync" class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-bold cursor-pointer" :class="account.auto_monitor ? 'border-emerald-400 text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-500/10' : 'border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300'" :title="`Sinkron otomatis tiap ${autoIntervalHours} jam + peringatan`">
                <input type="checkbox" :checked="account.auto_monitor" :disabled="!instagramReady" @change="toggleAuto(account, $event.target.checked)" class="accent-emerald-500" />
                <span>Pantau otomatis</span>
              </label>
              <template v-if="canSync">
                <button type="button" :disabled="accountBusy || !instagramReady" @click="syncAccount(account)" class="rounded-lg border border-slate-300 dark:border-slate-700 px-2.5 py-1.5 text-xs font-bold text-slate-800 dark:text-slate-200 hover:border-amber-400 disabled:opacity-50 cursor-pointer">{{ accountBusy ? 'Memproses...' : 'Sinkronkan' }}</button>
                <button type="button" :disabled="accountBusy" @click="removeAccount(account)" class="rounded-lg border border-rose-200 dark:border-rose-900 px-2.5 py-1.5 text-xs font-bold text-rose-700 dark:text-rose-400 cursor-pointer">Hapus</button>
              </template>
            </div>
          </div>

          <div v-if="igIsOpen(account)" class="p-3.5 space-y-3">
            <p class="text-xs text-slate-600 dark:text-slate-400">
              {{ account.last_synced_at ? `Terakhir berhasil: ${instagramDate(account.last_synced_at)}` : 'Belum ada sinkronisasi berhasil' }}
            </p>
            <p v-if="account.last_error" class="rounded-lg bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 px-3 py-2 text-xs font-semibold text-rose-700 dark:text-rose-300">Sinkronisasi terakhir gagal: {{ account.last_error }}</p>

            <p v-if="!account.posts?.length" class="text-xs text-slate-600 dark:text-slate-400">Belum ada unggahan yang dapat ditampilkan.</p>
            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
              <div v-for="post in igVisiblePosts(account)" :key="post.url" class="rounded-xl border p-3 transition" :class="post.highlight ? 'bg-rose-50/60 dark:bg-rose-950/20 border-rose-300 dark:border-rose-900/70' : 'bg-slate-50 dark:bg-slate-900 border-slate-200 dark:border-slate-800'">
                <a :href="post.url" target="_blank" rel="noopener noreferrer" class="flex gap-3 group">
                  <div class="w-20 h-20 rounded-lg bg-slate-200 dark:bg-slate-800 shrink-0 overflow-hidden flex items-center justify-center text-slate-500">
                    <img v-if="post.thumb" :src="post.thumb" alt="" referrerpolicy="no-referrer" loading="lazy" class="w-full h-full object-cover" @error="$event.target.style.display = 'none'" />
                    <Play v-else-if="post.type === 'video'" class="w-5 h-5" />
                    <Share2 v-else class="w-5 h-5" />
                  </div>
                  <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-semibold text-slate-600 dark:text-slate-400">
                      {{ instagramDate(post.posted_at) }}
                      <span v-if="post.type" class="ml-1 px-1.5 py-0.5 rounded bg-slate-200 dark:bg-slate-800 text-[10px] font-bold uppercase">{{ { image: 'Foto', video: 'Video', sidecar: 'Album', text: 'Teks' }[post.type] }}</span>
                    </p>
                    <p v-if="post.highlight" class="mt-1 text-[11px] font-bold text-rose-700 dark:text-rose-300">Menonjol: {{ igHighlightText(post.highlight) }}</p>
                    <p class="mt-1 text-sm text-slate-800 dark:text-slate-200 line-clamp-3 group-hover:text-amber-700 dark:group-hover:text-amber-300">{{ post.caption || 'Unggahan tanpa keterangan teks.' }}</p>
                    <p class="mt-1.5 text-[11px] text-slate-600 dark:text-slate-400">
                      Suka: {{ formatNumber(post.likes) }} · Komentar: {{ formatNumber(post.comments) }}<span v-if="post.views != null"> · Tayang: {{ formatNumber(post.views) }}</span><span v-if="post.shares != null"> · Bagikan: {{ formatNumber(post.shares) }}</span>
                    </p>
                    <p v-if="post.hashtags?.length" class="mt-1 text-[11px] text-amber-700 dark:text-amber-400 truncate">{{ post.hashtags.map((h) => '#' + h.replace(/^#/, '')).join(' ') }}</p>
                  </div>
                </a>

                <!-- Ringkasan Komentar -->
                <div class="mt-2.5 pt-2.5 border-t border-slate-200 dark:border-slate-800 space-y-2">
                  <div class="flex flex-wrap items-center gap-2">
                    <button v-if="digests[post.url]" type="button" @click="toggleComments(post)" :aria-expanded="commentOpen.has(post.url)" class="text-[11px] font-bold text-slate-700 dark:text-slate-200 hover:text-amber-600 cursor-pointer">
                      {{ commentOpen.has(post.url) ? 'Sembunyikan' : 'Lihat' }} ringkasan {{ digests[post.url].total }} komentar
                    </button>
                    <button v-if="canSync && instagramReady" type="button" :disabled="!!commentBusy[post.url]" @click="fetchComments(account, post)" class="text-[11px] font-bold text-amber-700 dark:text-amber-400 hover:underline disabled:opacity-50 cursor-pointer">
                      {{ commentBusy[post.url] === 'fetch' ? 'Mengambil komentar…' : (digests[post.url] ? 'Ambil ulang komentar' : 'Ringkasan komentar') }}
                    </button>
                    <span v-if="digests[post.url]?.fetched_at" class="text-[10px] text-slate-500 dark:text-slate-400">diambil {{ instagramDate(digests[post.url].fetched_at) }}</span>
                  </div>
                  <p v-if="commentNotice[post.url]" class="text-[11px] font-semibold" :class="commentNotice[post.url].error ? 'text-rose-700 dark:text-rose-400' : 'text-emerald-700 dark:text-emerald-400'">{{ commentNotice[post.url].text }}</p>

                  <div v-if="digests[post.url] && commentOpen.has(post.url)" class="space-y-2.5">
                    <template v-if="digests[post.url].total">
                      <div>
                        <div class="flex h-2 rounded-full overflow-hidden bg-slate-200 dark:bg-slate-800" role="img" :aria-label="`Sentimen komentar: ${digests[post.url].positif} positif, ${digests[post.url].netral} netral, ${digests[post.url].negatif} negatif`">
                          <div class="bg-emerald-500" :style="{ width: pct(digests[post.url].positif, digests[post.url].total) + '%' }"></div>
                          <div class="bg-slate-400 dark:bg-slate-600" :style="{ width: pct(digests[post.url].netral, digests[post.url].total) + '%' }"></div>
                          <div class="bg-rose-500" :style="{ width: pct(digests[post.url].negatif, digests[post.url].total) + '%' }"></div>
                        </div>
                        <p class="mt-1 text-[11px] text-slate-600 dark:text-slate-400 flex flex-wrap items-center gap-x-2">
                          <span class="text-emerald-700 dark:text-emerald-400 font-bold">{{ digests[post.url].positif }} positif</span>
                          <span>{{ digests[post.url].netral }} netral</span>
                          <span class="text-rose-700 dark:text-rose-400 font-bold">{{ digests[post.url].negatif }} negatif</span>
                          <span>dari {{ digests[post.url].total }} komentar</span>
                          <SentimentInfo variant="icon" kind="social" :lexicon="sentimentLexicon" />
                        </p>
                      </div>
                      <p v-if="digests[post.url].top_words?.length" class="text-[11px] text-slate-600 dark:text-slate-400">
                        <b class="text-slate-700 dark:text-slate-300">Sering disebut:</b>
                        <span v-for="w in digests[post.url].top_words" :key="w.word" class="ml-1 inline-block px-1.5 py-0.5 rounded bg-slate-200 dark:bg-slate-800">{{ w.word }} ({{ w.count }})</span>
                      </p>

                      <div class="rounded-lg bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 p-2.5">
                        <div class="flex items-center justify-between gap-2">
                          <p class="text-[11px] font-black text-slate-800 dark:text-slate-100">Ringkasan opini SADEWA</p>
                          <button type="button" :disabled="!!commentBusy[post.url]" @click="summarizeDigest(post, !!digests[post.url].ai_summary)" class="text-[11px] font-bold text-amber-700 dark:text-amber-400 hover:underline disabled:opacity-50 cursor-pointer">
                            {{ commentBusy[post.url] === 'ai' ? 'Meringkas…' : (digests[post.url].ai_summary ? 'Ringkas ulang' : 'Ringkas dengan SADEWA') }}
                          </button>
                        </div>
                        <div v-if="digests[post.url].ai_summary" class="mt-1.5 text-[12px] text-slate-700 dark:text-slate-200 leading-relaxed sadewa-markdown-body" v-html="renderSafeMarkdown(digests[post.url].ai_summary)"></div>
                        <p v-else class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Belum diringkas. Ringkasan memakai 1 permintaan dari batas AI harian Anda.</p>
                      </div>

                      <ul class="space-y-1 max-h-64 overflow-y-auto pr-1">
                        <li v-for="(c, ci) in digestComments(digests[post.url])" :key="ci" class="text-[11px] leading-snug flex gap-2">
                          <span class="mt-1 w-2 h-2 rounded-full shrink-0" :class="c.sentiment === 'negatif' ? 'bg-rose-500' : (c.sentiment === 'positif' ? 'bg-emerald-500' : 'bg-slate-400')" :title="`${c.sentiment}: ${c.reason}`"></span>
                          <span class="min-w-0">
                            <span class="font-bold text-slate-700 dark:text-slate-300">{{ c.author || 'anonim' }}</span>
                            <span class="text-slate-700 dark:text-slate-300"> {{ c.text }}</span>
                            <span v-if="c.likes" class="text-slate-500"> · {{ formatNumber(c.likes) }} suka</span>
                          </span>
                        </li>
                      </ul>
                    </template>
                    <p v-else class="text-[11px] text-slate-500 dark:text-slate-400">Belum ada komentar publik pada unggahan ini.</p>
                  </div>
                </div>
              </div>
            </div>
            <button v-if="(account.posts?.length ?? 0) > IG_PREVIEW" type="button" @click="igToggleAll(account)" class="text-xs font-bold text-amber-700 dark:text-amber-400 hover:underline cursor-pointer">
              {{ igShowAll.has(account.id) ? 'Tampilkan lebih sedikit' : `Lihat semua ${account.posts.length} unggahan` }}
            </button>
          </div>
        </article>
      </section>
      <div class="rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-4">
        <div class="border-b border-slate-200 dark:border-slate-800 pb-3">
          <h2 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
            <UserCheck class="w-5 h-5 text-amber-500" />
            <span>Direktori KOL &amp; Akun Berpengaruh (semua platform)</span>
          </h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
            Disusun dari unggahan tersimpan {{ isNational ? 'lingkup nasional' : (selectedPolda?.name || 'wilayah ini') }} yang penulisnya ditandai berpengaruh. Setiap kartu menunjukkan platform asal unggahan; akun yang sama di platform lain dihitung terpisah.
          </p>
        </div>

        <div v-if="kolPlatformTabs.length > 2" class="flex flex-wrap items-center gap-1.5" role="group" aria-label="Saring KOL per platform">
          <button
            v-for="t in kolPlatformTabs"
            :key="t.id"
            type="button"
            @click="kolPlatform = t.id"
            :aria-pressed="kolPlatform === t.id"
            class="px-2.5 py-1 rounded-xl text-xs font-bold transition cursor-pointer"
            :class="kolPlatform === t.id ? 'bg-amber-400 text-slate-950' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
          >{{ t.label }} ({{ t.count }})</button>
        </div>

        <p v-if="!kolProfiles?.length" class="rounded-xl bg-slate-50 dark:bg-slate-900 p-4 text-sm text-slate-600 dark:text-slate-400">Belum ada tokoh/KOL terdeteksi dari data yang tersimpan. Daftar terisi dari unggahan akun berpengaruh pada Radar Percakapan; Anda juga dapat memantau akun tertentu pada kotak di atas.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="(kol, idx) in visibleKols"
            :key="idx"
            class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 space-y-3"
          >
            <div class="flex items-center justify-between">
              <span
                class="px-2 py-0.5 rounded text-[10px] font-black uppercase"
                :class="getPlatformBadge(kol.platform).class"
              >
                {{ getPlatformBadge(kol.platform).label }}
              </span>
              <span class="text-[10px] font-bold text-amber-600 dark:text-amber-400 px-2 py-0.5 rounded bg-amber-50 dark:bg-amber-400/10">
                {{ kol.kol_category || 'Akun berpengaruh' }}
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
              <button
                v-if="canSync && ACCOUNT_PLATFORMS.some((x) => x.id === kol.platform) && kol.author_handle"
                @click="monitorKol(kol)"
                type="button"
                class="text-xs font-bold text-slate-600 dark:text-slate-300 hover:text-amber-600 cursor-pointer"
              >Pantau akun</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 04: TREN & JAM RAMAI -->
    <div v-if="activeTab === 'trends'" class="space-y-5">
      <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
        Dua sumber berbeda: <strong class="text-slate-800 dark:text-slate-200">tagar, kata, dan jam ramai</strong> dihitung dari unggahan media sosial yang tersimpan untuk wilayah aktif;
        <strong class="text-slate-800 dark:text-slate-200">tren pencarian Google</strong> menggambarkan isu yang sedang dicari publik se-Indonesia.
      </p>
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

        <!-- Jam ramai unggahan (data nyata, zona waktu tenant) -->
        <div class="rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-4">
          <div class="border-b border-slate-200 dark:border-slate-800 pb-3">
            <h3 class="text-sm font-black text-slate-900 dark:text-white flex items-center gap-2">
              <Clock class="w-4 h-4 text-amber-500" />
              <span>Jam Ramai Unggahan</span>
            </h3>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">
              Sebaran jam unggahan tersimpan ({{ peakHours?.timezone ?? '—' }}), {{ formatNumber(peakHours?.sample ?? 0) }} unggahan bertanggal.
            </p>
          </div>

          <template v-if="peakHours?.peak">
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
              <span class="text-[10px] text-slate-500 dark:text-slate-400 font-bold block">Paling ramai</span>
              <p class="font-black text-amber-600 dark:text-amber-400 text-base">{{ peakHours.peak.window }}</p>
              <p class="text-[11px] text-slate-600 dark:text-slate-400">{{ formatNumber(peakHours.peak.count) }} unggahan ({{ peakHours.peak.pct }}% dari semua unggahan bertanggal)</p>
            </div>
            <div>
              <div class="flex items-end gap-px h-20" role="img" :aria-label="`Sebaran unggahan per jam, paling ramai ${peakHours.peak.window}`">
                <div
                  v-for="(count, h) in (peakHours.hours || [])"
                  :key="h"
                  class="flex-1 rounded-t-sm"
                  :class="isPeakHour(h) ? 'bg-amber-400' : 'bg-slate-300 dark:bg-slate-700'"
                  :style="{ height: `${Math.max(2, (count / peakHourMax) * 100)}%` }"
                  :title="`${String(h).padStart(2, '0')}.00: ${count} unggahan`"
                ></div>
              </div>
              <div class="flex justify-between text-[9px] font-mono text-slate-500 dark:text-slate-400 mt-1">
                <span>00</span><span>06</span><span>12</span><span>18</span><span>23</span>
              </div>
            </div>
            <div class="p-3 rounded-2xl bg-amber-50 dark:bg-amber-400/10 border border-amber-300 dark:border-amber-400/30 text-[11px] text-amber-900 dark:text-amber-200 leading-relaxed">
              <p class="font-bold">Saran waktu rilis konter narasi</p>
              <p>Sekitar pukul <strong>{{ peakHours.peak.release_at }}</strong>, satu jam sebelum jam ramai. Disimpulkan dari pola jam unggahan, bukan jumlah pembaca.</p>
            </div>
          </template>
          <p v-else class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
            Belum cukup data untuk menyimpulkan jam ramai: perlu minimal {{ peakHours?.min_sample ?? 20 }} unggahan bertanggal (saat ini {{ formatNumber(peakHours?.sample ?? 0) }}).
          </p>
        </div>
      </div>

      <!-- VISUAL WORD CLOUD & TOPIC CLUSTERS (SEMANTIC ANALYSIS) -->
      <div class="rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-3">
          <div>
            <div class="flex items-center gap-2">
              <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-400 text-slate-950">
                Awan Kata
              </span>
              <span class="text-xs text-slate-500 dark:text-slate-400 font-mono">
                {{ (wordCloud?.words || []).length }} kata teratas dari {{ formatNumber(wordCloud?.total_unique ?? 0) }} kata unik
              </span>
            </div>
            <h3 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2 mt-1">
              <Cloud class="w-5 h-5 text-sky-500" />
              <span>Kata yang Paling Sering Muncul</span>
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
              Dihitung dari isi unggahan tersimpan (tanpa kata sambung). Ukuran = frekuensi; warna = sentimen yang paling sering menyertai kata itu. <strong>Klik kata untuk memfilter Radar.</strong>
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
        <p v-if="!(wordCloud?.words || []).length" class="p-8 text-center text-xs text-slate-500 dark:text-slate-400">Belum ada data: awan kata muncul setelah ada unggahan tersimpan untuk wilayah ini.</p>
        <div v-else class="p-6 rounded-2xl bg-slate-50/80 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 min-h-[220px] flex flex-wrap items-center justify-center gap-3 select-none">
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

      <!-- Tren pencarian Google Indonesia (sumber nyata, nasional) -->
      <section class="rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3 border-b border-slate-200 dark:border-slate-800 pb-3">
          <div>
            <h3 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
              <TrendingUp class="w-5 h-5 text-rose-500" />
              <span>Tren Pencarian Google Indonesia</span>
            </h3>
            <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5 max-w-3xl leading-relaxed">
              Topik yang sedang naik di pencarian Google se-Indonesia (sumber: Google Trends, berlaku nasional, diperbarui otomatis tiap jam).
              Ini tren <strong>pencarian</strong>, bukan jumlah unggahan media sosial. Penanda kamtibmas hanya kecocokan kata kunci, perlu ditinjau analis.
            </p>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
              Diperbarui: <strong class="text-slate-800 dark:text-slate-200">{{ publicTrends?.fetched_at ? formatTrendTime(publicTrends.fetched_at) : 'belum pernah' }}</strong>
            </p>
            <p v-if="publicTrends?.last_error" class="mt-2 px-3 py-2 rounded-xl bg-amber-400/10 border border-amber-400/40 text-[11px] text-amber-800 dark:text-amber-200" role="status">
              Pembaruan terakhir gagal: {{ publicTrends.last_error }}
            </p>
          </div>
          <button
            v-if="canSync"
            type="button"
            @click="triggerSyncTrends"
            :disabled="isSyncingTrends"
            class="shrink-0 px-4 py-2 rounded-xl text-xs font-bold bg-amber-400 hover:bg-amber-300 text-slate-950 transition shadow-sm flex items-center gap-2 disabled:opacity-50 cursor-pointer"
          >
            <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': isSyncingTrends }" />
            <span>{{ isSyncingTrends ? 'Memperbarui…' : 'Perbarui sekarang' }}</span>
          </button>
        </div>

        <div v-if="googleTrends.length" class="flex flex-wrap items-center gap-2">
          <div class="relative min-w-[220px] flex-1 sm:flex-initial">
            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5 pointer-events-none" />
            <input
              v-model="trendSearch"
              type="search"
              placeholder="Cari tren atau judul berita…"
              aria-label="Cari tren pencarian"
              class="w-full pl-8 pr-3 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-semibold text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-amber-400"
            />
          </div>
          <button
            type="button"
            @click="trendKamtibmasOnly = !trendKamtibmasOnly"
            :aria-pressed="trendKamtibmasOnly"
            class="px-3 py-1.5 rounded-xl text-xs font-bold transition border cursor-pointer flex items-center gap-1.5"
            :class="trendKamtibmasOnly
              ? 'bg-rose-500 text-white border-rose-500'
              : 'bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800'"
          >
            <AlertTriangle class="w-3.5 h-3.5" :class="trendKamtibmasOnly ? 'text-white' : 'text-rose-500'" />
            <span>Terkait kamtibmas saja</span>
            <span class="px-1.5 rounded-full text-[10px]" :class="trendKamtibmasOnly ? 'bg-white/20' : 'bg-rose-500/15 text-rose-600 dark:text-rose-400'">{{ googleTrendsKamtibmasCount }}</span>
          </button>
          <span class="text-[11px] text-slate-500 dark:text-slate-400">{{ filteredGoogleTrends.length }} dari {{ googleTrends.length }} tren</span>
        </div>

        <div v-if="filteredGoogleTrends.length" class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <article
            v-for="item in filteredGoogleTrends"
            :key="item.rank + item.title"
            class="rounded-2xl border p-4 space-y-2.5 flex flex-col"
            :class="item.kamtibmas?.length ? 'border-rose-300 dark:border-rose-900/70 bg-rose-50/40 dark:bg-rose-950/10' : 'border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/40'"
          >
            <div class="flex items-start gap-3">
              <span class="w-7 h-7 rounded-lg bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-200 flex items-center justify-center font-black text-xs shrink-0">{{ item.rank }}</span>
              <div class="min-w-0 flex-1">
                <h4 class="font-black text-sm text-slate-900 dark:text-white leading-snug break-words">{{ item.title }}</h4>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                  <span v-if="item.traffic">±{{ item.traffic }} pencarian</span><span v-else>Volume tidak tersedia</span>
                  · mulai naik {{ formatTrendTime(item.started_at) }}
                </p>
              </div>
            </div>
            <p v-if="item.kamtibmas?.length" class="text-[11px] font-bold text-rose-700 dark:text-rose-300 flex items-center gap-1.5">
              <AlertTriangle class="w-3.5 h-3.5 shrink-0" />
              <span>Memuat kata kunci kamtibmas: {{ item.kamtibmas.join(', ') }}</span>
            </p>
            <ul v-if="item.news?.length" class="space-y-1">
              <li v-for="n in item.news.slice(0, 2)" :key="n.url" class="text-[11px] leading-snug">
                <a :href="n.url" target="_blank" rel="noopener noreferrer" class="text-slate-700 dark:text-slate-300 hover:text-amber-600 dark:hover:text-amber-400 hover:underline">
                  {{ n.title }}
                </a>
                <span v-if="n.source" class="text-slate-500 dark:text-slate-500"> — {{ n.source }}</span>
              </li>
            </ul>
            <div class="mt-auto pt-2 border-t border-slate-200 dark:border-slate-800 flex flex-wrap items-center gap-1.5">
              <button
                type="button"
                @click="filterByGoogleTrend(item)"
                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-[11px] font-bold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition cursor-pointer"
              >
                <Search class="w-3 h-3" />
                <span>Cari di Radar</span>
              </button>
              <Link
                v-if="canCreateIssue"
                :href="getEscalationUrl(item)"
                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-[11px] font-bold bg-rose-500 hover:bg-rose-600 text-white transition"
              >
                <AlertTriangle class="w-3 h-3" />
                <span>Jadikan laporan isu</span>
              </Link>
              <a
                :href="item.explore_url"
                target="_blank"
                rel="noopener noreferrer"
                class="ml-auto inline-flex items-center gap-1 text-[11px] font-bold text-slate-500 dark:text-slate-400 hover:text-amber-600 dark:hover:text-amber-400"
              >
                <span>Google Trends</span>
                <ExternalLink class="w-3 h-3" />
              </a>
            </div>
          </article>
        </div>
        <div v-else class="p-8 text-center text-xs text-slate-500 dark:text-slate-400 space-y-1">
          <TrendingUp class="w-6 h-6 mx-auto text-slate-400 opacity-60" />
          <p v-if="googleTrends.length" class="font-bold text-slate-700 dark:text-slate-300">Tidak ada tren yang cocok dengan filter.</p>
          <template v-else>
            <p class="font-bold text-slate-700 dark:text-slate-300">Belum ada data tren pencarian.</p>
            <p>{{ canSync ? 'Klik "Perbarui sekarang", atau tunggu pembaruan otomatis tiap jam.' : 'Data diperbarui otomatis tiap jam oleh penjadwal.' }}</p>
          </template>
        </div>
      </section>
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

