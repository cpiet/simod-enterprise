<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import {
  Users,
  UserPlus,
  Shield,
  Search,
  Key,
  Edit3,
  CheckCircle2,
  Lock,
  Building2,
  X,
  Phone,
  Mail,
  UserCheck,
  Filter,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  users: Object, // Paginated { data: [...], links: [...] }
  polresList: Array,
  personnelStats: Object,
  filters: Object,
  metrics: Object,
});

const searchQuery = ref(props.filters?.q || '');
const filterRole = ref(props.filters?.role || 'all');
const showAddModal = ref(false);

const form = ref({
  polda_id: props.selectedPolda?.id || 1,
  polres_id: '',
  name: '',
  username: '',
  email: '',
  badge_number: '',
  role: 'operator',
  phone: '',
  password: '',
});

const isSubmitting = ref(false);

const applyFilter = () => {
  router.get('/personalia', {
    polda: props.selectedCode,
    role: filterRole.value,
    q: searchQuery.value,
  }, {
    preserveState: true,
    preserveScroll: true,
  });
};

const submitAddUser = () => {
  isSubmitting.value = true;
  router.post('/personalia', form.value, {
    preserveScroll: true,
    onSuccess: () => {
      isSubmitting.value = false;
      showAddModal.value = false;
      form.value = {
        polda_id: props.selectedPolda?.id || 1,
        polres_id: '',
        name: '',
        username: '',
        email: '',
        badge_number: '',
        role: 'operator',
        phone: '',
        password: '',
      };
    },
    onError: () => {
      isSubmitting.value = false;
    },
  });
};

