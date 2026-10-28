<script setup>
import { ref, computed } from 'vue';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import {
  Shield,
  Building2,
  AlertTriangle,
  Flame,
  Radio,
  Newspaper,
  CheckCircle2,
  ExternalLink,
  Bookmark,
  TrendingUp,
  MapPin,
  Sparkles,
  Info,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  earlyWarnings: Array,
  mediaFeeds: Array,
  sampleArticles: Array,
  metrics: Object,
});

// Bookmark state stored in localStorage per device
const bookmarkedIds = ref(JSON.parse(localStorage.getItem('simod_ent_bookmarks') || '[]'));

const toggleBookmark = (id) => {
  if (bookmarkedIds.value.includes(id)) {
    bookmarkedIds.value = bookmarkedIds.value.filter((item) => item !== id);
  } else {
    bookmarkedIds.value.push(id);
  }
  localStorage.setItem('simod_ent_bookmarks', JSON.stringify(bookmarkedIds.value));
};

const selectedCategory = ref('all');

const filteredArticles = computed(() => {
  if (selectedCategory.value === 'bookmark') {
    return props.sampleArticles.filter((a) => bookmarkedIds.value.includes(a.id));
  }
  if (selectedCategory.value === 'all') {
    return props.sampleArticles;
  }
  return props.sampleArticles.filter((a) => a.category === selectedCategory.value);
});
</script>

