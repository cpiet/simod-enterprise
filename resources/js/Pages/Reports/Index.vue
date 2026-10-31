<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
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
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  reportsKpi: Object,
  satwilBreakdown: Array,
  categoryBreakdown: Array,
  fieldReports: Object,
  filters: Object,
  metrics: Object,
});

const selectedCategory = ref(props.filters?.category || 'all');
const selectedStatus = ref(props.filters?.status || 'all');
const copySuccess = ref(false);

const applyFilters = () => {
  router.get('/reports', {
    polda: props.selectedCode,
    category: selectedCategory.value,
    status: selectedStatus.value,
  }, {
    preserveState: true,
    preserveScroll: true,
  });
};

const copyExecutiveSummary = () => {
  const poldaName = props.selectedCode === 'ALL' ? 'Mabes Polri' : (props.selectedPolda?.name || 'Polda Sulawesi Barat');
  const total = props.reportsKpi?.total_reports || 0;
  const sla = props.reportsKpi?.sla_rate || 100;
  const inProg = props.reportsKpi?.in_progress || 0;
  const pending = props.reportsKpi?.pending_verify || 0;

  const now = new Date();
  const dateStr = now.toLocaleDateString('id-ID', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  });

  const summary = `*LAPORAN REKAPITULASI & ANEV HUMAS POLRI*\nWilayah: ${poldaName}\nTanggal: ${dateStr}\n\n1. Total Laporan & Isu Terpantau: ${total} Kasus\n2. Tingkat Penyelesaian Sesuai SLA: ${sla}%\n3. Laporan Sedang Investigasi/Klarifikasi: ${inProg} Kasus\n4. Antrean Menunggu Verifikasi: ${pending} Laporan\n5. Indeks Kondusifitas Wilayah: SANGAT KONDUSIF\n\nDemikian dilaporkan kepada Pimpinan sebagai bahan pertimbangan kebijakan komando. Terima kasih.`;
  navigator.clipboard.writeText(summary);
  copySuccess.value = true;
  setTimeout(() => (copySuccess.value = false), 2500);
};

const triggerPrint = () => {
  window.print();
};

