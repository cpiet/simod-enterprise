<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { statusMeta, formatWitaDate } from '@/utils/issueFlow';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import {
  FileBarChart2,
  Download,
  Share2,
  Calendar,
  Building2,
  CheckCircle2,
  TrendingUp,
  FileText,
  Copy,
  Printer,
  Clock,
  Sparkles,
  AlertTriangle,
  Flame,
  Filter,
  Check,
  Ellipsis,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  reportsKpi: Object,
  satwilBreakdown: Array,
  categoryBreakdown: Array,
  categoryOptions: { type: Array, default: () => [] },
  fieldReports: Object,
  filters: Object,
  metrics: Object,
});

const selectedCategory = ref(props.filters?.category || 'all');
const selectedStatus = ref(props.filters?.status || 'all');
const copySuccess = ref(false);

const applyFilters = () => {
  router.get('/reports', {
    category: selectedCategory.value,
    status: selectedStatus.value,
  }, {
    preserveState: true,
    preserveScroll: true,
  });
};

const pct = (v) => (v === null || v === undefined ? 'belum ada data' : v + '%');

const copyExecutiveSummary = () => {
  const poldaName = props.selectedCode === 'ALL' ? 'Mabes Polri' : (props.selectedPolda?.name || '-');
  const k = props.reportsKpi || {};
  const dateStr = new Date().toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });

  const summary = '*LAPORAN REKAPITULASI & ANEV HUMAS POLRI*\n'
    + 'Wilayah: ' + poldaName + '\n'
    + 'Tanggal: ' + dateStr + '\n\n'
    + '1. Total laporan & isu terpantau: ' + (k.total_reports || 0) + ' kasus\n'
    + '2. Tingkat penyelesaian: ' + pct(k.sla_rate) + ' (' + (k.resolved_count || 0) + ' selesai/arsip)\n'
    + '3. Sedang investigasi/klarifikasi: ' + (k.in_progress || 0) + ' kasus\n'
    + '4. Antrean menunggu telaah: ' + (k.pending_verify || 0) + ' laporan\n\n'
    + 'Demikian dilaporkan sebagai bahan pertimbangan pimpinan.';
  navigator.clipboard.writeText(summary);
  copySuccess.value = true;
  setTimeout(() => (copySuccess.value = false), 2500);
};

const showMenu = ref(false);
const closeMenu = () => { showMenu.value = false; };
onMounted(() => document.addEventListener('click', closeMenu));
onUnmounted(() => document.removeEventListener('click', closeMenu));

const triggerPrint = () => {
  window.print();
};

const formatDateWita = formatWitaDate;
</script>

