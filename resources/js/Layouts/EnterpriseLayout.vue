<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
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
  ChevronDown,
  Bell,
  Search,
  ExternalLink,
  Sparkles,
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

const isTenantMenuOpen = ref(false);

const switchTenant = (code) => {
  isTenantMenuOpen.value = false;
  router.get('/', { polda: code }, { preserveScroll: true });
};
</script>

<template>
  <div class="min-h-screen bg-[#070d1d] text-slate-100 flex flex-col font-sans selection:bg-amber-400 selection:text-slate-950">
    <!-- Top Header -->
    <header class="sticky top-0 z-50 backdrop-blur-xl bg-[#0b1426]/90 border-b border-slate-800/80 px-4 lg:px-8 py-3 flex items-center justify-between shadow-2xl">
      <!-- Left: Brand & Tenant Switcher -->
      <div class="flex items-center gap-4 lg:gap-6">
        <!-- Logo -->
        <Link href="/" class="flex items-center gap-3 group">
          <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-amber-500 to-amber-300 p-0.5 shadow-lg shadow-amber-500/20 group-hover:scale-105 transition duration-200 flex items-center justify-center">
            <div class="w-full h-full bg-[#0a1224] rounded-[14px] flex items-center justify-center">
              <Shield class="w-5 h-5 text-amber-400" />
            </div>
          </div>
          <div>
            <div class="flex items-center gap-2">
              <span class="font-extrabold text-base tracking-tight text-white group-hover:text-amber-400 transition">SIMOD ENTERPRISE</span>
              <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-amber-400 text-slate-950 shadow-xs">Multi-Tenant v2.0</span>
            </div>
            <p class="text-[11px] text-slate-400 font-medium">Sistem Informasi Manajemen Operasional Dokumentasi POLRI</p>
          </div>
        </Link>

        <!-- Divider -->
        <div class="hidden md:block h-6 w-px bg-slate-800"></div>

        <!-- Tenant Switcher Dropdown (Polda Switcher) -->
        <div class="relative">
          <button
            @click="isTenantMenuOpen = !isTenantMenuOpen"
            class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-900/90 hover:bg-slate-800/90 border border-slate-700/80 text-xs font-bold text-slate-200 transition shadow-sm hover:border-amber-400/50"
          >
            <Building2 class="w-3.5 h-3.5 text-amber-400" />
            <span>{{ selectedCode === 'ALL' ? 'Mabes Polri (Semua Polda)' : (selectedPolda?.name || 'Pilih Wilayah Polda') }}</span>
            <ChevronDown class="w-3 h-3 text-slate-400 transition duration-200" :class="{ 'rotate-180': isTenantMenuOpen }" />
          </button>

          <!-- Dropdown Menu -->
          <div
            v-if="isTenantMenuOpen"
            class="absolute left-0 mt-2 w-72 rounded-2xl bg-[#0b1426] border border-slate-700/80 shadow-2xl p-2 z-50 backdrop-blur-2xl"
          >
            <div class="px-3 py-2 text-[10px] font-extrabold uppercase tracking-wider text-slate-400 border-b border-slate-800/80">
              Pilih Wilayah Hukum Satwil
            </div>

            <!-- All Poldas (Mabes View) -->
            <button
              @click="switchTenant('ALL')"
              class="w-full text-left px-3 py-2.5 rounded-xl text-xs font-semibold flex items-center justify-between transition mt-1"
              :class="selectedCode === 'ALL' ? 'bg-amber-400 text-slate-950 font-black shadow-sm' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white'"
            >
              <div class="flex items-center gap-2">
                <Layers class="w-4 h-4" />
                <span>Konsolidasi Nasional (Mabes)</span>
              </div>
              <span class="text-[10px] opacity-80">34 Polda</span>
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
                <span>{{ p.name }}</span>
              </div>
              <span class="text-[10px] px-2 py-0.5 rounded-full" :class="selectedCode === p.code ? 'bg-slate-950 text-amber-400' : 'bg-slate-800 text-slate-300'">
                {{ p.polres_count }} Polres
              </span>
            </button>
          </div>
        </div>
      </div>

      <!-- Right: Clock & User Profile -->
      <div class="flex items-center gap-3 md:gap-4">
        <!-- Live Clock -->
        <div class="hidden sm:flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900/60 border border-slate-800 text-xs font-mono text-slate-300">
          <Clock class="w-3.5 h-3.5 text-amber-400" />
          <span>{{ metrics.server_wita || 'WITA' }}</span>
        </div>

        <!-- Role Badge -->
        <div class="flex items-center gap-2 p-1.5 pr-3 rounded-xl bg-slate-900/80 border border-slate-800">
          <div class="w-7 h-7 rounded-lg bg-amber-400 text-slate-950 font-black text-xs flex items-center justify-center">
            P
          </div>
          <div class="text-left">
            <p class="text-xs font-bold text-white leading-tight">Admin Operasional</p>
            <p class="text-[10px] text-amber-400 font-semibold">{{ selectedCode === 'ALL' ? 'Mabes Polri' : (selectedPolda?.code || 'POLDA') }}</p>
          </div>
        </div>
      </div>
    </header>

    <!-- Main Content Area -->
    <main class="flex-1 p-4 lg:p-8 max-w-7xl mx-auto w-full space-y-8">
      <slot />
    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-800/80 py-6 px-4 text-center text-xs text-slate-400 bg-[#070d1d]">
      <p>
        SIMOD Enterprise Multi-Tenant &copy; 2026 Divisi Humas Polri & Bidang Humas Polda Jajaran.
        <span class="text-amber-400 font-semibold ml-2">Laravel 12 + Inertia.js v2 + Vue 3 + Lucide</span>
      </p>
    </footer>
  </div>
</template>
