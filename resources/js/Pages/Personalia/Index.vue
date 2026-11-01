<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import {
  Users,
  UserPlus,
  Shield,
  ShieldCheck,
  Search,
  Key,
  KeyRound,
  Edit3,
  CheckCircle2,
  XCircle,
  Lock,
  Building2,
  X,
  Phone,
  Mail,
  UserCheck,
  Filter,
  Trash2,
  RotateCcw,
  ExternalLink,
  Eye,
  AlertTriangle,
  Layers,
  ChevronRight,
  Sparkles,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: { type: Array, default: () => [] },
  selectedPolda: { type: Object, default: () => ({}) },
  selectedCode: { type: String, default: 'SULBAR' },
  users: { type: Object, default: () => ({ data: [] }) },
  polresList: { type: Array, default: () => [] },
  personnelStats: { type: Object, default: () => ({}) },
  roleMatrix: { type: Array, default: () => [] },
  modules: { type: Array, default: () => [] },
  canManageAll: { type: Boolean, default: false },
  currentUserId: { type: Number, default: null },
  currentUserRole: { type: String, default: '' },
  filters: { type: Object, default: () => ({}) },
  metrics: { type: Object, default: () => ({}) },
});

// Filters State
const searchQuery = ref(props.filters?.q || '');
const filterRole = ref(props.filters?.role || 'all');
const filterPolres = ref(props.filters?.polres || 'all');
const filterStatus = ref(props.filters?.status || 'all');

// Modals State
const showAddModal = ref(false);
const showEditModal = ref(false);
const showInspectModal = ref(false);
const showResetPasswordModal = ref(false);
const showDeleteModal = ref(false);

const selectedUserForAction = ref(null);
const isSubmitting = ref(false);

// Form for Adding Personnel
const addForm = ref({
  polda_id: props.selectedPolda?.id || '',
  polres_id: '',
  name: '',
  username: '',
  email: '',
  badge_number: '',
  role: 'operator',
  phone: '',
  password: '',
  is_active: true,
});

// Form for Editing Personnel
const editForm = ref({
  id: null,
  polda_id: '',
  polres_id: '',
  name: '',
  username: '',
  email: '',
  badge_number: '',
  role: 'operator',
  phone: '',
  password: '',
  is_active: true,
});

// Form for Reset Password
const resetPasswordForm = ref({
  userId: null,
  userName: '',
  new_password: '',
});

// Toast / Notification State
const toastMessage = ref('');
const showToast = ref(false);
const toastType = ref('success');

const triggerToast = (msg, type = 'success') => {
  toastMessage.value = msg;
  toastType.value = type;
  showToast.value = true;
  setTimeout(() => {
    showToast.value = false;
  }, 4000);
};

// Filter Execution
const applyFilter = () => {
  router.get('/personalia', {
    role: filterRole.value,
    polres: filterPolres.value,
    status: filterStatus.value,
    q: searchQuery.value,
  }, {
    preserveState: true,
    preserveScroll: true,
  });
};

const resetFilter = () => {
  searchQuery.value = '';
  filterRole.value = 'all';
  filterPolres.value = 'all';
  filterStatus.value = 'all';
  applyFilter();
};

// Role Badges and Metadata
const getRoleMeta = (roleKey) => {
  const match = (props.roleMatrix || []).find(r => r.role_key === roleKey);
  if (match) {
    return {
      name: match.role_name,
      badge: match.badge,
      description: match.description,
      grantedCount: match.granted_count || 0,
      totalCount: match.total_count || 17,
    };
  }

  switch (roleKey) {
    case 'kadiv_humas':
      return { name: 'Kadivhumas Polri', badge: 'Mabes ⭐⭐', description: 'Pimpinan tertinggi kehumasan Mabes Polri', grantedCount: 17, totalCount: 17 };
    case 'super_admin_mabes':
      return { name: 'Super Administrator Mabes', badge: 'Superadmin', description: 'Pengelola teknis nasional dan sistem', grantedCount: 17, totalCount: 17 };
    case 'polda_leader':
      return { name: 'Pimpinan Polda (Kabid Humas)', badge: 'Polda', description: 'Pimpinan kehumasan tingkat Polda', grantedCount: 17, totalCount: 17 };
    case 'verifikator':
      return { name: 'Verifikator (Penmas)', badge: 'Verifikator', description: 'Validasi fakta & klarifikasi hoaks', grantedCount: 12, totalCount: 17 };
    case 'analis':
      return { name: 'Analis Intelijen Media', badge: 'Analis', description: 'Analisis sentimen siber & EWS', grantedCount: 8, totalCount: 17 };
    case 'operator':
    default:
      return { name: 'Operator Satwil (Polres)', badge: 'Operator', description: 'Operator lapangan & pelaksana tugas', grantedCount: 7, totalCount: 17 };
  }
};

const getRoleBadgeStyle = (roleKey) => {
  switch (roleKey) {
    case 'kadiv_humas':
      return 'bg-gradient-to-r from-amber-500/25 to-yellow-500/20 text-amber-300 border-amber-400/50 shadow-sm shadow-amber-500/10';
    case 'super_admin_mabes':
      return 'bg-rose-500/20 text-rose-300 border-rose-500/30';
    case 'polda_leader':
      return 'bg-amber-400/20 text-amber-300 border-amber-400/30';
    case 'verifikator':
      return 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30';
    case 'analis':
      return 'bg-sky-500/20 text-sky-300 border-sky-500/30';
    case 'operator':
    default:
      return 'bg-slate-800 text-slate-300 border-slate-700';
  }
};

// Handlers for Add Modal
const openAddModal = () => {
  addForm.value = {
    polda_id: props.selectedPolda?.id || '',
    polres_id: '',
    name: '',
    username: '',
    email: '',
    badge_number: '',
    role: 'operator',
    phone: '',
    password: '',
    is_active: true,
  };
  showAddModal.value = true;
};

// Auto-fill username when typing name in Add Modal
const onAddNameChange = () => {
  if (!addForm.value.username && addForm.value.name) {
    const slug = addForm.value.name
      .toLowerCase()
      .replace(/[^a-z0-9]/g, '_')
      .replace(/_+/g, '_')
      .replace(/^_|_$/g, '')
      .substring(0, 20);
    if (slug) {
      addForm.value.username = slug;
    }
  }
};

const submitAddUser = () => {
  isSubmitting.value = true;
  router.post('/personalia', addForm.value, {
    preserveScroll: true,
    onSuccess: () => {
      isSubmitting.value = false;
      showAddModal.value = false;
      triggerToast('✓ Akun personel baru berhasil didaftarkan ke sistem!', 'success');
    },
    onError: (errs) => {
      isSubmitting.value = false;
      const firstErr = Object.values(errs)[0] || 'Terjadi kesalahan validasi data.';
      triggerToast(firstErr, 'error');
    },
  });
};