<template>
  <EnterpriseLayout
    :all-poldas="allPoldas"
    :selected-polda="selectedPolda"
    :selected-code="selectedCode"
    :metrics="metrics"
  >
    <!-- Header ringkas: 1 aksi utama (CSV) + salin ringkasan + menu -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 print:hidden">
      <div class="min-w-0">
        <p class="text-xs font-bold text-slate-400">{{ selectedCode === 'ALL' ? 'Konsolidasi Nasional' : (selectedPolda?.name || '-') }}</p>
        <h1 class="text-2xl md:text-3xl font-black text-white tracking-tight">Laporan &amp; Anev</h1>
      </div>

      <div class="flex items-center gap-2 shrink-0">
        <a
          href="/reports/export/csv"
          class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black shadow-lg shadow-amber-400/20 transition cursor-pointer"
        >
          <Download class="w-4 h-4" />
          <span>Ekspor CSV</span>
        </a>

        <button
          @click="copyExecutiveSummary"
          class="flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-200 text-xs font-bold transition cursor-pointer"
          title="Salin ringkasan angka untuk laporan WhatsApp pimpinan"
        >
          <Copy class="w-4 h-4 text-amber-400" />
          <span class="hidden sm:inline">{{ copySuccess ? 'Tersalin!' : 'Salin Ringkasan' }}</span>
        </button>

        <div class="relative">
          <button type="button" @click.stop="showMenu = !showMenu" class="p-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-300 cursor-pointer" title="Aksi lainnya">
            <Ellipsis class="w-4 h-4" />
          </button>
          <div v-if="showMenu" @click.stop class="absolute right-0 top-full mt-2 z-30 w-48 rounded-xl bg-[#0b1426] border border-slate-700 shadow-2xl p-1.5">
            <button type="button" @click="triggerPrint(); showMenu = false" class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-bold text-slate-200 hover:bg-slate-800 transition cursor-pointer text-left">
              <Printer class="w-4 h-4 text-sky-400" />
              <span>Cetak halaman</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Strip angka (nyata dari basis data) -->
    <div class="rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg grid grid-cols-2 lg:grid-cols-4 divide-x divide-y lg:divide-y-0 divide-slate-800 overflow-hidden">
      <div class="p-4">
        <p class="text-[11px] font-bold text-slate-400">Total laporan</p>
        <p class="text-2xl font-black text-white mt-1">{{ reportsKpi?.total_reports || 0 }}</p>
        <p class="text-[11px] text-slate-500">seluruh riwayat wilayah ini</p>
      </div>
      <div class="p-4">
        <p class="text-[11px] font-bold text-slate-400">Tingkat penyelesaian</p>
        <p class="text-2xl font-black text-emerald-400 mt-1">{{ reportsKpi?.sla_rate === null || reportsKpi?.sla_rate === undefined ? '—' : reportsKpi.sla_rate + '%' }}</p>
        <p class="text-[11px] text-slate-500">{{ reportsKpi?.resolved_count || 0 }} selesai / arsip</p>
      </div>
      <div class="p-4">
        <p class="text-[11px] font-bold text-slate-400">Sedang ditangani</p>
        <p class="text-2xl font-black text-amber-400 mt-1">{{ reportsKpi?.in_progress || 0 }}</p>
        <p class="text-[11px] text-slate-500">investigasi &amp; klarifikasi</p>
      </div>
      <div class="p-4">
        <p class="text-[11px] font-bold text-slate-400">Menunggu telaah</p>
        <p class="text-2xl font-black text-sky-400 mt-1">{{ reportsKpi?.pending_verify || 0 }}</p>
        <p class="text-[11px] text-slate-500">belum diverifikasi</p>
      </div>
    </div>

    <!-- 2 Column Layout: Satwil Breakdown & Category Distribution -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Satwil Breakdown (6 Polres) -->
      <div class="lg:col-span-2 rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div>
            <h3 class="text-sm font-black text-white uppercase tracking-wider">
              Kinerja per Satwil / Polres
            </h3>
            <p class="text-xs text-slate-400">Sebaran beban laporan dan kepatuhan tindak lanjut per wilayah hukum.</p>
          </div>
          <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-slate-800 text-slate-300 border border-slate-700">
            {{ (satwilBreakdown || []).length }} satwil
          </span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="border-b border-slate-800 text-[11px] font-black uppercase text-slate-400">
                <th class="py-2.5">Satwil / Polres</th>
                <th class="py-2.5 text-center">Total Isu</th>
                <th class="py-2.5 text-center">Selesai</th>
                <th class="py-2.5 text-center">Tingkat SLA</th>
                <th class="py-2.5 text-right">Sentimen Positif</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
              <tr v-for="satwil in satwilBreakdown" :key="satwil.id" class="hover:bg-slate-900/40 transition">
                <td class="py-3 font-bold text-white flex items-center gap-2">
                  <Building2 class="w-3.5 h-3.5 text-amber-400" />
                  <span>{{ satwil.name }}</span>
                </td>
                <td class="py-3 text-center font-mono font-bold text-slate-200">{{ satwil.total }}</td>
                <td class="py-3 text-center font-mono text-emerald-400 font-bold">{{ satwil.resolved }}</td>
                <td class="py-3 text-center">
                  <div class="inline-flex items-center gap-1.5">
                    <div class="w-16 h-2 rounded-full bg-slate-200 dark:bg-slate-800 overflow-hidden">
                      <div
                        class="h-full bg-gradient-to-r from-amber-400 to-emerald-400 rounded-full"
                        :style="{ width: (satwil.sla_pct || 0) + '%' }"
                      ></div>
                    </div>
                    <span class="font-mono text-[10px] text-slate-600 dark:text-slate-300">{{ satwil.sla_pct === null ? '—' : satwil.sla_pct + '%' }}</span>
                  </div>
                </td>
                <td class="py-3 text-right">
                  <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                    {{ satwil.sentiment === null ? '—' : satwil.sentiment + '%' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Category Breakdown -->
      <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-2xl space-y-4">
        <div class="border-b border-slate-800 pb-3">
          <h3 class="text-sm font-black text-white uppercase tracking-wider">
            Sebaran Kategori Isu
          </h3>
          <p class="text-xs text-slate-400">Proporsi bidang pelaporan Kamtibmas.</p>
        </div>

        <div class="space-y-3.5">
          <div v-for="cat in categoryBreakdown" :key="cat.name" class="space-y-1">
            <div class="flex items-center justify-between text-xs">
              <span class="font-bold text-slate-200">{{ cat.name }}</span>
              <span class="font-mono text-amber-400 font-bold">{{ cat.count }} ({{ cat.pct }}%)</span>
            </div>
            <div class="w-full h-2 rounded-full bg-slate-200 dark:bg-slate-900 border border-slate-300 dark:border-slate-800 overflow-hidden">
              <div
                class="h-full bg-gradient-to-r from-amber-500 to-amber-300 rounded-full"
                :style="{ width: `${cat.pct}%` }"
              ></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Paginated Field Reports Table -->
    <div class="rounded-3xl bg-[#0b1426] border border-slate-800 shadow-2xl overflow-hidden space-y-0">
      <div class="px-6 py-4 border-b border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <h3 class="text-sm font-black text-white uppercase tracking-wider">
            Rincian Berkas Laporan Lapangan & Isu Terdaftar
          </h3>
          <p class="text-xs text-slate-400">Data mentah penanganan isu untuk audit dan verifikasi berjenjang.</p>
        </div>

        <!-- Filter Controls -->
        <div class="flex items-center gap-2 print:hidden">
          <select
            v-model="selectedCategory"
            @change="applyFilters"
            class="px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-700 text-xs text-white focus:outline-none focus:border-amber-400"
          >
            <option value="all">Semua Kategori</option>
            <option v-for="c in categoryOptions" :key="c" :value="c">{{ c }}</option>
          </select>

          <select
            v-model="selectedStatus"
            @change="applyFilters"
            class="px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-700 text-xs text-white focus:outline-none focus:border-amber-400"
          >
            <option value="all">Semua Status</option>
            <option value="monitoring">Perlu Telaah</option>
            <option value="investigasi">Dalam Penanganan</option>
            <option value="klarifikasi">Klarifikasi</option>
            <option value="selesai">Selesai</option>
            <option value="arsip">Arsip</option>
          </select>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="border-b border-slate-800 bg-slate-900/60 text-[11px] font-black uppercase text-slate-400">
              <th class="px-6 py-3.5">Kode Laporan</th>
              <th class="px-6 py-3.5">Judul Isu / Insiden</th>
              <th class="px-6 py-3.5">Satwil / Polres</th>
              <th class="px-6 py-3.5">Kategori</th>
              <th class="px-6 py-3.5">Status</th>
              <th class="px-6 py-3.5">Waktu Lapor</th>
              <th class="px-6 py-3.5 text-right print:hidden">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <tr
              v-for="rep in fieldReports?.data || []"
              :key="rep.id"
              class="hover:bg-slate-900/40 transition"
            >
              <td class="px-6 py-4 font-mono font-bold text-amber-400">
                {{ rep.report_code || `SIM-${rep.id}` }}
              </td>
              <td class="px-6 py-4">
                <p class="font-extrabold text-white max-w-sm line-clamp-2">{{ rep.title }}</p>
                <span v-if="rep.location_text" class="text-[10px] text-slate-400">
                  {{ rep.location_text }}
                </span>
              </td>
              <td class="px-6 py-4 font-semibold text-slate-300">
                {{ rep.polres?.name || selectedPolda?.name || '-' }}
              </td>
              <td class="px-6 py-4">
                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border border-slate-300 dark:border-slate-700">
                  {{ rep.category }}
                </span>
              </td>
              <td class="px-6 py-4">
                <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider" :class="statusMeta(rep.status).cls">
                  {{ statusMeta(rep.status).label }}
                </span>
              </td>
              <td class="px-6 py-4 font-mono text-slate-500 dark:text-slate-400 whitespace-nowrap">
                {{ formatDateWita(rep.created_at) }}
              </td>
              <td class="px-6 py-4 text-right print:hidden">
                <Link
                  :href="'/issues/' + rep.id"
                  class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 border border-slate-300 text-amber-700 dark:bg-slate-900 dark:hover:bg-slate-800 dark:border-slate-700 dark:text-amber-400 font-bold text-xs inline-flex items-center gap-1 transition shadow-sm"
                >
                  <span>Detail &rarr;</span>
                </Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Links -->
      <div v-if="fieldReports?.links && fieldReports.links.length > 3" class="p-4 border-t border-slate-800 flex items-center justify-center gap-1.5 print:hidden">
        <Link
          v-for="(link, i) in fieldReports.links"
          :key="i"
          :href="link.url || '#'"
          class="px-3 py-1.5 rounded-xl text-xs font-bold transition border"
          :class="link.active
            ? 'bg-amber-400 text-slate-950 border-amber-400 font-black'
            : link.url
              ? 'bg-slate-100 dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-800 hover:text-slate-950 dark:hover:text-white'
              : 'bg-slate-100 dark:bg-slate-950 text-slate-400 dark:text-slate-600 border-slate-200 dark:border-slate-900 pointer-events-none'"
          v-html="link.label"
        />
      </div>
    </div>
  </EnterpriseLayout>
</template>
