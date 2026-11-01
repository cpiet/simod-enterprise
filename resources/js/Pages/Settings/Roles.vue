<script setup>
import SecurityMaintenanceTabs from '@/Components/SecurityMaintenanceTabs.vue';
import { ref, computed, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import {
  KeyRound,
  Shield,
  Save,
  CheckCircle2,
  Users,
  Lock,
  RotateCcw,
  Search,
  Check,
  X,
  AlertTriangle,
  Info,
  Layers,
  Sparkles,
  ShieldCheck,
  CheckCheck,
  Ban,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: {
    type: String,
    default: 'SULBAR',
  },
  metrics: Object,
  modules: {
    type: Array,
    default: () => [],
  },
  roleMatrix: {
    type: Array,
    default: () => [],
  },
  targetPoldaId: Number,
});

const page = usePage();
const isSaving = ref(false);
const isResetting = ref(false);
const toastMessage = ref('');
const searchQuery = ref('');
const selectedPillar = ref('all');
const showResetModal = ref(false);

// Deep copy data matrix agar bisa diedit secara reaktif di tabel
const matrix = ref(JSON.parse(JSON.stringify(props.roleMatrix || [])));
const originalJson = ref(JSON.stringify(props.roleMatrix || []));

watch(() => props.roleMatrix, (newVal) => {
  matrix.value = JSON.parse(JSON.stringify(newVal || []));
  originalJson.value = JSON.stringify(newVal || []);
}, { deep: true });

// Deteksi perubahan yang belum disimpan
const hasUnsavedChanges = computed(() => {
  return JSON.stringify(matrix.value) !== originalJson.value;
});

// Daftar 4 Pilar Hierarki SIMOD
const pillars = computed(() => {
  const set = new Set();
  (props.modules || []).forEach(m => {
    if (m.pillar) set.add(m.pillar);
  });
  return Array.from(set);
});

// Modul yang terfilter pencarian & pilar
const filteredModules = computed(() => {
  let list = props.modules || [];
  if (selectedPillar.value !== 'all') {
    list = list.filter(m => m.pillar === selectedPillar.value);
  }
  if (searchQuery.value.trim() !== '') {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(m =>
      m.name.toLowerCase().includes(q) ||
      m.key.toLowerCase().includes(q) ||
      (m.description && m.description.toLowerCase().includes(q))
    );
  }
  return list;
});

// Hitung jumlah izin aktif per peran
const getGrantedCount = (roleIndex) => {
  const r = matrix.value[roleIndex];
  if (!r || !r.permissions) return 0;
  return Object.values(r.permissions).filter(Boolean).length;
};

// Hitung jumlah total peran yang memiliki izin pada modul tertentu
const getRolesCountForModule = (moduleKey) => {
  return matrix.value.filter(r => !!r.permissions?.[moduleKey]).length;
};

// Toggle izin modul untuk peran tertentu
const togglePermission = (roleIndex, moduleKey) => {
  if (!matrix.value[roleIndex].permissions) {
    matrix.value[roleIndex].permissions = {};
  }
  matrix.value[roleIndex].permissions[moduleKey] = !matrix.value[roleIndex].permissions[moduleKey];
};

// Beri seluruh izin untuk suatu peran
const grantAllForRole = (roleIndex) => {
  const perms = {};
  (props.modules || []).forEach(m => {
    perms[m.key] = true;
  });
  matrix.value[roleIndex].permissions = perms;
};

// Cabut seluruh izin untuk suatu peran
const revokeAllForRole = (roleIndex) => {
  const roleKey = matrix.value[roleIndex].role_key;
  if (roleKey === 'kadiv_humas') {
    alert('Kadivhumas Polri adalah pimpinan tertinggi institusi dan memiliki hak akses komando mutlak.');
    return;
  }
  const perms = {};
  (props.modules || []).forEach(m => {
    perms[m.key] = false;
  });
  matrix.value[roleIndex].permissions = perms;
};

