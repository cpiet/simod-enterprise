<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ShieldAlert, KeyRound, History, Database, Eraser, Wrench, Mail, Radio } from 'lucide-vue-next';

const props = defineProps({ selectedCode: { type: String, default: 'SULBAR' } });
const page = usePage();

const tabs = [
  { name: 'Keamanan', href: '/security', icon: ShieldAlert },
  { name: 'Hak Akses', href: '/roles', icon: KeyRound },
  { name: 'Audit Log', href: '/audit-logs', icon: History },
  { name: 'Backup Data', href: '/database/backup', icon: Database },
  { name: 'Pembersihan Berita', href: '/maintenance/news-cleanup', icon: Eraser },
  { name: 'Otomasi Sinkron RSS', href: '/maintenance/rss-sync', icon: Radio },
  { name: 'Mode Pemeliharaan', href: '/maintenance', icon: Wrench },
  { name: 'Konfigurasi Mail', href: '/settings/mail', icon: Mail },
];

const currentPath = computed(() => (page.url || '').split('?')[0].replace(/\/+$/, '') || '/');
const isActive = (href) => currentPath.value === href;
</script>

<template>
  <div class="space-y-2">
    <div class="flex items-center gap-2">
      <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-rose-500/20 text-rose-300 border border-rose-500/40">
        Keamanan &amp; Pemeliharaan
      </span>
    </div>
    <div class="p-2 rounded-2xl bg-[#08101e] border border-slate-800 shadow-xl">
      <div class="grid grid-cols-2 sm:grid-cols-4 xl:grid-cols-8 gap-2">
        <Link
          v-for="t in tabs"
          :key="t.href"
          :href="`${t.href}?polda=${props.selectedCode}`"
          class="flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl text-[11px] md:text-xs font-extrabold text-center leading-tight transition"
          :class="isActive(t.href)
            ? 'bg-amber-500/20 text-amber-400 border-2 border-amber-500 shadow-[0_0_15px_rgba(245,158,11,0.25)]'
            : 'bg-slate-900/90 text-slate-200 hover:text-white hover:bg-slate-800 border border-slate-700/80'"
        >
          <component :is="t.icon" class="w-4 h-4 shrink-0" :class="isActive(t.href) ? 'text-amber-400' : 'text-slate-400'" />
          <span>{{ t.name }}</span>
        </Link>
      </div>
    </div>
  </div>
</template>
