<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import {
  Building2,
  Shield,
  Save,
  Plus,
  MapPin,
  Clock,
  Phone,
  CheckCircle2,
  Users,
  Globe,
  Radio,
  Layers,
  ExternalLink,
  X,
  PlusCircle,
  AlertCircle,
  ArrowRight,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  polresList: Array,
  metrics: Object,
});

const activeTab = ref('identity'); // 'identity', 'polres', 'tenants'
const isSaved = ref(false);
const isSubmitting = ref(false);

// Active Polda Edit Form
const form = ref({
  code: props.selectedPolda?.code || 'SULBAR',
  name: props.selectedPolda?.name || 'Polda Sulawesi Barat',
  region: props.selectedPolda?.region || 'Sulawesi Barat',
  timezone: props.selectedPolda?.timezone || 'Asia/Makassar',
  kapolda_name: props.selectedPolda?.kapolda_name || 'Irjen Pol. Drs. R. Adang Ginanjar S., M.M.',
  kabid_humas_name: props.selectedPolda?.kabid_humas_name || 'Kombes Pol. Slamet Wahyudi, S.I.K., M.H.',
});

// Modal: Tambah Polda Baru (Mabes)
const showAddPoldaModal = ref(false);
const newPoldaForm = ref({
  code: '',
  name: '',
  region: '',
  timezone: 'Asia/Makassar',
  kapolda_name: '',
  kabid_humas_name: '',
});

// Modal: Tambah Polres Baru
const showAddPolresModal = ref(false);
const newPolresForm = ref({
  polda_id: props.selectedPolda?.id || 1,
  code: '',
  name: '',
  short_name: '',
  tag_desc: '',
  latitude: -2.6718,
  longitude: 118.8872,
  map_x: 50,
  map_y: 50,
});

const saveSettings = () => {
  isSubmitting.value = true;
  router.post('/settings/polda/update', form.value, {
    preserveScroll: true,
    onSuccess: () => {
      isSubmitting.value = false;
      isSaved.value = true;
      setTimeout(() => (isSaved.value = false), 3500);
    },
    onError: () => {
      isSubmitting.value = false;
    },
  });
};

const submitNewPolda = () => {
  if (!newPoldaForm.value.code || !newPoldaForm.value.name) {
    alert('Kode dan Nama Polda wajib diisi.');
    return;
  }
  isSubmitting.value = true;
  router.post('/settings/polda/create', newPoldaForm.value, {
    preserveScroll: true,
    onSuccess: () => {
      isSubmitting.value = false;
      showAddPoldaModal.value = false;
      newPoldaForm.value = {
        code: '',
        name: '',
        region: '',
        timezone: 'Asia/Makassar',
        kapolda_name: '',
        kabid_humas_name: '',
      };
    },
    onError: () => {
      isSubmitting.value = false;
    },
  });
};

const submitNewPolres = () => {
  if (!newPolresForm.value.code || !newPolresForm.value.name) {
    alert('Kode dan Nama Polres wajib diisi.');
    return;
  }
  newPolresForm.value.polda_id = props.selectedPolda?.id || 1;
  isSubmitting.value = true;
  router.post('/settings/polres/create', newPolresForm.value, {
    preserveScroll: true,
    onSuccess: () => {
      isSubmitting.value = false;
      showAddPolresModal.value = false;
      newPolresForm.value = {
        polda_id: props.selectedPolda?.id || 1,
        code: '',
        name: '',
        short_name: '',
        tag_desc: '',
        latitude: -2.6718,
        longitude: 118.8872,
        map_x: 50,
        map_y: 50,
      };
    },
    onError: () => {
      isSubmitting.value = false;
    },
  });
};

