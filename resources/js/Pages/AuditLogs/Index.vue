<script setup>
import { ref, computed, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import SecurityMaintenanceTabs from '@/Components/SecurityMaintenanceTabs.vue';
import AuditPurgeDialog from '@/Components/AuditPurgeDialog.vue';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import {
  History, Server, Download, Search, Trash2, X, ChevronDown, ChevronRight, RotateCcw,
  CheckCircle2, AlertTriangle, XCircle, Database, HardDrive, Cpu, Clock, Settings2, ShieldAlert, Timer, Copy, Check,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  metrics: Object,
  logs: Object,
  stats: Object,
  filters: Object,
  facets: Object,
  timezone: String,
  retention: Object,
  serverHealth: Object,
  readiness: { type: Array, default: () => [] },
  scheduler: { type: Object, default: () => ({ active: false, tasks: [], suggestions: [] }) },
});

const tab = ref('logs');
const showPurge = ref(false);
const showRetention = ref(false);
const retentionDays = ref(props.retention?.days ?? 180);
const expanded = ref(new Set());

// ---- Filter: dijalankan di server; URL ikut berubah sehingga tautan/refresh memulihkan tampilan ----
const f = ref({
  q: props.filters?.q || '',
  kategori: props.filters?.kategori || '',
  aksi: props.filters?.aksi || '',
  hasil: props.filters?.hasil || '',
  user: props.filters?.user || '',
  from: props.filters?.from || '',
  to: props.filters?.to || '',
  per_page: props.filters?.per_page || 20,
});
const loading = ref(false);
const query = computed(() => Object.fromEntries(Object.entries(f.value).filter(([k, v]) => v !== '' && v !== null && !(k === 'per_page' && Number(v) === 20))));
const apply = () => {
  loading.value = true;
  router.get('/audit-logs', query.value, { preserveState: true, preserveScroll: true, replace: true, onFinish: () => { loading.value = false; } });
};
watch(() => [f.value.kategori, f.value.aksi, f.value.hasil, f.value.user, f.value.from, f.value.to, f.value.per_page], apply);
const hasFilters = computed(() => ['q', 'kategori', 'aksi', 'hasil', 'user', 'from', 'to'].some((k) => f.value[k] !== ''));
const reset = () => {
  f.value = { q: '', kategori: '', aksi: '', hasil: '', user: '', from: '', to: '', per_page: f.value.per_page };
  if (!hasFilters.value) apply();
};
const pickCategory = (key) => { f.value.kategori = f.value.kategori === key ? '' : key; f.value.aksi = ''; };
const exportHref = computed(() => '/audit-logs/export?' + new URLSearchParams(Object.fromEntries(Object.entries(query.value).filter(([k]) => k !== 'per_page'))).toString());

const toggleRow = (id) => {
  const s = new Set(expanded.value);
  if (s.has(id)) s.delete(id); else s.add(id);
  expanded.value = s;
};

const saveRetention = () => {
  router.post('/audit-logs/retention', { days: retentionDays.value }, { preserveScroll: true, onSuccess: () => { showRetention.value = false; } });
};
const retentionLabel = computed(() => (props.retention?.days > 0 ? `${props.retention.days} hari` : 'selamanya'));

const pageLabel = (label) => {
  if (/Previous/i.test(label)) return '‹ Sebelumnya';
  if (/Next/i.test(label)) return 'Berikutnya ›';
  return label;
};

// ---- Kesiapan hosting ----
const readyCount = computed(() => ({
  ok: props.readiness.filter((c) => c.status === 'ok').length,
  warn: props.readiness.filter((c) => c.status === 'warn').length,
  fail: props.readiness.filter((c) => c.status === 'fail').length,
}));
const statusIcon = (s) => ({ ok: CheckCircle2, warn: AlertTriangle, fail: XCircle }[s] || AlertTriangle);
const statusTone = (s) => ({ ok: 'text-emerald-500', warn: 'text-amber-500', fail: 'text-rose-500' }[s] || 'text-slate-400');

