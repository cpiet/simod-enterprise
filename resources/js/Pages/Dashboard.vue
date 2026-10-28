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

// Active Navigation Tab (01-06)
const activeTab = ref('01');

const tabs = [
  { id: '01', name: '01. Live RSS Media', badge: 'LIVE', icon: Radio },
  { id: '02', name: '02. Berita & Briefing', badge: null, icon: Newspaper },
  { id: '03', name: '03. Executive Snapshot', badge: null, icon: Zap },
  { id: '04', name: '04. Statistik Satwil', badge: null, icon: MapPin },
  { id: '05', name: '05. Early Warning (EWS)', badge: 'EWS', icon: Shield },
  { id: '06', name: '06. Narasi & Rekomendasi', badge: null, icon: Award },
];

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
const searchQuery = ref('');

const filteredArticles = computed(() => {
  let list = props.sampleArticles || [];

  if (selectedCategory.value === 'bookmark') {
    list = list.filter((a) => bookmarkedIds.value.includes(a.id));
  } else if (selectedCategory.value !== 'all') {
    list = list.filter((a) => a.category.toLowerCase() === selectedCategory.value.toLowerCase());
  }

  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter((a) => a.title.toLowerCase().includes(q) || a.summary.toLowerCase().includes(q));
  }

  return list;
});

// Create Issue Modal State
const showIssueModal = ref(false);
const activeArticleForIssue = ref(null);
const issueForm = ref({
  priority: 'KUNING',
  pic: 'Bidhumas Polda',
  notes: '',
});
const toastMessage = ref('');

const openIssueModal = (article) => {
  activeArticleForIssue.value = article;
  issueForm.value.notes = `Eskalasi temuan media: ${article.title}`;
  showIssueModal.value = true;
};

const submitIssue = () => {
  showIssueModal.value = false;
  toastMessage.value = `Berhasil membuat Isu Baru dari [${activeArticleForIssue.value?.title.substring(0, 30)}...]! Diteruskan ke Command Center EWS.`;
  setTimeout(() => {
    toastMessage.value = '';
  }, 4500);
};
</script>

