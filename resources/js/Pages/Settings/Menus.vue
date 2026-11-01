<script setup>
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import SecurityMaintenanceTabs from '@/Components/SecurityMaintenanceTabs.vue';
import {
  SlidersHorizontal,
  Plus,
  Edit3,
  Trash2,
  RotateCcw,
  ArrowUp,
  ArrowDown,
  CheckCircle2,
  XCircle,
  Shield,
  ShieldAlert,
  Radio,
  Building2,
  Flame,
  Layers,
  Users,
  Share2,
  CheckSquare,
  Menu,
  LayoutDashboard,
  Image as ImageIcon,
  FileBarChart2,
  KeyRound,
  Mail,
  Database,
  UserCheck,
  PlusCircle,
  History,
  Eraser,
  Wrench,
  Bot,
  Globe,
  Lock,
  Bell,
  X,
  AlertTriangle,
  Sparkles,
  FolderTree,
  Eye,
  EyeOff,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: { type: Array, default: () => [] },
  selectedPolda: { type: Object, default: null },
  selectedCode: { type: String, default: 'ALL' },
  sidebarPillars: { type: Array, default: () => [] },
  securityTabs: { type: Array, default: () => [] },
  availableModules: { type: Array, default: () => [] },
  availableRoles: { type: Array, default: () => [] },
  availableIcons: { type: Array, default: () => [] },
  stats: { type: Object, default: () => ({}) },
});

const page = usePage();

const iconMap = {
  LayoutDashboard,
  Radio,
  Share2,
  Flame,
  PlusCircle,
  CheckCircle2,
  CheckSquare,
  Image: ImageIcon,
  FileBarChart2,
  Users,
  Building2,
  ShieldAlert,
  Shield,
  UserCheck,
  KeyRound,
  SlidersHorizontal,
  History,
  Database,
  Eraser,
  Wrench,
  Mail,
  Bot,
  Layers,
  Globe,
  Lock,
  Bell,
  Menu,
};

const resolveIcon = (name) => iconMap[name] || Layers;

const activeSection = ref('sidebar'); // 'sidebar' | 'security_tabs'
const isSubmitting = ref(false);

// Modal State: Add / Edit
const isFormModalOpen = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const form = ref({
  title: '',
  description: '',
  route_or_url: '',
  icon: 'Layers',
  parent_id: '',
  menu_location: 'sidebar',
  order_index: 1,
  permission_required: '',
  role_required: '',
  badge_text: '',
  badge_key: '',
  badge_color: '',
  exact_match: false,
  is_active: true,
});

// Modal State: Delete & Reset Confirmations
const deleteTarget = ref(null);
const isResetModalOpen = ref(false);

const parentOptions = computed(() => {
  if (form.value.menu_location !== 'sidebar') return [];
  const options = [];
  for (const pillar of props.sidebarPillars) {
    if (isEditing.value && pillar.id === editingId.value) continue;
    options.push({
      id: pillar.id,
      label: `📁 [PILAR] ${pillar.title}`,
    });
    if (Array.isArray(pillar.children)) {
      for (const child of pillar.children) {
        if (isEditing.value && child.id === editingId.value) continue;
        options.push({
          id: child.id,
          label: `   ↳ ${child.title} (${child.route_or_url || '#'})`,
        });
      }
    }
  }
  return options;
});

const openAddModal = (preset = {}) => {
  isEditing.value = false;
  editingId.value = null;
  form.value = {
    title: '',
    description: '',
    route_or_url: preset.route_or_url ?? '',
    icon: preset.icon ?? 'Layers',
    parent_id: preset.parent_id ?? '',
    menu_location: preset.menu_location ?? activeSection.value,
    order_index: preset.order_index ?? 1,
    permission_required: '',
    role_required: '',
    badge_text: '',
    badge_key: '',
    badge_color: '',
    exact_match: false,
    is_active: true,
  };
  isFormModalOpen.value = true;
};

const openEditModal = (item) => {
  isEditing.value = true;
  editingId.value = item.id;
  form.value = {
    title: item.title || '',
    description: item.description || '',
    route_or_url: item.route_or_url || '',
    icon: item.icon || 'Layers',
    parent_id: item.parent_id || '',
    menu_location: item.menu_location || 'sidebar',
    order_index: item.order_index ?? 1,
    permission_required: item.permission_required || '',
    role_required: item.role_required || '',
    badge_text: item.badge_text || '',
    badge_key: item.badge_key || '',
    badge_color: item.badge_color || '',
    exact_match: Boolean(item.exact_match),
    is_active: Boolean(item.is_active),
  };
  isFormModalOpen.value = true;
};

const submitForm = () => {
  isSubmitting.value = true;
  const payload = {
    ...form.value,
    parent_id: form.value.parent_id || null,
  };

  if (isEditing.value && editingId.value) {
    router.put(`/settings/menus/${editingId.value}`, payload, {
      preserveScroll: true,
      onSuccess: () => {
        isFormModalOpen.value = false;
      },
      onFinish: () => {
        isSubmitting.value = false;
      },
    });
  } else {
    router.post('/settings/menus', payload, {
      preserveScroll: true,
      onSuccess: () => {
        isFormModalOpen.value = false;
      },
      onFinish: () => {
        isSubmitting.value = false;
      },
    });
  }
};