// ---- Penjadwal (cron) ----
const copied = ref('');
const copyCmd = async (key, text) => {
  try { await navigator.clipboard.writeText(text); copied.value = key; setTimeout(() => { copied.value = ''; }, 2000); } catch (e) { copied.value = ''; }
};
const TASK_STATUS = {
  ok: { label: 'Berjalan', cls: 'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-300 dark:border-emerald-500/30' },
  late: { label: 'Terlambat', cls: 'bg-amber-100 dark:bg-amber-400/15 text-amber-700 dark:text-amber-300 border-amber-300 dark:border-amber-400/40' },
  failed: { label: 'Gagal', cls: 'bg-rose-100 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-300 dark:border-rose-500/30' },
  never: { label: 'Belum pernah', cls: 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-300 dark:border-slate-600' },
  off: { label: 'Nonaktif', cls: 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border-slate-300 dark:border-slate-600' },
};
const orderedSuggestions = computed(() => [...(props.scheduler.suggestions || [])].sort((a, b) => (b.key === props.scheduler.os) - (a.key === props.scheduler.os)));

const card = 'rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 shadow-sm';
const inputCls = 'px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs text-slate-900 dark:text-white outline-none focus:border-amber-400';
const fmt = (n) => Number(n || 0).toLocaleString('id-ID');
</script>

<template>
  <EnterpriseLayout :all-poldas="allPoldas" :selected-polda="selectedPolda" :selected-code="selectedCode" :metrics="metrics">
    <SecurityMaintenanceTabs :selected-code="selectedCode" />

    <!-- Header -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
      <div class="min-w-0">
        <h1 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white tracking-tight">Audit Log</h1>
        <p class="text-xs md:text-sm text-slate-500 dark:text-slate-300 mt-1">
          Catatan siapa melakukan apa, kapan, dan dari mana &bull; {{ selectedCode === 'ALL' ? 'Seluruh wilayah' : (selectedPolda?.name || '') }} &bull; zona waktu {{ timezone }}.
          Log dapat dibersihkan, dan setiap pembersihan selalu tercatat.
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <div class="relative">
          <button type="button" @click="showRetention = !showRetention" class="flex items-center gap-2 px-3.5 py-2.5 rounded-xl text-xs font-bold border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:border-amber-400 transition cursor-pointer" :aria-expanded="showRetention">
            <Settings2 class="w-4 h-4 text-amber-500" />
            <span>Retensi otomatis: <strong>{{ retentionLabel }}</strong></span>
            <ChevronDown class="w-3.5 h-3.5" />
          </button>
          <div v-if="showRetention" class="absolute right-0 top-full mt-2 z-30 w-72 rounded-2xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-700 shadow-2xl p-4 space-y-3">
            <p class="text-xs font-black text-slate-900 dark:text-white">Pangkas otomatis log yang lebih lama dari</p>
            <select v-model.number="retentionDays" :class="inputCls + ' w-full font-bold'">
              <option v-for="d in retention.options" :key="d" :value="d">{{ d === 0 ? 'Jangan pangkas (simpan selamanya)' : d + ' hari' + (d === 180 ? ' (bawaan)' : '') }}</option>
            </select>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">Berjalan tiap hari pukul 02:30 lewat penjadwal (cron) hosting. Catatan pembersihan tidak ikut dihapus.</p>
            <div class="flex justify-end gap-2">
              <button type="button" @click="showRetention = false" class="px-3 py-1.5 rounded-lg text-xs font-bold text-slate-500 hover:text-slate-800 dark:hover:text-white cursor-pointer">Batal</button>
              <button type="button" @click="saveRetention" :disabled="retentionDays === retention.days" class="px-3.5 py-1.5 rounded-lg text-xs font-black bg-amber-400 text-slate-950 disabled:opacity-40 cursor-pointer">Simpan</button>
            </div>
          </div>
        </div>

        <a :href="exportHref" class="flex items-center gap-2 px-3.5 py-2.5 rounded-xl text-xs font-bold border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:border-amber-400 transition" title="Unduh log yang sedang ditampilkan (sesuai filter) sebagai CSV">
          <Download class="w-4 h-4 text-amber-500" /><span>Ekspor CSV</span>
        </a>
        <button type="button" @click="showPurge = true" class="flex items-center gap-2 px-3.5 py-2.5 rounded-xl text-xs font-black bg-rose-600 hover:bg-rose-500 text-white shadow-lg shadow-rose-600/20 transition cursor-pointer">
          <Trash2 class="w-4 h-4" /><span>Bersihkan log</span>
        </button>
      </div>
    </div>

    <!-- Ringkasan: semua angka dari basis data -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
      <div :class="card + ' p-4 space-y-1'"><p class="text-[11px] font-bold text-slate-500">Total log</p><p class="text-2xl font-black text-slate-900 dark:text-white">{{ fmt(stats.total) }}</p><p class="text-[11px] text-slate-500">{{ stats.oldest ? 'tertua ' + stats.oldest : 'belum ada log' }}</p></div>
      <div :class="card + ' p-4 space-y-1'"><p class="text-[11px] font-bold text-slate-500">24 jam terakhir</p><p class="text-2xl font-black text-slate-900 dark:text-white">{{ fmt(stats.last24) }}</p><p class="text-[11px] text-slate-500">aktivitas tercatat</p></div>
      <button type="button" @click="f.hasil = 'gagal'" :class="card + ' p-4 space-y-1 text-left hover:border-rose-400 transition cursor-pointer'">
        <p class="text-[11px] font-bold text-slate-500">Gagal / ditolak 24 jam</p>
        <p class="text-2xl font-black" :class="stats.failed24 > 0 ? 'text-rose-500' : 'text-slate-900 dark:text-white'">{{ fmt(stats.failed24) }}</p>
        <p class="text-[11px] text-slate-500">{{ fmt(stats.failed_login24) }} gagal masuk</p>
      </button>
      <div :class="card + ' p-4 space-y-1'"><p class="text-[11px] font-bold text-slate-500">Pengguna aktif 24 jam</p><p class="text-2xl font-black text-slate-900 dark:text-white">{{ fmt(stats.users24) }}</p><p class="text-[11px] text-slate-500">akun berbeda</p></div>
      <div :class="card + ' p-4 space-y-1 col-span-2 lg:col-span-1'"><p class="text-[11px] font-bold text-slate-500">Retensi otomatis</p><p class="text-2xl font-black text-slate-900 dark:text-white">{{ retentionLabel }}</p><p class="text-[11px] text-slate-500">{{ retention.days > 0 ? 'dipangkas harian' : 'tidak dipangkas' }}</p></div>
    </div>

    <div v-if="!scheduler.active" class="rounded-2xl border border-amber-300 dark:border-amber-400/40 bg-amber-50 dark:bg-amber-400/10 px-4 py-3 flex flex-wrap items-center justify-between gap-3" role="status">
      <p class="text-xs text-amber-900 dark:text-amber-100 flex items-center gap-2">
        <Timer class="w-4 h-4 shrink-0 text-amber-600 dark:text-amber-300" />
        <span><strong>Penjadwal (cron) tidak aktif.</strong> Sinkron RSS, cadangan otomatis, dan pemangkasan log tidak berjalan sampai cron dipasang.</span>
      </p>
      <button type="button" @click="tab = 'scheduler'" class="px-3.5 py-1.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black cursor-pointer">Lihat cara mengaktifkan</button>
    </div>

    <!-- Tab -->
    <div class="flex items-center gap-2 border-b border-slate-200 dark:border-slate-800 pb-2" role="tablist">
      <button type="button" role="tab" :aria-selected="tab === 'logs'" @click="tab = 'logs'" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition cursor-pointer" :class="tab === 'logs' ? 'bg-amber-400 text-slate-950 font-black shadow-lg shadow-amber-400/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'"><History class="w-4 h-4" /> Riwayat aktivitas</button>
      <button type="button" role="tab" :aria-selected="tab === 'health'" @click="tab = 'health'" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition cursor-pointer" :class="tab === 'health' ? 'bg-amber-400 text-slate-950 font-black shadow-lg shadow-amber-400/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'">
        <Server class="w-4 h-4" /> Kesehatan server &amp; kesiapan hosting
        <span v-if="readyCount.fail || readyCount.warn" class="px-1.5 py-0.5 rounded-full text-[10px] font-black" :class="readyCount.fail ? 'bg-rose-500 text-white' : 'bg-amber-400 text-slate-950'">{{ readyCount.fail || readyCount.warn }}</span>
      </button>
      <button type="button" role="tab" :aria-selected="tab === 'scheduler'" @click="tab = 'scheduler'" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition cursor-pointer" :class="tab === 'scheduler' ? 'bg-amber-400 text-slate-950 font-black shadow-lg shadow-amber-400/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'">
        <Timer class="w-4 h-4" /> Penjadwal
        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black" :class="scheduler.active ? 'bg-emerald-500 text-white' : 'bg-rose-500 text-white'">{{ scheduler.active ? 'AKTIF' : 'MATI' }}</span>
      </button>
    </div>

    <!-- ================= RIWAYAT ================= -->
    <div v-if="tab === 'logs'" class="space-y-3">
      <div :class="card + ' p-4 space-y-3'">
        <div class="flex flex-wrap items-center gap-2">
          <div class="relative flex-1 min-w-[14rem]">
            <Search class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
            <input v-model="f.q" @keyup.enter="apply" type="text" maxlength="100" placeholder="Cari pengguna, aksi, alamat IP, atau rincian (Enter)" :class="inputCls + ' w-full pl-9'" aria-label="Cari log" />
          </div>
          <select v-model="f.hasil" :class="inputCls" aria-label="Hasil">
            <option value="">Semua hasil</option>
            <option value="berhasil">Berhasil</option>
            <option value="gagal">Gagal / ditolak</option>
          </select>
          <select v-model="f.aksi" :class="inputCls" aria-label="Jenis aksi">
            <option value="">Semua aksi ({{ facets.actions.length }})</option>
            <option v-for="a in facets.actions" :key="a.code" :value="a.code">{{ a.label }} ({{ fmt(a.count) }})</option>
          </select>
          <select v-model="f.user" :class="inputCls" aria-label="Pengguna">
            <option value="">Semua pengguna</option>
            <option v-for="u in facets.users" :key="u.id" :value="u.id">{{ u.name }} ({{ fmt(u.count) }})</option>
          </select>
          <div class="flex items-center gap-1.5">
            <input v-model="f.from" type="date" :class="inputCls" aria-label="Dari tanggal" />
            <span class="text-xs text-slate-400">s.d.</span>
            <input v-model="f.to" type="date" :class="inputCls" aria-label="Sampai tanggal" />
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <button type="button" @click="pickCategory('')" class="px-3 py-1.5 rounded-full text-[11px] font-black border transition cursor-pointer" :class="f.kategori === '' ? 'bg-amber-400 text-slate-950 border-amber-300' : 'border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:border-amber-400'">Semua</button>
          <button v-for="c in facets.categories" :key="c.key" type="button" @click="pickCategory(c.key)" class="px-3 py-1.5 rounded-full text-[11px] font-black border transition cursor-pointer" :class="f.kategori === c.key ? 'bg-amber-400 text-slate-950 border-amber-300' : 'border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:border-amber-400'">
            {{ c.label }} <span class="font-mono opacity-70">{{ fmt(c.count) }}</span>
          </button>
          <button v-if="hasFilters" type="button" @click="reset" class="ml-auto flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[11px] font-bold text-slate-500 hover:text-slate-900 dark:hover:text-white cursor-pointer"><RotateCcw class="w-3 h-3" /> Atur ulang filter</button>
        </div>
      </div>

      <div :class="[card, 'overflow-hidden transition-opacity', loading ? 'opacity-60' : '']" :aria-busy="loading">
        <div class="px-5 py-3 border-b border-slate-200 dark:border-slate-800 flex flex-wrap items-center justify-between gap-2 text-xs">
          <p class="text-slate-600 dark:text-slate-300"><strong>{{ fmt(logs.total) }}</strong> log{{ hasFilters ? ' cocok dengan filter' : '' }}<span v-if="logs.total"> &bull; menampilkan {{ fmt(logs.from) }}&ndash;{{ fmt(logs.to) }}</span></p>
          <label class="flex items-center gap-2 text-slate-500">Baris
            <select v-model.number="f.per_page" :class="inputCls + ' py-1'"><option :value="20">20</option><option :value="50">50</option><option :value="100">100</option></select>
          </label>
        </div>

        <div v-if="logs.data.length === 0" class="p-10 text-center space-y-2">
          <History class="w-10 h-10 mx-auto text-slate-300 dark:text-slate-600" />
          <p class="text-sm font-bold text-slate-900 dark:text-white">{{ hasFilters ? 'Tidak ada log yang cocok' : 'Belum ada log tercatat' }}</p>
          <p class="text-xs text-slate-500 max-w-sm mx-auto">{{ hasFilters ? 'Ubah kata kunci, rentang tanggal, atau atur ulang filter.' : 'Aktivitas pengguna dan sistem akan muncul di sini secara otomatis.' }}</p>
          <button v-if="hasFilters" type="button" @click="reset" class="px-4 py-2 rounded-xl bg-amber-400 text-slate-950 text-xs font-black cursor-pointer">Atur ulang filter</button>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="bg-slate-50 dark:bg-slate-900/60 text-[11px] font-black uppercase text-slate-500 border-b border-slate-200 dark:border-slate-800">
                <th class="w-8 px-3 py-3"></th>
                <th class="px-3 py-3">Waktu</th>
                <th class="px-3 py-3">Pengguna</th>
                <th class="px-3 py-3">Operasi</th>
                <th class="px-3 py-3">Hasil</th>
                <th class="px-3 py-3">Wilayah</th>
                <th class="px-3 py-3">IP</th>
                <th class="px-3 py-3">Rincian</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
              <template v-for="row in logs.data" :key="row.id">
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/40 transition cursor-pointer" @click="toggleRow(row.id)">
                  <td class="px-3 py-3 text-slate-400"><component :is="expanded.has(row.id) ? ChevronDown : ChevronRight" class="w-3.5 h-3.5" /></td>
                  <td class="px-3 py-3 font-mono text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ row.time }}</td>
                  <td class="px-3 py-3"><p class="font-bold text-slate-900 dark:text-white">{{ row.user }}</p><p v-if="row.role" class="text-[10px] text-amber-600 dark:text-amber-400 font-semibold">{{ row.role }}</p></td>
                  <td class="px-3 py-3"><p class="font-bold text-slate-800 dark:text-slate-100">{{ row.label }}</p><p class="font-mono text-[10px] text-slate-400">{{ row.action }}</p></td>
                  <td class="px-3 py-3"><span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold border" :class="row.failed ? 'bg-rose-100 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-300 dark:border-rose-500/30' : 'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-300 dark:border-emerald-500/30'">{{ row.failed ? 'GAGAL' : 'BERHASIL' }}</span></td>
                  <td class="px-3 py-3 text-slate-600 dark:text-slate-300 font-semibold">{{ row.polda }}</td>
                  <td class="px-3 py-3 font-mono text-slate-500 dark:text-slate-400">{{ row.ip }}</td>
                  <td class="px-3 py-3 text-slate-600 dark:text-slate-300 max-w-xs truncate" :title="row.detail">{{ row.detail || '—' }}</td>
                </tr>
                <tr v-if="expanded.has(row.id)" class="bg-slate-50 dark:bg-slate-900/40">
                  <td></td>
                  <td colspan="7" class="px-3 py-3 space-y-2">
                    <p v-if="row.subject" class="text-[11px] text-slate-500"><strong>Objek:</strong> {{ row.subject }}</p>
                    <p v-if="row.user_agent" class="text-[11px] text-slate-500 break-all"><strong>Perangkat:</strong> {{ row.user_agent }}</p>
                    <pre v-if="row.meta" class="text-[11px] font-mono rounded-xl bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 p-3 overflow-x-auto text-slate-700 dark:text-slate-300">{{ JSON.stringify(row.meta, null, 2) }}</pre>
                    <p v-else class="text-[11px] text-slate-400">Tidak ada rincian tambahan.</p>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <div v-if="logs.links && logs.links.length > 3" class="p-4 border-t border-slate-200 dark:border-slate-800 flex flex-wrap items-center justify-center gap-1.5">
          <Link
            v-for="(link, i) in logs.links"
            :key="i"
            :href="link.url || '#'"
            preserve-scroll
            class="px-3 py-1.5 rounded-xl text-xs font-bold transition border"
            :class="link.active ? 'bg-amber-400 text-slate-950 border-amber-400 font-black' : link.url ? 'border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:border-amber-400' : 'border-transparent text-slate-300 dark:text-slate-600 pointer-events-none'"
          >{{ pageLabel(link.label) }}</Link>
        </div>
      </div>
    </div>

    <!-- ================= KESEHATAN & KESIAPAN HOSTING ================= -->
    <div v-else-if="tab === 'health'" class="space-y-5">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div :class="card + ' p-5 space-y-3'">
          <div class="flex items-center gap-3"><div class="w-10 h-10 rounded-xl bg-emerald-500/15 text-emerald-500 flex items-center justify-center"><Cpu class="w-5 h-5" /></div><div><p class="text-[10px] font-black uppercase text-slate-500">Mesin aplikasi</p><p class="text-sm font-extrabold text-slate-900 dark:text-white">PHP {{ serverHealth.php_version }}</p></div></div>
          <dl class="text-xs space-y-1 pt-2 border-t border-slate-200 dark:border-slate-800">
            <div class="flex justify-between"><dt class="text-slate-500">Laravel</dt><dd class="font-mono text-slate-900 dark:text-white">{{ serverHealth.laravel_ver }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Lingkungan</dt><dd class="font-bold uppercase text-slate-900 dark:text-white">{{ serverHealth.environment }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Memori puncak</dt><dd class="font-mono text-slate-900 dark:text-white">{{ serverHealth.memory_mb }} MB / {{ serverHealth.memory_limit }}</dd></div>
          </dl>
        </div>

        <div :class="card + ' p-5 space-y-3'">
          <div class="flex items-center gap-3"><div class="w-10 h-10 rounded-xl bg-amber-400/15 text-amber-500 flex items-center justify-center"><Database class="w-5 h-5" /></div><div><p class="text-[10px] font-black uppercase text-slate-500">Basis data</p><p class="text-sm font-extrabold text-slate-900 dark:text-white">{{ serverHealth.database.driver }} {{ serverHealth.database.version || '' }}</p></div></div>
          <dl class="text-xs space-y-1 pt-2 border-t border-slate-200 dark:border-slate-800">
            <div class="flex justify-between"><dt class="text-slate-500">Koneksi</dt><dd class="font-bold" :class="serverHealth.database.ok ? 'text-emerald-500' : 'text-rose-500'">{{ serverHealth.database.ok ? 'Terhubung' : (serverHealth.database.error || 'Gagal') }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Latensi kueri (rata-rata 3x)</dt><dd class="font-mono text-slate-900 dark:text-white">{{ serverHealth.database.latency_ms ?? '—' }} ms</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Sesi / cache / antrean</dt><dd class="font-mono text-slate-900 dark:text-white">{{ serverHealth.drivers.session }} / {{ serverHealth.drivers.cache }} / {{ serverHealth.drivers.queue }}</dd></div>
          </dl>
        </div>

        <div :class="card + ' p-5 space-y-3'">
          <div class="flex items-center gap-3"><div class="w-10 h-10 rounded-xl bg-sky-500/15 text-sky-500 flex items-center justify-center"><HardDrive class="w-5 h-5" /></div><div><p class="text-[10px] font-black uppercase text-slate-500">Penyimpanan</p><p class="text-sm font-extrabold text-slate-900 dark:text-white">{{ serverHealth.disk_free_gb ?? '—' }} GB bebas<span v-if="serverHealth.disk_total_gb"> dari {{ serverHealth.disk_total_gb }} GB</span></p></div></div>
          <dl class="text-xs space-y-1 pt-2 border-t border-slate-200 dark:border-slate-800">
            <div class="flex justify-between"><dt class="text-slate-500">Waktu server</dt><dd class="font-mono text-slate-900 dark:text-white">{{ serverHealth.server_time }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Antrean tugas</dt><dd class="font-mono text-slate-900 dark:text-white">{{ serverHealth.queue.pending ?? '—' }} menunggu &bull; <span :class="serverHealth.queue.failed > 0 ? 'text-rose-500 font-bold' : ''">{{ serverHealth.queue.failed ?? '—' }} gagal</span></dd></div>
          </dl>
        </div>
      </div>

      <div :class="card + ' p-5 md:p-6 space-y-4'">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 dark:border-slate-800 pb-3">
          <div class="flex items-center gap-2"><ShieldAlert class="w-4 h-4 text-amber-500" /><h2 class="text-sm font-black text-slate-900 dark:text-white">Kesiapan hosting &amp; keamanan</h2></div>
          <p class="text-[11px] text-slate-500"><span class="text-emerald-500 font-bold">{{ readyCount.ok }} siap</span> &bull; <span class="text-amber-500 font-bold">{{ readyCount.warn }} perlu perhatian</span> &bull; <span class="text-rose-500 font-bold">{{ readyCount.fail }} kritis</span> &mdash; diperiksa langsung saat halaman dibuka</p>
        </div>
        <ul class="grid grid-cols-1 lg:grid-cols-2 gap-3">
          <li v-for="c in readiness" :key="c.key" class="rounded-2xl border p-3.5 flex gap-3" :class="c.status === 'fail' ? 'border-rose-300 dark:border-rose-500/40 bg-rose-50/60 dark:bg-rose-500/5' : c.status === 'warn' ? 'border-amber-300 dark:border-amber-400/40 bg-amber-50/60 dark:bg-amber-400/5' : 'border-slate-200 dark:border-slate-800'">
            <component :is="statusIcon(c.status)" class="w-5 h-5 shrink-0 mt-0.5" :class="statusTone(c.status)" />
            <div class="min-w-0">
              <p class="text-sm font-bold text-slate-900 dark:text-white">{{ c.title }}</p>
              <p class="text-xs text-slate-600 dark:text-slate-300">{{ c.detail }}</p>
              <p v-if="c.hint" class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">&rarr; {{ c.hint }}</p>
            </div>
          </li>
        </ul>
      </div>
    </div>

    <!-- ================= PENJADWAL (CRON) ================= -->
    <div v-else class="space-y-5">
      <div :class="card + ' p-5 md:p-6 flex flex-wrap items-center justify-between gap-4'">
        <div class="flex items-center gap-3">
          <div class="w-12 h-12 rounded-2xl flex items-center justify-center" :class="scheduler.active ? 'bg-emerald-500/15 text-emerald-500' : 'bg-rose-500/15 text-rose-500'"><Timer class="w-6 h-6" /></div>
          <div>
            <p class="text-[11px] font-black uppercase text-slate-500">Status penjadwal</p>
            <p class="text-xl font-black" :class="scheduler.active ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">{{ scheduler.active ? 'Aktif' : 'Tidak aktif' }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ scheduler.last_tick ? 'Detak terakhir ' + scheduler.last_tick_ago : 'Belum pernah ada detak: cron belum dipasang atau belum berjalan.' }}</p>
          </div>
        </div>
        <p class="text-[11px] text-slate-500 dark:text-slate-400 max-w-md">Cron memanggil <code class="font-mono">schedule:run</code> tiap menit. Setiap pemanggilan menulis "detak"; bila detak berhenti lebih dari 3 menit, penjadwal dianggap tidak aktif.</p>
      </div>

      <div :class="card + ' overflow-hidden'">
        <div class="px-5 py-3 border-b border-slate-200 dark:border-slate-800"><h2 class="text-sm font-black text-slate-900 dark:text-white">Tugas otomatis</h2></div>
        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
          <li v-for="t in scheduler.tasks" :key="t.key" class="px-5 py-3.5 flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
              <p class="text-sm font-bold text-slate-900 dark:text-white">{{ t.label }}</p>
              <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ t.why }} &bull; {{ t.cadence }}</p>
              <p class="font-mono text-[10px] text-slate-400 mt-0.5">{{ t.command }}</p>
            </div>
            <div class="text-right shrink-0">
              <span class="px-2.5 py-1 rounded-full text-[10px] font-black border" :class="TASK_STATUS[t.status].cls">{{ TASK_STATUS[t.status].label }}</span>
              <p class="text-[11px] text-slate-500 mt-1">{{ t.last_run_ago ? 'terakhir ' + t.last_run_ago : 'belum tercatat berjalan' }}</p>
            </div>
          </li>
        </ul>
      </div>

      <div :class="card + ' p-5 md:p-6 space-y-4'">
        <div>
          <h2 class="text-sm font-black text-slate-900 dark:text-white">{{ scheduler.active ? 'Pemasangan cron (sudah aktif)' : 'Cara mengaktifkan penjadwal' }}</h2>
          <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ scheduler.active ? 'Simpan perintah ini untuk server baru atau saat pindah hosting.' : 'Pasang satu tugas yang memanggil penjadwal tiap menit. Perintah di bawah sudah memakai lokasi proyek ini.' }}</p>
        </div>
        <div v-for="g in orderedSuggestions" :key="g.key" class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4 space-y-2" :class="g.key === scheduler.os ? 'border-amber-300 dark:border-amber-400/40' : ''">
          <div class="flex items-center justify-between gap-2">
            <p class="text-xs font-black text-slate-900 dark:text-white">{{ g.title }} <span v-if="g.key === scheduler.os" class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] bg-amber-400 text-slate-950">sistem ini</span></p>
            <button type="button" @click="copyCmd(g.key, g.command)" class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-[11px] font-bold border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:border-amber-400 cursor-pointer">
              <component :is="copied === g.key ? Check : Copy" class="w-3.5 h-3.5" /> {{ copied === g.key ? 'Tersalin' : 'Salin' }}
            </button>
          </div>
          <pre class="text-[11px] font-mono rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 p-3 overflow-x-auto whitespace-pre-wrap break-all text-slate-700 dark:text-slate-300">{{ g.command }}</pre>
          <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ g.note }}</p>
        </div>
        <p class="text-[11px] text-slate-500 dark:text-slate-400">Setelah dipasang, buka ulang halaman ini dalam 1&ndash;2 menit: status berubah menjadi <strong>Aktif</strong> dan setiap tugas menampilkan waktu terakhir berjalan.</p>
      </div>
    </div>

    <AuditPurgeDialog :open="showPurge" scope="all" :filters="f" @close="showPurge = false" />
  </EnterpriseLayout>
</template>