<template>
  <EnterpriseLayout
    :all-poldas="allPoldas"
    :selected-polda="selectedPolda"
    :selected-code="selectedCode"
    :metrics="metrics"
  >
    <!-- Toast Notification -->
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

    <!-- ======================================================== -->
    <!-- HIGH CONTRAST NAVIGATION TAB BAR (01 s/d 06)             -->
    <!-- Menjawab Screenshot 2: Semua tab terbaca sangat jelas!   -->
    <!-- ======================================================== -->
    <div class="p-2 rounded-2xl bg-[#08101e] border border-slate-800 shadow-xl overflow-x-auto">
      <div class="flex items-center gap-2 min-w-max">
        <button
          v-for="t in tabs"
          :key="t.id"
          @click="activeTab = t.id"
          type="button"
          class="flex items-center gap-2.5 px-4 py-2.5 rounded-xl text-xs md:text-sm font-extrabold transition-all duration-200 cursor-pointer"
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
                : t.id === '05'
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
                ? 'bg-rose-500 text-white animate-pulse'
                : 'bg-amber-400 text-slate-950'
            ]"
          >
            {{ t.badge }}
          </span>
        </button>
      </div>
    </div>

    <!-- ======================================================== -->
    <!-- TAB 01: LIVE RSS MEDIA INTELLIGENCE (SCREENSHOT 3)        -->
    <!-- ======================================================== -->
    <div v-show="activeTab === '01' || activeTab === 'all'" class="space-y-4">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-2">
          <Newspaper class="w-5 h-5 text-amber-400" />
          <h2 class="text-lg md:text-xl font-bold text-white tracking-tight">
            Pemantauan Intelijen Media & Berita Terkini
          </h2>
          <span class="px-2.5 py-0.5 rounded-full bg-slate-800 text-slate-300 text-xs font-bold border border-slate-700">
            {{ filteredArticles.length }} Berita
          </span>
        </div>

        <!-- Filter & Search Bar -->
        <div class="flex items-center gap-3">
          <div class="relative">
            <Search class="w-4 h-4 text-slate-400 absolute left-3 top-2.5 pointer-events-none" />
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Cari judul berita..."
              class="pl-9 pr-3 py-1.5 rounded-xl bg-[#0b1426] border border-slate-700 text-xs font-bold text-slate-100 placeholder-slate-400 outline-none focus:border-amber-400 w-44 md:w-56"
            />
          </div>

          <select
            v-model="selectedCategory"
            class="px-3 py-1.5 rounded-xl bg-[#0b1426] border border-slate-700 text-xs font-bold text-slate-200 outline-none focus:border-amber-400 cursor-pointer"
          >
            <option value="all">Semua Kategori</option>
            <option value="bookmark">⭐ Tersimpan / Bookmark ({{ bookmarkedIds.length }})</option>
            <option value="Nasional">Nasional</option>
            <option value="Hukum & Kriminal">Hukum & Kriminal</option>
            <option value="Harkamtibmas">Harkamtibmas</option>
            <option value="Lalu Lintas">Lalu Lintas</option>
          </select>
        </div>
      </div>

      <!-- Articles Grid Layout Matching Screenshot 3 -->
      <div v-if="filteredArticles.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <div
          v-for="art in filteredArticles"
          :key="art.id"
          class="group rounded-2xl bg-[#0e172a] border border-slate-800 hover:border-slate-600 transition-all duration-300 shadow-xl overflow-hidden flex flex-col justify-between"
        >
          <!-- Top Thumbnail Image with Badges -->
          <div class="relative h-44 w-full bg-slate-900 overflow-hidden">
            <img
              :src="art.image_url"
              :alt="art.title"
              class="w-full h-full object-cover group-hover:scale-105 transition duration-500"
              loading="lazy"
            />
            <div class="absolute inset-0 bg-gradient-to-t from-[#0e172a] via-[#0e172a]/40 to-transparent"></div>

            <!-- Top Left Source Tag -->
            <div class="absolute top-3 left-3">
              <span class="px-2.5 py-1 rounded-md text-[10px] font-black uppercase tracking-wider bg-slate-950/80 text-amber-400 border border-slate-700/80 backdrop-blur-md shadow-md">
                {{ art.source_name }}
              </span>
            </div>

            <!-- Top Right Time Ago -->
            <div class="absolute top-3 right-3">
              <span class="px-2.5 py-1 rounded-md text-[10px] font-bold bg-slate-950/80 text-amber-300 border border-slate-700/80 backdrop-blur-md shadow-md flex items-center gap-1">
                <Clock class="w-3 h-3 text-amber-400" />
                {{ art.time_ago }}
              </span>
            </div>

            <!-- Bottom Left Category Pill -->
            <div class="absolute bottom-2 left-3">
              <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-slate-900/90 text-slate-200 border border-slate-700 shadow">
                {{ art.category_badge }}
              </span>
            </div>
          </div>

          <!-- Card Content -->
          <div class="p-5 flex-1 flex flex-col justify-between space-y-3">
            <div class="space-y-2">
              <div class="flex items-center gap-1.5 text-[11px] text-slate-400 font-medium">
                <Calendar class="w-3.5 h-3.5 text-slate-400" />
                <span>{{ art.pub_date }}</span>
              </div>
              <h3 class="font-extrabold text-sm md:text-base text-white leading-snug line-clamp-2 group-hover:text-amber-400 transition cursor-pointer">
                {{ art.title }}
              </h3>
              <p class="text-xs text-slate-300 leading-relaxed font-normal line-clamp-2">
                {{ art.summary }}
              </p>
            </div>

            <!-- Card Action Buttons: [Buka], [Simpan], [Buat Isu] -->
            <div class="flex items-center justify-between pt-3 border-t border-slate-800/80 gap-2">
              <!-- Buka Link -->
              <a
                :href="art.link"
                target="_blank"
                class="px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-200 flex items-center gap-1.5 transition"
              >
                <ExternalLink class="w-3.5 h-3.5 text-slate-400" />
                <span>Buka</span>
              </a>

              <!-- Right Buttons Group -->
              <div class="flex items-center gap-1.5">
                <!-- Bookmark Button -->
                <button
                  @click="toggleBookmark(art.id)"
                  type="button"
                  class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm cursor-pointer"
                  :class="[
                    bookmarkedIds.includes(art.id)
                      ? 'bg-amber-400 text-slate-950 font-black shadow-amber-400/20'
                      : 'bg-slate-900 hover:bg-amber-400/10 border border-slate-700 text-amber-400'
                  ]"
                >
                  <Bookmark class="w-3.5 h-3.5" :class="bookmarkedIds.includes(art.id) ? 'fill-current' : ''" />
                  <span>{{ bookmarkedIds.includes(art.id) ? '★ Tersimpan' : '🔖 Simpan' }}</span>
                </button>

                <!-- Buat Isu Button -->
                <button
                  @click="openIssueModal(art)"
                  type="button"
                  class="px-3 py-1.5 rounded-xl text-xs font-bold bg-indigo-950/80 hover:bg-indigo-900 border border-indigo-700/80 text-indigo-200 hover:text-white flex items-center gap-1.5 transition cursor-pointer"
                >
                  <PlusCircle class="w-3.5 h-3.5 text-indigo-400" />
                  <span>Buat Isu</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="p-12 rounded-2xl bg-[#0b1426] border border-slate-800 text-center space-y-2">
        <Bookmark class="w-8 h-8 text-slate-500 mx-auto" />
        <p class="text-sm font-bold text-slate-300">Belum ada berita pada kategori ini</p>
        <p class="text-xs text-slate-500">Klik tombol [🔖 Simpan] pada berita mana saja untuk melihatnya di tab bookmark.</p>
      </div>
    </div>

    <!-- ======================================================== -->
    <!-- TAB 05: EARLY WARNING SYSTEM (EWS)                       -->
    <!-- ======================================================== -->
    <div v-show="activeTab === '05' || activeTab === 'all'" class="space-y-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <div class="w-3 h-3 rounded-full bg-amber-400 animate-pulse"></div>
          <h2 class="text-lg md:text-xl font-bold text-white tracking-tight">
            Early Warning System (EWS) & Matriks Tindakan Taktis
          </h2>
        </div>
        <span class="text-xs font-bold text-slate-400">
          Protokol Divhumas Polri
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

    <!-- ======================================================== -->
    <!-- TAB 02: BERITA & BRIEFING                                -->
    <!-- ======================================================== -->
    <div v-show="activeTab === '02'" class="space-y-4">
      <div class="flex items-center gap-2">
        <Newspaper class="w-5 h-5 text-amber-400" />
        <h2 class="text-lg md:text-xl font-bold text-white tracking-tight">
          Briefing Narasi & Siaran Pers Resmi
        </h2>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="p-6 rounded-3xl bg-[#0b1426] border border-slate-800 space-y-3">
          <span class="px-3 py-1 rounded-full text-[10px] font-black bg-sky-500/20 text-sky-400 border border-sky-500/40">
            DRAF SIARAN PERS RESMI
          </span>
          <h3 class="text-base font-extrabold text-white">
            Klarifikasi Pemberitaan Terkait Kelancaran Pasokan BBM Subsidi Jelang Pilkada Serentak
          </h3>
          <p class="text-xs text-slate-300 leading-relaxed">
            Humas bersama Ditreskrimsus menegaskan sinergi aktif dengan Pertamina Patra Niaga menjamin stok BBM di seluruh SPBU aman tanpa antrean mengular. Masyarakat dihimbau tidak terpancing isu hoaks penimbunan.
          </p>
          <div class="flex items-center gap-2 pt-2">
            <button class="px-3 py-1.5 rounded-xl bg-amber-400 text-slate-950 font-black text-xs">
              Salin Siaran Pers
            </button>
            <button class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs border border-slate-700">
              Kirim ke Jajaran Satwil
            </button>
          </div>
        </div>

        <div class="p-6 rounded-3xl bg-[#0b1426] border border-slate-800 space-y-3">
          <span class="px-3 py-1 rounded-full text-[10px] font-black bg-emerald-500/20 text-emerald-400 border border-emerald-500/40">
            BRIEFING JURU BICARA (DO & DON'T)
          </span>
          <h3 class="text-base font-extrabold text-white">
            Panduan Komunikasi Publik Juru Bicara Polres & Satuan Fungsi
          </h3>
          <ul class="text-xs text-slate-300 space-y-2 list-disc list-inside">
            <li>Gunakan satu pintu pernyataan resmi melalui Kasi Humas Polres.</li>
            <li>Segera rilis klarifikasi awal dalam kurun waktu kurang dari 2 jam pasca insiden.</li>
            <li>Kedepankan fakta penyidikan ilmiah (scientific crime investigation) tanpa opini subjektif.</li>
          </ul>
        </div>
      </div>
    </div>

    <!-- ======================================================== -->
    <!-- TAB 03: EXECUTIVE SNAPSHOT                               -->
    <!-- ======================================================== -->
    <div v-show="activeTab === '03'" class="space-y-4">
      <div class="flex items-center gap-2">
        <Zap class="w-5 h-5 text-amber-400" />
        <h2 class="text-lg md:text-xl font-bold text-white tracking-tight">
          Executive Snapshot Pimpinan
        </h2>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="p-6 rounded-3xl bg-[#0b1426] border border-slate-800 space-y-3">
          <p class="text-xs font-bold text-slate-400">Indeks Kepercayaan Publik (IKP)</p>
          <p class="text-4xl font-black text-emerald-400">84.2%</p>
          <p class="text-xs text-slate-400">Naik +2.4% dibandingkan periode bulan sebelumnya.</p>
        </div>
        <div class="p-6 rounded-3xl bg-[#0b1426] border border-slate-800 space-y-3">
          <p class="text-xs font-bold text-slate-400">Total Mention & Jangkauan Media</p>
          <p class="text-4xl font-black text-amber-400">14.8K</p>
          <p class="text-xs text-slate-400">Terekam di portal siber, media sosial, dan cetak regional.</p>
        </div>
        <div class="p-6 rounded-3xl bg-[#0b1426] border border-slate-800 space-y-3">
          <p class="text-xs font-bold text-slate-400">Rasio Viralisasi Positif</p>
          <p class="text-4xl font-black text-sky-400">91.6%</p>
          <p class="text-xs text-slate-400">Respons counter narasi cepat efektif meredam isu negatif.</p>
        </div>
      </div>
    </div>

    <!-- ======================================================== -->
    <!-- TAB 04: STATISTIK SATWIL (POLRES)                        -->
    <!-- ======================================================== -->
    <div v-show="activeTab === '04' || activeTab === 'all'" class="space-y-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <Building2 class="w-5 h-5 text-sky-400" />
          <h2 class="text-lg md:text-xl font-bold text-white tracking-tight">
            Satuan Wilayah Jajaran ({{ selectedPolda ? (selectedPolda.polres?.length || 0) : metrics.total_polres }} Polres)
          </h2>
        </div>
      </div>

      <div v-if="selectedPolda" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
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

    <!-- ======================================================== -->
    <!-- TAB 06: NARASI & REKOMENDASI                             -->
    <!-- ======================================================== -->
    <div v-show="activeTab === '06'" class="space-y-4">
      <div class="flex items-center gap-2">
        <Award class="w-5 h-5 text-amber-400" />
        <h2 class="text-lg md:text-xl font-bold text-white tracking-tight">
          Rekomendasi Kebijakan & Strategi Orkestrasi Narasi
        </h2>
      </div>

      <div class="p-6 rounded-3xl bg-[#0b1426] border border-slate-800 space-y-4">
        <div class="flex items-center gap-3">
          <span class="px-3 py-1 rounded-full text-xs font-black bg-amber-400 text-slate-950">
            REKOMENDASI 1
          </span>
          <h3 class="text-base font-extrabold text-white">
            Penguatan Konten Humanis Polantas Menjelang Libur Panjang
          </h3>
        </div>
        <p class="text-xs text-slate-300 leading-relaxed">
          Disarankan memproduksi 3 video pendek per hari menampilkan personel patroli memberikan bantuan logistik kepada pengendara mogok dan edukasi keselamatan tanpa tindakan represif, guna mendominasi algoritma FYP TikTok regional.
        </p>
      </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL POPUP: BUAT ISU BARU (+ Buat Isu)                  -->
    <!-- ======================================================== -->
    <div
      v-if="showIssueModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm"
    >
      <div class="w-full max-w-lg rounded-3xl bg-[#0d182e] border border-slate-700 p-6 shadow-2xl space-y-5">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center font-bold">
              <Plus class="w-5 h-5" />
            </div>
            <div>
              <h3 class="text-base font-extrabold text-white">Eskalasi Isu Baru (EWS)</h3>
              <p class="text-[11px] text-slate-400">Diteruskan ke Command Center & Humas Wilayah</p>
            </div>
          </div>
          <button
            @click="showIssueModal = false"
            class="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition"
          >
            <X class="w-5 h-5" />
          </button>
        </div>

        <!-- Selected Article Teaser -->
        <div class="p-3.5 rounded-2xl bg-slate-900/90 border border-slate-800 space-y-1">
          <span class="text-[10px] uppercase font-bold text-amber-400">{{ activeArticleForIssue?.source_name }}</span>
          <p class="text-xs font-extrabold text-white leading-snug">{{ activeArticleForIssue?.title }}</p>
        </div>

        <div class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1">Tingkat Siaga (Severity)</label>
            <select
              v-model="issueForm.priority"
              class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-xs font-bold text-white focus:border-amber-400 outline-none"
            >
              <option value="HIJAU">🟢 Siaga Hijau (Pemantauan Rutin)</option>
              <option value="KUNING">🟡 Siaga Kuning (Potensi Eskalasi / Perlu Counter Narasi)</option>
              <option value="MERAH">🔴 Siaga Merah (Krisis Reputasi / Penanganan Cepat 0-2 Jam)</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1">Satuan Kerja Penanggung Jawab (PIC)</label>
            <select
              v-model="issueForm.pic"
              class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-xs font-bold text-white focus:border-amber-400 outline-none"
            >
              <option value="Bidhumas Polda">Bidhumas Polda</option>
              <option value="Ditreskrimsus & Siber">Ditreskrimsus & Siber</option>
              <option value="Ditlantas">Ditlantas</option>
              <option value="Bidpropam">Bidpropam</option>
              <option value="Polres Wilayah Terkait">Polres Wilayah Terkait</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1">Catatan Instruksi Awal</label>
            <textarea
              v-model="issueForm.notes"
              rows="3"
              class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-xs text-white placeholder-slate-500 focus:border-amber-400 outline-none resize-none"
              placeholder="Instruksi taktis untuk juru bicara atau operator patroli siber..."
            ></textarea>
          </div>
        </div>

        <!-- Modal Actions -->
        <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-800">
          <button
            @click="showIssueModal = false"
            class="px-4 py-2 rounded-xl text-xs font-bold text-slate-300 hover:bg-slate-800 border border-slate-700 transition"
          >
            Batal
          </button>
          <button
            @click="submitIssue"
            class="px-4 py-2 rounded-xl text-xs font-black bg-amber-400 hover:bg-amber-300 text-slate-950 flex items-center gap-1.5 shadow-lg transition"
          >
            <Send class="w-3.5 h-3.5" />
            <span>Kirim & Terbitkan Isu</span>
          </button>
        </div>
      </div>
    </div>
  </EnterpriseLayout>
</template>