<template>
  <EnterpriseLayout
    :all-poldas="allPoldas"
    :selected-polda="selectedPolda"
    :selected-code="selectedCode"
    :metrics="metrics"
  >
    <!-- Hero Banner with Polda Info -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-[#0d182e] via-[#0b1426] to-[#070d1d] border border-slate-800 p-6 md:p-8 shadow-2xl">
      <div class="absolute top-0 right-0 -mt-12 -mr-12 w-64 h-64 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>

      <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 relative z-10">
        <div class="space-y-2">
          <div class="flex items-center gap-2">
            <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-rose-500/20 text-rose-400 border border-rose-500/40">
              Klasifikasi: Rahasia Terbatas / Command Center
            </span>
            <span class="flex items-center gap-1 text-[11px] font-bold text-emerald-400">
              <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
              Sistem Aktif
            </span>
          </div>
          <h1 class="text-2xl md:text-3xl font-extrabold text-white tracking-tight">
            {{ selectedCode === 'ALL' ? 'KONSOLIDASI NASIONAL MABES POLRI' : selectedPolda?.name }}
          </h1>
          <p class="text-xs md:text-sm text-slate-300 max-w-2xl leading-relaxed">
            Pusat Komando Intelijen Media, Sistem Peringatan Dini (EWS), dan Orkestrasi Narasi Publik untuk jajaran satuan wilayah.
          </p>
        </div>

        <!-- Leader Card -->
        <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-800 flex items-center gap-4 shrink-0 shadow-lg">
          <div class="w-12 h-12 rounded-xl bg-amber-400/20 border border-amber-400/40 flex items-center justify-center text-amber-400 font-extrabold text-base">
            POL
          </div>
          <div>
            <p class="text-[10px] uppercase font-bold text-slate-400">Kapolda</p>
            <p class="text-xs font-bold text-white">{{ selectedPolda?.kapolda_name || 'Pejabat Utama Mabes Polri' }}</p>
            <p class="text-[10px] text-amber-400 font-medium">Kabid Humas: {{ selectedPolda?.kabid_humas_name || 'Divhumas Polri' }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- KPI Metrics Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
          <span>Polda Terdaftar</span>
          <Building2 class="w-4 h-4 text-amber-400" />
        </div>
        <p class="text-2xl md:text-3xl font-black text-white">{{ metrics.total_poldas }}</p>
        <p class="text-[11px] text-slate-400">Multi-tenant siap ekspansi</p>
      </div>

      <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
          <span>Polres Jajaran</span>
          <MapPin class="w-4 h-4 text-sky-400" />
        </div>
        <p class="text-2xl md:text-3xl font-black text-white">
          {{ selectedPolda ? selectedPolda.polres?.length : metrics.total_polres }}
        </p>
        <p class="text-[11px] text-slate-400">{{ selectedPolda ? 'Satwil di ' + selectedPolda.name : 'Seluruh Indonesia' }}</p>
      </div>

      <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
          <span>EWS Siaga Aktif</span>
          <Flame class="w-4 h-4 text-rose-400" />
        </div>
        <div class="flex items-baseline gap-2">
          <p class="text-2xl md:text-3xl font-black text-amber-400">{{ metrics.active_ews }}</p>
          <span class="text-xs font-bold text-rose-400">Siaga Kuning</span>
        </div>
        <p class="text-[11px] text-slate-400">Prioritas mitigasi risiko</p>
      </div>

      <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
          <span>Sentimen Positif</span>
          <TrendingUp class="w-4 h-4 text-emerald-400" />
        </div>
        <p class="text-2xl md:text-3xl font-black text-emerald-400">76.8%</p>
        <p class="text-[11px] text-slate-400">Pemantauan 24 jam terakhir</p>
      </div>
    </div>

    <!-- Section 1: Early Warning System (EWS) -->
    <div class="space-y-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <div class="w-3 h-3 rounded-full bg-amber-400 animate-pulse"></div>
          <h2 class="text-lg md:text-xl font-bold text-white tracking-tight">
            Early Warning System (EWS) & Matriks Tindakan Taktis
          </h2>
        </div>
        <span class="text-xs font-bold text-slate-400">
          Protokol Resmi Divhumas Polri
        </span>
      </div>

      <!-- EWS Cards Loop -->
      <div v-if="earlyWarnings && earlyWarnings.length > 0" class="space-y-4">
        <div
          v-for="ews in earlyWarnings"
          :key="ews.id"
          class="rounded-3xl p-6 border shadow-2xl space-y-4 transition-all duration-300"
          :class="ews.level === 'red' ? 'bg-gradient-to-br from-rose-950/70 to-[#070d1d] border-rose-500/70' : 'bg-gradient-to-br from-amber-950/70 to-[#070d1d] border-amber-500/70'"
        >
          <!-- Header Card -->
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 border-b border-slate-800/80 pb-4">
            <div class="flex items-center gap-3">
              <span
                class="px-3 py-1 rounded-xl text-xs font-black uppercase tracking-wider"
                :class="ews.level === 'red' ? 'bg-rose-500 text-white' : 'bg-amber-400 text-slate-950 font-black'"
              >
                {{ ews.level === 'red' ? 'SIAGA MERAH' : 'SIAGA KUNING' }}
              </span>
              <h3 class="text-base md:text-lg font-bold text-white">{{ ews.title }}</h3>
            </div>
            <span class="px-3 py-1 rounded-xl bg-slate-900/90 border border-slate-700 text-xs font-bold text-sky-400 self-start">
              PIC: {{ ews.pic_units }}
            </span>
          </div>

          <!-- Facts & Reputation Risk -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="p-4 rounded-2xl bg-[#0b1426] border border-slate-800 space-y-1">
              <p class="text-xs font-black text-sky-400 flex items-center gap-1.5">
                <Info class="w-4 h-4" /> Fakta & Kondisi Riil:
              </p>
              <p class="text-xs text-slate-200 leading-relaxed font-medium">{{ ews.facts }}</p>
            </div>
            <div class="p-4 rounded-2xl bg-[#0b1426] border border-slate-800 space-y-1">
              <p class="text-xs font-black text-rose-400 flex items-center gap-1.5">
                <AlertTriangle class="w-4 h-4" /> Risiko Reputasi:
              </p>
              <p class="text-xs text-slate-200 leading-relaxed font-medium">{{ ews.reputation_risk }}</p>
            </div>
          </div>

          <!-- 3-Phase Timeline Response Protocol -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3 pt-2">
            <div class="p-4 rounded-2xl bg-[#0b1426] border border-slate-800 space-y-1">
              <p class="text-[11px] font-black text-sky-400 uppercase tracking-wider flex items-center gap-1">
                ⏱️ 0-2 Jam:
              </p>
              <p class="text-xs text-slate-200 leading-relaxed">{{ ews.protocol_0_2h }}</p>
            </div>
            <div class="p-4 rounded-2xl bg-[#0b1426] border border-slate-800 space-y-1">
              <p class="text-[11px] font-black text-indigo-400 uppercase tracking-wider flex items-center gap-1">
                ⏱️ 2-6 Jam:
              </p>
              <p class="text-xs text-slate-200 leading-relaxed">{{ ews.protocol_2_6h }}</p>
            </div>
            <div class="p-4 rounded-2xl bg-[#0b1426] border border-slate-800 space-y-1">
              <p class="text-[11px] font-black text-emerald-400 uppercase tracking-wider flex items-center gap-1">
                ⏱️ 6-24 Jam:
              </p>
              <p class="text-xs text-slate-200 leading-relaxed">{{ ews.protocol_6_24h }}</p>
            </div>
          </div>
        </div>
      </div>
      <div v-else class="p-8 rounded-2xl bg-[#0b1426] border border-slate-800 text-center text-slate-400 text-xs">
        Tidak ada peringatan dini aktif pada wilayah hukum ini. Seluruh jajaran terpantau aman terkendali.
      </div>
    </div>

    <!-- Section 2: Intelijen Media & RSS Feed Stream -->
    <div class="space-y-4">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-2">
          <Newspaper class="w-5 h-5 text-amber-400" />
          <h2 class="text-lg md:text-xl font-bold text-white tracking-tight">
            Pemantauan Intelijen Media & Pemberitaan Terkini
          </h2>
        </div>

        <!-- Category & Bookmark Filter -->
        <div class="flex items-center gap-2">
          <select
            v-model="selectedCategory"
            class="px-3 py-1.5 rounded-xl bg-[#0b1426] border border-slate-700 text-xs font-bold text-slate-200 outline-none focus:border-amber-400"
          >
            <option value="all">Semua Kategori</option>
            <option value="bookmark">⭐ Tersimpan / Bookmark ({{ bookmarkedIds.length }})</option>
            <option value="Harkamtibmas">Harkamtibmas</option>
            <option value="Lalu Lintas">Lalu Lintas</option>
            <option value="Ekonomi & Logistik">Ekonomi & Logistik</option>
          </select>
        </div>
      </div>

      <!-- Articles Grid -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div
          v-for="art in filteredArticles"
          :key="art.id"
          class="group rounded-3xl bg-[#0b1426] border border-slate-800/90 p-5 flex flex-col justify-between space-y-4 hover:border-amber-400/50 transition duration-300 shadow-xl"
        >
          <div class="space-y-2">
            <div class="flex items-center justify-between text-[11px]">
              <span class="px-2.5 py-0.5 rounded-full font-black bg-slate-800 text-amber-400 border border-slate-700">
                {{ art.source_name }}
              </span>
              <span class="text-slate-400 font-medium">{{ art.pub_date }}</span>
            </div>
            <h4 class="font-extrabold text-sm md:text-base text-white leading-snug group-hover:text-amber-400 transition">
              {{ art.title }}
            </h4>
            <p class="text-xs text-slate-300 leading-relaxed font-normal">
              {{ art.summary }}
            </p>
          </div>

          <!-- Bottom Action Buttons -->
          <div class="flex items-center justify-between pt-3 border-t border-slate-800/80 gap-2">
            <a
              href="#"
              class="px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-900/80 hover:bg-slate-800 border border-slate-700 text-slate-200 flex items-center gap-1.5 transition"
            >
              <ExternalLink class="w-3.5 h-3.5" /> Buka
            </a>

            <!-- Interactive Bookmark Button -->
            <button
              @click="toggleBookmark(art.id)"
              class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm"
              :class="bookmarkedIds.includes(art.id) ? 'bg-amber-400 text-slate-950 font-black' : 'bg-slate-900/80 hover:bg-amber-400/10 border border-slate-700 text-amber-400'"
            >
              <Bookmark class="w-3.5 h-3.5" />
              <span>{{ bookmarkedIds.includes(art.id) ? '★ Tersimpan' : '🔖 Simpan' }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Section 3: Satker & Satwil Jajaran (Polres) -->
    <div v-if="selectedPolda" class="space-y-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <Building2 class="w-5 h-5 text-sky-400" />
          <h2 class="text-lg md:text-xl font-bold text-white tracking-tight">
            Satuan Wilayah Jajaran ({{ selectedPolda.polres?.length || 0 }} Polres di {{ selectedPolda.name }})
          </h2>
        </div>
      </div>

      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <div
          v-for="polres in selectedPolda.polres"
          :key="polres.id"
          class="p-4 rounded-2xl bg-[#0b1426] border border-slate-800 hover:border-sky-500/40 transition text-center space-y-1 shadow-md"
        >
          <div class="w-8 h-8 mx-auto rounded-xl bg-sky-500/10 text-sky-400 flex items-center justify-center font-bold text-xs">
            POL
          </div>
          <p class="text-xs font-extrabold text-white truncate">{{ polres.name }}</p>
          <p class="text-[10px] text-emerald-400 font-bold flex items-center justify-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
            Siaga 24/7
          </p>
        </div>
      </div>
    </div>
  </EnterpriseLayout>
</template>
