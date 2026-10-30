<script setup>
import { ref, computed, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import SecurityMaintenanceTabs from '@/Components/SecurityMaintenanceTabs.vue';
import {
  Eraser,
  Bookmark,
  Database,
  AlertTriangle,
  Trash2,
  Info,
  CheckCircle2,
  Calendar,
  Building2,
  History,
  RotateCcw,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  metrics: Object,
  perPolda: Array,
  totals: Object,
  days: Number,
  target: String,
  preview: Number,
  retentionDays: Number,
  cleanupLogs: Array,
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const toastMessage = ref('');

const days = ref(props.days ?? 90);
const target = ref(props.target ?? 'ALL');
const isDeleting = ref(false);

// Watch for prop changes from server/inertia navigation
watch(() => props.days, (newVal) => {
  if (newVal !== undefined) days.value = newVal;
});
watch(() => props.target, (newVal) => {
  if (newVal !== undefined) target.value = newVal;
});

const dayOptions = [
  { v: 7, label: '7 Hari', desc: '> 7 hari' },
  { v: 30, label: '30 Hari', desc: '> 30 hari' },
  { v: 90, label: '90 Hari (Standar)', desc: '> 90 hari' },
  { v: 180, label: '180 Hari', desc: '> 180 hari' },
  { v: 365, label: '1 Tahun', desc: '> 1 tahun' },
  { v: 0, label: 'Semua Berita', desc: 'Semua non-bookmark' },
];

const refreshPreview = () => {
  router.get(
    '/maintenance/news-cleanup',
    {
      polda: props.selectedCode,
      days: days.value,
      target: target.value,
    },
    {
      preserveScroll: true,
      preserveState: true,
      only: ['perPolda', 'totals', 'preview', 'days', 'target', 'cleanupLogs'],
    }
  );
};

const selectDayOption = (v) => {
  days.value = v;
  refreshPreview();
};

const selectTargetPolda = (code) => {
  target.value = code;
  refreshPreview();
};

const targetLabel = () => {
  if (target.value === 'ALL') return 'SEMUA POLDA (KONSOLIDASI NASIONAL)';
  const p = props.perPolda?.find((item) => item.code === target.value);
  return p ? p.name : target.value;
};

const purge = () => {
  if (!props.preview || props.preview <= 0) return;
  const rule = days.value === 0 ? 'seluruh artikel non-bookmark' : `artikel non-bookmark yang berumur lebih dari ${days.value} hari`;

  if (!confirm(`KONFIRMASI PEMBERSIHAN DATA:\n\nApakah Anda yakin ingin menghapus ${props.preview} ${rule} milik ${targetLabel()}?\n\nCATATAN:\n• Seluruh artikel yang telah di-BOOKMARK (bintang) dijamin AMAN dan TIDAK akan terhapus.\n• Laporan Isu di SIMOD yang menggunakan tautan berita tetap aman.\n• Tindakan ini akan dicatat di Audit Log sistem.`)) {
    return;
  }

  isDeleting.value = true;
  router.post(
    '/maintenance/news-cleanup',
    {
      days: days.value,
      target: target.value,
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        isDeleting.value = false;
        toastMessage.value = '✓ Pembersihan berita berhasil dijalankan dan dicatat ke Audit Log.';
        setTimeout(() => (toastMessage.value = ''), 4500);
      },
      onError: () => {
        isDeleting.value = false;
      },
      onFinish: () => {
        isDeleting.value = false;
      },
    }
  );
};
</script>

<template>
  <EnterpriseLayout
    :all-poldas="allPoldas"
    :selected-polda="selectedPolda"
    :selected-code="selectedCode"
    :metrics="metrics"
  >
    <!-- Floating Success Toast -->
    <transition
      enter-active-class="transform ease-out duration-300 transition"
      enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
      enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="toastMessage || flashSuccess"
        class="fixed bottom-6 right-6 z-50 max-w-md bg-emerald-950 border border-emerald-500 text-emerald-100 p-4 rounded-2xl shadow-2xl flex items-center gap-3 backdrop-blur-xl"
      >
        <CheckCircle2 class="w-6 h-6 text-emerald-400 shrink-0" />
        <p class="text-xs font-bold leading-relaxed">{{ toastMessage || flashSuccess }}</p>
      </div>
    </transition>

    <SecurityMaintenanceTabs :selected-code="selectedCode" />

    <!-- Header Section -->
    <div class="rounded-3xl bg-gradient-to-r from-[#0d182e] via-[#0b1426] to-[#070d1d] border border-slate-800 p-6 shadow-xl space-y-2">
      <div class="flex items-center gap-2">
        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-rose-500/20 text-rose-300 border border-rose-500/40">
          Pemeliharaan Basis Data
        </span>
        <span class="text-xs font-bold text-slate-400">
          {{ selectedCode === 'ALL' ? 'Mabes Polri' : selectedPolda?.name }}
        </span>
      </div>

      <h1 class="text-xl md:text-2xl font-black text-white flex items-center gap-2 tracking-tight">
        <Eraser class="w-6 h-6 text-amber-400" />
        <span>Pembersihan Berita &amp; Manajemen Retensi</span>
      </h1>

      <p class="text-xs md:text-sm text-slate-300 max-w-3xl leading-relaxed">
        Hapus artikel arsip agregasi RSS lama untuk menjaga performa query dan efisiensi penyimpanan database. Artikel yang ditandai <strong>Bookmark (Bintang)</strong> selalu diproteksi dan tidak pernah terhapus.
      </p>

      <div class="pt-2 flex items-center gap-2 text-xs text-slate-400 flex-wrap">
        <span class="flex items-center gap-1.5 px-3 py-1 rounded-xl bg-slate-900 border border-slate-800">
          <Info class="w-3.5 h-3.5 text-sky-400" />
          <span>Retensi Otomatis Cron:</span>
          <strong class="text-amber-400">{{ retentionDays > 0 ? `${retentionDays} Hari` : 'Nonaktif' }}</strong>
        </span>
        <span class="text-[11px] text-slate-400">
          (Jadwal harian pukul 02:00 via <code>php artisan media:prune</code>)
        </span>
      </div>
    </div>

    <!-- 3 Summary Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
          <span>Total Berita Tersimpan</span>
          <Database class="w-4 h-4 text-sky-400" />
        </div>
        <p class="text-3xl font-black text-white">{{ totals?.total || 0 }}</p>
        <p class="text-[11px] text-slate-400">Akumulasi seluruh Polda terdaftar</p>
      </div>

      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
          <span>Bookmark Terproteksi</span>
          <Bookmark class="w-4 h-4 text-amber-400" />
        </div>
        <p class="text-3xl font-black text-amber-300">{{ totals?.bookmarked || 0 }}</p>
        <p class="text-[11px] text-slate-400">Kebal dari proses pembersihan data</p>
      </div>

      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
          <span>Bisa Dibersihkan (&gt; {{ days === 0 ? 'Semua' : `${days} hr` }})</span>
          <Eraser class="w-4 h-4 text-rose-400" />
        </div>
        <p class="text-3xl font-black text-rose-300">{{ totals?.deletable || 0 }}</p>
        <p class="text-[11px] text-slate-400">Artikel non-bookmark siap dieksekusi</p>
      </div>
    </div>

    <!-- Interactive Execution Control Panel -->
    <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-5">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800 pb-4">
        <div>
          <h2 class="text-base font-black text-white uppercase tracking-wider flex items-center gap-2">
            <span>Kontrol Eksekusi Pembersihan</span>
          </h2>
          <p class="text-xs text-slate-400 mt-0.5">
            Tentukan target wilayah Polda dan rentang waktu artikel yang akan dibersihkan.
          </p>
        </div>

        <!-- Quick Reset to 90 Days -->
        <button
          type="button"
          @click="selectDayOption(90)"
          class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-300 text-xs font-bold transition shrink-0"
        >
          <RotateCcw class="w-3.5 h-3.5 text-amber-400" />
          <span>Reset ke 90 Hari</span>
        </button>
      </div>

      <!-- Quick Day Filter Pills -->
      <div class="space-y-2">
        <label class="block text-xs font-bold text-slate-300">Pilih Rentang Umur Berita:</label>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="opt in dayOptions"
            :key="opt.v"
            type="button"
            @click="selectDayOption(opt.v)"
            class="px-3.5 py-2 rounded-xl text-xs font-bold border transition cursor-pointer"
            :class="[
              days === opt.v
                ? 'bg-amber-400 text-slate-950 border-amber-300 shadow-md shadow-amber-400/20 font-black'
                : 'bg-slate-900 text-slate-300 border-slate-700 hover:border-slate-500 hover:text-white'
            ]"
          >
            {{ opt.label }}
          </button>
        </div>
      </div>

      <!-- Target Selection & Purge Action Form -->
      <div class="p-5 rounded-2xl bg-[#070d19] border border-slate-800 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div class="flex flex-wrap items-end gap-3 flex-1">
          <div class="min-w-[220px]">
            <label class="block font-bold text-slate-300 text-xs mb-1">Target Satker / Polda:</label>
            <select
              v-model="target"
              @change="refreshPreview"
              class="w-full px-3.5 py-2.5 rounded-xl bg-[#0b1426] border border-slate-700 text-white font-bold text-xs focus:outline-none focus:border-amber-400 cursor-pointer"
            >
              <option value="ALL">★ SEMUA POLDA (Konsolidasi Nasional)</option>
              <option
                v-for="p in perPolda"
                :key="p.code"
                :value="p.code"
              >
                {{ p.name }} ({{ p.total }} berita)
              </option>
            </select>
          </div>

          <!-- Preview Metric Box -->
          <div class="px-5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 space-y-0.5">
            <span class="text-[10px] font-black uppercase text-slate-400 block">Kandidat Penghapusan:</span>
            <div class="text-sm font-black flex items-center gap-1.5">
              <span class="text-amber-400 text-lg">{{ preview }}</span>
              <span class="text-slate-300 text-xs">berita non-bookmark</span>
            </div>
          </div>
        </div>

        <!-- Action Button -->
        <div class="shrink-0">
          <button
            type="button"
            @click="purge"
            :disabled="!preview || preview <= 0 || isDeleting"
            class="w-full sm:w-auto flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-black text-xs transition shadow-lg disabled:opacity-40 disabled:cursor-not-allowed"
            :class="[
              preview > 0
                ? 'bg-rose-500 hover:bg-rose-600 text-white shadow-rose-500/25 cursor-pointer ring-1 ring-rose-400'
                : 'bg-slate-800 text-slate-400 border border-slate-700'
            ]"
          >
            <Trash2 class="w-4 h-4" />
            <span>
              {{ isDeleting ? 'Sedang Membersihkan...' : (preview > 0 ? `Bersihkan ${preview} Berita Sekarang` : 'Tidak Ada Berita Memenuhi Syarat') }}
            </span>
          </button>
        </div>
      </div>

      <!-- Zero-match guidance notice -->
      <div
        v-if="preview === 0"
        class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 flex items-start gap-3 text-xs text-amber-200"
      >
        <Info class="w-4 h-4 text-amber-400 shrink-0 mt-0.5" />
        <div class="space-y-1">
          <p class="font-bold">Tidak ada artikel yang berumur lebih dari {{ days }} hari untuk {{ targetLabel() }}.</p>
          <p class="text-slate-300 text-[11px] leading-relaxed">
            Artikel yang ada saat ini masih berada dalam batas retensi aman. Jika Anda ingin mengosongkan seluruh artikel yang tidak di-bookmark untuk pengujian, klik tombol pill <strong>"Semua Berita"</strong> di atas.
          </p>
        </div>
      </div>
    </div>

    <!-- Breakdown Table per Polda -->
    <div class="rounded-3xl bg-[#0b1426] border border-slate-800 overflow-hidden shadow-xl space-y-0">
      <div class="p-5 bg-gradient-to-r from-slate-900 via-[#0b1426] to-[#080f20] border-b border-slate-800 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <Database class="w-4 h-4 text-sky-400" />
          <h3 class="text-sm font-black text-white uppercase tracking-wider">
            Distribusi &amp; Status Berita per Polda
          </h3>
        </div>
        <span class="text-xs text-slate-400">Klik baris untuk memilih target Polda</span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-xs">
          <thead>
            <tr class="text-left text-slate-400 border-b border-slate-800/80 bg-slate-900/40">
              <th class="px-5 py-3 font-bold">Wilayah / Satuan Polda</th>
              <th class="px-5 py-3 font-bold text-right">Total Berita</th>
              <th class="px-5 py-3 font-bold text-right">
                <Bookmark class="w-3 h-3 inline text-amber-400 mr-1" />
                Bookmark (Aman)
              </th>
              <th class="px-5 py-3 font-bold text-right">Bisa Dibersihkan (> {{ days === 0 ? 'Semua' : `${days} hr` }})</th>
              <th class="px-5 py-3 font-bold text-center">Aksi Cepat</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800/60">
            <!-- Row: Konsolidasi Semua Polda -->
            <tr
              @click="selectTargetPolda('ALL')"
              class="cursor-pointer transition bg-slate-900/60 font-bold"
              :class="target === 'ALL' ? 'ring-2 ring-inset ring-amber-400 bg-amber-400/5' : 'hover:bg-slate-800/50'"
            >
              <td class="px-5 py-3 text-amber-300 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full" :class="target === 'ALL' ? 'bg-amber-400 animate-ping' : 'bg-slate-600'"></span>
                <span>★ SEMUA POLDA (Konsolidasi Nasional)</span>
              </td>
              <td class="px-5 py-3 text-right text-white font-mono font-bold">{{ totals?.total || 0 }}</td>
              <td class="px-5 py-3 text-right text-amber-300 font-mono">{{ totals?.bookmarked || 0 }}</td>
              <td class="px-5 py-3 text-right font-mono font-bold" :class="totals?.deletable > 0 ? 'text-rose-400' : 'text-slate-500'">
                {{ totals?.deletable || 0 }}
              </td>
              <td class="px-5 py-3 text-center">
                <span
                  class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase"
                  :class="target === 'ALL' ? 'bg-amber-400 text-slate-950' : 'bg-slate-800 text-slate-400'"
                >
                  {{ target === 'ALL' ? 'Dipilih' : 'Pilih' }}
                </span>
              </td>
            </tr>

            <!-- Individual Polda Rows -->
            <tr
              v-for="p in perPolda"
              :key="p.code"
              @click="selectTargetPolda(p.code)"
              class="cursor-pointer transition"
              :class="target === p.code ? 'ring-2 ring-inset ring-amber-400 bg-amber-400/5' : 'hover:bg-slate-900/40'"
            >
              <td class="px-5 py-3 font-semibold text-white flex items-center gap-2">
                <Building2 class="w-3.5 h-3.5 text-slate-400" />
                <span>{{ p.name }}</span>
                <span class="text-[10px] text-slate-400 font-mono">({{ p.code }})</span>
              </td>
              <td class="px-5 py-3 text-right font-mono text-slate-200">{{ p.total }}</td>
              <td class="px-5 py-3 text-right font-mono text-amber-300">{{ p.bookmarked }}</td>
              <td class="px-5 py-3 text-right font-mono font-bold" :class="p.deletable > 0 ? 'text-rose-400' : 'text-slate-500'">
                {{ p.deletable }}
              </td>
              <td class="px-5 py-3 text-center">
                <span
                  class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase"
                  :class="target === p.code ? 'bg-amber-400 text-slate-950' : 'bg-slate-800 text-slate-400'"
                >
                  {{ target === p.code ? 'Dipilih' : 'Pilih' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Riwayat Pembersihan Audit Logs Table -->
    <div class="rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl overflow-hidden space-y-0">
      <div class="p-5 bg-gradient-to-r from-slate-900 via-[#0b1426] to-[#080f20] border-b border-slate-800 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <History class="w-4 h-4 text-amber-400" />
          <h3 class="text-sm font-black text-white uppercase tracking-wider">
            Riwayat Pembersihan Berita (Audit Trail Log)
          </h3>
        </div>
        <span class="text-xs text-slate-400 font-mono">Tersimpan di Database</span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-xs">
          <thead>
            <tr class="text-left text-slate-400 border-b border-slate-800/80 bg-slate-900/40">
              <th class="px-5 py-3 font-bold">Waktu Eksekusi</th>
              <th class="px-5 py-3 font-bold">Operator / Pelaksana</th>
              <th class="px-5 py-3 font-bold">Target Wilayah</th>
              <th class="px-5 py-3 font-bold">Kriteria Umur</th>
              <th class="px-5 py-3 font-bold text-right">Jumlah Dihapus</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800/60">
            <tr
              v-for="log in cleanupLogs"
              :key="log.id"
              class="hover:bg-slate-900/40 transition"
            >
              <td class="px-5 py-3 text-slate-300 font-mono">{{ log.created_at }} ({{ log.time_ago }})</td>
              <td class="px-5 py-3 text-white font-semibold">{{ log.user }}</td>
              <td class="px-5 py-3 text-amber-300 font-bold font-mono">{{ log.target }}</td>
              <td class="px-5 py-3 text-slate-300">
                {{ log.days === 0 ? 'Semua non-bookmark' : `> ${log.days} hari` }}
              </td>
              <td class="px-5 py-3 text-right font-mono font-black text-rose-400">
                {{ log.deleted }} berita
              </td>
            </tr>
            <tr v-if="!cleanupLogs || cleanupLogs.length === 0">
              <td colspan="5" class="px-5 py-6 text-center text-slate-500 italic">
                Belum ada rekaman riwayat pembersihan. Setelah pembersihan dieksekusi, log akan tercatat di sini.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </EnterpriseLayout>
</template>
