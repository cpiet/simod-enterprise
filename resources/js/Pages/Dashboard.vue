<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import {
  Shield,
  Radio,
  Building2,
  Flame,
  Newspaper,
  Clock,
  Layers,
  Users,
  CheckCircle2,
  AlertTriangle,
  ChevronRight,
  TrendingUp,
  MapPin,
  ExternalLink,
  PlusCircle,
  Download,
  FileBarChart2,
  Sparkles,
  ArrowRight,
  Activity,
  Check,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  kpi: Object,
  polresStatuses: Array,
  topIssues: Array,
  sentiment7Days: Array,
  liveRadarArticles: Array,
  publicIndex: Object,
  metrics: Object,
});

const selectedPolresModal = ref(null);
</script>

<template>
  <EnterpriseLayout
    :all-poldas="allPoldas"
    :selected-polda="selectedPolda"
    :selected-code="selectedCode"
    :metrics="metrics"
  >
    <!-- ========================================================================= -->
    <!-- 1. HERO BANNER: COMMAND CENTER PRESISI                                    -->
    <!-- ========================================================================= -->
    <div class="rounded-3xl bg-gradient-to-r from-[#0d182e] via-[#0b1426] to-[#070d1d] border border-slate-800 p-6 md:p-8 shadow-2xl relative overflow-hidden">
      <div class="absolute top-0 right-0 -mt-10 -mr-10 w-72 h-72 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>

      <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 relative z-10">
        <div class="space-y-3">
          <div class="flex flex-wrap items-center gap-2">
            <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-rose-500/20 text-rose-400 border border-rose-500/40">
              Klasifikasi: Rahasia Terbatas / Command Center
            </span>
            <span class="flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300">
              <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
              SITUSI KONDUSIF & TERKENDALI
            </span>
          </div>

          <h1 class="text-2xl md:text-4xl font-black text-white tracking-tight">
            Dashboard Media Intelligence & Kamtibmas
          </h1>
          <p class="text-xs md:text-sm text-slate-300 max-w-2xl leading-relaxed">
            Monitoring terpadu pemberitaan, media sosial, laporan lapangan, dan analisis sentimen untuk mendukung pengambilan keputusan cepat dan tepat bagi pimpinan <strong>{{ selectedCode === 'ALL' ? 'Mabes Polri' : selectedPolda?.name }}</strong>.
          </p>

          <!-- 3 Quick Action Buttons -->
          <div class="flex flex-wrap items-center gap-2.5 pt-2">
            <Link
              :href="`/issues/create?polda=${selectedCode}`"
              class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-400 hover:from-amber-400 hover:to-amber-300 text-slate-950 text-xs font-black transition shadow-lg shadow-amber-500/20"
            >
              <PlusCircle class="w-4 h-4" />
              <span>+ Buat Laporan / Disposisi Cepat</span>
            </Link>

            <Link
              :href="`/reports?polda=${selectedCode}`"
              class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-200 text-xs font-bold transition"
            >
              <FileBarChart2 class="w-4 h-4 text-amber-400" />
              <span>Unduh Rekap Eksekutif</span>
            </Link>

            <Link
              :href="`/verification-queue?polda=${selectedCode}`"
              class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-200 text-xs font-bold transition"
            >
              <CheckCircle2 class="w-4 h-4 text-emerald-400" />
              <span>Buka Antrean Verifikasi ({{ metrics?.pending_verify || 4 }}) &rarr;</span>
            </Link>
          </div>
        </div>

        <!-- Right: Public Sentiment Gauge 78% -->
        <div class="p-6 rounded-3xl bg-[#091122]/90 border border-amber-400/30 shadow-2xl flex flex-col items-center text-center space-y-2 shrink-0">
          <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Indeks Citra Publik</span>
          <div class="flex items-baseline gap-2">
            <span class="text-5xl font-black text-amber-400 tracking-tight">{{ publicIndex?.score || 78 }}%</span>
            <span class="text-xs font-bold text-emerald-400 flex items-center">
              <TrendingUp class="w-3.5 h-3.5 mr-0.5" /> {{ publicIndex?.change || '+6%' }}
            </span>
          </div>
          <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
            Kategori: {{ publicIndex?.status || 'Positif' }}
          </span>
          <p class="text-[10px] text-slate-400">Periode 7 Hari Terakhir</p>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 2. 4 OPERATIONAL KPI CARDS                                                -->
    <!-- ========================================================================= -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
          <span>Total Berita Pantauan</span>
          <Newspaper class="w-4 h-4 text-sky-400" />
        </div>
        <div class="flex items-baseline justify-between">
          <p class="text-3xl font-black text-white">{{ kpi?.total_articles || 150 }}</p>
          <span class="text-xs font-bold text-emerald-400 flex items-center gap-0.5">
            <TrendingUp class="w-3 h-3" /> +14%
          </span>
        </div>
        <p class="text-[11px] text-slate-400">14 Portal RSS Sulbar & Nasional</p>
      </div>

      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
          <span>Isu Prioritas Atensi</span>
          <Flame class="w-4 h-4 text-rose-400" />
        </div>
        <div class="flex items-baseline justify-between">
          <p class="text-3xl font-black text-rose-400">{{ kpi?.priority_issues || 3 }}</p>
          <span class="px-2 py-0.5 rounded text-[10px] font-black bg-rose-500/20 text-rose-300">Siaga</span>
        </div>
        <p class="text-[11px] text-slate-400">Perlu Atensi Pimpinan</p>
      </div>

      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
          <span>Laporan Lapangan Polres</span>
          <MapPin class="w-4 h-4 text-amber-400" />
        </div>
        <div class="flex items-baseline justify-between">
          <p class="text-3xl font-black text-amber-400">{{ kpi?.field_reports || 18 }}</p>
          <span class="text-xs font-bold text-amber-300">{{ metrics?.pending_verify || 4 }} pending</span>
        </div>
        <p class="text-[11px] text-slate-400">Dumas & Insiden Kamtibmas</p>
      </div>

      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
          <span>Tindak Lanjut Selesai</span>
          <CheckCircle2 class="w-4 h-4 text-emerald-400" />
        </div>
        <div class="flex items-baseline justify-between">
          <p class="text-3xl font-black text-emerald-400">{{ kpi?.tasks_resolved || 14 }}</p>
          <span class="text-xs font-bold text-emerald-300">75% Selesai</span>
        </div>
        <p class="text-[11px] text-slate-400">Disposisi & Klarifikasi</p>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 3. MIDDLE GRID: PETA 6 POLRES SULBAR & SENTIMEN AI                        -->
    <!-- ========================================================================= -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Col 1 & 2: Peta Sebaran Isu & Sentimen Kamtibmas 6 Polres -->
      <div class="lg:col-span-2 rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-2xl space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-800/80 pb-4">
          <div class="flex items-center gap-2">
            <MapPin class="w-5 h-5 text-amber-400" />
            <div>
              <h2 class="text-base font-black text-white uppercase tracking-wider">
                Peta Sebaran Isu & Sentimen Kamtibmas 6 Polres
              </h2>
              <p class="text-xs text-slate-400">Wilayah Hukum Polda Sulawesi Barat</p>
            </div>
          </div>
          <span class="text-xs font-mono text-emerald-400 flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
            6 Satwil Terpantau
          </span>
        </div>

        <!-- 6 Satwil Cards Grid / Interactive Map Representation -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
          <div
            v-for="p in polresStatuses"
            :key="p.id"
            @click="selectedPolresModal = p"
            class="p-4 rounded-2xl bg-slate-900 border transition cursor-pointer hover:border-amber-400/60 group relative overflow-hidden"
            :class="p.status === 'ATENSI' ? 'border-rose-500/40 bg-rose-500/5' : 'border-slate-800'"
          >
            <div class="flex items-center justify-between mb-2">
              <span class="font-bold text-white text-xs group-hover:text-amber-400 transition">{{ p.name }}</span>
              <span
                class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-wider"
                :class="{
                  'bg-rose-500/20 text-rose-300 border border-rose-500/30': p.status === 'ATENSI',
                  'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30': p.status === 'KONDUSIF',
                  'bg-amber-400/20 text-amber-300 border border-amber-400/30': p.status === 'WASPADA',
                }"
              >
                {{ p.status }}
              </span>
            </div>
            <p class="text-[11px] text-slate-300 line-clamp-2 leading-relaxed mb-3">
              {{ p.summary }}
            </p>
            <div class="flex items-center justify-between text-[10px] text-slate-400 pt-2 border-t border-slate-800">
              <span>Skor Opini: <strong class="text-white">{{ p.score }}</strong></span>
              <span class="text-amber-400 font-bold">{{ p.issues_count }} Isu Aktif</span>
            </div>
          </div>
        </div>

        <!-- Bottom Map Legend -->
        <div class="pt-2 flex flex-wrap items-center justify-between text-xs text-slate-400 border-t border-slate-800/60 gap-2">
          <div class="flex items-center gap-4">
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span> Kondusif</span>
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span> Waspada</span>
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span> Atensi Pimpinan</span>
          </div>
          <Link :href="`/media-intelligence?polda=${selectedCode}`" class="text-amber-400 font-bold hover:underline inline-flex items-center gap-1 text-xs">
            <span>Buka Media Intelligence Radar</span>
            <ArrowRight class="w-3.5 h-3.5" />
          </Link>
        </div>
      </div>

      <!-- Col 3: Sentimen 7 Hari & Top 5 Isu -->
      <div class="space-y-6">
        <!-- 7-Day Trend Chart Preview -->
        <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
          <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <h3 class="text-xs font-black text-white uppercase tracking-wider">Tren Sentimen Publik (7 Hari)</h3>
            <span class="text-[10px] font-bold text-emerald-400">84% Rata-rata Positif</span>
          </div>

          <!-- Bar Visualizer -->
          <div class="space-y-2 text-xs">
            <div v-for="t in sentiment7Days" :key="t.day" class="space-y-1">
              <div class="flex items-center justify-between text-[11px]">
                <span class="font-bold text-slate-300">{{ t.day }}</span>
                <span class="font-mono text-emerald-400">{{ t.positif }}% Positif</span>
              </div>
              <div class="h-2 rounded-full bg-slate-900 overflow-hidden flex">
                <div class="bg-emerald-400" :style="{ width: t.positif + '%' }"></div>
                <div class="bg-amber-400" :style="{ width: t.netral + '%' }"></div>
                <div class="bg-rose-500" :style="{ width: t.negatif + '%' }"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Top 5 Isu Kamtibmas -->
        <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
          <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <h3 class="text-xs font-black text-white uppercase tracking-wider">Top 5 Isu Kamtibmas Sulbar</h3>
            <Link :href="`/issues?polda=${selectedCode}`" class="text-[10px] text-amber-400 font-bold hover:underline">
              Semua Isu &rarr;
            </Link>
          </div>

          <div class="space-y-3">
            <div
              v-for="(iss, idx) in topIssues"
              :key="iss.id || idx"
              class="p-3 rounded-2xl bg-slate-900 border border-slate-800/80 space-y-1 text-xs hover:border-slate-700 transition"
            >
              <div class="flex items-center justify-between gap-1">
                <span class="font-black text-amber-400 text-[11px]">#{{ idx + 1 }}</span>
                <span class="px-2 py-0.2 rounded text-[9px] font-black uppercase bg-slate-800 text-slate-300">
                  {{ iss.category }}
                </span>
                <span class="font-mono text-[10px] text-emerald-400 font-bold">Virality {{ iss.virality_score }}%</span>
              </div>
              <p class="font-bold text-white text-xs line-clamp-2 leading-snug">{{ iss.title }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 4. BOTTOM GRID: MATRIKS OPINI 6 POLRES & RADAR LIVE STREAM                -->
    <!-- ========================================================================= -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Left: Matriks Kondusifitas Opini 6 Polres -->
      <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <h3 class="text-sm font-black text-white uppercase tracking-wider">Matriks Kondusifitas Opini 6 Polres Jajaran</h3>
          <span class="text-xs text-slate-400">Polda Sulbar</span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="border-b border-slate-800 text-[10px] font-black uppercase text-slate-400">
                <th class="py-2.5">Satwil</th>
                <th class="py-2.5">Skor Opini</th>
                <th class="py-2.5">Status</th>
                <th class="py-2.5 text-right">Isu Aktif</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
              <tr v-for="p in polresStatuses" :key="p.id" class="hover:bg-slate-900/40 transition">
                <td class="py-3 font-bold text-white">{{ p.name }}</td>
                <td class="py-3 font-mono font-bold text-emerald-400">{{ p.score }}</td>
                <td class="py-3">
                  <span
                    class="px-2 py-0.5 rounded text-[9px] font-black uppercase"
                    :class="{
                      'bg-rose-500/20 text-rose-300 border border-rose-500/30': p.status === 'ATENSI',
                      'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30': p.status === 'KONDUSIF',
                      'bg-amber-400/20 text-amber-300 border border-amber-400/30': p.status === 'WASPADA',
                    }"
                  >
                    {{ p.status }}
                  </span>
                </td>
                <td class="py-3 text-right font-bold text-amber-400">{{ p.issues_count }} Isu</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Right: Radar Sindikasi Media Live (Latest 6 Articles) -->
      <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <Radio class="w-4 h-4 text-amber-400 animate-pulse" />
            <h3 class="text-sm font-black text-white uppercase tracking-wider">Radar Sindikasi Media Live</h3>
          </div>
          <Link :href="`/media-intelligence?polda=${selectedCode}`" class="text-xs text-amber-400 font-bold hover:underline">
            Buka Radar Selengkapnya &rarr;
          </Link>
        </div>

        <div class="space-y-3">
          <div
            v-for="art in liveRadarArticles"
            :key="art.id"
            class="p-3.5 rounded-2xl bg-slate-900 border border-slate-800 hover:border-slate-700 transition space-y-1.5 text-xs"
          >
            <div class="flex items-center justify-between">
              <span class="px-2 py-0.2 rounded text-[9px] font-black uppercase bg-slate-800 text-amber-400 border border-slate-700">
                {{ art.source_name }}
              </span>
              <span class="text-[10px] text-slate-400 font-mono">
                {{ art.pub_date ? new Date(art.pub_date).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) + ' WITA' : 'Baru saja' }}
              </span>
            </div>
            <a :href="art.url" target="_blank" class="block font-bold text-white hover:text-amber-400 transition leading-snug line-clamp-1">
              {{ art.title }}
            </a>
            <p class="text-[11px] text-slate-400 line-clamp-2 leading-relaxed">
              {{ art.summary }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </EnterpriseLayout>
</template>