const switchTenant = (code) => {
  router.get('/settings/polda', { polda: code }, { preserveScroll: true });
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
            Arsitektur Multi-Tenant
          </span>
          <span class="text-xs font-bold text-slate-400">
            {{ selectedCode === 'ALL' ? 'Mabes Polri (Nasional)' : (selectedPolda?.name || 'Polda Sulawesi Barat') }}
          </span>
        </div>
        <h1 class="text-2xl md:text-3xl font-black text-white tracking-tight mt-1">
          Pengaturan Wilayah Polda & Multi-Tenant Satwil
        </h1>
        <p class="text-xs md:text-sm text-slate-300">
          Identitas satuan kewilayahan, pimpinan komando, zona waktu operasi, dan manajemen tenant 34 Polda se-Indonesia.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          v-if="activeTab === 'identity'"
          @click="saveSettings"
          :disabled="isSubmitting"
          class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black transition shadow-lg shadow-amber-400/20 disabled:opacity-50"
        >
          <Save class="w-4 h-4" />
          <span>{{ isSaved ? 'Perubahan Disimpan!' : (isSubmitting ? 'Menyimpan...' : 'Simpan Perubahan') }}</span>
        </button>

        <button
          v-else-if="activeTab === 'polres'"
          @click="showAddPolresModal = true"
          class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-400 hover:brightness-110 text-slate-950 text-xs font-black shadow-lg shadow-amber-500/20 transition"
        >
          <PlusCircle class="w-4 h-4" />
          <span>+ Tambah Polres Baru</span>
        </button>

        <button
          v-else-if="activeTab === 'tenants'"
          @click="showAddPoldaModal = true"
          class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-400 hover:brightness-110 text-slate-950 text-xs font-black shadow-lg shadow-amber-500/20 transition"
        >
          <PlusCircle class="w-4 h-4" />
          <span>+ Daftarkan Polda Baru (Mabes)</span>
        </button>
      </div>
    </div>

    <!-- 4 Multi-Tenant Top KPI Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <span class="text-[11px] font-black uppercase text-slate-400">Total Tenant Polda</span>
        <div class="text-3xl font-black text-white">{{ allPoldas?.length || 5 }}</div>
        <p class="text-[11px] text-amber-400 font-semibold">Terkoneksi Multi-Wilayah</p>
      </div>

      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <span class="text-[11px] font-black uppercase text-slate-400">Satker Polres Jajaran</span>
        <div class="text-3xl font-black text-emerald-400">{{ polresList?.length || 6 }}</div>
        <p class="text-[11px] text-emerald-400">Di Bawah {{ selectedPolda?.code || 'SULBAR' }}</p>
      </div>

      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <span class="text-[11px] font-black uppercase text-slate-400">Zona Waktu Operasi</span>
        <div class="text-2xl font-black text-sky-400 font-mono">{{ selectedPolda?.timezone || 'Asia/Makassar' }}</div>
        <p class="text-[11px] text-slate-400">Format WITA / UTC+8</p>
      </div>

      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <span class="text-[11px] font-black uppercase text-slate-400">Status Multi-Tenant</span>
        <div class="text-3xl font-black text-white">TERISOLASI</div>
        <p class="text-[11px] text-emerald-400">RBAC & Data Scoping Aman</p>
      </div>
    </div>

    <!-- Tab Navigation Bar -->
    <div class="flex items-center gap-2 border-b border-slate-800 pb-2 overflow-x-auto">
      <button
        @click="activeTab = 'identity'"
        class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition whitespace-nowrap"
        :class="activeTab === 'identity'
          ? 'bg-amber-400 text-slate-950 font-black shadow-lg shadow-amber-400/20'
          : 'bg-slate-900 text-slate-300 hover:text-white border border-slate-800'"
      >
        <Building2 class="w-4 h-4" />
        <span>Identitas Polda Terpilih ({{ selectedPolda?.code || 'SULBAR' }})</span>
      </button>

      <button
        @click="activeTab = 'polres'"
        class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition whitespace-nowrap"
        :class="activeTab === 'polres'
          ? 'bg-amber-400 text-slate-950 font-black shadow-lg shadow-amber-400/20'
          : 'bg-slate-900 text-slate-300 hover:text-white border border-slate-800'"
      >
        <MapPin class="w-4 h-4" />
        <span>Struktur Satwil ({{ polresList?.length || 6 }} Polres)</span>
      </button>

      <button
        @click="activeTab = 'tenants'"
        class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition whitespace-nowrap"
        :class="activeTab === 'tenants'
          ? 'bg-amber-400 text-slate-950 font-black shadow-lg shadow-amber-400/20'
          : 'bg-slate-900 text-slate-300 hover:text-white border border-slate-800'"
      >
        <Globe class="w-4 h-4" />
        <span>Katalog 34 Polda Se-Indonesia (Mabes)</span>
      </button>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 1: IDENTITAS POLDA AKTIF                                              -->
    <!-- ========================================================================= -->
    <div v-if="activeTab === 'identity'" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
        <div class="flex items-center gap-2 border-b border-slate-800 pb-3">
          <Building2 class="w-4 h-4 text-amber-400" />
          <h3 class="text-sm font-black text-white uppercase tracking-wider">Identitas Induk Satuan Wilayah</h3>
        </div>

        <form @submit.prevent="saveSettings" class="space-y-4 text-xs">
          <div class="grid grid-cols-3 gap-3">
            <div>
              <label class="block font-bold text-slate-300 mb-1">Kode Satwil</label>
              <input v-model="form.code" type="text" disabled class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-amber-400 font-mono font-bold" />
            </div>
            <div class="col-span-2">
              <label class="block font-bold text-slate-300 mb-1">Nama Resmi Satuan Induk *</label>
              <input v-model="form.name" type="text" required class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white font-bold focus:border-amber-400" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-300 mb-1">Wilayah / Provinsi</label>
              <input v-model="form.region" type="text" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:border-amber-400" />
            </div>
            <div>
              <label class="block font-bold text-slate-300 mb-1">Zona Waktu Operasional</label>
              <select v-model="form.timezone" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:border-amber-400">
                <option value="Asia/Jakarta">WIB (UTC+7) - Barat</option>
                <option value="Asia/Makassar">WITA (UTC+8) - Tengah</option>
                <option value="Asia/Jayapura">WIT (UTC+9) - Timur</option>
              </select>
            </div>
          </div>

          <div>
            <label class="block font-bold text-slate-300 mb-1">Nama Kepala Kepolisian Daerah (Kapolda)</label>
            <input v-model="form.kapolda_name" type="text" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:border-amber-400" />
          </div>

          <div>
            <label class="block font-bold text-slate-300 mb-1">Nama Kepala Bidang Humas (Kabid Humas)</label>
            <input v-model="form.kabid_humas_name" type="text" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:border-amber-400" />
          </div>

          <div class="pt-3 border-t border-slate-800 flex justify-end">
            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-5 py-2.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-black flex items-center gap-2 transition"
            >
              <Save class="w-4 h-4" />
              <span>{{ isSubmitting ? 'Menyimpan...' : 'Simpan Data Wilayah' }}</span>
            </button>
          </div>
        </form>
      </div>

      <!-- Quick Switcher Sidebar Card -->
      <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
        <div class="border-b border-slate-800 pb-3">
          <h3 class="text-sm font-black text-white uppercase tracking-wider">Tenant Aktif Lainnya</h3>
          <p class="text-xs text-slate-400">Pilih tenant untuk berpindah konteks komando seketika.</p>
        </div>

        <div class="space-y-2.5">
          <div
            v-for="p in allPoldas"
            :key="p.id"
            @click="switchTenant(p.code)"
            class="p-4 rounded-2xl border transition cursor-pointer flex items-center justify-between"
            :class="p.code === selectedCode
              ? 'bg-amber-400/10 border-amber-400 text-white shadow-lg shadow-amber-400/10'
              : 'bg-slate-900/60 border-slate-800 text-slate-300 hover:border-slate-600'"
          >
            <div class="flex items-center gap-3">
              <div
                class="w-10 h-10 rounded-xl flex items-center justify-center font-black text-xs"
                :class="p.code === selectedCode ? 'bg-amber-400 text-slate-950' : 'bg-slate-800 text-amber-400'"
              >
                {{ p.code }}
              </div>
              <div>
                <p class="font-bold text-white text-xs">{{ p.name }}</p>
                <span class="text-[10px] text-slate-400 font-mono">{{ p.polres_count }} Satker Polres &bull; {{ p.timezone }}</span>
              </div>
            </div>

            <ArrowRight class="w-4 h-4" :class="p.code === selectedCode ? 'text-amber-400' : 'text-slate-600'" />
          </div>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 2: STRUKTUR SATWIL POLRES JAJARAN                                     -->
    <!-- ========================================================================= -->
    <div v-else-if="activeTab === 'polres'" class="space-y-4">
      <div class="rounded-3xl bg-[#0b1426] border border-slate-800 shadow-2xl overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
          <div>
            <h3 class="text-sm font-black text-white uppercase tracking-wider">
              Daftar Satuan Wilayah (Polres / Polresta)
            </h3>
            <p class="text-xs text-slate-400">Jajaran kewilayahan di bawah naungan {{ selectedPolda?.name || 'Polda Sulbar' }}.</p>
          </div>
          <button
            @click="showAddPolresModal = true"
            class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-black text-xs transition"
          >
            <Plus class="w-4 h-4" />
            <span>Tambah Polres</span>
          </button>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="border-b border-slate-800 bg-slate-900/60 text-[11px] font-black uppercase text-slate-400">
                <th class="px-6 py-3.5">Kode</th>
                <th class="px-6 py-3.5">Nama Satker Polres</th>
                <th class="px-6 py-3.5">Karakter Wilayah / Tag</th>
                <th class="px-6 py-3.5">Koordinat Geospatial</th>
                <th class="px-6 py-3.5">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
              <tr v-for="pol in polresList" :key="pol.id" class="hover:bg-slate-900/40 transition">
                <td class="px-6 py-4 font-mono font-bold text-amber-400">{{ pol.code }}</td>
                <td class="px-6 py-4 font-extrabold text-white">{{ pol.name }}</td>
                <td class="px-6 py-4 text-slate-300">{{ pol.tag_desc || '-' }}</td>
                <td class="px-6 py-4 font-mono text-slate-400">
                  <span v-if="pol.latitude">{{ pol.latitude }}, {{ pol.longitude }}</span>
                  <span v-else class="text-slate-600">-</span>
                </td>
                <td class="px-6 py-4">
                  <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                    AKTIF
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 3: KATALOG TENANT POLDA SE-INDONESIA (MABES LEVEL)                    -->
    <!-- ========================================================================= -->
    <div v-else-if="activeTab === 'tenants'" class="space-y-4">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <div
          v-for="p in allPoldas"
          :key="p.id"
          class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl flex flex-col justify-between space-y-4 hover:border-slate-600 transition"
        >
          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <span class="px-2.5 py-0.5 rounded-full font-mono text-xs font-black bg-amber-400 text-slate-950">
                {{ p.code }}
              </span>
              <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300">
                Aktif
              </span>
            </div>
            <h4 class="text-base font-black text-white">{{ p.name }}</h4>
            <p class="text-xs text-slate-400">{{ p.region || 'Wilayah Hukum NKRI' }}</p>
          </div>

          <div class="pt-3 border-t border-slate-800/80 text-xs space-y-1 text-slate-300">
            <div class="flex justify-between">
              <span class="text-slate-400">Zona Waktu:</span>
              <span class="font-mono font-bold">{{ p.timezone }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-400">Satker Polres:</span>
              <span class="font-bold text-amber-400">{{ p.polres_count }} Satker</span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-400">Kapolda:</span>
              <span class="truncate max-w-[160px]">{{ p.kapolda_name || '-' }}</span>
            </div>
          </div>

          <button
            @click="switchTenant(p.code)"
            class="w-full py-2 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5"
            :class="p.code === selectedCode
              ? 'bg-slate-800 text-amber-400 border border-slate-700'
              : 'bg-slate-900 hover:bg-slate-800 text-white border border-slate-800'"
          >
            <span>{{ p.code === selectedCode ? 'Tenant Sedang Aktif' : 'Beralih ke Polda Ini' }}</span>
            <ArrowRight v-if="p.code !== selectedCode" class="w-3.5 h-3.5" />
          </button>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL: DAFTARKAN POLDA BARU (MABES LEVEL)                                -->
    <!-- ========================================================================= -->
    <div
      v-if="showAddPoldaModal"
      class="fixed inset-0 z-50 bg-black/85 backdrop-blur-md flex items-center justify-center p-4"
      @click.self="showAddPoldaModal = false"
    >
      <div class="relative w-full max-w-lg bg-[#091122] border border-slate-800 rounded-3xl overflow-hidden shadow-2xl flex flex-col">
        <div class="px-6 py-4 border-b border-slate-800 bg-[#0b1426] flex items-center justify-between">
          <div class="flex items-center gap-2">
            <Globe class="w-4 h-4 text-amber-400" />
            <h3 class="text-sm font-extrabold text-white">Daftarkan Tenant Polda Baru (Mabes)</h3>
          </div>
          <button @click="showAddPoldaModal = false" class="w-7 h-7 rounded-lg bg-slate-900 hover:bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center">
            <X class="w-4 h-4" />
          </button>
        </div>

        <form @submit.prevent="submitNewPolda" class="p-6 space-y-4 text-xs">
          <div class="grid grid-cols-3 gap-3">
            <div>
              <label class="block font-bold text-slate-300 mb-1">Kode Polda *</label>
              <input v-model="newPoldaForm.code" type="text" required placeholder="SULTRA" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white font-mono uppercase focus:border-amber-400" />
            </div>
            <div class="col-span-2">
              <label class="block font-bold text-slate-300 mb-1">Nama Polda Resmi *</label>
              <input v-model="newPoldaForm.name" type="text" required placeholder="Polda Sulawesi Tenggara" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:border-amber-400" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-300 mb-1">Provinsi / Wilayah *</label>
              <input v-model="newPoldaForm.region" type="text" required placeholder="Sulawesi Tenggara" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:border-amber-400" />
            </div>
            <div>
              <label class="block font-bold text-slate-300 mb-1">Zona Waktu Operasional</label>
              <select v-model="newPoldaForm.timezone" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:border-amber-400">
                <option value="Asia/Jakarta">WIB (UTC+7)</option>
                <option value="Asia/Makassar">WITA (UTC+8)</option>
                <option value="Asia/Jayapura">WIT (UTC+9)</option>
              </select>
            </div>
          </div>

          <div>
            <label class="block font-bold text-slate-300 mb-1">Nama Kapolda</label>
            <input v-model="newPoldaForm.kapolda_name" type="text" placeholder="Irjen Pol. ..." class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:border-amber-400" />
          </div>

          <div>
            <label class="block font-bold text-slate-300 mb-1">Nama Kabid Humas</label>
            <input v-model="newPoldaForm.kabid_humas_name" type="text" placeholder="Kombes Pol. ..." class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:border-amber-400" />
          </div>

          <div class="pt-3 border-t border-slate-800 flex justify-end gap-2">
            <button type="button" @click="showAddPoldaModal = false" class="px-4 py-2 rounded-xl bg-slate-900 border border-slate-700 text-slate-300 font-bold">Batal</button>
            <button type="submit" :disabled="isSubmitting" class="px-5 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-black flex items-center gap-2">
              <PlusCircle class="w-4 h-4" />
              <span>Daftarkan Tenant</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL: TAMBAH SATWIL POLRES BARU                                         -->
    <!-- ========================================================================= -->
    <div
      v-if="showAddPolresModal"
      class="fixed inset-0 z-50 bg-black/85 backdrop-blur-md flex items-center justify-center p-4"
      @click.self="showAddPolresModal = false"
    >
      <div class="relative w-full max-w-lg bg-[#091122] border border-slate-800 rounded-3xl overflow-hidden shadow-2xl flex flex-col">
        <div class="px-6 py-4 border-b border-slate-800 bg-[#0b1426] flex items-center justify-between">
          <div class="flex items-center gap-2">
            <MapPin class="w-4 h-4 text-amber-400" />
            <h3 class="text-sm font-extrabold text-white">Tambah Satker Polres Baru</h3>
          </div>
          <button @click="showAddPolresModal = false" class="w-7 h-7 rounded-lg bg-slate-900 hover:bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center">
            <X class="w-4 h-4" />
          </button>
        </div>

        <form @submit.prevent="submitNewPolres" class="p-6 space-y-4 text-xs">
          <div class="grid grid-cols-3 gap-3">
            <div>
              <label class="block font-bold text-slate-300 mb-1">Kode Polres *</label>
              <input v-model="newPolresForm.code" type="text" required placeholder="MAMUJU" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white font-mono uppercase focus:border-amber-400" />
            </div>
            <div class="col-span-2">
              <label class="block font-bold text-slate-300 mb-1">Nama Satker Resmi *</label>
              <input v-model="newPolresForm.name" type="text" required placeholder="Polresta Mamuju" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:border-amber-400" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-300 mb-1">Nama Singkat (Label)</label>
              <input v-model="newPolresForm.short_name" type="text" required placeholder="Mamuju" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:border-amber-400" />
            </div>
            <div>
              <label class="block font-bold text-slate-300 mb-1">Karakter Wilayah</label>
              <input v-model="newPolresForm.tag_desc" type="text" placeholder="Pesisir Barat / Jalur Trans" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:border-amber-400" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-300 mb-1">Latitude</label>
              <input v-model.number="newPolresForm.latitude" type="number" step="any" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white font-mono focus:border-amber-400" />
            </div>
            <div>
              <label class="block font-bold text-slate-300 mb-1">Longitude</label>
              <input v-model.number="newPolresForm.longitude" type="number" step="any" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white font-mono focus:border-amber-400" />
            </div>
          </div>

          <div class="pt-3 border-t border-slate-800 flex justify-end gap-2">
            <button type="button" @click="showAddPolresModal = false" class="px-4 py-2 rounded-xl bg-slate-900 border border-slate-700 text-slate-300 font-bold">Batal</button>
            <button type="submit" :disabled="isSubmitting" class="px-5 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-black flex items-center gap-2">
              <PlusCircle class="w-4 h-4" />
              <span>Simpan Satker Polres</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </EnterpriseLayout>
</template>
