<script setup>
import { ref, computed, onMounted } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import {
  Shield,
  ShieldAlert,
  Radio,
  Building2,
  Flame,
  Newspaper,
  Clock,
  Layers,
  Users,
  CheckCircle2,
  AlertTriangle,
  ChevronDown,
  ChevronRight,
  Bell,
  Search,
  ExternalLink,
  Sparkles,
  Share2,
  CheckSquare,
  Menu,
  X,
  LayoutDashboard,
  Image as ImageIcon,
  FileBarChart2,
  KeyRound,
  Mail,
  Database,
  History,
  Wrench,
  UserCheck,
  LogOut,
  SlidersHorizontal,
  Activity,
  Check,
  PlusCircle,
  Sun,
  Moon,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: {
    type: Array,
    default: () => [],
  },
  selectedPolda: {
    type: Object,
    default: null,
  },
  selectedCode: {
    type: String,
    default: 'SULBAR',
  },
  metrics: {
    type: Object,
    default: () => ({}),
  },
});

const page = usePage();
const isTenantMenuOpen = ref(false);
const isUserMenuOpen = ref(false);
const isMobileSidebarOpen = ref(false);
const isDesktopSidebarCollapsed = ref(false);
const searchQuery = ref('');

const currentUrl = computed(() => page.url);

const switchTenant = (code) => {
  isTenantMenuOpen.value = false;
  // Preserve current pathname but change polda query
  const url = new URL(window.location.href);
  url.searchParams.set('polda', code);
  router.get(url.pathname, { polda: code }, { preserveScroll: true });
};

// Merged global & page metrics for accurate dynamic badges across all routes
const displayMetrics = computed(() => {
  return {
    ...(page.props?.sidebarMetrics || {}),
    ...(props.metrics || {}),
  };
});

// Dual Theme Engine (Dark Command Center & Clean Executive Light Mode)
const isDarkMode = ref(true);

const initTheme = () => {
  try {
    const saved = localStorage.getItem('simod_theme');
    if (saved === 'light') {
      isDarkMode.value = false;
      document.documentElement.classList.remove('dark');
      document.documentElement.classList.add('light');
    } else {
      isDarkMode.value = true;
      document.documentElement.classList.add('dark');
      document.documentElement.classList.remove('light');
    }
  } catch (e) {
    isDarkMode.value = true;
  }
};

const toggleTheme = () => {
  isDarkMode.value = !isDarkMode.value;
  try {
    if (isDarkMode.value) {
      document.documentElement.classList.add('dark');
      document.documentElement.classList.remove('light');
      localStorage.setItem('simod_theme', 'dark');
    } else {
      document.documentElement.classList.remove('dark');
      document.documentElement.classList.add('light');
      localStorage.setItem('simod_theme', 'light');
    }
  } catch (e) {}
};

onMounted(() => {
  initTheme();
});

// Check if a route is currently active with reactive URL tracking & collision prevention
const isRouteActive = (target) => {
  if (!target) return false;

  // 1. Reactive current URL from Inertia usePage()
  const rawUrl = page.url || (typeof window !== 'undefined' ? window.location.pathname : '');
  const currentPath = (rawUrl.split('?')[0].replace(/\/+$/, '') || '/').toLowerCase();

  // Normalize target to object
  const item = typeof target === 'object' && !Array.isArray(target) ? target : { match: target };

  // 2. Check exclusions first (e.g. /issues/create is excluded from /issues)
  if (item.exclude && Array.isArray(item.exclude)) {
    const isExcluded = item.exclude.some(ex => {
      const cleanEx = (ex.split('?')[0].replace(/\/+$/, '') || '/').toLowerCase();
      return currentPath === cleanEx || currentPath.startsWith(cleanEx + '/');
    });
    if (isExcluded) return false;
  }

  // 3. Candidate paths to match against
  const paths = Array.isArray(item.match) ? item.match : (item.href ? [item.href] : [item]);

  return paths.some(p => {
    if (typeof p !== 'string') return false;
    const cleanP = (p.split('?')[0].replace(/\/+$/, '') || '/').toLowerCase();

    // Command Center / Home exact matching
    if (cleanP === '/' || cleanP === '/dashboard' || cleanP === '/command-center') {
      return currentPath === '/' || currentPath === '/dashboard' || currentPath === '/command-center';
    }

    // Explicit exact match requested on item
    if (item.exact) {
      return currentPath === cleanP;
    }

    // Exact match or subpath boundary (e.g. /issues/123 matches /issues, but not /issues-archive)
    return currentPath === cleanP || currentPath.startsWith(cleanP + '/');
  });
};