// Handlers for Edit Modal
const openEditModal = (user) => {
  selectedUserForAction.value = user;
  editForm.value = {
    id: user.id,
    polda_id: user.polda_id || '',
    polres_id: user.polres_id || '',
    name: user.name,
    username: user.username,
    email: user.email,
    badge_number: user.badge_number || '',
    role: user.role,
    phone: user.phone || '',
    password: '',
    is_active: Boolean(user.is_active),
  };
  showEditModal.value = true;
};

const submitEditUser = () => {
  if (!editForm.value.id) return;
  isSubmitting.value = true;

  router.put(`/personalia/${editForm.value.id}`, editForm.value, {
    preserveScroll: true,
    onSuccess: () => {
      isSubmitting.value = false;
      showEditModal.value = false;
      triggerToast('✓ Data dan otoritas personel berhasil diperbarui!', 'success');
    },
    onError: (errs) => {
      isSubmitting.value = false;
      const firstErr = Object.values(errs)[0] || 'Gagal memperbarui data personel.';
      triggerToast(firstErr, 'error');
    },
  });
};

// Handlers for Toggle Status
const toggleUserStatus = (user) => {
  if (user.id === props.currentUserId) {
    triggerToast('Anda tidak dapat menonaktifkan akun Anda sendiri yang sedang aktif.', 'error');
    return;
  }

  if (user.id === 1 || user.username === 'kadivhumas') {
    triggerToast('Akun induk pimpinan sistem dilindungi dan tidak dapat dinonaktifkan.', 'error');
    return;
  }

  const actionText = user.is_active ? 'nonaktifkan' : 'aktifkan';
  if (confirm(`Apakah Anda yakin ingin me-${actionText} akun ${user.name}?`)) {
    router.post(`/personalia/${user.id}/toggle-status`, {}, {
      preserveScroll: true,
      onSuccess: () => {
        triggerToast(`Status akun ${user.name} berhasil diubah.`, 'success');
      },
      onError: (errs) => {
        const msg = Object.values(errs)[0] || 'Gagal mengubah status akun.';
        triggerToast(msg, 'error');
      },
    });
  }
};

// Handlers for Reset Password Modal
const openResetPasswordModal = (user) => {
  selectedUserForAction.value = user;
  resetPasswordForm.value = {
    userId: user.id,
    userName: user.name,
    new_password: '',
  };
  showResetPasswordModal.value = true;
};

const submitResetPassword = () => {
  if (!resetPasswordForm.value.userId) return;
  isSubmitting.value = true;

  router.post(`/personalia/${resetPasswordForm.value.userId}/reset-password`, {
    new_password: resetPasswordForm.value.new_password,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      isSubmitting.value = false;
      showResetPasswordModal.value = false;
      triggerToast(`✓ Password dinas untuk ${resetPasswordForm.value.userName} berhasil direset!`, 'success');
    },
    onError: (errs) => {
      isSubmitting.value = false;
      const msg = Object.values(errs)[0] || 'Gagal mereset password.';
      triggerToast(msg, 'error');
    },
  });
};

// Handlers for Delete Modal
const openDeleteModal = (user) => {
  selectedUserForAction.value = user;
  showDeleteModal.value = true;
};

