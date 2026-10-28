<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
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
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  metrics: Object,
});

const selectedPeriod = ref('monthly');
const copySuccess = ref(false);

const reportsList = ref([
  {
    id: 'ANEV-2026-10-W4',
    title: 'Laporan Anev Mingguan Harkamtibmas & Sentimen Publik Sulbar Minggu IV Oktober 2026',
    date: '28 Okt 2026',
    period: 'Minggu IV (21 - 27 Okt)',
    author: 'Subbid Penmas Bidhumas',
    sentiment: '84.2% Positif',
    status: 'Disetujui Kapolda',
    file_size: '2.4 MB PDF',
  },
  {
    id: 'ANEV-2026-10-M',
    title: 'Rekapitulasi Bulanan Kinerja Kehumasan Satwil Jajaran Polda Sulawesi Barat',
    date: '25 Okt 2026',
    period: 'Bulan Oktober 2026',
    author: 'Tim Analis Siber Humas',
    sentiment: '81.5% Positif',
    status: 'Final / Diarsipkan',
    file_size: '4.8 MB PDF',
  },
  {
    id: 'ANEV-2026-PILKADA-02',
    title: 'Laporan Khusus Pemantauan Ruang Siber & Netralitas Anggota Jelang Pilkada Serentak',
    date: '22 Okt 2026',
    period: 'Tahapan Kampanye',
    author: 'Satgas Humas Operasi Mantap Praja',
    sentiment: '79.8% Positif',
    status: 'Atensi Khusus',
    file_size: '3.1 MB PDF',
  },
  {
    id: 'ANEV-2026-09-M',
    title: 'Laporan Analisis & Evaluasi Publikasi Media Sosial dan Konter Opini September 2026',
    date: '30 Sep 2026',
    period: 'Bulan September 2026',
    author: 'Subbid Penmas Bidhumas',
    sentiment: '86.0% Positif',
    status: 'Selesai',
    file_size: '5.2 MB PDF',
  },
]);