const formatDateWita = (dateStr) => {
  if (!dateStr) return '-';
  try {
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }) + ' WITA';
  } catch (e) {
    return dateStr;
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
    <!-- Header Page -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-400 text-slate-950">
            Analisis & Evaluasi
          </span>
          <span class="text-xs font-bold text-slate-400">
            {{ selectedCode === 'ALL' ? 'Konsolidasi Nasional' : (selectedPolda?.name || 'Polda Sulawesi Barat') }}
          </span>
        </div>
        <h1 class="text-2xl md:text-3xl font-black text-white tracking-tight mt-1">
          Laporan & Rekapitulasi Anev Humas
        </h1>
        <p class="text-xs md:text-sm text-slate-300">
          Statistik agregasi pemberitaan, rekapitulasi penanganan isu Kamtibmas, dan kinerja kehumasan 6 Satwil Polres.
        </p>
      </div>

      <!-- Action Buttons -->
      <div class="flex flex-wrap items-center gap-2 print:hidden">
        <button
          @click="copyExecutiveSummary"
          class="flex items-center gap-2 px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 border border-slate-300 text-slate-800 dark:bg-slate-900 dark:hover:bg-slate-800 dark:border-slate-700 dark:text-slate-200 text-xs font-bold transition shadow-sm"
        >
          <Copy class="w-3.5 h-3.5 text-amber-500 dark:text-amber-400" />
          <span>{{ copySuccess ? 'Tersalin ke Clipboard!' : 'Salin Format WA Pimpinan' }}</span>
        </button>

        <button
          @click="triggerPrint"
          class="flex items-center gap-2 px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 border border-slate-300 text-slate-800 dark:bg-slate-900 dark:hover:bg-slate-800 dark:border-slate-700 dark:text-slate-200 text-xs font-bold transition shadow-sm"
        >
          <Printer class="w-3.5 h-3.5 text-sky-500 dark:text-sky-400" />
          <span>Cetak Dokumen</span>
        </button>

        <a
          :href="`/reports/export/csv?polda=${selectedCode}`"
          class="flex items-center gap-2 px-3.5 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black shadow-lg shadow-amber-400/20 transition cursor-pointer"
        >
          <Download class="w-3.5 h-3.5" />
          <span>Ekspor CSV Resmi</span>
        </a>
      </div>
    </div>

    <!-- 4 KPI Metrics -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <span class="text-[11px] font-black uppercase tracking-wider text-slate-400">Total Laporan Masuk</span>
        <div class="flex items-baseline justify-between">
          <span class="text-3xl font-black text-white">{{ reportsKpi?.total_reports || 0 }}</span>
          <span class="text-xs font-bold text-emerald-400 flex items-center gap-0.5">
            <TrendingUp class="w-3 h-3" /> Aktif
          </span>
        </div>
        <p class="text-[11px] text-slate-400">6 Satwil Jajaran Sulbar</p>
      </div>

      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <span class="text-[11px] font-black uppercase tracking-wider text-slate-400">Tingkat Penyelesaian (SLA)</span>
        <div class="flex items-baseline justify-between">
          <span class="text-3xl font-black text-emerald-400">{{ reportsKpi?.sla_rate || 100 }}%</span>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300">Target > 80%</span>
        </div>
        <p class="text-[11px] text-slate-400">{{ reportsKpi?.resolved_count || 0 }} Laporan Terselesaikan</p>
      </div>

      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <span class="text-[11px] font-black uppercase tracking-wider text-slate-400">Sedang Ditindaklanjuti</span>
        <div class="flex items-baseline justify-between">
          <span class="text-3xl font-black text-amber-400">{{ reportsKpi?.in_progress || 0 }}</span>
          <span class="text-xs font-bold text-amber-400">Operasional</span>
        </div>
        <p class="text-[11px] text-slate-400">Tahap Investigasi / Klarifikasi</p>
      </div>

      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <span class="text-[11px] font-black uppercase tracking-wider text-slate-400">Antrean Verifikasi</span>
        <div class="flex items-baseline justify-between">
          <span class="text-3xl font-black text-sky-400">{{ reportsKpi?.pending_verify || 0 }}</span>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-500/20 text-sky-300">Menunggu</span>
        </div>
        <p class="text-[11px] text-slate-400">Validasi Data Tim Humas</p>
      </div>
    </div>

    <!-- Executive Briefing Preview Card -->
    <div class="rounded-3xl bg-gradient-to-br from-[#0c162e] to-[#080f20] border border-amber-400/30 p-6 shadow-2xl relative overflow-hidden">
      <div class="flex items-center gap-2 mb-3">
        <Sparkles class="w-4 h-4 text-amber-400" />
        <h2 class="text-sm font-extrabold text-amber-400 uppercase tracking-wider">
          Ringkasan Eksekutif Anev Siap Disposisi Pimpinan
        </h2>
      </div>
      <p class="text-xs sm:text-sm text-slate-200 leading-relaxed max-w-4xl">
        Berdasarkan konsolidasi pelaporan di lingkungan <strong>{{ selectedCode === 'ALL' ? 'Mabes Polri' : (selectedPolda?.name || 'Polda Sulawesi Barat') }}</strong>, penanganan informasi dan klarifikasi isu Kamtibmas berjalan optimal dengan tingkat penyelesaian SLA <strong>{{ reportsKpi?.sla_rate || 100 }}%</strong>. Sebanyak <strong>{{ reportsKpi?.resolved_count || 0 }}</strong> laporan berhasil dirampungkan dan diklarifikasi ke publik. Koordinasi lintas satwil di 6 Polres jajaran terpantau tanggap dan cepat mengantisipasi potensi eskalasi di ruang siber dan lapangan.
      </p>
    </div>

    <!-- 2 Column Layout: Satwil Breakdown & Category Distribution -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Satwil Breakdown (6 Polres) -->
      <div class="lg:col-span-2 rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div>
            <h3 class="text-sm font-black text-white uppercase tracking-wider">
              Kinerja Kehumasan 6 Satwil Polres Jajaran
            </h3>
            <p class="text-xs text-slate-400">Sebaran beban laporan dan kepatuhan tindak lanjut per wilayah hukum.</p>
          </div>
          <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
            6 Satwil Terdata
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
                        :style="{ width: `${satwil.sla_pct}%` }"
                      ></div>
                    </div>
                    <span class="font-mono text-[10px] text-slate-600 dark:text-slate-300">{{ satwil.sla_pct }}%</span>
                  </div>
                </td>
                <td class="py-3 text-right">
                  <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                    {{ satwil.sentiment }}%
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
            <option value="Harkamtibmas">Harkamtibmas</option>
            <option value="Kriminalitas">Kriminalitas</option>
            <option value="Lalu Lintas">Lalu Lintas</option>
            <option value="Pelayanan Publik">Pelayanan Publik</option>
            <option value="Bencana">Bencana</option>
          </select>

          <select
            v-model="selectedStatus"
            @change="applyFilters"
            class="px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-700 text-xs text-white focus:outline-none focus:border-amber-400"
          >
            <option value="all">Semua Status</option>
            <option value="selesai">Selesai</option>
            <option value="investigasi">Investigasi</option>
            <option value="klarifikasi">Klarifikasi</option>
            <option value="monitoring">Monitoring</option>
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
                {{ rep.polres?.name || 'Polda Sulbar' }}
              </td>
              <td class="px-6 py-4">
                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border border-slate-300 dark:border-slate-700">
                  {{ rep.category }}
                </span>
              </td>
              <td class="px-6 py-4">
                <span
                  class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider border"
                  :class="{
                    'bg-emerald-500/20 text-emerald-600 dark:text-emerald-300 border-emerald-500/40': rep.status === 'selesai' || rep.status === 'arsip',
                    'bg-amber-400/20 text-amber-700 dark:text-amber-300 border-amber-400/40': rep.status === 'investigasi' || rep.status === 'klarifikasi',
                    'bg-sky-500/20 text-sky-600 dark:text-sky-300 border-sky-500/40': rep.status === 'monitoring',
                    'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-300 dark:border-slate-700': rep.status === 'draft' || rep.status === 'returned',
                  }"
                >
                  {{ rep.status }}
                </span>
              </td>
              <td class="px-6 py-4 font-mono text-slate-500 dark:text-slate-400 whitespace-nowrap">
                {{ formatDateWita(rep.created_at) }}
              </td>
              <td class="px-6 py-4 text-right print:hidden">
                <Link
                  :href="`/issues/${rep.id}?polda=${selectedCode}`"
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