const getRoleBadge = (role) => {
  switch (role) {
    case 'kadiv_humas':
      return { label: '⭐⭐ KADIVHUMAS POLRI', color: 'bg-gradient-to-r from-amber-500/25 to-yellow-500/20 text-amber-300 border-amber-400/50 font-black tracking-wider' };
    case 'super_admin_mabes':
      return { label: 'SUPERADMIN MABES', color: 'bg-rose-500/20 text-rose-300 border-rose-500/30' };
    case 'polda_leader':
      return { label: 'PIMPINAN POLDA', color: 'bg-amber-400/20 text-amber-300 border-amber-400/30' };
    case 'verifikator':
      return { label: 'VERIFIKATOR', color: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30' };
    case 'analis':
      return { label: 'ANALIS INTELIJEN', color: 'bg-sky-500/20 text-sky-300 border-sky-500/30' };
    case 'operator':
    default:
      return { label: 'OPERATOR SATWIL', color: 'bg-slate-800 text-slate-300 border-slate-700' };
  }
};
</script>

<template>
  <EnterpriseLayout
    :all-poldas="allPoldas"
    :selected-polda="selectedPolda"
    :selected-code="selectedCode"
    :metrics="metrics"
  >
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-400 text-slate-950">
            Manajemen Personalia
          </span>
          <span class="text-xs font-bold text-slate-400">
            {{ selectedCode === 'ALL' ? 'Divisi Humas Polri (Komando Nasional)' : (selectedPolda?.name || 'Polda Sulawesi Barat') }}
          </span>
        </div>
        <h1 class="text-2xl md:text-3xl font-black text-white tracking-tight mt-1">
          Personalia &amp; Pengguna Sistem
        </h1>
        <p class="text-xs md:text-sm text-slate-300">
          Pengelolaan akun personel Humas, otorisasi peran berjenjang, dan delegasi hak akses Divhumas Polri &amp; jajaran Satwil.
        </p>
      </div>

      <button
        @click="showAddModal = true"
        class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-400 hover:brightness-110 text-slate-950 text-xs font-black shadow-lg shadow-amber-500/20 transition cursor-pointer"
      >
        <UserPlus class="w-4 h-4" />
        <span>+ Tambah Personel Baru</span>
      </button>
    </div>

    <!-- 4 Personnel KPI Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <span class="text-[11px] font-black uppercase text-slate-400">Total Personel Aktif</span>
        <div class="text-3xl font-black text-white">
          {{ personnelStats?.total || users?.total || 14 }}
        </div>
        <p class="text-[11px] text-emerald-400">Terdaftar Resmi</p>
      </div>

      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <span class="text-[11px] font-black uppercase text-slate-400">Komando Mabes &amp; Divhumas</span>
        <div class="text-3xl font-black text-amber-400">
          {{ personnelStats?.superadmin || 3 }}
        </div>
        <p class="text-[11px] text-amber-400 font-semibold">Kadivhumas &amp; Superadmin</p>
      </div>

      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <span class="text-[11px] font-black uppercase text-slate-400">Staf Bidhumas Polda</span>
        <div class="text-3xl font-black text-sky-400">
          {{ personnelStats?.polda_staff || 4 }}
        </div>
        <p class="text-[11px] text-slate-400">Verifikator &amp; Analis</p>
      </div>

      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <span class="text-[11px] font-black uppercase text-slate-400">Operator Satwil Jajaran</span>
        <div class="text-3xl font-black text-emerald-400">
          {{ personnelStats?.satwil || 7 }}
        </div>
        <p class="text-[11px] text-slate-400">Kasihumas Polres</p>
      </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="p-4 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-xl flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
      <div class="flex items-center gap-2">
        <Filter class="w-4 h-4 text-slate-400" />
        <select
          v-model="filterRole"
          @change="applyFilter"
          class="px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-700 text-xs text-white focus:outline-none focus:border-amber-400"
        >
          <option value="all">Semua Peran (Roles)</option>
          <option value="kadiv_humas">🇮🇩 Kadivhumas Polri (Mabes)</option>
          <option value="super_admin_mabes">Super Administrator Mabes</option>
          <option value="polda_leader">Pimpinan Polda (Kabid Humas)</option>
          <option value="verifikator">Verifikator (Penmas)</option>
          <option value="analis">Analis Intelijen Media</option>
          <option value="operator">Operator Satwil (Polres)</option>
        </select>
      </div>

      <div class="relative w-full sm:w-72">
        <Search class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
        <input
          v-model="searchQuery"
          @keyup.enter="applyFilter"
          type="text"
          placeholder="Cari nama personel, NRP, username..."
          class="w-full pl-9 pr-3 py-1.5 rounded-xl bg-slate-900 border border-slate-700 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-amber-400"
        />
      </div>
    </div>

    <!-- Table of Personnel -->
    <div class="rounded-3xl bg-[#0b1426] border border-slate-800 shadow-2xl overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
        <h3 class="text-sm font-black text-white uppercase tracking-wider">Daftar Anggota Humas Terdaftar</h3>
        <span class="text-xs text-slate-400">Total {{ users?.total || 0 }} Personel</span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="border-b border-slate-800 bg-slate-900/60 text-[11px] font-black uppercase text-slate-400">
              <th class="px-6 py-3.5">Nama &amp; NRP Personel</th>
              <th class="px-6 py-3.5">Peran Sistem</th>
              <th class="px-6 py-3.5">Satuan Wilayah / Penugasan</th>
              <th class="px-6 py-3.5">Kontak &amp; Username</th>
              <th class="px-6 py-3.5">Status Akun</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <tr
              v-for="u in users?.data || []"
              :key="u.id"
              class="hover:bg-slate-900/40 transition"
            >
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <div
                    class="w-9 h-9 rounded-xl border flex items-center justify-center font-bold text-xs shadow-sm"
                    :class="u.role === 'kadiv_humas'
                      ? 'bg-gradient-to-tr from-amber-400 to-yellow-300 text-slate-950 border-amber-300 ring-2 ring-amber-400/40 font-black'
                      : 'bg-slate-800 border-slate-700 text-amber-400'"
                  >
                    {{ u.role === 'kadiv_humas' ? '⭐⭐' : u.name.charAt(0) }}
                  </div>
                  <div>
                    <p class="font-extrabold text-white flex items-center gap-1.5">
                      <span>{{ u.name }}</span>
                      <span v-if="u.role === 'kadiv_humas'" class="px-1.5 py-0.2 rounded text-[8px] font-black uppercase bg-amber-400 text-slate-950">
                        Kadiv
                      </span>
                    </p>
                    <span class="text-[11px] font-mono text-slate-400">NRP: {{ u.badge_number || '-' }}</span>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4">
                <span
                  class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase border tracking-wider"
                  :class="getRoleBadge(u.role).color"
                >
                  {{ getRoleBadge(u.role).label }}
                </span>
              </td>
              <td class="px-6 py-4">
                <div class="flex items-center gap-1.5 font-semibold text-slate-200">
                  <Building2 class="w-3.5 h-3.5 text-amber-400 flex-shrink-0" />
                  <span>{{ u.polres?.name || u.polda?.name || (u.role === 'kadiv_humas' || u.role === 'super_admin_mabes' ? 'Divisi Humas Polri (Mabes)' : 'Bidhumas Polda Sulbar') }}</span>
                </div>
              </td>
              <td class="px-6 py-4 space-y-0.5">
                <p class="font-mono text-slate-300">{{ u.username }}</p>
                <p class="text-[11px] text-slate-400">{{ u.email }}</p>
                <p v-if="u.phone" class="text-[10px] text-slate-500 font-mono">{{ u.phone }}</p>
              </td>
              <td class="px-6 py-4">
                <span
                  class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase flex items-center gap-1 w-fit"
                  :class="u.is_active ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-400 border border-rose-500/30'"
                >
                  <CheckCircle2 class="w-3 h-3" />
                  {{ u.is_active ? 'Aktif' : 'Non-Aktif' }}
                </span>
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

    <!-- ========================================================================= -->
    <!-- TAMBAH PERSONEL MODAL                                                     -->
    <!-- ========================================================================= -->
    <div
      v-if="showAddModal"
      class="fixed inset-0 z-50 bg-black/85 backdrop-blur-md flex items-center justify-center p-4"
      @click.self="showAddModal = false"
    >
      <div class="relative w-full max-w-lg bg-[#091122] border border-slate-800 rounded-3xl overflow-hidden shadow-2xl flex flex-col">
        <div class="px-6 py-4 border-b border-slate-800 bg-[#0b1426] flex items-center justify-between">
          <div class="flex items-center gap-2">
            <UserPlus class="w-4 h-4 text-amber-400" />
            <h3 class="text-sm font-extrabold text-white">Tambah Akun Personel Humas</h3>
          </div>
          <button
            @click="showAddModal = false"
            class="w-7 h-7 rounded-lg bg-slate-900 hover:bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center"
          >
            <X class="w-4 h-4" />
          </button>
        </div>

        <form @submit.prevent="submitAddUser" class="p-6 space-y-4 text-xs">
          <div>
            <label class="block font-bold text-slate-300 mb-1">Nama Lengkap & Pangkat *</label>
            <input
              v-model="form.name"
              type="text"
              required
              placeholder="Contoh: Bripka Muh. Yunus, S.H."
              class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:border-amber-400"
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-300 mb-1">Satuan Induk (Polda / Mabes) *</label>
              <select
                v-model="form.polda_id"
                class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400"
              >
                <option value="">🇮🇩 Divisi Humas Polri (Mabes)</option>
                <option v-for="p in allPoldas" :key="p.id" :value="p.id">
                  🏛️ {{ p.name }}
                </option>
              </select>
            </div>

            <div>
              <label class="block font-bold text-slate-300 mb-1">Peran Akses (Role) *</label>
              <select
                v-model="form.role"
                class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400"
              >
                <option value="kadiv_humas">🇮🇩 Kadivhumas Polri (Mabes ⭐⭐)</option>
                <option value="super_admin_mabes">Super Administrator Mabes</option>
                <option value="polda_leader">Pimpinan Polda (Kabid Humas)</option>
                <option value="verifikator">Verifikator (Penmas)</option>
                <option value="analis">Analis Intelijen Media</option>
                <option value="operator">Operator Satwil (Polres)</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-300 mb-1">NRP Anggota *</label>
              <input
                v-model="form.badge_number"
                type="text"
                required
                placeholder="Contoh: 89030214"
                class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:border-amber-400 font-mono"
              />
            </div>

            <div>
              <label class="block font-bold text-slate-300 mb-1">Satker / Polres (Opsional)</label>
              <select
                v-model="form.polres_id"
                class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400"
              >
                <option value="">{{ form.polda_id ? 'Tingkat Markas Polda' : 'Divisi Humas Polri Mabes' }}</option>
                <option v-for="p in polresList" :key="p.id" :value="p.id">
                  {{ p.name }}
                </option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-300 mb-1">Email Dinas *</label>
              <input
                v-model="form.email"
                type="email"
                required
                placeholder="nama@polri.go.id"
                class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:border-amber-400"
              />
            </div>

            <div>
              <label class="block font-bold text-slate-300 mb-1">Nomor WhatsApp / HP</label>
              <input
                v-model="form.phone"
                type="text"
                placeholder="0812xxxxxxxx"
                class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:border-amber-400 font-mono"
              />
            </div>
          </div>

          <div>
            <label class="block font-bold text-slate-300 mb-1">Password Sementara *</label>
            <input
              v-model="form.password"
              type="password"
              required
              minlength="6"
              placeholder="Minimal 6 karakter..."
              class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:border-amber-400"
            />
          </div>

          <div class="pt-3 border-t border-slate-800 flex items-center justify-end gap-2">
            <button
              type="button"
              @click="showAddModal = false"
              class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-300 font-bold transition"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-5 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-black flex items-center gap-2 transition disabled:opacity-50"
            >
              <UserPlus class="w-4 h-4" />
              <span>{{ isSubmitting ? 'Mendaftarkan...' : 'Daftarkan Personel' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </EnterpriseLayout>
</template>