// Grouped Navigation Definition - 4 Professional Enterprise Groups
const navigationGroups = [
  {
    name: 'KOMANDO & INTELIJEN',
    items: [
      {
        name: 'Command Center',
        desc: 'Ringkasan Eksekutif & EWS',
        href: '/',
        icon: LayoutDashboard,
        match: ['/', '/dashboard', '/command-center'],
        badge: 'LIVE',
        badgeColor: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
      },
      {
        name: 'Radar Media Online',
        desc: 'Agregasi 29 RSS & Berita Satwil',
        href: '/media-intelligence',
        icon: Radio,
        match: ['/media-intelligence', '/monitoring-media-online'],
        badge: 'ONLINE',
        badgeColor: 'bg-amber-400/20 text-amber-300 border-amber-400/30',
      },
      {
        name: 'Radar Media Sosial',
        desc: 'Pantauan 𝕏, TikTok, IG, YT & KOL',
        href: '/social-intelligence',
        icon: Share2,
        match: ['/social-intelligence'],
        badge: 'RADAR',
        badgeColor: 'bg-sky-500/20 text-sky-400 border border-sky-500/30',
      },
    ],
  },
  {
    name: 'MANAJEMEN ISU & TUGAS',
    items: [
      {
        name: 'Laporan Polres & Isu',
        desc: 'Pemantauan & Eskalasi Berita',
        href: '/issues',
        icon: Flame,
        match: ['/issues'],
        exclude: ['/issues/create'],
        badgeKey: 'total_issues',
      },
      {
        name: 'Input Laporan Baru',
        desc: 'Formulir Laporan Lapangan & Foto',
        href: '/issues/create',
        icon: PlusCircle,
        match: ['/issues/create'],
        exact: true,
      },
      {
        name: 'Antrean Verifikasi',
        desc: 'Validasi Fakta & Berita Satker',
        href: '/verification-queue',
        icon: CheckCircle2,
        match: ['/verification-queue'],
        badgeKey: 'pending_verify',
        badgeColor: 'bg-rose-500/20 text-rose-300 border-rose-500/30',
      },
      {
        name: 'Disposisi Tugas',
        desc: 'Penugasan Anggota & Laporan',
        href: '/tasks',
        icon: CheckSquare,
        match: ['/tasks'],
        badgeKey: 'active_tasks',
      },
    ],
  },
  {
    name: 'PUBLIKASI & DOKUMENTASI',
    items: [
      {
        name: 'Publikasi Multi-Kanal',
        desc: 'Tribratanews, IG, TikTok, FB, X',
        href: '/publications',
        icon: Share2,
        match: ['/publications'],
        badgeKey: 'total_publications',
      },
      {
        name: 'Galeri Media Aman',
        desc: 'Dokumentasi & Bukti Terenkripsi',
        href: '/gallery',
        icon: ImageIcon,
        match: ['/gallery'],
      },
      {
        name: 'Laporan & Anev',
        desc: 'Rekapitulasi Kinerja & Ekspor',
        href: '/reports',
        icon: FileBarChart2,
        match: ['/reports'],
      },
    ],
  },
  {
    name: 'ADMINISTRASI & SISTEM',
    items: [
      {
        name: 'Personalia & Petugas',
        desc: 'Manajemen Akun Anggota',
        href: '/personalia',
        icon: Users,
        match: ['/personalia'],
      },
      {
        name: 'Pengaturan Satwil & Mabes',
        desc: 'Konfigurasi Divhumas, Polda & Polres',
        href: '/settings/polda',
        icon: Building2,
        match: ['/settings/polda'],
      },
      {
        name: 'Keamanan & Pemeliharaan',
        desc: 'Keamanan, Hak Akses, Audit, Backup, Pembersihan, Sinkron RSS',
        href: '/security',
        icon: ShieldAlert,
        match: ['/security', '/roles', '/audit-logs', '/database/backup', '/maintenance', '/settings/mail', '/maintenance/news-cleanup', '/maintenance/rss-sync'],
      },
      {
        name: 'Profil Saya',
        desc: 'Kredensial & PIN Cepat',
        href: '/profile',
        icon: UserCheck,
        match: ['/profile'],
      },
    ],
  },
];
</script>