const toggleActive = (item) => {
  router.post(`/settings/menus/${item.id}/toggle`, {}, { preserveScroll: true });
};

const moveItem = (item, direction) => {
  router.post(`/settings/menus/${item.id}/move`, { direction }, { preserveScroll: true });
};

const confirmDelete = (item) => {
  deleteTarget.value = item;
};

const executeDelete = () => {
  if (!deleteTarget.value) return;
  isSubmitting.value = true;
  router.delete(`/settings/menus/${deleteTarget.value.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      deleteTarget.value = null;
    },
    onFinish: () => {
      isSubmitting.value = false;
    },
  });
};

const executeReset = () => {
  isSubmitting.value = true;
  router.post('/settings/menus/reset', {}, {
    preserveScroll: true,
    onSuccess: () => {
      isResetModalOpen.value = false;
    },
    onFinish: () => {
      isSubmitting.value = false;
    },
  });
};
</script>

<template>
  <Head title="Manajemen Menu & Navigasi Dinamis" />

  <EnterpriseLayout
    :all-poldas="allPoldas"
    :selected-polda="selectedPolda"
    :selected-code="selectedCode"
  >
    <div class="space-y-6">
      <!-- Sub-Navigation Tabs (10 Unified Security & System Modules) -->
      <SecurityMaintenanceTabs :selected-code="selectedCode" />

      <!-- Flash Feedback Banner -->
      <div
        v-if="page.props.flash?.success"
        class="p-4 rounded-2xl bg-emerald-500/15 border border-emerald-500/40 text-emerald-300 text-xs font-bold flex items-center justify-between shadow-lg"
      >
        <div class="flex items-center gap-2.5">
          <CheckCircle2 class="w-4 h-4 text-emerald-400 shrink-0" />
          <span>{{ page.props.flash.success }}</span>
        </div>
      </div>

      <div
        v-if="page.props.flash?.error"
        class="p-4 rounded-2xl bg-rose-500/15 border border-rose-500/40 text-rose-300 text-xs font-bold flex items-center justify-between shadow-lg"
      >
        <div class="flex items-center gap-2.5">
          <AlertTriangle class="w-4 h-4 text-rose-400 shrink-0" />
          <span>{{ page.props.flash.error }}</span>
        </div>
      </div>

      <!-- Hero Header Card -->
      <div class="rounded-3xl bg-gradient-to-br from-[#0b1426] via-[#0d1930] to-[#091122] border border-slate-800 p-6 md:p-8 shadow-2xl">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
          <div class="space-y-2 max-w-3xl">
            <div class="flex flex-wrap items-center gap-2">
              <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-400 text-slate-950">
                DYNAMIC NAVIGATION ENGINE
              </span>
              <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">
                Database-Driven &bull; RBAC Integrated
              </span>
            </div>
            <h1 class="text-xl md:text-2xl font-black text-white tracking-tight flex items-center gap-2.5">
              <SlidersHorizontal class="w-6 h-6 text-amber-400 shrink-0" />
              <span>Manajemen Menu &amp; Arsitektur Navigasi Dinamis</span>
            </h1>
            <p class="text-xs text-slate-300 leading-relaxed">
              Kelola struktur menu sidebar 4 pilar, sub-menu bertingkat, urutan tampil (<em>ordering</em>), ikon visual, lencana metrik, serta pemetaan hak akses (RBAC) secara langsung tanpa menyentuh kode program.
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-3">
            <button
              type="button"
              @click="isResetModalOpen = true"
              class="px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-xs font-bold text-slate-300 hover:text-rose-300 flex items-center gap-2 transition cursor-pointer"
              title="Kembalikan susunan menu ke standar awal SIMOD Enterprise"
            >
              <RotateCcw class="w-3.5 h-3.5 text-rose-400" />
              <span>Reset ke Default</span>
            </button>

            <button
              type="button"
              @click="openAddModal()"
              class="px-5 py-2.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black flex items-center gap-2 shadow-lg shadow-amber-400/20 transition cursor-pointer"
            >
              <Plus class="w-4 h-4" />
              <span>Tambah Menu / Pilar Baru</span>
            </button>
          </div>
        </div>
      </div>

      <!-- KPI Summary Cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
          <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
            <span>Total Item Navigasi</span>
            <FolderTree class="w-4 h-4 text-amber-400" />
          </div>
          <p class="text-2xl font-black text-white font-mono">{{ stats.total_items || 0 }}</p>
          <p class="text-[11px] text-slate-400">Tersimpan di tabel <code class="text-amber-300">menu_items</code></p>
        </div>

        <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
          <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
            <span>Status Menu Aktif</span>
            <Eye class="w-4 h-4 text-emerald-400" />
          </div>
          <p class="text-2xl font-black text-emerald-400 font-mono">{{ stats.active_items || 0 }}</p>
          <p class="text-[11px] text-slate-400">Dirender otomatis sesuai RBAC</p>
        </div>

        <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
          <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
            <span>Menu Nonaktif / Disembunyikan</span>
            <EyeOff class="w-4 h-4 text-rose-400" />
          </div>
          <p class="text-2xl font-black text-rose-400 font-mono">{{ stats.inactive_items || 0 }}</p>
          <p class="text-[11px] text-slate-400">Dapat diaktifkan kembali 1-klik</p>
        </div>

        <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
          <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
            <span>Pilar &amp; Sub-Tab Sistem</span>
            <Sparkles class="w-4 h-4 text-sky-400" />
          </div>
          <p class="text-2xl font-black text-white font-mono">
            {{ stats.pillars_count || 4 }} <span class="text-xs font-sans text-slate-400">Pilar</span> &bull; {{ stats.tabs_count || 10 }} <span class="text-xs font-sans text-slate-400">Tab</span>
          </p>
          <p class="text-[11px] text-slate-400">Mendukung hierarki parent-child</p>
        </div>
      </div>

      <!-- Location Switcher Tabs -->
      <div class="flex flex-wrap items-center justify-between gap-4 bg-[#0b1426] p-2 rounded-2xl border border-slate-800">
        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            @click="activeSection = 'sidebar'"
            class="px-4 py-2.5 rounded-xl text-xs font-black transition flex items-center gap-2 cursor-pointer"
            :class="activeSection === 'sidebar'
              ? 'bg-amber-400 text-slate-950 shadow-md'
              : 'text-slate-300 hover:text-white hover:bg-slate-800'"
          >
            <Menu class="w-4 h-4" />
            <span>1. Navigasi Sidebar Utama ({{ sidebarPillars.length }} Pilar)</span>
          </button>

          <button
            type="button"
            @click="activeSection = 'security_tabs'"
            class="px-4 py-2.5 rounded-xl text-xs font-black transition flex items-center gap-2 cursor-pointer"
            :class="activeSection === 'security_tabs'
              ? 'bg-amber-400 text-slate-950 shadow-md'
              : 'text-slate-300 hover:text-white hover:bg-slate-800'"
          >
            <ShieldAlert class="w-4 h-4" />
            <span>2. Tab Keamanan &amp; Pemeliharaan ({{ securityTabs.length }} Tab)</span>
          </button>
        </div>

        <div class="pr-2 text-[11px] text-slate-400 hidden md:block">
          Gunakan tombol <strong class="text-amber-300">&uarr; / &darr;</strong> untuk mengatur urutan atau klik <strong class="text-emerald-400">Status</strong> untuk menyembunyikan modul.
        </div>
      </div>

      <!-- SECTION 1: SIDEBAR PILLARS & NESTED ITEMS -->
      <div v-if="activeSection === 'sidebar'" class="space-y-6">
        <div
          v-for="(pillar, pIdx) in sidebarPillars"
          :key="pillar.id"
          class="rounded-3xl bg-[#0b1426] border border-slate-800 overflow-hidden shadow-xl"
        >
          <!-- Pillar Header Row -->
          <div class="px-6 py-4 bg-slate-900/90 border-b border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
              <span class="w-8 h-8 rounded-xl bg-amber-400/15 border border-amber-400/40 text-amber-400 font-mono text-xs font-black flex items-center justify-center">
                #{{ pillar.order_index }}
              </span>
              <div>
                <div class="flex items-center gap-2">
                  <h2 class="text-sm font-black uppercase tracking-wider text-white">
                    {{ pillar.title }}
                  </h2>
                  <span
                    class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                    :class="pillar.is_active ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-400 border border-rose-500/30'"
                  >
                    {{ pillar.is_active ? 'Pilar Aktif' : 'Nonaktif' }}
                  </span>
                </div>
                <p class="text-[11px] text-slate-400">
                  {{ pillar.description || 'Kelompok navigasi utama sidebar' }} &bull; {{ pillar.children?.length || 0 }} Modul
                </p>
              </div>
            </div>

            <!-- Pillar Actions -->
            <div class="flex flex-wrap items-center gap-2">
              <button
                type="button"
                @click="moveItem(pillar, 'up')"
                :disabled="pIdx === 0"
                class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 disabled:opacity-30 transition cursor-pointer"
                title="Geser Pilar ke Atas"
              >
                <ArrowUp class="w-3.5 h-3.5" />
              </button>
              <button
                type="button"
                @click="moveItem(pillar, 'down')"
                :disabled="pIdx === sidebarPillars.length - 1"
                class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 disabled:opacity-30 transition cursor-pointer"
                title="Geser Pilar ke Bawah"
              >
                <ArrowDown class="w-3.5 h-3.5" />
              </button>

              <button
                type="button"
                @click="openAddModal({ parent_id: pillar.id, menu_location: 'sidebar', order_index: (pillar.children?.length || 0) + 1 })"
                class="px-3 py-1.5 rounded-xl bg-amber-400/15 hover:bg-amber-400/25 border border-amber-400/40 text-amber-300 text-xs font-bold flex items-center gap-1.5 transition cursor-pointer"
              >
                <Plus class="w-3.5 h-3.5" />
                <span>Tambah Modul</span>
              </button>

              <button
                type="button"
                @click="toggleActive(pillar)"
                class="px-3 py-1.5 rounded-xl border text-xs font-bold transition cursor-pointer"
                :class="pillar.is_active
                  ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300 hover:bg-rose-500/15 hover:text-rose-300'
                  : 'bg-slate-800 border-slate-700 text-slate-400 hover:text-emerald-300'"
              >
                {{ pillar.is_active ? 'Aktif' : 'Nonaktif' }}
              </button>

              <button
                type="button"
                @click="openEditModal(pillar)"
                class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-amber-400 transition cursor-pointer"
                title="Edit Pilar"
              >
                <Edit3 class="w-3.5 h-3.5" />
              </button>

              <button
                type="button"
                @click="confirmDelete(pillar)"
                class="p-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 text-rose-400 transition cursor-pointer ml-1"
                title="Hapus Pilar"
              >
                <Trash2 class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>

          <!-- Pillar Child Items Table -->
          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="border-b border-slate-800/80 text-[10px] font-black uppercase tracking-wider text-slate-400 bg-slate-950/40">
                  <th class="py-3 px-4 w-20">Urutan</th>
                  <th class="py-3 px-4">Nama Modul &amp; Deskripsi</th>
                  <th class="py-3 px-4">Rute / Endpoint URL</th>
                  <th class="py-3 px-4">Otoritas RBAC</th>
                  <th class="py-3 px-4">Lencana (Badge)</th>
                  <th class="py-3 px-4 text-center">Status</th>
                  <th class="py-3 px-4 text-right">Aksi Pengaturan</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800/60 text-xs">
                <template v-for="(item, cIdx) in pillar.children" :key="item.id">
                  <tr class="hover:bg-slate-900/50 transition" :class="{ 'opacity-50': !item.is_active }">
                    <td class="py-3.5 px-4 font-mono font-bold text-slate-300">
                      <div class="flex items-center gap-1">
                        <span class="px-2 py-0.5 rounded bg-slate-800 text-amber-400">#{{ item.order_index }}</span>
                        <div class="flex flex-col">
                          <button
                            type="button"
                            @click="moveItem(item, 'up')"
                            :disabled="cIdx === 0"
                            class="text-slate-400 hover:text-amber-400 disabled:opacity-20 cursor-pointer"
                            title="Naikkan Urutan"
                          >
                            <ArrowUp class="w-3 h-3" />
                          </button>
                          <button
                            type="button"
                            @click="moveItem(item, 'down')"
                            :disabled="cIdx === pillar.children.length - 1"
                            class="text-slate-400 hover:text-amber-400 disabled:opacity-20 cursor-pointer"
                            title="Turunkan Urutan"
                          >
                            <ArrowDown class="w-3 h-3" />
                          </button>
                        </div>
                      </div>
                    </td>

                    <td class="py-3.5 px-4">
                      <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-slate-900 border border-slate-700 flex items-center justify-center text-amber-400 shrink-0">
                          <component :is="resolveIcon(item.icon)" class="w-4 h-4" />
                        </div>
                        <div>
                          <p class="font-bold text-white">{{ item.title }}</p>
                          <p class="text-[11px] text-slate-400">{{ item.description || '-' }}</p>
                        </div>
                      </div>
                    </td>

                    <td class="py-3.5 px-4 font-mono">
                      <span class="px-2.5 py-1 rounded-lg bg-slate-900 border border-slate-800 text-amber-300 text-[11px]">
                        {{ item.route_or_url || '#' }}
                      </span>
                    </td>

                    <td class="py-3.5 px-4">
                      <div class="flex flex-wrap gap-1">
                        <span
                          v-if="item.permission_required"
                          class="px-2 py-0.5 rounded bg-sky-500/15 border border-sky-500/30 text-sky-300 font-mono text-[10px]"
                        >
                          🔑 {{ item.permission_required }}
                        </span>
                        <span
                          v-else
                          class="px-2 py-0.5 rounded bg-slate-800 text-slate-400 text-[10px]"
                        >
                          Semua Personel Login
                        </span>
                        <span
                          v-if="item.role_required"
                          class="px-2 py-0.5 rounded bg-amber-500/15 border border-amber-500/30 text-amber-300 font-mono text-[10px]"
                        >
                          👤 {{ item.role_required }}
                        </span>
                      </div>
                    </td>

                    <td class="py-3.5 px-4">
                      <span
                        v-if="item.badge_text"
                        class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-emerald-500/20 text-emerald-300 border border-emerald-500/30"
                      >
                        {{ item.badge_text }}
                      </span>
                      <span
                        v-else-if="item.badge_key"
                        class="px-2 py-0.5 rounded text-[10px] font-mono bg-amber-500/15 text-amber-300 border border-amber-500/30"
                      >
                        ⚡ {{ item.badge_key }}
                      </span>
                      <span v-else class="text-slate-500 text-[11px]">-</span>
                    </td>

                    <td class="py-3.5 px-4 text-center">
                      <button
                        type="button"
                        @click="toggleActive(item)"
                        class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase transition cursor-pointer"
                        :class="item.is_active
                          ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 hover:bg-rose-500/20 hover:text-rose-300'
                          : 'bg-rose-500/20 text-rose-300 border border-rose-500/40 hover:bg-emerald-500/20 hover:text-emerald-300'"
                      >
                        {{ item.is_active ? 'Aktif' : 'Nonaktif' }}
                      </button>
                    </td>

                    <td class="py-3.5 px-4 text-right">
                      <div class="inline-flex items-center gap-1.5">
                        <button
                          type="button"
                          @click="openAddModal({ parent_id: item.id, menu_location: 'sidebar', order_index: (item.children?.length || 0) + 1 })"
                          class="px-2 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-[10px] font-bold transition cursor-pointer"
                          title="Tambah Sub-Menu di bawah item ini"
                        >
                          + Sub
                        </button>
                        <button
                          type="button"
                          @click="openEditModal(item)"
                          class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-amber-400 transition cursor-pointer"
                          title="Edit Modul"
                        >
                          <Edit3 class="w-3.5 h-3.5" />
                        </button>
                        <button
                          type="button"
                          @click="confirmDelete(item)"
                          class="p-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/25 border border-rose-500/20 text-rose-400 transition cursor-pointer ml-1"
                          title="Hapus Modul"
                        >
                          <Trash2 class="w-3.5 h-3.5" />
                        </button>
                      </div>
                    </td>
                  </tr>

                  <!-- Nested Sub-Items Row (Level 3) -->
                  <tr
                    v-for="sub in (item.children || [])"
                    :key="sub.id"
                    class="bg-slate-950/60 hover:bg-slate-900/40 transition text-xs"
                    :class="{ 'opacity-50': !sub.is_active }"
                  >
                    <td class="py-2.5 px-4 pl-8 font-mono text-[11px] text-slate-400">
                      ↳ #{{ sub.order_index }}
                    </td>
                    <td class="py-2.5 px-4 pl-8">
                      <div class="flex items-center gap-2.5">
                        <component :is="resolveIcon(sub.icon)" class="w-3.5 h-3.5 text-amber-400/80" />
                        <div>
                          <span class="font-bold text-slate-200">{{ sub.title }}</span>
                          <span v-if="sub.description" class="block text-[10px] text-slate-400">{{ sub.description }}</span>
                        </div>
                      </div>
                    </td>
                    <td class="py-2.5 px-4 font-mono text-[11px] text-amber-300/90">
                      {{ sub.route_or_url || '#' }}
                    </td>
                    <td class="py-2.5 px-4 text-[10px] font-mono text-sky-300">
                      {{ sub.permission_required || 'Publik Login' }}
                    </td>
                    <td class="py-2.5 px-4 text-[10px]">
                      {{ sub.badge_text || sub.badge_key || '-' }}
                    </td>
                    <td class="py-2.5 px-4 text-center">
                      <button
                        type="button"
                        @click="toggleActive(sub)"
                        class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase cursor-pointer"
                        :class="sub.is_active ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300'"
                      >
                        {{ sub.is_active ? 'Aktif' : 'Nonaktif' }}
                      </button>
                    </td>
                    <td class="py-2.5 px-4 text-right">
                      <div class="inline-flex items-center gap-1.5">
                        <button
                          type="button"
                          @click="openEditModal(sub)"
                          class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-amber-400 cursor-pointer"
                        >
                          <Edit3 class="w-3 h-3" />
                        </button>
                        <button
                          type="button"
                          @click="confirmDelete(sub)"
                          class="p-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/25 text-rose-400 cursor-pointer"
                        >
                          <Trash2 class="w-3 h-3" />
                        </button>
                      </div>
                    </td>
                  </tr>
                </template>

                <tr v-if="!pillar.children || pillar.children.length === 0">
                  <td colspan="7" class="py-8 text-center text-slate-400 text-xs">
                    Belum ada modul pada pilar ini. Klik <strong>+ Tambah Modul</strong> untuk menambahkan.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- SECTION 2: SECURITY & MAINTENANCE SUB-TABS -->
      <div v-else class="rounded-3xl bg-[#0b1426] border border-slate-800 overflow-hidden shadow-xl">
        <div class="px-6 py-4 bg-slate-900/90 border-b border-slate-800 flex items-center justify-between">
          <div>
            <h2 class="text-sm font-black uppercase tracking-wider text-white">
              Sub-Modul Keamanan &amp; Pemeliharaan Terpadu
            </h2>
            <p class="text-[11px] text-slate-400">
              Barisan tab navigasi horizontal pada halaman administrasi sistem
            </p>
          </div>
          <button
            type="button"
            @click="openAddModal({ menu_location: 'security_tabs', order_index: securityTabs.length + 1 })"
            class="px-3.5 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black flex items-center gap-1.5 transition cursor-pointer"
          >
            <Plus class="w-3.5 h-3.5" />
            <span>Tambah Tab Baru</span>
          </button>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="border-b border-slate-800/80 text-[10px] font-black uppercase tracking-wider text-slate-400 bg-slate-950/40">
                <th class="py-3 px-4 w-24">Urutan</th>
                <th class="py-3 px-4">Label Tab</th>
                <th class="py-3 px-4">Rute / URL</th>
                <th class="py-3 px-4">Izin RBAC</th>
                <th class="py-3 px-4 text-center">Status</th>
                <th class="py-3 px-4 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60 text-xs">
              <tr
                v-for="(tab, tIdx) in securityTabs"
                :key="tab.id"
                class="hover:bg-slate-900/50 transition"
                :class="{ 'opacity-50': !tab.is_active }"
              >
                <td class="py-3.5 px-4 font-mono font-bold">
                  <div class="flex items-center gap-1.5">
                    <span class="px-2 py-0.5 rounded bg-slate-800 text-amber-400">#{{ tab.order_index }}</span>
                    <button
                      type="button"
                      @click="moveItem(tab, 'up')"
                      :disabled="tIdx === 0"
                      class="p-1 text-slate-400 hover:text-amber-400 disabled:opacity-20 cursor-pointer"
                    >
                      <ArrowUp class="w-3 h-3" />
                    </button>
                    <button
                      type="button"
                      @click="moveItem(tab, 'down')"
                      :disabled="tIdx === securityTabs.length - 1"
                      class="p-1 text-slate-400 hover:text-amber-400 disabled:opacity-20 cursor-pointer"
                    >
                      <ArrowDown class="w-3 h-3" />
                    </button>
                  </div>
                </td>
                <td class="py-3.5 px-4">
                  <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-slate-900 border border-slate-700 flex items-center justify-center text-amber-400">
                      <component :is="resolveIcon(tab.icon)" class="w-4 h-4" />
                    </div>
                    <span class="font-bold text-white">{{ tab.title }}</span>
                  </div>
                </td>
                <td class="py-3.5 px-4 font-mono text-amber-300">
                  {{ tab.route_or_url }}
                </td>
                <td class="py-3.5 px-4 font-mono text-[11px] text-sky-300">
                  {{ tab.permission_required || 'Semua Admin' }}
                </td>
                <td class="py-3.5 px-4 text-center">
                  <button
                    type="button"
                    @click="toggleActive(tab)"
                    class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase cursor-pointer"
                    :class="tab.is_active ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' : 'bg-rose-500/20 text-rose-300 border border-rose-500/40'"
                  >
                    {{ tab.is_active ? 'Aktif' : 'Nonaktif' }}
                  </button>
                </td>
                <td class="py-3.5 px-4 text-right">
                  <div class="inline-flex items-center gap-1.5">
                    <button
                      type="button"
                      @click="openEditModal(tab)"
                      class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-amber-400 cursor-pointer"
                    >
                      <Edit3 class="w-3.5 h-3.5" />
                    </button>
                    <button
                      type="button"
                      @click="confirmDelete(tab)"
                      class="p-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/25 text-rose-400 cursor-pointer ml-1"
                    >
                      <Trash2 class="w-3.5 h-3.5" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ===================================================================== -->
    <!-- MODAL 1: TAMBAH / EDIT MENU DINAMIS                                   -->
    <!-- ===================================================================== -->
    <div
      v-if="isFormModalOpen"
      class="fixed inset-0 z-50 bg-black/75 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto"
    >
      <div class="w-full max-w-2xl rounded-3xl bg-[#0b1426] border border-slate-700 shadow-2xl overflow-hidden my-8">
        <div class="px-6 py-4 bg-slate-900 border-b border-slate-800 flex items-center justify-between">
          <div class="flex items-center gap-2.5">
            <SlidersHorizontal class="w-5 h-5 text-amber-400" />
            <h3 class="text-base font-black text-white">
              {{ isEditing ? 'Edit Konfigurasi Menu / Navigasi' : 'Tambah Menu / Pilar Navigasi Baru' }}
            </h3>
          </div>
          <button
            type="button"
            @click="isFormModalOpen = false"
            class="p-1.5 rounded-xl bg-slate-800 text-slate-400 hover:text-white cursor-pointer"
          >
            <X class="w-4 h-4" />
          </button>
        </div>

        <form @submit.prevent="submitForm" class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Lokasi Navigasi -->
            <div>
              <label class="block text-xs font-bold text-slate-200 mb-1">Lokasi Menu</label>
              <select
                v-model="form.menu_location"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs font-bold text-white focus:border-amber-400 outline-none"
              >
                <option value="sidebar">Sidebar Utama (4 Pilar &amp; Modul)</option>
                <option value="security_tabs">Tab Horizontal Keamanan &amp; Sistem</option>
              </select>
            </div>

            <!-- Parent / Induk Menu -->
            <div v-if="form.menu_location === 'sidebar'">
              <label class="block text-xs font-bold text-slate-200 mb-1">Induk / Hierarki Parent</label>
              <select
                v-model="form.parent_id"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs font-bold text-white focus:border-amber-400 outline-none"
              >
                <option value="">-- Tanpa Induk (Jadikan Grup Pilar Utama) --</option>
                <option v-for="opt in parentOptions" :key="opt.id" :value="opt.id">
                  {{ opt.label }}
                </option>
              </select>
            </div>

            <!-- Urutan Tampil -->
            <div>
              <label class="block text-xs font-bold text-slate-200 mb-1">Urutan Tampil (Order Index)</label>
              <input
                v-model.number="form.order_index"
                type="number"
                min="0"
                max="999"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs font-bold text-white focus:border-amber-400 outline-none"
                required
              />
            </div>

            <!-- Judul Menu -->
            <div>
              <label class="block text-xs font-bold text-slate-200 mb-1">Judul / Label Menu *</label>
              <input
                v-model="form.title"
                type="text"
                placeholder="Contoh: Radar Media Online"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs font-bold text-white focus:border-amber-400 outline-none"
                required
              />
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Rute / URL -->
            <div>
              <label class="block text-xs font-bold text-slate-200 mb-1">Rute / URL Tujuan (Kosongkan jika Pilar)</label>
              <input
                v-model="form.route_or_url"
                type="text"
                placeholder="Contoh: /media-intelligence"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs font-mono text-amber-300 focus:border-amber-400 outline-none"
              />
            </div>

            <!-- Deskripsi Singkat -->
            <div>
              <label class="block text-xs font-bold text-slate-200 mb-1">Deskripsi Sub-Teks</label>
              <input
                v-model="form.description"
                type="text"
                placeholder="Contoh: Ringkasan Eksekutif & EWS"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs text-white focus:border-amber-400 outline-none"
              />
            </div>
          </div>

          <!-- Pemilih Ikon Visual -->
          <div>
            <label class="block text-xs font-bold text-slate-200 mb-1.5">Pilih Ikon Navigasi ({{ form.icon }})</label>
            <div class="grid grid-cols-7 sm:grid-cols-9 gap-1.5 p-2.5 rounded-2xl bg-slate-950 border border-slate-800 max-h-36 overflow-y-auto">
              <button
                v-for="ic in availableIcons"
                :key="ic"
                type="button"
                @click="form.icon = ic"
                class="p-2 rounded-xl flex flex-col items-center justify-center gap-1 border transition cursor-pointer"
                :class="form.icon === ic
                  ? 'bg-amber-400 text-slate-950 border-amber-400 font-black'
                  : 'bg-slate-900 text-slate-300 border-slate-800 hover:border-slate-600'"
                :title="ic"
              >
                <component :is="resolveIcon(ic)" class="w-4 h-4" />
              </button>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Izin RBAC -->
            <div>
              <label class="block text-xs font-bold text-slate-200 mb-1">Izin Modul RBAC (Permission Key)</label>
              <select
                v-model="form.permission_required"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs text-white focus:border-amber-400 outline-none"
              >
                <option value="">-- Terbuka untuk Semua Personel Login --</option>
                <option v-for="mod in availableModules" :key="mod.key" :value="mod.key">
                  [{{ mod.group }}] {{ mod.label }} ({{ mod.key }})
                </option>
              </select>
            </div>

            <!-- Role Required -->
            <div>
              <label class="block text-xs font-bold text-slate-200 mb-1">Batasi Role Khusus (Opsional)</label>
              <select
                v-model="form.role_required"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs text-white focus:border-amber-400 outline-none"
              >
                <option value="">-- Ikuti Matriks RBAC Standar --</option>
                <option value="kadiv_humas,super_admin_mabes">Hanya Komando Mabes Polri</option>
                <option v-for="r in availableRoles" :key="r.key" :value="r.key">
                  Hanya {{ r.label }} ({{ r.key }})
                </option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Static Badge -->
            <div>
              <label class="block text-xs font-bold text-slate-200 mb-1">Teks Lencana Statis (Opsional)</label>
              <input
                v-model="form.badge_text"
                type="text"
                placeholder="Contoh: LIVE, ONLINE, BARU"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs text-white focus:border-amber-400 outline-none"
              />
            </div>

            <!-- Dynamic Metric Badge -->
            <div>
              <label class="block text-xs font-bold text-slate-200 mb-1">Metrik Angka Dinamis (Opsional)</label>
              <select
                v-model="form.badge_key"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs text-white focus:border-amber-400 outline-none"
              >
                <option value="">-- Tanpa Angka Metrik --</option>
                <option value="total_issues">Total Laporan &amp; Isu (total_issues)</option>
                <option value="pending_verify">Antrean Verifikasi (pending_verify)</option>
                <option value="active_tasks">Disposisi Tugas Aktif (active_tasks)</option>
                <option value="total_publications">Total Publikasi (total_publications)</option>
              </select>
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-6 pt-2">
            <label class="inline-flex items-center gap-2 text-xs font-bold text-slate-200 cursor-pointer">
              <input v-model="form.is_active" type="checkbox" class="rounded text-amber-400 focus:ring-amber-400" />
              <span>Tampilkan / Aktifkan Menu Ini</span>
            </label>

            <label class="inline-flex items-center gap-2 text-xs font-bold text-slate-200 cursor-pointer">
              <input v-model="form.exact_match" type="checkbox" class="rounded text-amber-400 focus:ring-amber-400" />
              <span>Pencocokan URL Persis (Exact Route Match)</span>
            </label>
          </div>

          <div class="pt-4 border-t border-slate-800 flex items-center justify-end gap-3">
            <button
              type="button"
              @click="isFormModalOpen = false"
              class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-bold text-slate-300 cursor-pointer"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-5 py-2.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black shadow-lg shadow-amber-400/20 disabled:opacity-50 cursor-pointer"
            >
              {{ isSubmitting ? 'Menyimpan...' : (isEditing ? 'Simpan Perubahan' : 'Tambahkan Menu') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ===================================================================== -->
    <!-- MODAL 2: KONFIRMASI HAPUS MENU                                        -->
    <!-- ===================================================================== -->
    <div
      v-if="deleteTarget"
      class="fixed inset-0 z-50 bg-black/75 backdrop-blur-sm flex items-center justify-center p-4"
    >
      <div class="w-full max-w-md rounded-3xl bg-[#0b1426] border border-rose-500/40 shadow-2xl p-6 space-y-4">
        <div class="flex items-center gap-3 text-rose-400">
          <div class="w-10 h-10 rounded-2xl bg-rose-500/15 border border-rose-500/30 flex items-center justify-center shrink-0">
            <AlertTriangle class="w-5 h-5" />
          </div>
          <div>
            <h3 class="text-base font-black text-white">Hapus Menu Navigasi?</h3>
            <p class="text-xs text-slate-400">Tindakan ini akan menghapus item dari database.</p>
          </div>
        </div>

        <p class="text-xs text-slate-300 leading-relaxed bg-slate-900/80 p-3.5 rounded-xl border border-slate-800">
          Apakah Anda yakin ingin menghapus menu <strong class="text-amber-300">{{ deleteTarget.title }}</strong>? Jika item ini memiliki sub-menu di bawahnya, seluruh sub-menu terkait juga akan ikut terhapus.
        </p>

        <div class="flex items-center justify-end gap-3 pt-2">
          <button
            type="button"
            @click="deleteTarget = null"
            class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-bold text-slate-300 cursor-pointer"
          >
            Batal
          </button>
          <button
            type="button"
            :disabled="isSubmitting"
            @click="executeDelete"
            class="px-4 py-2 rounded-xl bg-rose-500 hover:bg-rose-400 text-white text-xs font-black cursor-pointer disabled:opacity-50"
          >
            {{ isSubmitting ? 'Menghapus...' : 'Ya, Hapus Permanen' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ===================================================================== -->
    <!-- MODAL 3: KONFIRMASI RESET DEFAULT                                     -->
    <!-- ===================================================================== -->
    <div
      v-if="isResetModalOpen"
      class="fixed inset-0 z-50 bg-black/75 backdrop-blur-sm flex items-center justify-center p-4"
    >
      <div class="w-full max-w-md rounded-3xl bg-[#0b1426] border border-amber-500/40 shadow-2xl p-6 space-y-4">
        <div class="flex items-center gap-3 text-amber-400">
          <div class="w-10 h-10 rounded-2xl bg-amber-400/15 border border-amber-400/30 flex items-center justify-center shrink-0">
            <RotateCcw class="w-5 h-5" />
          </div>
          <div>
            <h3 class="text-base font-black text-white">Pulihkan Navigasi Standar?</h3>
            <p class="text-xs text-slate-400">Reset ke 4 Pilar &amp; 10 Sub-Tab bawaan SIMOD Enterprise</p>
          </div>
        </div>

        <p class="text-xs text-slate-300 leading-relaxed bg-slate-900/80 p-3.5 rounded-xl border border-slate-800">
          Seluruh kustomisasi menu akan dikembalikan ke struktur standar Divisi Humas Polri &amp; Polda Jajaran.
        </p>

        <div class="flex items-center justify-end gap-3 pt-2">
          <button
            type="button"
            @click="isResetModalOpen = false"
            class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-bold text-slate-300 cursor-pointer"
          >
            Batal
          </button>
          <button
            type="button"
            :disabled="isSubmitting"
            @click="executeReset"
            class="px-4 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black cursor-pointer disabled:opacity-50"
          >
            {{ isSubmitting ? 'Memulihkan...' : 'Ya, Pulihkan Standar' }}
          </button>
        </div>
      </div>
    </div>
  </EnterpriseLayout>
</template>

