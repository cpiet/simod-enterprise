<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
  ShieldAlert,
  KeyRound,
  History,
  Database,
  Eraser,
  Radio,
  Bot,
  SlidersHorizontal,
  Layers,
} from 'lucide-vue-next';

defineProps({ selectedCode: { type: String, default: 'SULBAR' } });
const page = usePage();

const iconMap = { ShieldAlert, KeyRound, History, Database, Eraser, Radio, Bot, SlidersHorizontal, Layers };
const resolveIcon = (icon) => (typeof icon === 'string' ? iconMap[icon] || Layers : icon || Layers);

// Cadangan bila navigasi dinamis belum tersedia (urutan sama dengan menu_items).
const fallbackTabs = [
  { name: 'Keamanan', href: '/security', icon: 'ShieldAlert', permission: 'security_center' },
  { name: 'Hak Akses', href: '/roles', icon: 'KeyRound', permission: 'roles_matrix_manage' },
  { name: 'Manajemen Menu', href: '/settings/menus', icon: 'SlidersHorizontal', permission: 'roles_matrix_manage' },
  { name: 'Audit Log', href: '/audit-logs', icon: 'History', permission: 'security_center' },
  { name: 'Backup Data', href: '/database/backup', icon: 'Database', permission: 'database_backup' },
  { name: 'Pembersihan Berita', href: '/maintenance/news-cleanup', icon: 'Eraser', permission: 'maintenance_ops' },
  { name: 'Sinkron RSS', href: '/maintenance/rss-sync', icon: 'Radio', permission: 'maintenance_ops' },
  { name: 'Mesin AI', href: '/settings/ai', icon: 'Bot', permission: 'security_center' },
];

const currentPath = computed(() => (page.url || '').split('?')[0].replace(/\/+$/, '') || '/');
const isActive = (href) => currentPath.value === href;
const userPermissions = computed(() => page.props.auth?.permissions || {});

const visibleTabs = computed(() => {
  const dynamicTabs = page.props.navigation?.securityTabs;
  if (Array.isArray(dynamicTabs) && dynamicTabs.length > 0) {
    return dynamicTabs;
  }
  return fallbackTabs.filter((t) => !t.permission || userPermissions.value[t.permission] !== false);
});
</script>

<template>
  <nav class="flex items-center gap-1.5 overflow-x-auto pb-1" aria-label="Sub-modul Administrasi & Sistem">
    <Link
      v-for="t in visibleTabs"
      :key="t.href"
      :href="t.href"
      :title="t.name"
      class="group flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition border shrink-0"
      :class="isActive(t.href)
        ? 'bg-amber-400 text-slate-950 border-amber-300 font-black shadow-md'
        : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 hover:text-slate-950 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 border-slate-200 dark:border-slate-700'"
    >
      <component :is="resolveIcon(t.icon)" class="w-4 h-4 shrink-0" :class="isActive(t.href) ? 'text-slate-950' : 'text-slate-400 group-hover:text-amber-500'" />
      <span>{{ t.name }}</span>
    </Link>
  </nav>
</template>
