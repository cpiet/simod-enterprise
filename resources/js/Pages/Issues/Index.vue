<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import { ISSUE_GROUPS, statusMeta, priorityMeta } from '@/utils/issueFlow';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
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
  Ellipsis,
  SlidersHorizontal,
  Archive,
  ArchiveRestore,
  Trash2,
  ArrowUpDown,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  polresList: Array,
  issues: Object, // Paginated
  filters: Object,
  metrics: Object,
  groupCounts: { type: Object, default: () => ({}) },
  categoryOptions: { type: Array, default: () => [] },
  canManage: { type: Boolean, default: false },
});

const searchQuery = ref(props.filters?.q || '');
const selectedStatus = ref(props.filters?.status || 'all');
const selectedPriority = ref(props.filters?.priority || 'all');
const selectedCategory = ref(props.filters?.category || 'all');
const selectedPolres = ref(props.filters?.polres || 'all');
const selectedSort = ref(props.filters?.sort || 'terbaru');
const selectedPerPage = ref(props.filters?.per_page || 15);

const handleFilter = () => {
  const params = {
    status: selectedStatus.value,
    priority: selectedPriority.value,
    category: selectedCategory.value,
    polres: selectedPolres.value,
    q: searchQuery.value,
  };
  if (selectedSort.value !== 'terbaru') params.sort = selectedSort.value;
  if (Number(selectedPerPage.value) !== 15) params.per_page = selectedPerPage.value;
  router.get('/issues', params, { preserveState: true, preserveScroll: true });
};