const copyExecutiveSummary = () => {
  const summary = `*LAPORAN EKSEKUTIF ANEV HUMAS POLRI*\nWilayah: ${props.selectedCode === 'ALL' ? 'Mabes Polri' : props.selectedPolda?.name || 'Polda Sulbar'}\nTanggal: 28 Oktober 2026\n\n1. Indeks Sentimen: 84.2% Positif (Kondusif)\n2. Total Pemberitaan Terverifikasi: 142 Konten\n3. Isu Prioritas Tertangani: 100%\n4. Respons Rata-rata Satwil: 14.8 Menit\n\nDemikian dilaporkan kepada Pimpinan. Terima kasih.`;
  navigator.clipboard.writeText(summary);
  copySuccess.value = true;
  setTimeout(() => (copySuccess.value = false), 2500);
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
            {{ selectedCode === 'ALL' ? 'Konsolidasi Nasional' : selectedPolda?.name }}
          </span>
        </div>
        <h1 class="text-2xl md:text-3xl font-black text-white tracking-tight mt-1">
          Laporan & Rekapitulasi Anev Humas
        </h1>
        <p class="text-xs md:text-sm text-slate-300">
          Statistik agregasi pemberitaan, rekapitulasi isu Kamtibmas, dan kinerja kehumasan multi-satwil.
        </p>
      </div>

      <!-- Action Buttons -->
      <div class="flex flex-wrap items-center gap-2">
        <button
          @click="copyExecutiveSummary"
          class="flex items-center gap-2 px-3 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-xs font-bold text-slate-200 transition"
        >
          <Copy class="w-3.5 h-3.5 text-amber-400" />
          <span>{{ copySuccess ? 'Tersalin!' : 'Salin Format WA Pimpinan' }}</span>
        </button>
        <button
          class="flex items-center gap-2 px-3.5 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-amber-400 text-slate-950 text-xs font-extrabold hover:brightness-110 shadow-lg shadow-amber-500/20 transition"
        >
          <Download class="w-3.5 h-3.5" />
          <span>Export Laporan Excel</span>
        </button>
      </div>
    </div>

    <!-- 4 KPI Metrics -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <span class="text-[11px] font-black uppercase tracking-wider text-slate-400">Total Berita Terpantau</span>
        <div class="flex items-baseline justify-between">
          <span class="text-3xl font-black text-white">611</span>
          <span class="text-xs font-bold text-emerald-400 flex items-center gap-0.5">
            <TrendingUp class="w-3 h-3" /> +14%
          </span>
        </div>
        <p class="text-[11px] text-slate-400">14 Portal RSS Aktif Sulbar & Nasional</p>
      </div>

      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <span class="text-[11px] font-black uppercase tracking-wider text-slate-400">Indeks Sentimen Publik</span>
        <div class="flex items-baseline justify-between">
          <span class="text-3xl font-black text-emerald-400">84.2%</span>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300">Positif</span>
        </div>
        <p class="text-[11px] text-slate-400">Kondusifitas Wilayah Tinggi</p>
      </div>

      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <span class="text-[11px] font-black uppercase tracking-wider text-slate-400">Waktu Respons Isu</span>
        <div class="flex items-baseline justify-between">
          <span class="text-3xl font-black text-amber-400">14.8 m</span>
          <span class="text-xs font-bold text-emerald-400">Sesuai SOP</span>
        </div>
        <p class="text-[11px] text-slate-400">Target Maksimal 30 Menit</p>
      </div>

      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <span class="text-[11px] font-black uppercase tracking-wider text-slate-400">Publikasi Terverifikasi</span>
        <div class="flex items-baseline justify-between">
          <span class="text-3xl font-black text-white">{{ metrics?.total_publications || 24 }}</span>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-400/20 text-amber-300">6 Kanal</span>
        </div>
        <p class="text-[11px] text-slate-400">Tribratanews & Media Sosial</p>
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
        Selama sepekan terakhir, tren pemberitaan di wilayah hukum <strong>{{ selectedCode === 'ALL' ? 'Mabes Polri' : selectedPolda?.name || 'Polda Sulawesi Barat' }}</strong> terpantau sangat kondusif. Dari 611 artikel dan 24 rilis pers yang dipantau, isu terkait pengamanan tahapan Pilkada serentak di wilayah pesisir Polresta Mamuju dan Majene mendapatkan sentimen positif 84.2%. Seluruh laporan klarifikasi lapangan terselesaikan dalam kurun waktu rata-rata 14.8 menit.
      </p>
    </div>

    <!-- Anev Reports Document Table -->
    <div class="rounded-3xl bg-[#0b1426] border border-slate-800 shadow-2xl overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <h3 class="text-sm font-black text-white uppercase tracking-wider">Daftar Dokumen Anev & Laporan Berkala</h3>
          <p class="text-xs text-slate-400">Arsip laporan resmi yang siap diunduh dalam format PDF atau disahkan secara digital.</p>
        </div>
        <div class="flex items-center gap-2">
          <button class="px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-700 text-xs font-semibold text-slate-300 hover:text-white">
            Semua Periode
          </button>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="border-b border-slate-800 bg-slate-900/60 text-[11px] font-black uppercase text-slate-400">
              <th class="px-6 py-3">No. Dokumen</th>
              <th class="px-6 py-3">Judul Laporan</th>
              <th class="px-6 py-3">Periode</th>
              <th class="px-6 py-3">Penyusun</th>
              <th class="px-6 py-3">Sentimen</th>
              <th class="px-6 py-3">Status</th>
              <th class="px-6 py-3 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800 text-xs">
            <tr v-for="rep in reportsList" :key="rep.id" class="hover:bg-slate-900/40 transition">
              <td class="px-6 py-4 font-mono font-bold text-amber-400">{{ rep.id }}</td>
              <td class="px-6 py-4 font-bold text-white max-w-sm">{{ rep.title }}</td>
              <td class="px-6 py-4 text-slate-300">{{ rep.period }}</td>
              <td class="px-6 py-4 text-slate-400">{{ rep.author }}</td>
              <td class="px-6 py-4">
                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-500/20 text-emerald-300">
                  {{ rep.sentiment }}
                </span>
              </td>
              <td class="px-6 py-4">
                <span class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase bg-slate-800 text-slate-300 border border-slate-700">
                  {{ rep.status }}
                </span>
              </td>
              <td class="px-6 py-4 text-right">
                <button class="px-3 py-1.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-black text-xs inline-flex items-center gap-1.5 transition">
                  <Download class="w-3.5 h-3.5" />
                  <span>PDF</span>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </EnterpriseLayout>
</template>