// Toggle semua peran pada 1 modul sekaligus
const toggleAllRolesForModule = (moduleKey) => {
  const anyInactive = matrix.value.some(r => !r.permissions?.[moduleKey]);
  const targetVal = anyInactive;
  matrix.value.forEach(r => {
    if (!r.permissions) r.permissions = {};
    r.permissions[moduleKey] = targetVal;
  });
};

// Simpan perubahan matriks ke database MySQL/SQLite
const saveMatrix = () => {
  isSaving.value = true;
  router.post(
    `/roles?polda=${props.selectedCode || 'ALL'}`,
    {
      matrix: matrix.value,
      polda_id: props.targetPoldaId,
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        isSaving.value = false;
        originalJson.value = JSON.stringify(matrix.value);
        toastMessage.value = '✓ Matriks Hak Akses & Otoritas berhasil disimpan permanen ke basis data!';
        setTimeout(() => {
          toastMessage.value = '';
        }, 4000);
      },
      onError: (err) => {
        isSaving.value = false;
        alert('Gagal menyimpan matriks: ' + Object.values(err).join(', '));
      },
    }
  );
};

// Reset matriks ke standar Polri Presisi
const confirmResetMatrix = () => {
  showResetModal.value = false;
  isResetting.value = true;
  router.post(
    `/roles/reset?polda=${props.selectedCode || 'ALL'}`,
    {},
    {
      preserveScroll: true,
      onSuccess: () => {
        isResetting.value = false;
        toastMessage.value = '✓ Matriks Hak Akses berhasil direset ke standar resmi Polri Presisi!';
        setTimeout(() => {
          toastMessage.value = '';
        }, 4000);
      },
      onError: () => {
        isResetting.value = false;
      },
    }
  );
};