<template>
  <div class="min-h-screen bg-[#070d1d] text-slate-100 flex font-sans selection:bg-amber-400 selection:text-slate-950 antialiased">
    <!-- ========================================================================= -->
    <!-- DESKTOP FIXED SIDEBAR (260px)                                             -->
    <!-- ========================================================================= -->
    <aside
      class="hidden lg:flex flex-col flex-shrink-0 z-40 bg-[#091122]/95 border-r border-slate-800/80 transition-all duration-300 ease-in-out relative select-none"
      :class="isDesktopSidebarCollapsed ? 'w-20' : 'w-64'"
    >
      <!-- Top Brand Header -->
      <div class="h-16 px-4 flex items-center justify-between border-b border-slate-800/80 bg-[#0b1426]">
        <Link :href="`/?polda=${selectedCode}`" class="flex items-center gap-3 overflow-hidden group">
          <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-amber-500 to-amber-300 p-0.5 shadow-lg shadow-amber-500/20 group-hover:scale-105 transition duration-200 flex-shrink-0 flex items-center justify-center">
            <div class="w-full h-full bg-[#0a1224] rounded-[14px] flex items-center justify-center">
              <Shield class="w-5 h-5 text-amber-400" />
            </div>
          </div>
          <div v-if="!isDesktopSidebarCollapsed" class="min-w-0">
            <div class="flex items-center gap-1.5">
              <span class="font-extrabold text-sm tracking-tight text-white group-hover:text-amber-400 transition truncate">
                SIMOD HUMAS
              </span>
              <span class="px-1.5 py-0.2 rounded text-[8px] font-black uppercase bg-amber-400 text-slate-950">
                ENT
              </span>
            </div>
            <p class="text-[10px] text-amber-400/90 font-medium truncate">
              {{ selectedCode === 'ALL' ? 'Divhumas Polri' : (selectedPolda?.name || 'Polda Sulbar') }}
            </p>
          </div>
        </Link>
      </div>

      <!-- Scrollable Navigation Items -->
      <div class="flex-1 overflow-y-auto px-3 py-4 space-y-6 custom-scrollbar">
        <div v-for="group in navigationGroups" :key="group.name" class="space-y-1">
          <!-- Section Heading -->
          <div
            v-if="!isDesktopSidebarCollapsed"
            class="px-3 pb-1.5 text-[10px] font-black uppercase tracking-wider text-slate-300/80"
          >
            {{ group.name }}
          </div>
          <div v-else class="h-px bg-slate-800/80 my-2"></div>

          <!-- Nav Links -->
          <Link
            v-for="item in group.items"
            :key="item.name"
            :href="`${item.href}?polda=${selectedCode}`"
            class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition group relative"
            :class="isRouteActive(item)
              ? 'bg-amber-400 text-slate-950 font-black shadow-md shadow-amber-400/20'
              : 'text-slate-200 hover:text-white hover:bg-slate-800/70'"
            :title="isDesktopSidebarCollapsed ? item.name : undefined"
          >
            <!-- Active Indicator Pill on left -->
            <div
              v-if="isRouteActive(item) && !isDesktopSidebarCollapsed"
              class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-5 rounded-r bg-slate-950"
            ></div>

            <component
              :is="item.icon"
              class="w-4 h-4 flex-shrink-0 transition-transform group-hover:scale-110"
              :class="isRouteActive(item) ? 'text-slate-950' : 'text-slate-300 group-hover:text-amber-400'"
            />

            <div v-if="!isDesktopSidebarCollapsed" class="min-w-0 flex-1">
              <div class="flex items-center justify-between">
                <span class="truncate">{{ item.name }}</span>

                <!-- Static Badge -->
                <span
                  v-if="item.badge"
                  class="ml-1.5 px-1.5 py-0.5 rounded text-[9px] font-black uppercase border"
                  :class="isRouteActive(item) ? 'bg-slate-950 text-amber-400 border-slate-900' : (item.badgeColor || 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-300 dark:border-slate-700')"
                >
                  {{ item.badge }}
                </span>

                <!-- Dynamic Metric Badge -->
                <span
                  v-else-if="item.badgeKey && displayMetrics[item.badgeKey]"
                  class="ml-1.5 px-1.5 py-0.5 rounded-full text-[10px] font-black"
                  :class="isRouteActive(item) ? 'bg-slate-950 text-amber-400' : (item.badgeColor || 'bg-amber-100 text-amber-800 border border-amber-300 dark:bg-slate-800 dark:text-amber-400 dark:border-slate-700/60')"
                >
                  {{ displayMetrics[item.badgeKey] }}
                </span>
              </div>
            </div>
          </Link>
        </div>
      </div>

      <!-- Presisi Motto Footer Box -->
      <div v-if="!isDesktopSidebarCollapsed" class="p-3 border-t border-slate-800/80 bg-[#080e1c]">
        <div class="rounded-xl bg-gradient-to-br from-slate-900 to-[#0c162d] border border-amber-400/20 p-3 shadow-inner">
          <div class="flex items-center gap-2 mb-1.5">
            <Shield class="w-3.5 h-3.5 text-amber-400" />
            <span class="text-[10px] font-black tracking-wider text-amber-400 uppercase">POLRI PRESISI</span>
          </div>
          <p class="text-[9px] text-slate-300 leading-tight">
            Prediktif &bull; Responsibilitas &bull; Transparansi Berkeadilan
          </p>
          <div class="mt-2 pt-2 border-t border-slate-800/60 flex items-center justify-between text-[9px] text-slate-300 font-mono">
            <span class="flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
              MULTI-TENANT
            </span>
            <span class="text-amber-400/80 font-bold">v2.0</span>
          </div>
        </div>
      </div>
    </aside>

    <!-- ========================================================================= -->
    <!-- MOBILE DRAWER BACKDROP & SIDEBAR (< 1024px)                                -->
    <!-- ========================================================================= -->
    <div
      v-if="isMobileSidebarOpen"
      class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm lg:hidden transition-opacity"
      @click="isMobileSidebarOpen = false"
    ></div>

    <div
      class="fixed inset-y-0 left-0 z-50 w-72 bg-[#091122] border-r border-slate-800 flex flex-col lg:hidden transform transition-transform duration-300 ease-in-out shadow-2xl"
      :class="isMobileSidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <!-- Mobile Sidebar Header -->
      <div class="h-16 px-4 flex items-center justify-between border-b border-slate-800 bg-[#0b1426]">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-amber-500 to-amber-300 p-0.5 flex items-center justify-center">
            <div class="w-full h-full bg-[#0a1224] rounded-[10px] flex items-center justify-center">
              <Shield class="w-4 h-4 text-amber-400" />
            </div>
          </div>
          <div>
            <span class="font-extrabold text-sm text-white">SIMOD ENTERPRISE</span>
            <p class="text-[10px] text-amber-400 font-medium">
              {{ selectedCode === 'ALL' ? 'Divhumas Polri' : selectedPolda?.name }}
            </p>
          </div>
        </div>
        <div class="flex items-center gap-1.5">
          <button
            @click="toggleTheme"
            type="button"
            class="p-2 rounded-xl text-slate-400 hover:text-amber-400 hover:bg-slate-800 border border-slate-700/60"
            :title="isDarkMode ? 'Mode Terang' : 'Mode Gelap'"
          >
            <Sun v-if="!isDarkMode" class="w-4 h-4 text-amber-500" />
            <Moon v-else class="w-4 h-4 text-amber-400" />
          </button>
          <button
            @click="isMobileSidebarOpen = false"
            class="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 border border-slate-700/60"
          >
            <X class="w-5 h-5" />
          </button>
        </div>
      </div>

      <!-- Mobile Navigation Scroll -->
      <div class="flex-1 overflow-y-auto px-3 py-4 space-y-6">
        <div v-for="group in navigationGroups" :key="group.name" class="space-y-1">
          <div class="px-3 pb-1 text-[10px] font-black uppercase tracking-wider text-slate-300/80">
            {{ group.name }}
          </div>
          <Link
            v-for="item in group.items"
            :key="item.name"
            :href="`${item.href}?polda=${selectedCode}`"
            @click="isMobileSidebarOpen = false"
            class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition"
            :class="isRouteActive(item)
              ? 'bg-amber-400 text-slate-950 font-black'
              : 'text-slate-300 hover:text-white hover:bg-slate-800/80'"
          >
            <component :is="item.icon" class="w-4 h-4" />
            <span class="flex-1">{{ item.name }}</span>

            <!-- Static Badge Mobile -->
            <span
              v-if="item.badge"
              class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase border"
              :class="isRouteActive(item) ? 'bg-slate-950 text-amber-400 border-slate-900' : (item.badgeColor || 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-300 dark:border-slate-700')"
            >
              {{ item.badge }}
            </span>

            <!-- Dynamic Metric Badge Mobile -->
            <span
              v-else-if="item.badgeKey && displayMetrics[item.badgeKey]"
              class="px-2 py-0.5 rounded-full text-[10px] font-black"
              :class="isRouteActive(item) ? 'bg-slate-950 text-amber-400' : 'bg-amber-100 text-amber-800 border border-amber-300 dark:bg-slate-800 dark:text-amber-400'"
            >
              {{ displayMetrics[item.badgeKey] }}
            </span>
          </Link>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MAIN APPLICATION WRAPPER (Top Header + Page Content)                      -->
    <!-- ========================================================================= -->
    <div class="flex-1 flex flex-col min-w-0 overflow-x-hidden">
      <!-- Sticky Top Header Bar -->
      <header class="sticky top-0 z-30 backdrop-blur-xl bg-[#091122]/90 border-b border-slate-800/80 px-4 lg:px-8 py-2.5 flex items-center justify-between shadow-xl">
        <!-- Left: Mobile Hamburger & Desktop Collapse Toggle & Polda Switcher -->
        <div class="flex items-center gap-3">
          <!-- Mobile Menu Toggle Button -->
          <button
            @click="isMobileSidebarOpen = true"
            class="lg:hidden p-2 rounded-xl bg-slate-900 border border-slate-700/80 text-slate-300 hover:text-white"
          >
            <Menu class="w-4 h-4" />
          </button>

          <!-- Desktop Sidebar Collapse Toggle -->
          <button
            @click="isDesktopSidebarCollapsed = !isDesktopSidebarCollapsed"
            class="hidden lg:flex p-1.5 rounded-xl bg-slate-900/80 hover:bg-slate-800 border border-slate-800 text-slate-400 hover:text-amber-400 transition"
            title="Sembunyikan/Buka Sidebar"
          >
            <SlidersHorizontal class="w-4 h-4" />
          </button>

          <!-- Tenant Switcher Dropdown (Polda Switcher) -->
          <div class="relative">
            <button
              @click="isTenantMenuOpen = !isTenantMenuOpen"
              class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-900/90 hover:bg-slate-800 border border-slate-700 text-xs font-bold text-slate-200 transition shadow-sm hover:border-amber-400/50"
            >
              <Building2 class="w-3.5 h-3.5 text-amber-400" />
              <span class="truncate max-w-[140px] sm:max-w-[220px]">
                {{ selectedCode === 'ALL' ? 'Divhumas Polri (Nasional)' : (selectedPolda?.name || 'Polda Sulawesi Barat') }}
              </span>
              <ChevronDown class="w-3 h-3 text-slate-400 transition duration-200" :class="{ 'rotate-180': isTenantMenuOpen }" />
            </button>

            <!-- Dropdown Menu -->
            <div
              v-if="isTenantMenuOpen"
              class="absolute left-0 mt-2 w-72 rounded-2xl bg-[#0b1426] border border-slate-700/80 shadow-2xl p-2 z-50 backdrop-blur-2xl"
            >
              <div class="px-3 py-2 text-[10px] font-extrabold uppercase tracking-wider text-slate-400 border-b border-slate-800/80 flex items-center justify-between">
                <span>Pilih Wilayah Komando</span>
                <span class="text-amber-400">Multi-Tenant</span>
              </div>

              <!-- All Poldas (Divhumas Polri View) -->
              <button
                @click="switchTenant('ALL')"
                class="w-full text-left px-3 py-2.5 rounded-xl text-xs font-semibold flex items-center justify-between transition mt-1"
                :class="selectedCode === 'ALL' ? 'bg-amber-400 text-slate-950 font-black shadow-sm' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white'"
              >
                <div class="flex items-center gap-2">
                  <Layers class="w-4 h-4" />
                  <div>
                    <span class="block">Divhumas Polri (Nasional)</span>
                    <span class="text-[9px] block opacity-75 font-normal">Orkestrasi 34 Polda Jajaran</span>
                  </div>
                </div>
                <span class="text-[10px] opacity-80 font-black">Nasional</span>
              </button>

              <!-- Individual Poldas -->
              <div class="my-1 border-t border-slate-800/60"></div>
              <button
                v-for="p in allPoldas"
                :key="p.id"
                @click="switchTenant(p.code)"
                class="w-full text-left px-3 py-2.5 rounded-xl text-xs font-semibold flex items-center justify-between transition"
                :class="selectedCode === p.code ? 'bg-amber-400 text-slate-950 font-black shadow-sm' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white'"
              >
                <div class="flex items-center gap-2">
                  <Shield class="w-3.5 h-3.5" :class="selectedCode === p.code ? 'text-slate-950' : 'text-amber-400'" />
                  <span class="truncate">{{ p.name }}</span>
                </div>
                <span class="text-[10px] px-2 py-0.5 rounded-full" :class="selectedCode === p.code ? 'bg-slate-950 text-amber-400' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700'">
                  {{ p.polres_count || 6 }} Polres
                </span>
              </button>
            </div>
          </div>
        </div>

        <!-- Right: Live WITA Clock & Theme Toggle & Notification & User Profile -->
        <div class="flex items-center gap-2 sm:gap-2.5">
          <!-- Real-time WITA Clock -->
          <div class="hidden md:flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900/80 border border-slate-800 text-xs font-mono text-slate-300 shadow-inner">
            <Clock class="w-3.5 h-3.5 text-amber-400" />
            <span>{{ metrics.server_wita || 'WITA Realtime' }}</span>
          </div>

          <!-- Dual Theme Switcher Toggle (Command Center Dark vs Executive Light) -->
          <button
            @click="toggleTheme"
            type="button"
            class="flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-xl bg-slate-900/90 hover:bg-slate-800 border border-slate-700/80 text-xs font-bold text-slate-200 hover:text-amber-400 transition shadow-sm cursor-pointer select-none"
            :title="isDarkMode ? 'Beralih ke Tampilan Putih / Terang (Executive Light Mode)' : 'Beralih ke Tampilan Gelap (Command Center Dark Mode)'"
          >
            <Sun v-if="!isDarkMode" class="w-3.5 h-3.5 text-amber-500" />
            <Moon v-else class="w-3.5 h-3.5 text-amber-400" />
            <span class="hidden sm:inline text-xs font-bold">
              {{ isDarkMode ? 'Mode Gelap' : 'Mode Terang' }}
            </span>
          </button>

          <!-- Notification Bell -->
          <Link
            :href="`/verification-queue?polda=${selectedCode}`"
            class="relative p-2 rounded-xl bg-slate-900/80 hover:bg-slate-800 border border-slate-800 text-slate-300 hover:text-white transition"
            title="Antrean Verifikasi Siaga"
          >
            <Bell class="w-4 h-4" />
            <span
              v-if="metrics.pending_verify > 0"
              class="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-rose-500 text-white text-[9px] font-black flex items-center justify-center animate-pulse"
            >
              {{ metrics.pending_verify }}
            </span>
          </Link>

          <!-- User Profile Dropdown -->
          <div class="relative">
            <button
              @click="isUserMenuOpen = !isUserMenuOpen"
              class="flex items-center gap-2 p-1 pl-1.5 pr-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 transition"
            >
              <div
                class="w-7 h-7 rounded-lg text-slate-950 font-black text-xs flex items-center justify-center shadow-sm"
                :class="selectedCode === 'ALL' ? 'bg-gradient-to-tr from-amber-400 to-yellow-300 ring-2 ring-amber-400/50 text-[10px]' : 'bg-amber-400'"
              >
                {{ selectedCode === 'ALL' ? '⭐⭐' : 'P' }}
              </div>
              <div class="hidden sm:block text-left">
                <p class="text-xs font-bold text-white leading-tight truncate max-w-[130px]">
                  {{ selectedCode === 'ALL' ? 'Irjen Pol. Johnny E. Isir' : (selectedPolda?.kabid_humas_name ? selectedPolda.kabid_humas_name.split('(')[0].trim() : 'Kombes Pol. Slamet Wahyudi') }}
                </p>
                <p class="text-[10px] text-amber-400 font-semibold leading-tight">
                  {{ selectedCode === 'ALL' ? 'Kadivhumas Polri' : ('Kabid Humas ' + (selectedPolda?.code || 'SULBAR')) }}
                </p>
              </div>
              <ChevronDown class="w-3 h-3 text-slate-400 transition" :class="{ 'rotate-180': isUserMenuOpen }" />
            </button>

            <!-- User Menu Dropdown -->
            <div
              v-if="isUserMenuOpen"
              class="absolute right-0 mt-2 w-64 rounded-2xl bg-[#0b1426] border border-slate-700/80 shadow-2xl p-2 z-50 backdrop-blur-2xl"
            >
              <div class="px-3 py-2 border-b border-slate-800/80">
                <p class="text-xs font-bold text-white">
                  {{ selectedCode === 'ALL' ? 'Irjen Pol. Johnny Eddizon Isir, S.I.K., M.T.C.P.' : (selectedPolda?.kabid_humas_name || 'Kombes Pol. Slamet Wahyudi, S.I.K., M.H.') }}
                </p>
                <p class="text-[10px] text-amber-400 font-bold">
                  {{ selectedCode === 'ALL' ? 'NRP: 75060789 • Kadivhumas Polri' : ('NRP: 76040112 • Kabid Humas ' + (selectedPolda?.name || 'Polda Sulbar')) }}
                </p>
              </div>
              <div class="py-1">
                <Link
                  :href="`/profile?polda=${selectedCode}`"
                  @click="isUserMenuOpen = false"
                  class="w-full text-left px-3 py-2 rounded-xl text-xs font-semibold flex items-center gap-2 text-slate-300 hover:bg-slate-800 hover:text-white transition"
                >
                  <UserCheck class="w-3.5 h-3.5 text-amber-400" />
                  <span>Profil & PIN Dinas</span>
                </Link>
                <Link
                  :href="`/security?polda=${selectedCode}`"
                  @click="isUserMenuOpen = false"
                  class="w-full text-left px-3 py-2 rounded-xl text-xs font-semibold flex items-center gap-2 text-slate-300 hover:bg-slate-800 hover:text-white transition"
                >
                  <ShieldAlert class="w-3.5 h-3.5 text-amber-400" />
                  <span>Pusat Keamanan</span>
                </Link>
                <Link
                  :href="`/settings/polda?polda=${selectedCode}`"
                  @click="isUserMenuOpen = false"
                  class="w-full text-left px-3 py-2 rounded-xl text-xs font-semibold flex items-center gap-2 text-slate-300 hover:bg-slate-800 hover:text-white transition"
                >
                  <Building2 class="w-3.5 h-3.5 text-amber-400" />
                  <span>{{ selectedCode === 'ALL' ? 'Pengaturan Divhumas' : 'Pengaturan Satwil' }}</span>
                </Link>
              </div>
              <div class="border-t border-slate-800/80 pt-1">
                <Link
                  href="/login"
                  @click="isUserMenuOpen = false"
                  class="w-full text-left px-3 py-2 rounded-xl text-xs font-semibold flex items-center gap-2 text-rose-400 hover:bg-rose-500/10 hover:text-rose-300 transition"
                >
                  <LogOut class="w-3.5 h-3.5 text-rose-400" />
                  <span>Keluar Sesi (Logout)</span>
                </Link>
              </div>
            </div>
          </div>
        </div>
      </header>

      <!-- Main Content Dynamic Slot -->
      <main class="flex-1 p-4 lg:p-8 w-full max-w-7xl mx-auto space-y-8">
        <slot />
      </main>

      <!-- Bottom System Status & Copyright Footer -->
      <footer class="border-t border-slate-800/80 py-4 px-6 text-center text-xs text-slate-300 bg-[#070d1d] flex flex-col sm:flex-row items-center justify-between gap-2">
        <div class="flex items-center gap-2">
          <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
          <span>SIMOD Enterprise Multi-Tenant &copy; 2026 Divisi Humas Polri & Satwil Polda Jajaran</span>
        </div>
        <div class="flex items-center gap-3 text-slate-300">
          <span>Komando / Satker: <strong class="text-amber-400">{{ selectedCode === 'ALL' ? 'DIVHUMAS POLRI (ORKESTRASI NASIONAL)' : (selectedPolda?.name || selectedCode) }}</strong></span>
          <span>&bull;</span>
          <span>Laravel 12 + Inertia.js v2 + Vue 3</span>
        </div>
      </footer>
    </div>
  </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 5px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(148, 163, 184, 0.2);
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgba(245, 158, 11, 0.5);
}
</style>