const confirmDeleteUser = () => {
  if (!selectedUserForAction.value) return;
  isSubmitting.value = true;

  router.delete(`/personalia/${selectedUserForAction.value.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      isSubmitting.value = false;
      showDeleteModal.value = false;
      triggerToast(`✓ Akun personel ${selectedUserForAction.value.name} telah berhasil dihapus.`, 'success');
      selectedUserForAction.value = null;
    },
    onError: (errs) => {
      isSubmitting.value = false;
      const msg = Object.values(errs)[0] || 'Gagal menghapus akun personel.';
      triggerToast(msg, 'error');
    },
  });
};

// Handlers for Inspect Permissions Modal
const inspectUser = ref(null);
const openInspectModal = (user) => {
  inspectUser.value = user;
  showInspectModal.value = true;
};

const inspectRoleMatrix = computed(() => {
  if (!inspectUser.value) return null;
  return (props.roleMatrix || []).find(r => r.role_key === inspectUser.value.role);
});

// Grouped Modules for Inspect Modal (4 Pillars)
const modulesByPillar = computed(() => {
  const groups = {};
  (props.modules || []).forEach(m => {
    const p = m.pillar || 'Modul Sistem';
    if (!groups[p]) groups[p] = [];
    groups[p].push(m);
  });
  return groups;
});
</script>

<template>
  <EnterpriseLayout
    :all-poldas="allPoldas"
    :selected-polda="selectedPolda"
    :selected-code="selectedCode"
    :metrics="metrics"
  >
    <div class="space-y-6">
      <!-- Toast Notification Alert -->
      <transition
        enter-active-class="transform ease-out duration-300 transition"
        enter-from-class="-translate-y-4 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition ease-in duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="showToast"
          class="p-4 rounded-2xl text-white shadow-2xl flex items-center justify-between gap-4 border"
          :class="toastType === 'success' ? 'bg-gradient-to-r from-emerald-600 via-emerald-700 to-teal-800 border-emerald-400/40' : 'bg-gradient-to-r from-rose-600 via-rose-700 to-red-800 border-rose-400/40'"
        >
          <div class="flex items-center gap-3">
            <CheckCircle2 v-if="toastType === 'success'" class="w-5 h-5 text-emerald-300" />
            <AlertTriangle v-else class="w-5 h-5 text-rose-300" />
            <p class="text-xs font-bold">{{ toastMessage }}</p>
          </div>
          <button @click="showToast = false" class="text-white/80 hover:text-white">
            <X class="w-4 h-4" />
          </button>
        </div>
      </transition>

      <!-- Page Header with Quick Shortcut to /roles -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-800/80 pb-6">
        <div>
          <div class="flex items-center gap-2">
            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-400 text-slate-950">
              Administrasi &amp; Personalia
            </span>
            <span class="text-xs font-bold text-slate-400">
              {{ selectedCode === 'ALL' ? 'Divisi Humas Polri (Komando Nasional)' : (selectedPolda?.name || 'Polda Sulawesi Barat') }}
            </span>
          </div>
          <h1 class="text-2xl md:text-3xl font-black text-white tracking-tight mt-1.5 flex items-center gap-2.5">
            <Users class="w-7 h-7 text-amber-400" />
            <span>Personalia &amp; Otoritas Hak Akses</span>
          </h1>
          <p class="text-xs md:text-sm text-slate-300 max-w-3xl mt-1">
            Pengelolaan akun dinas kehumasan, hierarki penugasan kewilayahan, serta sinkronisasi wewenang dengan
            <strong class="text-amber-400">Matriks Hak Akses (RBAC)</strong> berstandar Divisi Humas Polri &amp; Polda Jajaran.
          </p>
        </div>

        <div class="flex items-center gap-2.5 shrink-0">
          <!-- Shortcut Link to /roles -->
          <Link
            href="/roles"
            class="flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 hover:border-amber-400/50 text-slate-200 text-xs font-bold transition shadow-md"
            title="Buka Matriks Otoritas & Perizinan Sistem"
          >
            <KeyRound class="w-4 h-4 text-amber-400" />
            <span>Matriks Hak Akses (RBAC) &rarr;</span>
          </Link>

          <!-- Add Personnel Button -->
          <button
            @click="openAddModal"
            class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-400 hover:brightness-110 text-slate-950 text-xs font-black shadow-lg shadow-amber-500/20 transition cursor-pointer"
          >
            <UserPlus class="w-4 h-4" />
            <span>+ Tambah Personel Baru</span>
          </button>
        </div>
      </div>

      <!-- 4 Personnel KPI Cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
          <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
            <span>Total Personel</span>
            <Users class="w-4 h-4 text-emerald-400" />
          </div>
          <div class="text-3xl font-black text-white">
            {{ personnelStats?.total || users?.total || 0 }}
          </div>
          <div class="flex items-center gap-2 text-[11px]">
            <span class="text-emerald-400 font-bold font-mono">{{ personnelStats?.active || 0 }} Aktif</span>
            <span class="text-slate-500">•</span>
            <span class="text-rose-400 font-bold font-mono">{{ personnelStats?.inactive || 0 }} Non-Aktif</span>
          </div>
        </div>

        <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
          <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
            <span>Komando Mabes</span>
            <ShieldCheck class="w-4 h-4 text-amber-400" />
          </div>
          <div class="text-3xl font-black text-amber-400">
            {{ personnelStats?.superadmin || 0 }}
          </div>
          <p class="text-[11px] text-amber-400 font-semibold truncate">Kadivhumas ⭐⭐ &amp; Superadmin</p>
        </div>

        <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
          <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
            <span>Bidhumas Polda</span>
            <Building2 class="w-4 h-4 text-sky-400" />
          </div>
          <div class="text-3xl font-black text-sky-400">
            {{ personnelStats?.polda_staff || 0 }}
          </div>
          <p class="text-[11px] text-slate-400 truncate">Pimpinan, Verifikator &amp; Analis</p>
        </div>

        <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
          <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
            <span>Operator Satwil</span>
            <Phone class="w-4 h-4 text-emerald-400" />
          </div>
          <div class="text-3xl font-black text-emerald-400">
            {{ personnelStats?.satwil || 0 }}
          </div>
          <p class="text-[11px] text-slate-400 truncate">Kasihumas &amp; Pelaksana Polres</p>
        </div>
      </div>

      <!-- Filter & Search Toolbar -->
      <div class="p-4 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-xl flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
          <!-- Role Filter -->
          <div class="flex items-center gap-1.5">
            <Filter class="w-3.5 h-3.5 text-slate-400 shrink-0" />
            <select
              v-model="filterRole"
              @change="applyFilter"
              class="px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-700 text-xs text-white focus:outline-none focus:border-amber-400"
            >
              <option value="all">Semua Peran (Roles)</option>
              <option value="kadiv_humas">🇮🇩 Kadivhumas Polri (Mabes ⭐⭐)</option>
              <option value="super_admin_mabes">Super Administrator Mabes</option>
              <option value="polda_leader">Pimpinan Polda (Kabid Humas)</option>
              <option value="verifikator">Verifikator (Penmas)</option>
              <option value="analis">Analis Intelijen Media</option>
              <option value="operator">Operator Satwil (Polres)</option>
            </select>
          </div>

          <!-- Satker / Polres Filter -->
          <select
            v-model="filterPolres"
            @change="applyFilter"
            class="px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-700 text-xs text-white focus:outline-none focus:border-amber-400"
          >
            <option value="all">Semua Satker Penugasan</option>
            <option value="none">Markas Polda / Divhumas (Tanpa Polres)</option>
            <option v-for="p in polresList" :key="p.id" :value="p.id">
              {{ p.name }}
            </option>
          </select>

          <!-- Status Filter -->
          <select
            v-model="filterStatus"
            @change="applyFilter"
            class="px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-700 text-xs text-white focus:outline-none focus:border-amber-400"
          >
            <option value="all">Semua Status Akun</option>
            <option value="active">🟢 Aktif Saja</option>
            <option value="inactive">🔴 Non-Aktif Saja</option>
          </select>

          <button
            v-if="filterRole !== 'all' || filterPolres !== 'all' || filterStatus !== 'all' || searchQuery"
            @click="resetFilter"
            type="button"
            class="text-[11px] text-amber-400 hover:underline px-2 py-1 flex items-center gap-1 font-bold cursor-pointer"
          >
            <RotateCcw class="w-3 h-3" />
            <span>Reset Filter</span>
          </button>
        </div>

        <!-- Search Input -->
        <div class="relative w-full md:w-80">
          <Search class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <input
            v-model="searchQuery"
            @keyup.enter="applyFilter"
            type="text"
            placeholder="Cari nama, NRP, username, email..."
            class="w-full pl-9 pr-3 py-1.5 rounded-xl bg-slate-900 border border-slate-700 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-amber-400 transition"
          />
        </div>
      </div>

      <!-- Personnel Table Card -->
      <div class="rounded-3xl bg-[#0b1426] border border-slate-800 shadow-2xl overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
          <div class="flex items-center gap-2">
            <h3 class="text-sm font-black text-white uppercase tracking-wider">
              Daftar Personel Kehumasan &amp; Otoritas
            </h3>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-400 text-slate-950 font-mono">
              {{ users?.total || 0 }} Anggota
            </span>
          </div>

          <p class="text-xs text-slate-400">
            Hak akses operasional modul tersinkronisasi otomatis dengan konfigurasi <Link href="/roles" class="text-amber-400 font-bold hover:underline">/roles</Link>.
          </p>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="border-b border-slate-800 bg-slate-900/60 text-[11px] font-black uppercase text-slate-400 tracking-wider">
                <th class="px-5 py-3.5">Nama &amp; Identitas Personel</th>
                <th class="px-4 py-3.5">Peran &amp; Otoritas Sistem</th>
                <th class="px-4 py-3.5">Satwil / Penugasan</th>
                <th class="px-4 py-3.5">Kredensial &amp; Kontak</th>
                <th class="px-4 py-3.5 text-center">Status</th>
                <th class="px-5 py-3.5 text-right">Aksi Otoritas</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/80">
              <tr
                v-for="u in users?.data || []"
                :key="u.id"
                class="hover:bg-slate-900/50 transition group"
              >
                <!-- 1. Name & Badge -->
                <td class="px-5 py-4">
                  <div class="flex items-center gap-3">
                    <div
                      class="w-10 h-10 rounded-2xl border flex items-center justify-center font-bold text-xs shadow-md shrink-0"
                      :class="u.role === 'kadiv_humas'
                        ? 'bg-gradient-to-tr from-amber-400 via-amber-300 to-yellow-200 text-slate-950 border-amber-300 ring-2 ring-amber-400/50 font-black'
                        : 'bg-slate-900 border-slate-700 text-amber-400 group-hover:border-amber-400/50 transition'"
                    >
                      <span v-if="u.role === 'kadiv_humas'">⭐⭐</span>
                      <span v-else-if="u.role === 'super_admin_mabes'">🛡️</span>
                      <span v-else>{{ u.name.charAt(0) }}</span>
                    </div>

                    <div class="min-w-0">
                      <p class="font-extrabold text-white flex items-center gap-1.5 truncate">
                        <span class="truncate">{{ u.name }}</span>
                        <span
                          v-if="u.id === currentUserId"
                          class="px-1.5 py-0.2 rounded text-[8px] font-black uppercase bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 shrink-0"
                        >
                          Anda
                        </span>
                      </p>
                      <div class="flex items-center gap-2 mt-0.5">
                        <span class="text-[11px] font-mono text-slate-400">
                          NRP: <strong class="text-slate-300">{{ u.badge_number || '-' }}</strong>
                        </span>
                      </div>
                    </div>
                  </div>
                </td>

                <!-- 2. Role & RBAC Modules Count -->
                <td class="px-4 py-4">
                  <div class="space-y-1.5">
                    <span
                      class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase border tracking-wider inline-block"
                      :class="getRoleBadgeStyle(u.role)"
                    >
                      {{ getRoleMeta(u.role).badge }} • {{ getRoleMeta(u.role).name }}
                    </span>

                    <div>
                      <button
                        type="button"
                        @click="openInspectModal(u)"
                        class="text-[10px] font-mono text-slate-400 hover:text-amber-300 flex items-center gap-1 cursor-pointer transition hover:underline"
                        title="Klik untuk melihat matriks izin 17 modul untuk peran ini"
                      >
                        <Eye class="w-3 h-3 text-amber-400" />
                        <span>{{ getRoleMeta(u.role).grantedCount }} / {{ getRoleMeta(u.role).totalCount }} Modul Aktif</span>
                      </button>
                    </div>
                  </div>
                </td>

                <!-- 3. Satwil & Satker -->
                <td class="px-4 py-4">
                  <div class="space-y-1">
                    <div class="flex items-center gap-1.5 font-bold text-slate-200">
                      <Building2 class="w-3.5 h-3.5 text-amber-400 shrink-0" />
                      <span class="truncate">
                        {{ u.polres?.name || (u.polda?.name ? `Markas ${u.polda.name}` : (u.role === 'kadiv_humas' || u.role === 'super_admin_mabes' ? 'Divisi Humas Polri (Mabes)' : 'Markas Polda')) }}
                      </span>
                    </div>
                    <p v-if="u.polda && u.polres" class="text-[10px] text-slate-400">
                      {{ u.polda.name }}
                    </p>
                  </div>
                </td>

                <!-- 4. Credentials & Contact -->
                <td class="px-4 py-4 space-y-0.5">
                  <div class="flex items-center gap-1.5 text-slate-300 font-mono text-[11px]">
                    <UserCheck class="w-3 h-3 text-slate-400" />
                    <span>{{ u.username }}</span>
                  </div>
                  <div class="flex items-center gap-1.5 text-slate-400 text-[11px]">
                    <Mail class="w-3 h-3 text-slate-500" />
                    <span class="truncate">{{ u.email }}</span>
                  </div>
                  <div v-if="u.phone" class="flex items-center gap-1.5 text-slate-500 font-mono text-[10px]">
                    <Phone class="w-3 h-3 text-emerald-500/70" />
                    <span>{{ u.phone }}</span>
                  </div>
                </td>

                <!-- 5. Active Status -->
                <td class="px-4 py-4 text-center">
                  <button
                    type="button"
                    @click="toggleUserStatus(u)"
                    :disabled="u.id === currentUserId || u.id === 1 || u.username === 'kadivhumas'"
                    class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase inline-flex items-center gap-1.5 border transition cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
                    :class="u.is_active
                      ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40 hover:bg-emerald-500/30'
                      : 'bg-rose-500/20 text-rose-300 border-rose-500/40 hover:bg-rose-500/30'"
                    :title="u.is_active ? 'Klik untuk nonaktifkan' : 'Klik untuk aktifkan'"
                  >
                    <CheckCircle2 v-if="u.is_active" class="w-3 h-3 text-emerald-400" />
                    <XCircle v-else class="w-3 h-3 text-rose-400" />
                    <span>{{ u.is_active ? 'Aktif' : 'Non-Aktif' }}</span>
                  </button>
                </td>

                <!-- 6. Actions Toolbar -->
                <td class="px-5 py-4 text-right">
                  <div class="flex items-center justify-end gap-1.5">
                    <!-- Inspect Permissions Button -->
                    <button
                      type="button"
                      @click="openInspectModal(u)"
                      class="p-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-slate-400 hover:text-amber-300 border border-slate-800 hover:border-slate-700 transition cursor-pointer"
                      title="Lihat Otoritas Hak Akses (RBAC)"
                    >
                      <Eye class="w-3.5 h-3.5" />
                    </button>

                    <!-- Edit User Button -->
                    <button
                      type="button"
                      @click="openEditModal(u)"
                      class="p-1.5 rounded-lg bg-slate-900 hover:bg-amber-400 hover:text-slate-950 text-slate-300 border border-slate-800 transition cursor-pointer"
                      title="Edit Data & Peran Personel"
                    >
                      <Edit3 class="w-3.5 h-3.5" />
                    </button>

                    <!-- Reset Password Button -->
                    <button
                      type="button"
                      @click="openResetPasswordModal(u)"
                      class="p-1.5 rounded-lg bg-slate-900 hover:bg-sky-500 hover:text-white text-slate-300 border border-slate-800 transition cursor-pointer"
                      title="Reset Password Dinas"
                    >
                      <Key class="w-3.5 h-3.5" />
                    </button>

                    <!-- Delete Button -->
                    <button
                      type="button"
                      @click="openDeleteModal(u)"
                      :disabled="u.id === currentUserId || u.id === 1 || u.username === 'kadivhumas' || u.username === 'superadmin'"
                      class="p-1.5 rounded-lg bg-slate-900 hover:bg-rose-600 hover:text-white text-slate-400 border border-slate-800 transition cursor-pointer disabled:opacity-30 disabled:pointer-events-none"
                      title="Hapus Akun Personel"
                    >
                      <Trash2 class="w-3.5 h-3.5" />
                    </button>
                  </div>
                </td>
              </tr>

              <tr v-if="!users?.data || users.data.length === 0">
                <td colspan="6" class="px-6 py-12 text-center text-slate-400">
                  <Users class="w-8 h-8 mx-auto mb-2 text-slate-600" />
                  <p class="font-bold text-white text-sm">Tidak ada personel yang sesuai dengan kriteria filter.</p>
                  <p class="text-xs text-slate-500 mt-1">Coba sesuaikan pencarian atau klik tombol Reset Filter di atas.</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination Links -->
        <div v-if="users?.links && users.links.length > 3" class="p-4 border-t border-slate-800 flex items-center justify-center gap-1.5">
          <Link
            v-for="(link, i) in users.links"
            :key="i"
            :href="link.url || '#'"
            class="px-3 py-1.5 rounded-xl text-xs font-bold transition border"
            :class="link.active
              ? 'bg-amber-400 text-slate-950 border-amber-400 font-black'
              : link.url
                ? 'bg-slate-900 text-slate-300 border-slate-800 hover:text-white hover:border-slate-700'
                : 'bg-slate-950 text-slate-600 border-slate-900 pointer-events-none'"
            v-html="link.label"
          />
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 1. MODAL TAMBAH PERSONEL BARU                                             -->
    <!-- ========================================================================= -->
    <div
      v-if="showAddModal"
      class="fixed inset-0 z-50 bg-black/85 backdrop-blur-md flex items-center justify-center p-4 overflow-y-auto"
      @click.self="showAddModal = false"
    >
      <div class="relative w-full max-w-2xl bg-[#091122] border border-slate-800 rounded-3xl overflow-hidden shadow-2xl flex flex-col my-8">
        <div class="px-6 py-4 border-b border-slate-800 bg-[#0b1426] flex items-center justify-between">
          <div class="flex items-center gap-2.5">
            <UserPlus class="w-5 h-5 text-amber-400" />
            <div>
              <h3 class="text-sm font-extrabold text-white">Tambah Akun Personel &amp; Tetapkan Otoritas</h3>
              <p class="text-[10px] text-slate-400">Pendaftaran personel resmi Divisi Humas Polri &amp; Satwil Jajaran</p>
            </div>
          </div>
          <button
            @click="showAddModal = false"
            class="w-8 h-8 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center transition cursor-pointer"
          >
            <X class="w-4 h-4" />
          </button>
        </div>

        <form @submit.prevent="submitAddUser" class="p-6 space-y-4 text-xs">
          <!-- Nama Lengkap & NRP -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block font-bold text-slate-200 mb-1">Nama Lengkap &amp; Gelar *</label>
              <input
                v-model="addForm.name"
                @input="onAddNameChange"
                type="text"
                required
                placeholder="Contoh: Bripka Muh. Yunus, S.H."
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:border-amber-400"
              />
            </div>

            <div>
              <label class="block font-bold text-slate-200 mb-1">NRP / NIP Anggota *</label>
              <input
                v-model="addForm.badge_number"
                type="text"
                required
                placeholder="Contoh: 89030214"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:border-amber-400 font-mono"
              />
            </div>
          </div>

          <!-- Role & Authority Selector -->
          <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-800 space-y-3">
            <div>
              <label class="block font-black text-amber-400 uppercase tracking-wider text-[11px] mb-1">
                Peran Akses Sistem (Role Berdasarkan /roles) *
              </label>
              <select
                v-model="addForm.role"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white focus:outline-none focus:border-amber-400 font-bold"
              >
                <option v-if="canManageAll" value="kadiv_humas">🇮🇩 Kadivhumas Polri (Mabes ⭐⭐)</option>
                <option v-if="canManageAll" value="super_admin_mabes">🛡️ Super Administrator Mabes</option>
                <option value="polda_leader">🏛️ Pimpinan Polda (Kabid Humas)</option>
                <option value="verifikator">✅ Verifikator (Kasubbid Penmas)</option>
                <option value="analis">📊 Analis Intelijen Media &amp; Siber</option>
                <option value="operator">👮 Operator Satwil (Polres / Sie Humas)</option>
              </select>
            </div>

            <!-- Role Permission Summary Preview -->
            <div class="p-3 rounded-xl bg-[#0b1426] border border-slate-800 flex items-start gap-2.5">
              <KeyRound class="w-4 h-4 text-amber-400 shrink-0 mt-0.5" />
              <div class="text-[11px] leading-relaxed">
                <p class="font-bold text-white">
                  Otoritas Peran: {{ getRoleMeta(addForm.role).badge }} • {{ getRoleMeta(addForm.role).name }}
                </p>
                <p class="text-slate-400 mt-0.5">
                  {{ getRoleMeta(addForm.role).description }}
                </p>
                <span class="inline-block font-mono text-[10px] text-amber-300 font-bold mt-1 bg-amber-400/10 px-2 py-0.5 rounded border border-amber-400/20">
                  Diberikan izin pada {{ getRoleMeta(addForm.role).grantedCount }} dari {{ getRoleMeta(addForm.role).totalCount }} modul operasional di /roles
                </span>
              </div>
            </div>
          </div>

          <!-- Satuan Wilayah (Polda) & Satker (Polres) -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block font-bold text-slate-200 mb-1">Satuan Induk (Polda / Mabes)</label>
              <select
                v-model="addForm.polda_id"
                :disabled="addForm.role === 'kadiv_humas' || addForm.role === 'super_admin_mabes'"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400 disabled:opacity-50"
              >
                <option value="">Divisi Humas Polri (Mabes)</option>
                <option v-for="p in allPoldas" :key="p.id" :value="p.id">
                  {{ p.name }}
                </option>
              </select>
            </div>

            <div>
              <label class="block font-bold text-slate-200 mb-1">Satker / Polres Jajaran</label>
              <select
                v-model="addForm.polres_id"
                :disabled="addForm.role === 'kadiv_humas' || addForm.role === 'super_admin_mabes'"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400 disabled:opacity-50"
              >
                <option value="">Tingkat Markas Polda (Tanpa Polres)</option>
                <option v-for="p in polresList" :key="p.id" :value="p.id">
                  {{ p.name }}
                </option>
              </select>
            </div>
          </div>

          <!-- Username, Email, Phone -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label class="block font-bold text-slate-200 mb-1">Username Login *</label>
              <input
                v-model="addForm.username"
                type="text"
                required
                placeholder="bripka_yunus"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:border-amber-400 font-mono"
              />
            </div>

            <div>
              <label class="block font-bold text-slate-200 mb-1">Email Resmi Dinas *</label>
              <input
                v-model="addForm.email"
                type="email"
                required
                placeholder="yunus@polri.go.id"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:border-amber-400"
              />
            </div>

            <div>
              <label class="block font-bold text-slate-200 mb-1">No. WhatsApp / HP</label>
              <input
                v-model="addForm.phone"
                type="text"
                placeholder="0812xxxxxxxx"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:border-amber-400 font-mono"
              />
            </div>
          </div>

          <!-- Password Sementara & Status Toggle -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
            <div>
              <label class="block font-bold text-slate-200 mb-1">Password Sementara *</label>
              <input
                v-model="addForm.password"
                type="password"
                required
                minlength="6"
                placeholder="Minimal 6 karakter..."
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:border-amber-400"
              />
            </div>

            <div class="flex items-center justify-between p-3 rounded-xl bg-slate-900 border border-slate-700 self-end">
              <div>
                <span class="block font-bold text-white text-xs">Status Akun Langsung Aktif</span>
                <span class="text-[10px] text-slate-400">Izinkan personel langsung login</span>
              </div>
              <input
                v-model="addForm.is_active"
                type="checkbox"
                class="w-5 h-5 rounded text-amber-500 bg-slate-950 border-slate-700 focus:ring-amber-400 cursor-pointer"
              />
            </div>
          </div>

          <!-- Modal Action Buttons -->
          <div class="pt-4 border-t border-slate-800 flex items-center justify-end gap-2.5">
            <button
              type="button"
              @click="showAddModal = false"
              class="px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-300 font-bold transition cursor-pointer"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-6 py-2.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-black flex items-center gap-2 transition disabled:opacity-50 cursor-pointer shadow-lg shadow-amber-500/20"
            >
              <UserPlus class="w-4 h-4" />
              <span>{{ isSubmitting ? 'Mendaftarkan...' : 'Daftarkan Personel' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 2. MODAL EDIT PERSONEL & OTORITAS                                         -->
    <!-- ========================================================================= -->
    <div
      v-if="showEditModal"
      class="fixed inset-0 z-50 bg-black/85 backdrop-blur-md flex items-center justify-center p-4 overflow-y-auto"
      @click.self="showEditModal = false"
    >
      <div class="relative w-full max-w-2xl bg-[#091122] border border-slate-800 rounded-3xl overflow-hidden shadow-2xl flex flex-col my-8">
        <div class="px-6 py-4 border-b border-slate-800 bg-[#0b1426] flex items-center justify-between">
          <div class="flex items-center gap-2.5">
            <Edit3 class="w-5 h-5 text-amber-400" />
            <div>
              <h3 class="text-sm font-extrabold text-white">Edit Personel: {{ editForm.name }}</h3>
              <p class="text-[10px] text-slate-400">Penyesuaian data profil, pangkat/NRP, dan hak akses operasional</p>
            </div>
          </div>
          <button
            @click="showEditModal = false"
            class="w-8 h-8 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center transition cursor-pointer"
          >
            <X class="w-4 h-4" />
          </button>
        </div>

        <form @submit.prevent="submitEditUser" class="p-6 space-y-4 text-xs">
          <!-- Nama & NRP -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block font-bold text-slate-200 mb-1">Nama Lengkap &amp; Pangkat *</label>
              <input
                v-model="editForm.name"
                type="text"
                required
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400"
              />
            </div>

            <div>
              <label class="block font-bold text-slate-200 mb-1">NRP / NIP Anggota *</label>
              <input
                v-model="editForm.badge_number"
                type="text"
                required
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400 font-mono"
              />
            </div>
          </div>

          <!-- Role & Authority Selector -->
          <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-800 space-y-3">
            <div>
              <label class="block font-black text-amber-400 uppercase tracking-wider text-[11px] mb-1">
                Peran Akses Sistem (Role Berdasarkan /roles) *
              </label>
              <select
                v-model="editForm.role"
                :disabled="editForm.id === currentUserId"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white focus:outline-none focus:border-amber-400 font-bold disabled:opacity-50"
              >
                <option v-if="canManageAll" value="kadiv_humas">🇮🇩 Kadivhumas Polri (Mabes ⭐⭐)</option>
                <option v-if="canManageAll" value="super_admin_mabes">🛡️ Super Administrator Mabes</option>
                <option value="polda_leader">🏛️ Pimpinan Polda (Kabid Humas)</option>
                <option value="verifikator">✅ Verifikator (Kasubbid Penmas)</option>
                <option value="analis">📊 Analis Intelijen Media &amp; Siber</option>
                <option value="operator">👮 Operator Satwil (Polres / Sie Humas)</option>
              </select>
              <p v-if="editForm.id === currentUserId" class="text-[10px] text-amber-400/90 mt-1">
                * Anda tidak dapat mengubah peran akun Anda sendiri demi keamanan sistem.
              </p>
            </div>

            <!-- Role Permission Summary Preview -->
            <div class="p-3 rounded-xl bg-[#0b1426] border border-slate-800 flex items-start gap-2.5">
              <KeyRound class="w-4 h-4 text-amber-400 shrink-0 mt-0.5" />
              <div class="text-[11px] leading-relaxed">
                <p class="font-bold text-white">
                  Otoritas: {{ getRoleMeta(editForm.role).badge }} • {{ getRoleMeta(editForm.role).name }}
                </p>
                <p class="text-slate-400 mt-0.5">
                  {{ getRoleMeta(editForm.role).description }}
                </p>
                <span class="inline-block font-mono text-[10px] text-amber-300 font-bold mt-1 bg-amber-400/10 px-2 py-0.5 rounded border border-amber-400/20">
                  Diberikan izin pada {{ getRoleMeta(editForm.role).grantedCount }} dari {{ getRoleMeta(editForm.role).totalCount }} modul di /roles
                </span>
              </div>
            </div>
          </div>

          <!-- Satwil & Satker -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block font-bold text-slate-200 mb-1">Satuan Induk (Polda / Mabes)</label>
              <select
                v-model="editForm.polda_id"
                :disabled="editForm.role === 'kadiv_humas' || editForm.role === 'super_admin_mabes'"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400 disabled:opacity-50"
              >
                <option value="">Divisi Humas Polri (Mabes)</option>
                <option v-for="p in allPoldas" :key="p.id" :value="p.id">
                  {{ p.name }}
                </option>
              </select>
            </div>

            <div>
              <label class="block font-bold text-slate-200 mb-1">Satker / Polres Jajaran</label>
              <select
                v-model="editForm.polres_id"
                :disabled="editForm.role === 'kadiv_humas' || editForm.role === 'super_admin_mabes'"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400 disabled:opacity-50"
              >
                <option value="">Tingkat Markas Polda (Tanpa Polres)</option>
                <option v-for="p in polresList" :key="p.id" :value="p.id">
                  {{ p.name }}
                </option>
              </select>
            </div>
          </div>

          <!-- Username, Email, Phone -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label class="block font-bold text-slate-200 mb-1">Username Dinas *</label>
              <input
                v-model="editForm.username"
                type="text"
                required
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400 font-mono"
              />
            </div>

            <div>
              <label class="block font-bold text-slate-200 mb-1">Email Resmi *</label>
              <input
                v-model="editForm.email"
                type="email"
                required
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400"
              />
            </div>

            <div>
              <label class="block font-bold text-slate-200 mb-1">No. WhatsApp / HP</label>
              <input
                v-model="editForm.phone"
                type="text"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400 font-mono"
              />
            </div>
          </div>

          <!-- Ganti Password Opsional & Status Toggle -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
            <div>
              <label class="block font-bold text-slate-200 mb-1">Ganti Password Baru (Opsional)</label>
              <input
                v-model="editForm.password"
                type="password"
                minlength="6"
                placeholder="Kosongkan jika tidak ingin mengubah..."
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:border-amber-400"
              />
            </div>

            <div class="flex items-center justify-between p-3 rounded-xl bg-slate-900 border border-slate-700 self-end">
              <div>
                <span class="block font-bold text-white text-xs">Status Akun Aktif</span>
                <span class="text-[10px] text-slate-400">Kredensial diizinkan login</span>
              </div>
              <input
                v-model="editForm.is_active"
                :disabled="editForm.id === currentUserId"
                type="checkbox"
                class="w-5 h-5 rounded text-amber-500 bg-slate-950 border-slate-700 focus:ring-amber-400 cursor-pointer disabled:opacity-50"
              />
            </div>
          </div>

          <!-- Modal Action Buttons -->
          <div class="pt-4 border-t border-slate-800 flex items-center justify-end gap-2.5">
            <button
              type="button"
              @click="showEditModal = false"
              class="px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-300 font-bold transition cursor-pointer"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-6 py-2.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-black flex items-center gap-2 transition disabled:opacity-50 cursor-pointer shadow-lg shadow-amber-500/20"
            >
              <Edit3 class="w-4 h-4" />
              <span>{{ isSubmitting ? 'Menyimpan...' : 'Simpan Perubahan' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 3. MODAL INSPEKSI OTORITAS & MATRIKS HAK AKSES (17 MODUL DARI /roles)     -->
    <!-- ========================================================================= -->
    <div
      v-if="showInspectModal && inspectUser"
      class="fixed inset-0 z-50 bg-black/85 backdrop-blur-md flex items-center justify-center p-4 overflow-y-auto"
      @click.self="showInspectModal = false"
    >
      <div class="relative w-full max-w-2xl bg-[#091122] border border-slate-800 rounded-3xl overflow-hidden shadow-2xl flex flex-col my-8">
        <div class="px-6 py-4 border-b border-slate-800 bg-[#0b1426] flex items-center justify-between">
          <div class="flex items-center gap-2.5">
            <KeyRound class="w-5 h-5 text-amber-400" />
            <div>
              <h3 class="text-sm font-extrabold text-white">Inspeksi Otoritas: {{ inspectUser.name }}</h3>
              <p class="text-[10px] text-slate-400">Rincian matriks wewenang operasional berdasarkan konfigurasi /roles</p>
            </div>
          </div>
          <button
            @click="showInspectModal = false"
            class="w-8 h-8 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center transition cursor-pointer"
          >
            <X class="w-4 h-4" />
          </button>
        </div>

        <div class="p-6 space-y-5 text-xs max-h-[75vh] overflow-y-auto custom-scrollbar">
          <!-- User Summary Header Card -->
          <div class="p-4 rounded-2xl bg-slate-900/90 border border-slate-800 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
              <div class="w-12 h-12 rounded-2xl bg-slate-800 border border-slate-700 flex items-center justify-center font-bold text-amber-400 text-base shadow">
                {{ inspectUser.role === 'kadiv_humas' ? '⭐⭐' : (inspectUser.role === 'super_admin_mabes' ? '🛡️' : inspectUser.name.charAt(0)) }}
              </div>
              <div>
                <p class="font-extrabold text-white text-sm">{{ inspectUser.name }}</p>
                <div class="flex items-center gap-2 mt-0.5">
                  <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase border" :class="getRoleBadgeStyle(inspectUser.role)">
                    {{ getRoleMeta(inspectUser.role).badge }} • {{ getRoleMeta(inspectUser.role).name }}
                  </span>
                  <span class="text-slate-400 font-mono text-[11px]">NRP: {{ inspectUser.badge_number || '-' }}</span>
                </div>
              </div>
            </div>

            <div class="text-right">
              <span class="font-mono text-base font-black text-amber-400">
                {{ getRoleMeta(inspectUser.role).grantedCount }} / {{ getRoleMeta(inspectUser.role).totalCount }}
              </span>
              <span class="block text-[10px] text-slate-400">Modul Diizinkan</span>
            </div>
          </div>

          <!-- 4 Pillars Modules Breakdown -->
          <div class="space-y-4">
            <div
              v-for="(modList, pillarName) in modulesByPillar"
              :key="pillarName"
              class="space-y-2"
            >
              <h4 class="text-[11px] font-black uppercase tracking-wider text-amber-400 flex items-center gap-1.5">
                <Layers class="w-3.5 h-3.5" />
                <span>{{ pillarName }}</span>
              </h4>

              <div class="grid grid-cols-1 gap-2">
                <div
                  v-for="m in modList"
                  :key="m.key"
                  class="p-2.5 rounded-xl border flex items-center justify-between gap-3 transition"
                  :class="inspectRoleMatrix?.permissions?.[m.key]
                    ? 'bg-emerald-500/5 border-emerald-500/20 text-slate-200'
                    : 'bg-slate-900/40 border-slate-800/80 text-slate-500'"
                >
                  <div class="space-y-0.5 min-w-0">
                    <p class="font-bold text-xs flex items-center gap-1.5">
                      <span :class="inspectRoleMatrix?.permissions?.[m.key] ? 'text-white' : 'text-slate-500 line-through'">
                        {{ m.name }}
                      </span>
                    </p>
                    <p class="text-[10px] text-slate-400 truncate">{{ m.description }}</p>
                  </div>

                  <span
                    class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase shrink-0 flex items-center gap-1 border"
                    :class="inspectRoleMatrix?.permissions?.[m.key]
                      ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30'
                      : 'bg-slate-800 text-slate-500 border-slate-700'"
                  >
                    <CheckCircle2 v-if="inspectRoleMatrix?.permissions?.[m.key]" class="w-3 h-3 text-emerald-400" />
                    <XCircle v-else class="w-3 h-3 text-slate-500" />
                    <span>{{ inspectRoleMatrix?.permissions?.[m.key] ? 'Diizinkan' : 'Dibatasi' }}</span>
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="p-4 border-t border-slate-800 bg-[#0b1426] flex items-center justify-between">
          <Link
            href="/roles"
            class="text-xs text-amber-400 hover:text-amber-300 font-bold inline-flex items-center gap-1.5 transition"
          >
            <span>Sesuaikan Konfigurasi Otoritas di Matriks Hak Akses (/roles)</span>
            <ExternalLink class="w-3.5 h-3.5" />
          </Link>

          <button
            type="button"
            @click="showInspectModal = false"
            class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-200 font-bold transition cursor-pointer"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 4. MODAL RESET PASSWORD DINAS                                             -->
    <!-- ========================================================================= -->
    <div
      v-if="showResetPasswordModal"
      class="fixed inset-0 z-50 bg-black/85 backdrop-blur-md flex items-center justify-center p-4"
      @click.self="showResetPasswordModal = false"
    >
      <div class="relative w-full max-w-md bg-[#091122] border border-slate-800 rounded-3xl overflow-hidden shadow-2xl flex flex-col">
        <div class="px-6 py-4 border-b border-slate-800 bg-[#0b1426] flex items-center justify-between">
          <div class="flex items-center gap-2">
            <Key class="w-4 h-4 text-sky-400" />
            <h3 class="text-sm font-extrabold text-white">Reset Password Dinas</h3>
          </div>
          <button
            @click="showResetPasswordModal = false"
            class="w-7 h-7 rounded-lg bg-slate-900 hover:bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center cursor-pointer"
          >
            <X class="w-4 h-4" />
          </button>
        </div>

        <form @submit.prevent="submitResetPassword" class="p-6 space-y-4 text-xs">
          <div class="p-3.5 rounded-xl bg-slate-900 border border-slate-800 text-slate-300 space-y-1">
            <p class="font-bold text-white">Personel: {{ resetPasswordForm.userName }}</p>
            <p class="text-[11px] text-slate-400">
              Menetapkan password dinas sementara baru. Personel dapat mengganti password melalui menu Profil Saya setelah berhasil login.
            </p>
          </div>

          <div>
            <label class="block font-bold text-slate-200 mb-1">Password Baru *</label>
            <input
              v-model="resetPasswordForm.new_password"
              type="text"
              required
              minlength="6"
              placeholder="Minimal 6 karakter..."
              class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:border-sky-400 font-mono"
            />
          </div>

          <div class="pt-3 border-t border-slate-800 flex items-center justify-end gap-2.5">
            <button
              type="button"
              @click="showResetPasswordModal = false"
              class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-300 font-bold transition cursor-pointer"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-5 py-2 rounded-xl bg-sky-500 hover:bg-sky-400 text-white font-black flex items-center gap-2 transition disabled:opacity-50 cursor-pointer shadow-lg shadow-sky-500/20"
            >
              <Key class="w-4 h-4" />
              <span>{{ isSubmitting ? 'Mereset...' : 'Reset Password Sekarang' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 5. MODAL KONFIRMASI HAPUS PERSONEL                                        -->
    <!-- ========================================================================= -->
    <div
      v-if="showDeleteModal && selectedUserForAction"
      class="fixed inset-0 z-50 bg-black/85 backdrop-blur-md flex items-center justify-center p-4"
      @click.self="showDeleteModal = false"
    >
      <div class="relative w-full max-w-md bg-[#091122] border border-rose-500/30 rounded-3xl overflow-hidden shadow-2xl flex flex-col">
        <div class="px-6 py-4 border-b border-rose-500/20 bg-rose-950/40 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <AlertTriangle class="w-5 h-5 text-rose-400" />
            <h3 class="text-sm font-extrabold text-white">Konfirmasi Hapus Akun Personel</h3>
          </div>
          <button
            @click="showDeleteModal = false"
            class="w-7 h-7 rounded-lg bg-slate-900 hover:bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center cursor-pointer"
          >
            <X class="w-4 h-4" />
          </button>
        </div>

        <div class="p-6 space-y-4 text-xs">
          <div class="p-3.5 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-300 space-y-1">
            <p class="font-bold text-white text-sm">{{ selectedUserForAction.name }}</p>
            <p class="font-mono text-[11px] text-rose-300">
              Username: {{ selectedUserForAction.username }} • NRP: {{ selectedUserForAction.badge_number || '-' }}
            </p>
          </div>

          <p class="text-slate-300 leading-relaxed text-[11px]">
            Tindakan ini akan menghapus akses login personel secara permanen dari server SIMOD Presisi. Riwayat laporan dan penugasan yang pernah dibuat akan tetap tersimpan dalam Audit Log.
          </p>

          <div class="pt-3 border-t border-slate-800 flex items-center justify-end gap-2.5">
            <button
              type="button"
              @click="showDeleteModal = false"
              class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-300 font-bold transition cursor-pointer"
            >
              Batal
            </button>
            <button
              type="button"
              @click="confirmDeleteUser"
              :disabled="isSubmitting"
              class="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-black flex items-center gap-2 transition disabled:opacity-50 cursor-pointer shadow-lg shadow-rose-600/30"
            >
              <Trash2 class="w-4 h-4" />
              <span>{{ isSubmitting ? 'Menghapus...' : 'Ya, Hapus Akun' }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </EnterpriseLayout>
</template>