const getRoleColor = (roleKey) => {
  switch (roleKey) {
    case 'kadiv_humas':
      return 'border-amber-400/50 bg-amber-400/10 text-amber-300';
    case 'super_admin_mabes':
      return 'border-rose-500/50 bg-rose-500/10 text-rose-300';
    case 'polda_leader':
      return 'border-yellow-400/50 bg-yellow-400/10 text-yellow-300';
    case 'verifikator':
      return 'border-emerald-500/50 bg-emerald-500/10 text-emerald-300';
    case 'analis':
      return 'border-sky-500/50 bg-sky-500/10 text-sky-300';
    case 'operator':
    default:
      return 'border-slate-700 bg-slate-800/80 text-slate-300';
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
    <!-- Tab Navigasi Sub-Modul Keamanan & Pemeliharaan -->
    <SecurityMaintenanceTabs :selected-code="selectedCode" />

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
        class="fixed top-6 right-6 z-50 flex items-center gap-3 px-5 py-3 rounded-2xl bg-emerald-500/90 text-white font-bold text-xs shadow-2xl backdrop-blur-xl border border-emerald-400/50"
      >
        <CheckCircle2 class="w-5 h-5 text-white shrink-0" />
        <span>{{ toastMessage }}</span>
      </div>
    </transition>

    <!-- Header & Action Toolbar -->
    <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-400 text-slate-950">
            RBAC Presisi Terpadu
          </span>
          <span class="text-xs font-bold text-slate-400 flex items-center gap-1.5">
            <Shield class="w-3.5 h-3.5 text-amber-400" />
            <span>{{ selectedCode === 'ALL' ? 'Divisi Humas Polri (Nasional)' : (selectedPolda?.name || 'Polda Sulawesi Barat') }}</span>
          </span>
          <span
            v-if="hasUnsavedChanges"
            class="px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase bg-rose-500/20 text-rose-300 border border-rose-500/40 animate-pulse"
          >
            ● Perubahan Belum Disimpan
          </span>
        </div>
        <h1 class="text-2xl md:text-3xl font-black text-white tracking-tight mt-1 flex items-center gap-3">
          <span>Matriks Hak Akses &amp; Otoritas (RBAC)</span>
          <span class="text-xs px-2.5 py-1 rounded-xl bg-slate-900 border border-slate-800 text-amber-400 font-mono font-bold">
            {{ modules.length }} Modul / {{ matrix.length }} Peran
          </span>
        </h1>
        <p class="text-xs md:text-sm text-slate-400 max-w-3xl">
          Konfigurasi otorisasi granular hak cipta, akses menu, dan wewenang operasional komando kehumasan Polri berdasarkan tingkatan pangkat dinas dan satfung.
        </p>
      </div>

      <!-- Action Buttons -->
      <div class="flex flex-wrap items-center gap-2.5">
        <!-- Reset to Presisi Defaults -->
        <button
          type="button"
          @click="showResetModal = true"
          :disabled="isResetting || isSaving"
          class="flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700/80 text-slate-300 hover:text-white text-xs font-bold transition shadow-sm"
          title="Kembalikan seluruh hak akses ke standar baku Divhumas Polri"
        >
          <RotateCcw class="w-3.5 h-3.5 text-amber-400" :class="{ 'animate-spin': isResetting }" />
          <span>Reset Standar Polri</span>
        </button>

        <!-- Save Button -->
        <button
          type="button"
          @click="saveMatrix"
          :disabled="isSaving"
          class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-black transition shadow-xl"
          :class="hasUnsavedChanges
            ? 'bg-amber-400 hover:bg-amber-300 text-slate-950 shadow-amber-400/25 ring-2 ring-amber-400/50 animate-bounce-subtle'
            : 'bg-amber-400 hover:bg-amber-300 text-slate-950 shadow-amber-400/20'"
        >
          <Save class="w-4 h-4" :class="{ 'animate-spin': isSaving }" />
          <span>{{ isSaving ? 'Menyimpan Matriks...' : (hasUnsavedChanges ? 'Simpan Perubahan Otoritas' : 'Matriks Tersimpan') }}</span>
        </button>
      </div>
    </div>

    <!-- Role Summary Overview Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-3">
      <div
        v-for="(r, rIdx) in matrix"
        :key="r.role_key"
        class="p-3.5 rounded-2xl bg-[#0b1426] border shadow-lg transition hover:border-slate-700 flex flex-col justify-between"
        :class="getRoleColor(r.role_key)"
      >
        <div>
          <div class="flex items-center justify-between gap-1 mb-1.5">
            <span class="text-[9px] font-extrabold uppercase tracking-wider px-2 py-0.5 rounded-md bg-black/40 border border-current">
              {{ r.badge || 'ROLE' }}
            </span>
            <span class="text-[10px] font-mono font-black text-white">
              {{ getGrantedCount(rIdx) }}/{{ modules.length }}
            </span>
          </div>
          <div class="text-xs font-black text-white leading-snug truncate" :title="r.role_name">
            {{ r.role_name }}
          </div>
          <div class="text-[10px] text-slate-400 line-clamp-2 mt-1" :title="r.description">
            {{ r.description }}
          </div>
        </div>

        <!-- Quick column actions -->
        <div class="flex items-center justify-between gap-1 pt-2.5 mt-2.5 border-t border-slate-800/80 text-[10px]">
          <button
            type="button"
            @click="grantAllForRole(rIdx)"
            class="text-amber-400 hover:text-amber-300 font-bold transition flex items-center gap-1"
            title="Beri semua hak akses untuk peran ini"
          >
            <CheckCheck class="w-3 h-3" />
            <span>Semua</span>
          </button>
          <span class="text-slate-700">|</span>
          <button
            type="button"
            @click="revokeAllForRole(rIdx)"
            class="text-slate-400 hover:text-rose-400 font-bold transition flex items-center gap-1"
            title="Cabut seluruh izin untuk peran ini"
          >
            <Ban class="w-3 h-3" />
            <span>Cabut</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3 p-3 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-xl">
      <!-- Pillar Filter Pills -->
      <div class="flex flex-wrap items-center gap-1.5">
        <button
          type="button"
          @click="selectedPillar = 'all'"
          class="px-3 py-1.5 rounded-xl text-xs font-bold transition"
          :class="selectedPillar === 'all'
            ? 'bg-amber-400 text-slate-950 font-black shadow-sm'
            : 'bg-slate-900 text-slate-300 hover:bg-slate-800 hover:text-white border border-slate-800'"
        >
          Semua Modul ({{ modules.length }})
        </button>

        <button
          v-for="pil in pillars"
          :key="pil"
          type="button"
          @click="selectedPillar = pil"
          class="px-3 py-1.5 rounded-xl text-xs font-bold transition"
          :class="selectedPillar === pil
            ? 'bg-amber-400 text-slate-950 font-black shadow-sm'
            : 'bg-slate-900 text-slate-300 hover:bg-slate-800 hover:text-white border border-slate-800'"
        >
          {{ pil }}
        </button>
      </div>

      <!-- Keyword Search -->
      <div class="relative w-full md:w-72">
        <Search class="w-4 h-4 text-slate-500 absolute left-3 top-1/2 -translate-y-1/2" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Cari modul atau wewenang..."
          class="w-full pl-9 pr-3 py-1.5 rounded-xl bg-slate-900 border border-slate-700/80 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-amber-400"
        />
      </div>
    </div>

    <!-- Matrix Table -->
    <div class="rounded-3xl bg-[#0b1426] border border-slate-800 shadow-2xl overflow-hidden">
      <!-- Table Header Status Bar -->
      <div class="px-6 py-4 border-b border-slate-800/80 bg-slate-900/50 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div class="flex items-center gap-2.5">
          <KeyRound class="w-4 h-4 text-amber-400" />
          <h3 class="text-sm font-black text-white uppercase tracking-wider">
            Matriks Wewenang Fitur Terdaftar ({{ filteredModules.length }} Modul Ditampilkan)
          </h3>
        </div>
        <div class="flex items-center gap-2 text-xs text-slate-400">
          <Info class="w-3.5 h-3.5 text-amber-400 shrink-0" />
          <span>Klik kotak centang atau label peran untuk mengubah wewenang secara instan</span>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="border-b border-slate-800 bg-[#08101e] text-[11px] font-black uppercase text-slate-300 sticky top-0 z-10">
              <th class="px-6 py-4 min-w-[320px]">
                <div class="flex items-center justify-between">
                  <span>Modul / Kapabilitas Sistem</span>
                  <span class="text-[10px] text-slate-500 font-normal normal-case">Pilar &amp; Deskripsi</span>
                </div>
              </th>
              <th
                v-for="(r, rIdx) in matrix"
                :key="r.role_key"
                class="px-3 py-4 text-center min-w-[130px] border-l border-slate-800/60"
              >
                <div class="font-black text-white leading-tight">{{ r.role_name }}</div>
                <div class="text-[9px] font-mono font-normal opacity-75 mt-0.5 text-amber-400">
                  {{ r.badge }}
                </div>
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800/70 text-xs">
            <tr
              v-for="mod in filteredModules"
              :key="mod.key"
              class="hover:bg-slate-900/60 transition group"
            >
              <!-- Module Label & Pillar Info -->
              <td class="px-6 py-3.5">
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <div class="flex items-center gap-2">
                      <span class="font-extrabold text-white text-xs group-hover:text-amber-400 transition">
                        {{ mod.name }}
                      </span>
                      <span class="text-[9px] font-mono px-1.5 py-0.5 rounded bg-slate-900 text-slate-400 border border-slate-800">
                        {{ mod.key }}
                      </span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-0.5 line-clamp-1">
                      {{ mod.description }}
                    </p>
                  </div>
                  <div class="shrink-0 flex items-center gap-1.5 pt-0.5">
                    <span class="px-2 py-0.5 rounded-md text-[9px] font-bold bg-slate-900 text-slate-400 border border-slate-800 whitespace-nowrap">
                      {{ mod.pillar }}
                    </span>
                    <button
                      type="button"
                      @click="toggleAllRolesForModule(mod.key)"
                      class="opacity-0 group-hover:opacity-100 p-1 rounded hover:bg-slate-800 text-slate-400 hover:text-amber-400 transition"
                      title="Toggle wewenang untuk semua peran pada modul ini"
                    >
                      <CheckCheck class="w-3.5 h-3.5" />
                    </button>
                  </div>
                </div>
              </td>

              <!-- Checkbox Cells Per Role -->
              <td
                v-for="(r, rIdx) in matrix"
                :key="r.role_key"
                class="px-3 py-3.5 text-center border-l border-slate-800/60 transition"
                :class="r.permissions?.[mod.key] ? 'bg-amber-400/[0.02]' : ''"
              >
                <label class="inline-flex items-center justify-center cursor-pointer select-none">
                  <input
                    type="checkbox"
                    :checked="!!r.permissions?.[mod.key]"
                    @change="togglePermission(rIdx, mod.key)"
                    class="sr-only"
                  />
                  <div
                    class="w-6 h-6 rounded-lg border flex items-center justify-center transition-all duration-150"
                    :class="r.permissions?.[mod.key]
                      ? 'bg-amber-400 border-amber-400 text-slate-950 shadow-sm shadow-amber-400/30'
                      : 'bg-slate-900/90 border-slate-700 hover:border-slate-500 text-transparent'"
                  >
                    <Check class="w-3.5 h-3.5 stroke-[3]" />
                  </div>
                </label>
              </td>
            </tr>

            <!-- Empty State if Search yield no result -->
            <tr v-if="filteredModules.length === 0">
              <td :colspan="matrix.length + 1" class="px-6 py-12 text-center text-slate-500">
                <Search class="w-8 h-8 text-slate-600 mx-auto mb-2" />
                <p class="text-sm font-bold text-slate-400">Tidak ada modul yang cocok dengan kata kunci "{{ searchQuery }}"</p>
                <p class="text-xs text-slate-500 mt-1">Coba gunakan istilah pencarian lain atau pilih Semua Modul.</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Table Footer Summary -->
      <div class="px-6 py-4 border-t border-slate-800 bg-[#08101e] flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-slate-400">
        <div class="flex items-center gap-2">
          <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
          <span>Status Sistem: <strong>Otoritas RBAC Aktif &amp; Terintegrasi Database</strong></span>
        </div>
        <div class="flex items-center gap-4">
          <span>Total: <strong>{{ matrix.length }} Peran</strong></span>
          <span>&bull;</span>
          <span>Kapasitas: <strong>{{ modules.length }} Modul Komando</strong></span>
          <button
            type="button"
            @click="saveMatrix"
            class="ml-2 px-3 py-1 rounded-lg bg-amber-400 hover:bg-amber-300 text-slate-950 font-black text-[11px] transition"
          >
            Simpan Matriks
          </button>
        </div>
      </div>
    </div>

    <!-- Reset Confirmation Modal -->
    <div
      v-if="showResetModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md"
    >
      <div class="w-full max-w-md rounded-3xl bg-[#0b1426] border border-slate-700 shadow-2xl p-6 space-y-4">
        <div class="flex items-center gap-3 text-amber-400">
          <div class="p-3 rounded-2xl bg-amber-400/10 border border-amber-400/30">
            <AlertTriangle class="w-6 h-6" />
          </div>
          <div>
            <h3 class="text-base font-black text-white">Reset Matriks ke Standar Polri?</h3>
            <p class="text-xs text-slate-400">Tindakan ini akan mengembalikan seluruh izin ke setelan awal.</p>
          </div>
        </div>

        <p class="text-xs text-slate-300 bg-slate-900/80 p-3.5 rounded-xl border border-slate-800">
          Seluruh konfigurasi wewenang khusus yang telah Anda ubah akan ditimpa dengan matriks otorisasi baku Divisi Humas Polri. Apakah Anda yakin ingin melanjutkan?
        </p>

        <div class="flex items-center justify-end gap-2.5 pt-2">
          <button
            type="button"
            @click="showResetModal = false"
            class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold transition"
          >
            Batal
          </button>
          <button
            type="button"
            @click="confirmResetMatrix"
            class="px-4 py-2 rounded-xl bg-rose-500 hover:bg-rose-400 text-white text-xs font-black transition shadow-lg shadow-rose-500/20"
          >
            Ya, Reset ke Standar
          </button>
        </div>
      </div>
    </div>
  </EnterpriseLayout>
</template>