const resetFilter = () => {
  searchQuery.value = '';
  selectedStatus.value = 'all';
  selectedPriority.value = 'all';
  selectedCategory.value = 'all';
  selectedPolres.value = 'all';
  selectedSort.value = 'terbaru';
  selectedPerPage.value = 15;
  router.get('/issues', {}, { preserveState: true, preserveScroll: true });
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

// Izin pengguna menentukan tombol yang tampil
const page = usePage();
const permissions = computed(() => page.props.auth?.permissions || {});
const canCreate = computed(() => !!permissions.value.issues_create);
const canVerify = computed(() => !!permissions.value.issues_verify);

// Menu melayang & panel filter (satu terbuka pada satu waktu)
const openMenu = ref(null);
const showFilterPanel = ref(false);
const closeFloating = () => {
  openMenu.value = null;
  showFilterPanel.value = false;
};
onMounted(() => document.addEventListener('click', closeFloating));
onUnmounted(() => document.removeEventListener('click', closeFloating));

const activeFilterCount = computed(() =>
  (selectedCategory.value !== 'all' ? 1 : 0) + (selectedPriority.value !== 'all' ? 1 : 0) + (selectedPolres.value !== 'all' ? 1 : 0)
);

const setGroup = (key) => {
  selectedStatus.value = key;
  handleFilter();
};

const toggleAttention = () => {
  selectedPriority.value = selectedPriority.value === 'attention' ? 'all' : 'attention';
  handleFilter();
};

const exportUrl = computed(() => {
  const q = new URLSearchParams({
    status: selectedStatus.value,
    priority: selectedPriority.value,
    category: selectedCategory.value,
    polres: selectedPolres.value,
    q: searchQuery.value,
  });
  return '/issues-export-csv?' + q.toString();
});

const countOf = (key) => props.groupCounts?.[key] ?? 0;

// ---- Seleksi & aksi massal: arsipkan, pulihkan, hapus (ke Sampah), hapus permanen, ubah prioritas ----
const inTrash = computed(() => selectedStatus.value === 'trash');
const inArchive = computed(() => selectedStatus.value === 'archived');
const isClosed = (issue) => ['arsip'].includes(issue.status);
const hasMenu = (issue) => issue.can_archive || issue.can_restore || issue.can_delete || issue.can_force_delete || (props.canManage && !isClosed(issue) && !inTrash.value);

const selected = ref(new Set());
const pageIds = computed(() => (props.issues?.data || []).map((i) => i.id));
const selectedCount = computed(() => selected.value.size);
const allOnPage = computed(() => pageIds.value.length > 0 && pageIds.value.every((id) => selected.value.has(id)));
const toggleOne = (id) => {
  const next = new Set(selected.value);
  if (next.has(id)) next.delete(id); else next.add(id);
  selected.value = next;
};
const toggleAll = () => { selected.value = allOnPage.value ? new Set() : new Set(pageIds.value); };
const clearSelection = () => { selected.value = new Set(); };
// Berpindah halaman/filter: hanya pertahankan pilihan yang masih tampil.
watch(pageIds, (ids) => { selected.value = new Set([...selected.value].filter((id) => ids.includes(id))); });

const busy = ref(false);
const dialog = ref({ open: false, kind: '', ids: [] });
const DIALOGS = {
  archive: (n) => ({ title: `Arsipkan ${n} laporan?`, message: 'Laporan pindah ke tab Arsip dan tidak lagi muncul di daftar kerja. Dapat dipulihkan ke tahap semula kapan saja.', confirmLabel: 'Arsipkan', tone: 'primary', withReason: true }),
  delete: (n) => ({ title: `Pindahkan ${n} laporan ke Sampah?`, message: 'Laporan hilang dari daftar, tetapi tidak dihapus permanen: Anda dapat memulihkannya dari tab Sampah.', confirmLabel: 'Pindahkan ke Sampah', tone: 'danger', withReason: true }),
  delete_permanent: (n) => ({ title: `Hapus permanen ${n} laporan?`, message: 'Laporan beserta foto buktinya dihapus selamanya dan TIDAK dapat dipulihkan.', confirmLabel: 'Hapus permanen', tone: 'danger', requireText: 'HAPUS' }),
};
const dialogProps = computed(() => (dialog.value.open ? DIALOGS[dialog.value.kind](dialog.value.ids.length) : { title: '' }));

const send = (action, ids, extra = {}) => {
  busy.value = true;
  router.post('/issues-bulk', { action, ids, ...extra }, {
    preserveScroll: true,
    onSuccess: () => { dialog.value.open = false; clearSelection(); },
    onFinish: () => { busy.value = false; },
  });
};
const ask = (kind, ids) => { dialog.value = { open: true, kind, ids }; };
const confirmDialog = ({ reason, confirm }) => send(dialog.value.kind, dialog.value.ids, { reason, confirm });
const restore = (ids) => send('restore', ids);
const setPriority = (ids, priority) => { if (priority) send('priority', ids, { priority }); };

const emptyTitle = computed(() => (inTrash.value ? 'Sampah kosong' : inArchive.value ? 'Belum ada laporan di arsip' : 'Tidak ada laporan pada tampilan ini'));
const emptyHint = computed(() => (inTrash.value
  ? 'Laporan yang dihapus akan muncul di sini dan dapat dipulihkan.'
  : inArchive.value ? 'Laporan yang diarsipkan dapat dipulihkan dari sini.' : null));
</script>

<template>
  <EnterpriseLayout
    :all-poldas="allPoldas"
    :selected-polda="selectedPolda"
    :selected-code="selectedCode"
    :metrics="metrics"
  >
    <!-- Header ringkas: judul + 1 aksi utama + menu -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div class="min-w-0">
        <p class="text-[11px] font-bold text-slate-500 dark:text-slate-400">
          {{ selectedCode === 'ALL' ? 'Semua Wilayah Hukum Mabes Polri' : selectedPolda?.name }}
        </p>
        <h1 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white tracking-tight">Laporan Polres &amp; Isu</h1>
      </div>

      <div class="flex items-center gap-2 shrink-0">
        <Link
          v-if="canCreate"
          href="/issues/create"
          class="px-4 py-2.5 rounded-xl text-xs font-black bg-amber-400 text-slate-950 hover:bg-amber-300 shadow-lg shadow-amber-400/25 flex items-center gap-2 transition select-none"
        >
          <PlusCircle class="w-4 h-4" />
          <span>Buat Laporan</span>
        </Link>

        <div class="relative">
          <button
            type="button"
            @click.stop="openMenu = openMenu === 'head' ? null : 'head'"
            class="p-2.5 rounded-xl bg-white hover:bg-slate-100 text-slate-600 dark:bg-slate-900 dark:hover:bg-slate-800 dark:text-slate-300 border border-slate-300 dark:border-slate-700 transition cursor-pointer"
            title="Aksi lainnya"
          >
            <Ellipsis class="w-4 h-4" />
          </button>
          <div
            v-if="openMenu === 'head'"
            @click.stop
            class="absolute right-0 top-full mt-2 z-30 w-52 rounded-xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-700 shadow-2xl p-1.5"
          >
            <a
              :href="exportUrl"
              class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-bold text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800 transition"
            >
              <Download class="w-4 h-4 text-slate-500" />
              <span>Ekspor CSV (sesuai filter)</span>
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab status: kelompok alur + hitungan (menggantikan 5 kartu KPI & dropdown status) -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1">
      <button
        v-for="g in ISSUE_GROUPS"
        :key="g.key"
        type="button"
        @click="setGroup(g.key)"
        class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 cursor-pointer select-none whitespace-nowrap border"
        :class="selectedStatus === g.key
          ? 'bg-amber-400 text-slate-950 font-black border-amber-300 shadow-md'
          : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 border-slate-200 dark:border-slate-700'"
      >
        <span>{{ g.label }}</span>
        <span
          class="px-1.5 py-0.5 rounded-full text-[10px] font-mono"
          :class="selectedStatus === g.key ? 'bg-slate-950 text-amber-300 font-black' : 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold'"
        >{{ countOf(g.key) }}</span>
      </button>

      <button
        v-if="countOf('draft') > 0"
        type="button"
        @click="setGroup('draft')"
        class="px-3.5 py-2 rounded-xl text-xs font-bold transition cursor-pointer select-none whitespace-nowrap border"
        :class="selectedStatus === 'draft' ? 'bg-amber-400 text-slate-950 font-black border-amber-300' : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
      >Draf ({{ countOf('draft') }})</button>

      <button
        type="button"
        @click="setGroup('archived')"
        class="px-3.5 py-2 rounded-xl text-xs font-bold transition cursor-pointer select-none whitespace-nowrap border"
        :class="selectedStatus === 'archived' ? 'bg-amber-400 text-slate-950 font-black border-amber-300' : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
      >Arsip ({{ countOf('archived') }})</button>

      <button
        v-if="canManage"
        type="button"
        @click="setGroup('trash')"
        class="px-3.5 py-2 rounded-xl text-xs font-bold transition cursor-pointer select-none whitespace-nowrap border flex items-center gap-1.5"
        :class="selectedStatus === 'trash' ? 'bg-rose-600 text-white font-black border-rose-500 shadow-md' : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800'"
      >
        <Trash2 class="w-3.5 h-3.5" />
        <span>Sampah ({{ countOf('trash') }})</span>
      </button>

      <span class="h-5 w-px bg-slate-300 dark:bg-slate-700 shrink-0"></span>

      <button
        type="button"
        @click="toggleAttention"
        class="px-3.5 py-2 rounded-xl text-xs font-black transition flex items-center gap-1.5 cursor-pointer select-none whitespace-nowrap border"
        :class="selectedPriority === 'attention'
          ? 'bg-rose-600 text-white border-rose-500 shadow-md'
          : 'bg-white dark:bg-slate-900 text-rose-600 dark:text-rose-400 border-rose-300 dark:border-rose-900/60 hover:bg-rose-50 dark:hover:bg-rose-950/30'"
        title="Prioritas Kritis & Tinggi yang belum selesai"
      >
        <Flame class="w-3.5 h-3.5" />
        <span>Atensi Pimpinan ({{ countOf('attention') }})</span>
      </button>
    </div>

    <!-- Cari + Filter lanjutan -->
    <div class="p-3.5 rounded-2xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row items-stretch sm:items-center gap-3 shadow-sm">
      <div class="relative flex-1 min-w-[220px]">
        <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-2.5 pointer-events-none" />
        <input
          v-model="searchQuery"
          @keydown.enter="handleFilter"
          type="text"
          placeholder="Cari kode (SIM-...), judul, lokasi, atau narasi..."
          class="w-full pl-10 pr-4 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-semibold text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-amber-400"
        />
      </div>

      <div class="relative">
        <button
          type="button"
          @click.stop="showFilterPanel = !showFilterPanel"
          class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold transition border cursor-pointer select-none"
          :class="activeFilterCount > 0
            ? 'bg-amber-400/15 text-amber-700 dark:text-amber-300 border-amber-400/60'
            : 'bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-700 hover:border-slate-500'"
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
            <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400">Kategori</span>
            <select v-model="selectedCategory" @change="handleFilter" class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 outline-none focus:border-amber-400">
              <option value="all">Semua Kategori</option>
              <option v-for="c in categoryOptions" :key="c" :value="c">{{ c }}</option>
            </select>
          </label>

          <label class="block space-y-1">
            <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400">Prioritas</span>
            <select v-model="selectedPriority" @change="handleFilter" class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 outline-none focus:border-amber-400">
              <option value="all">Semua Prioritas</option>
              <option value="attention">Atensi (Kritis &amp; Tinggi)</option>
              <option value="critical">Kritis</option>
              <option value="high">Tinggi</option>
              <option value="medium">Sedang</option>
              <option value="low">Rendah</option>
            </select>
          </label>

          <label v-if="polresList && polresList.length > 0" class="block space-y-1">
            <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400">Wilayah (Polres)</span>
            <select v-model="selectedPolres" @change="handleFilter" class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 outline-none focus:border-amber-400">
              <option value="all">Semua Wilayah</option>
              <option v-for="p in polresList" :key="p.id" :value="p.code">{{ p.name }}</option>
            </select>
          </label>

          <label class="block space-y-1">
            <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 flex items-center gap-1"><ArrowUpDown class="w-3.5 h-3.5" /> Urutan</span>
            <select v-model="selectedSort" @change="handleFilter" class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:border-amber-400 cursor-pointer">
              <option value="terbaru">Terbaru dibuat</option>
              <option value="terlama">Terlama dibuat</option>
              <option value="prioritas">Prioritas tertinggi dulu</option>
              <option value="diperbarui">Terakhir diperbarui</option>
            </select>
          </label>

          <label class="block space-y-1">
            <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400">Laporan per halaman</span>
            <select v-model.number="selectedPerPage" @change="handleFilter" class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:border-amber-400 cursor-pointer">
              <option :value="15">15</option>
              <option :value="30">30</option>
              <option :value="50">50</option>
            </select>
          </label>

          <div class="flex items-center justify-between pt-1">
            <button type="button" @click="resetFilter(); showFilterPanel = false" class="text-[11px] font-bold text-slate-500 hover:text-slate-900 dark:hover:text-white cursor-pointer">Atur ulang semua</button>
            <button type="button" @click="showFilterPanel = false" class="px-3 py-1.5 rounded-lg bg-amber-400 text-slate-950 text-xs font-black cursor-pointer">Selesai</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Daftar laporan -->
    <div class="space-y-3">
      <!-- Empty State -->
      <div
        v-if="!issues?.data || issues.data.length === 0"
        class="rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 p-10 text-center shadow-sm space-y-3"
      >
        <div class="w-14 h-14 rounded-2xl bg-amber-100 dark:bg-amber-400/10 text-amber-600 dark:text-amber-400 flex items-center justify-center mx-auto">
          <FileText class="w-7 h-7" />
        </div>
        <h3 class="text-base font-black text-slate-900 dark:text-white">{{ emptyTitle }}</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto">{{ emptyHint || ('Ubah kata kunci atau filter' + (canCreate ? ', atau buat laporan lapangan baru.' : '.')) }}</p>
        <div class="flex items-center justify-center gap-2 pt-1">
          <button type="button" @click="resetFilter" class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-900 dark:hover:bg-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700 cursor-pointer">Atur ulang filter</button>
          <Link v-if="canCreate" href="/issues/create" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-400 text-slate-950 font-black text-xs hover:bg-amber-300 shadow-md transition">
            <PlusCircle class="w-4 h-4" />
            <span>Buat Laporan</span>
          </Link>
        </div>
      </div>

      <!-- Info tab Arsip / Sampah -->
      <p v-if="inArchive" class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-[11px] text-slate-600 dark:text-slate-300 flex items-center gap-2"><Archive class="w-4 h-4 text-slate-500 shrink-0" /> Laporan arsip tidak tampil di tab Semua. Pulihkan untuk mengembalikannya ke tahap semula.</p>
      <p v-if="inTrash" class="px-4 py-2.5 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 text-[11px] text-rose-700 dark:text-rose-300 flex items-center gap-2"><Trash2 class="w-4 h-4 shrink-0" /> Laporan di Sampah tersembunyi dari semua daftar dan KPI. Pulihkan bila keliru, atau hapus permanen.</p>

      <!-- Pilih semua + bilah aksi massal (muncul saat ada yang dipilih) -->
      <div v-if="canManage && issues?.data?.length" class="sticky top-2 z-20 flex flex-wrap items-center gap-2 px-4 py-2.5 rounded-2xl border shadow-sm transition" :class="selectedCount ? 'bg-amber-50 dark:bg-[#10182c] border-amber-300 dark:border-amber-400/50 shadow-lg' : 'bg-white dark:bg-[#0b1426] border-slate-200 dark:border-slate-800'">
        <label class="flex items-center gap-2 text-xs font-bold text-slate-700 dark:text-slate-200 cursor-pointer select-none">
          <input type="checkbox" :checked="allOnPage" @change="toggleAll" class="w-4 h-4 accent-amber-500" aria-label="Pilih semua laporan di halaman ini" />
          <span>{{ selectedCount ? selectedCount + ' dipilih' : 'Pilih semua di halaman ini' }}</span>
        </label>

        <template v-if="selectedCount">
          <span class="h-5 w-px bg-slate-300 dark:bg-slate-700"></span>
          <template v-if="!inTrash && !inArchive">
            <button type="button" @click="ask('archive', [...selected])" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-black bg-slate-900 hover:bg-slate-800 text-white dark:bg-slate-800 dark:hover:bg-slate-700 cursor-pointer"><Archive class="w-3.5 h-3.5" /> Arsipkan</button>
            <label class="flex items-center gap-1.5 text-xs font-bold text-slate-600 dark:text-slate-300">
              <span>Prioritas:</span>
              <select @change="setPriority([...selected], $event.target.value); $event.target.value = ''" class="px-2 py-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-bold cursor-pointer" aria-label="Ubah prioritas laporan terpilih">
                <option value="">Ubah ke...</option>
                <option value="critical">Kritis</option>
                <option value="high">Tinggi</option>
                <option value="medium">Sedang</option>
                <option value="low">Rendah</option>
              </select>
            </label>
          </template>
          <button v-if="inTrash || inArchive" type="button" @click="restore([...selected])" :disabled="busy" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-black bg-emerald-600 hover:bg-emerald-500 text-white cursor-pointer disabled:opacity-50"><ArchiveRestore class="w-3.5 h-3.5" /> Pulihkan</button>
          <button v-if="!inTrash" type="button" @click="ask('delete', [...selected])" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-black bg-rose-600 hover:bg-rose-500 text-white cursor-pointer"><Trash2 class="w-3.5 h-3.5" /> Hapus</button>
          <button v-else type="button" @click="ask('delete_permanent', [...selected])" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-black bg-rose-700 hover:bg-rose-600 text-white cursor-pointer"><Trash2 class="w-3.5 h-3.5" /> Hapus permanen</button>
          <button type="button" @click="clearSelection" class="ml-auto text-xs font-bold text-slate-500 hover:text-slate-900 dark:hover:text-white cursor-pointer">Batal pilih</button>
        </template>
      </div>

      <!-- Kartu laporan -->
      <div
        v-for="issue in issues.data"
        :key="issue.id"
        class="rounded-2xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800/90 p-4 md:p-5 shadow-sm hover:border-slate-400 dark:hover:border-slate-700 transition space-y-2.5"
      >
        <div class="flex flex-wrap items-center gap-2">
          <input
            v-if="canManage"
            type="checkbox"
            :checked="selected.has(issue.id)"
            @change="toggleOne(issue.id)"
            class="w-4 h-4 accent-amber-500 cursor-pointer"
            :aria-label="'Pilih laporan ' + (issue.report_code || issue.id)"
          />
          <button
            @click="copyCode(issue.report_code || 'SIM-' + issue.id)"
            type="button"
            class="px-2 py-0.5 rounded-lg text-[11px] font-mono font-bold bg-slate-100 dark:bg-slate-900 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-800 hover:border-amber-400 flex items-center gap-1.5 cursor-pointer transition"
            :title="copiedCode === (issue.report_code || 'SIM-' + issue.id) ? 'Tersalin!' : 'Klik untuk salin kode'"
          >
            <Check v-if="copiedCode === (issue.report_code || 'SIM-' + issue.id)" class="w-3 h-3 text-emerald-500" />
            <Copy v-else class="w-3 h-3 text-slate-400" />
            <span>{{ issue.report_code || 'SIM-' + issue.id }}</span>
          </button>

          <span class="px-2 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-wider" :class="priorityMeta(issue.priority).cls">
            {{ priorityMeta(issue.priority).label }}
          </span>

          <span class="px-2 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-wider" :class="statusMeta(issue.status).cls">
            {{ statusMeta(issue.status).label }}
          </span>

          <span v-if="issue.deleted_at" class="px-2 py-0.5 rounded-lg text-[10px] font-black bg-rose-100 dark:bg-rose-500/20 text-rose-700 dark:text-rose-300 border border-rose-300 dark:border-rose-500/40 flex items-center gap-1"><Trash2 class="w-3 h-3" /> DI SAMPAH</span>
          <span v-if="issue.is_hoax" class="px-2 py-0.5 rounded-lg text-[10px] font-black bg-rose-600 text-white flex items-center gap-1">
            <XCircle class="w-3 h-3" /> HOAKS
          </span>

          <span class="ml-auto text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
            <Building2 class="w-3.5 h-3.5 text-amber-500" />
            <span class="font-bold text-slate-800 dark:text-slate-200">{{ issue.polres?.name || issue.polda?.name || 'Divhumas Polri' }}</span>
          </span>
        </div>

        <div class="space-y-1">
          <Link :href="'/issues/' + issue.id" class="text-base font-black text-slate-900 dark:text-white leading-snug hover:text-amber-500 dark:hover:text-amber-400 transition block">
            {{ issue.title }}
          </Link>
          <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed line-clamp-2">{{ issue.summary }}</p>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-slate-100 dark:border-slate-800/80">
          <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-slate-500 dark:text-slate-400 text-[11px]">
            <span v-if="issue.category" class="font-bold text-slate-700 dark:text-slate-300">{{ issue.category }}</span>
            <span v-if="issue.location_text" class="flex items-center gap-1"><MapPin class="w-3.5 h-3.5" /> {{ issue.location_text }}</span>
            <span class="flex items-center gap-1"><Calendar class="w-3.5 h-3.5" /> {{ new Date(issue.occurred_at || issue.created_at).toLocaleString('id-ID') }}</span>
            <span v-if="issue.media_files_count > 0" class="font-bold text-amber-700 dark:text-amber-400">{{ issue.media_files_count }} foto bukti</span>
            <a v-if="issue.source_url" :href="issue.source_url" target="_blank" rel="noopener noreferrer" class="text-sky-600 dark:text-sky-400 hover:underline flex items-center gap-1">
              <ExternalLink class="w-3 h-3" /> Sumber
            </a>
          </div>

          <div class="flex items-center gap-1.5">
            <Link :href="'/issues/' + issue.id" class="px-4 py-1.5 rounded-xl text-xs font-black bg-amber-400 hover:bg-amber-300 text-slate-950 flex items-center gap-1.5 transition shadow">
              <span>Buka</span>
              <ChevronRight class="w-3.5 h-3.5" />
            </Link>

            <div v-if="hasMenu(issue)" class="relative">
              <button
                type="button"
                @click.stop="openMenu = openMenu === 'card-' + issue.id ? null : 'card-' + issue.id"
                class="p-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 transition cursor-pointer"
                title="Aksi lainnya"
              >
                <Ellipsis class="w-4 h-4" />
              </button>
              <div
                v-if="openMenu === 'card-' + issue.id"
                @click.stop
                class="absolute bottom-full right-0 mb-2 z-30 w-52 rounded-xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-700 shadow-2xl p-1.5"
              >
                <button
                  type="button"
                  v-if="canVerify && !isClosed(issue) && !issue.deleted_at"
                  @click="openClarifyModal(issue); openMenu = null"
                  class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-bold text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800 transition cursor-pointer text-left"
                >
                  <Sparkles class="w-4 h-4 text-amber-500" />
                  <span>Klarifikasi cepat</span>
                </button>
                <button v-if="issue.can_archive" type="button" @click="ask('archive', [issue.id]); openMenu = null" class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-bold text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800 transition cursor-pointer text-left">
                  <Archive class="w-4 h-4 text-slate-500" />
                  <span>Arsipkan</span>
                </button>
                <button v-if="issue.can_restore" type="button" @click="restore([issue.id]); openMenu = null" class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-bold text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-500/10 transition cursor-pointer text-left">
                  <ArchiveRestore class="w-4 h-4" />
                  <span>Pulihkan</span>
                </button>
                <button v-if="issue.can_delete" type="button" @click="ask('delete', [issue.id]); openMenu = null" class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-bold text-rose-700 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-500/10 transition cursor-pointer text-left">
                  <Trash2 class="w-4 h-4" />
                  <span>Hapus (ke Sampah)</span>
                </button>
                <button v-if="issue.can_force_delete" type="button" @click="ask('delete_permanent', [issue.id]); openMenu = null" class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-bold text-rose-700 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-500/10 transition cursor-pointer text-left">
                  <Trash2 class="w-4 h-4" />
                  <span>Hapus permanen</span>
                </button>
              </div>
            </div>
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
    <ConfirmDialog
      :open="dialog.open"
      :busy="busy"
      v-bind="dialogProps"
      @confirm="confirmDialog"
      @cancel="dialog.open = false"
    />
  </EnterpriseLayout>
</template>
