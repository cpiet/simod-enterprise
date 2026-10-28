<script setup>
import { ref, computed } from 'vue';
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
  Sparkles,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  users: Object, // Paginated
  metrics: Object,
});

const searchQuery = ref('');
const filterRole = ref('ALL');
const showAddModal = ref(false);

const personnelList = ref([
  {
    id: 1,
    name: 'Kombes Pol. M. Memo, S.I.K',
    nrp: '74090123',
    rank: 'KOMBES POL',
    role: 'SUPERADMIN',
    role_label: 'Kabid Humas Polda',
    satker: 'Bidang Humas Polda Sulbar',
    status: 'AKTIF',
    last_login: '10 menit lalu',
  },
  {
    id: 2,
    name: 'AKP Syamsuddin, S.H.',
    nrp: '81050412',
    rank: 'AKP',
    role: 'VERIFIKATOR',
    role_label: 'Kasubbid Penmas',
    satker: 'Bidang Humas Polda Sulbar',
    status: 'AKTIF',
    last_login: '25 menit lalu',
  },
  {
    id: 3,
    name: 'Ipda Muhammad Ridwan, S.Kom',
    nrp: '89110255',
    rank: 'IPDA',
    role: 'ANALIS',
    role_label: 'Analis Intelijen Media',
    satker: 'Subbid Multimedia Polda',
    status: 'AKTIF',
    last_login: '1 jam lalu',
  },
  {
    id: 4,
    name: 'Aipda Hendra Gunawan',
    nrp: '85030877',
    rank: 'AIPDA',
    role: 'OPERATOR',
    role_label: 'Kasihumas Polres',
    satker: 'Polresta Mamuju',
    status: 'AKTIF',
    last_login: '2 jam lalu',
  },
  {
    id: 5,
    name: 'Bripka Andi Pratama',
    nrp: '91070334',
    rank: 'BRIPKA',
    role: 'OPERATOR',
    role_label: 'Operator Lapangan',
    satker: 'Polres Polewali Mandar',
    status: 'AKTIF',
    last_login: '3 jam lalu',
  },
  {
    id: 6,
    name: 'Briptu Siti Nurhaliza',
    nrp: '96040811',
    rank: 'BRIPTU',
    role: 'OPERATOR',
    role_label: 'Operator Dokumentasi',
    satker: 'Polres Majene',
    status: 'AKTIF',
    last_login: '4 jam lalu',
  },
]);

const filteredPersonnel = computed(() => {
  return personnelList.value.filter((p) => {
    const matchSearch =
      p.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      p.nrp.includes(searchQuery.value) ||
      p.satker.toLowerCase().includes(searchQuery.value.toLowerCase());
    const matchRole = filterRole.value === 'ALL' || p.role === filterRole.value;
    return matchSearch && matchRole;
  });
});
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
            Personalia Satwil
          </span>
          <span class="text-xs font-bold text-slate-400">
            {{ selectedCode === 'ALL' ? 'Seluruh Personel Nasional' : selectedPolda?.name }}
          </span>
        </div>
        <h1 class="text-2xl md:text-3xl font-black text-white tracking-tight mt-1">
          Personalia & Pengguna Humas
        </h1>
        <p class="text-xs md:text-sm text-slate-300">
          Manajemen kredensial, role berjenjang, otorisasi wilayah, dan PIN dinas personel.
        </p>
      </div>

      <button
        @click="showAddModal = true"
        class="flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black transition shadow-lg shadow-amber-400/20"
      >
        <UserPlus class="w-4 h-4" />
        <span>Tambah Personel</span>
      </button>
    </div>

    <!-- 4 Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <span class="text-[11px] font-black uppercase text-slate-400">Total Personel</span>
        <div class="text-3xl font-black text-white">48 Anggota</div>
        <p class="text-[11px] text-slate-400">Polda & 6 Polres Jajaran</p>
      </div>

      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <span class="text-[11px] font-black uppercase text-slate-400">Verifikator Siaga</span>
        <div class="text-3xl font-black text-emerald-400">12 Perwira</div>
        <p class="text-[11px] text-slate-400">Otorisasi Rilis & Laporan</p>
      </div>

      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <span class="text-[11px] font-black uppercase text-slate-400">Operator Multimedia</span>
        <div class="text-3xl font-black text-amber-400">32 Bintara</div>
        <p class="text-[11px] text-slate-400">Liputan & Media Sosial</p>
      </div>

      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <span class="text-[11px] font-black uppercase text-slate-400">Kepatuhan PIN Dinas</span>
        <div class="text-3xl font-black text-white">100%</div>
        <p class="text-[11px] text-emerald-400">Seluruh Akun Terproteksi</p>
      </div>
    </div>

    <!-- Search & Filter Bar -->
    <div class="p-4 rounded-3xl bg-[#0b1426] border border-slate-800 flex flex-col md:flex-row items-center justify-between gap-3 shadow-xl">
      <div class="relative w-full md:w-96">
        <Search class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Cari nama, NRP, atau satuan kerja..."
          class="w-full pl-10 pr-4 py-2 rounded-xl bg-slate-900 border border-slate-700 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-amber-400"
        />
      </div>

      <div class="flex items-center gap-2 w-full md:w-auto">
        <button
          v-for="roleOption in ['ALL', 'SUPERADMIN', 'VERIFIKATOR', 'ANALIS', 'OPERATOR']"
          :key="roleOption"
          @click="filterRole = roleOption"
          class="px-3 py-1.5 rounded-xl text-xs font-bold transition"
          :class="filterRole === roleOption ? 'bg-amber-400 text-slate-950 font-black' : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800'"
        >
          {{ roleOption }}
        </button>
      </div>
    </div>

    <!-- Table of Personnel -->
    <div class="rounded-3xl bg-[#0b1426] border border-slate-800 shadow-2xl overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="border-b border-slate-800 bg-slate-900/60 text-[11px] font-black uppercase text-slate-400">
              <th class="px-6 py-3.5">Nama & Pangkat</th>
              <th class="px-6 py-3.5">NRP</th>
              <th class="px-6 py-3.5">Satuan Kerja</th>
              <th class="px-6 py-3.5">Peran / Jabatan</th>
              <th class="px-6 py-3.5">Status</th>
              <th class="px-6 py-3.5">Terakhir Aktif</th>
              <th class="px-6 py-3.5 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800 text-xs">
            <tr v-for="person in filteredPersonnel" :key="person.id" class="hover:bg-slate-900/40 transition">
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-xl bg-slate-800 border border-slate-700 text-amber-400 font-black flex items-center justify-center text-xs">
                    {{ person.name.charAt(0) }}
                  </div>
                  <div>
                    <p class="font-bold text-white">{{ person.name }}</p>
                    <span class="text-[10px] font-bold text-amber-400">{{ person.rank }}</span>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 font-mono font-bold text-slate-300">{{ person.nrp }}</td>
              <td class="px-6 py-4 text-slate-300">{{ person.satker }}</td>
              <td class="px-6 py-4">
                <span
                  class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider"
                  :class="{
                    'bg-amber-400/20 text-amber-300 border border-amber-400/40': person.role === 'SUPERADMIN',
                    'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40': person.role === 'VERIFIKATOR',
                    'bg-blue-500/20 text-blue-300 border border-blue-500/40': person.role === 'ANALIS',
                    'bg-slate-800 text-slate-300 border border-slate-700': person.role === 'OPERATOR',
                  }"
                >
                  {{ person.role_label }}
                </span>
              </td>
              <td class="px-6 py-4">
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-500/20 text-emerald-300">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                  {{ person.status }}
                </span>
              </td>
              <td class="px-6 py-4 text-slate-400">{{ person.last_login }}</td>
              <td class="px-6 py-4 text-right">
                <div class="inline-flex items-center gap-2">
                  <button class="p-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-300 hover:text-white" title="Reset PIN">
                    <Key class="w-3.5 h-3.5 text-amber-400" />
                  </button>
                  <button class="p-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-300 hover:text-white" title="Edit Profil">
                    <Edit3 class="w-3.5 h-3.5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal Tambah Personel -->
    <div
      v-if="showAddModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
    >
      <div class="w-full max-w-lg rounded-3xl bg-[#0b1426] border border-slate-700 shadow-2xl p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <UserPlus class="w-4 h-4 text-amber-400" />
            <h3 class="text-base font-extrabold text-white">Tambah Personel Humas Baru</h3>
          </div>
          <button @click="showAddModal = false" class="text-slate-400 hover:text-white">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="space-y-3 text-xs">
          <div>
            <label class="block font-bold text-slate-300 mb-1">Nama Lengkap & Gelar</label>
            <input type="text" placeholder="Contoh: Bripka Ahmad Fauzi, S.H." class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400" />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-300 mb-1">NRP / NIP</label>
              <input type="text" placeholder="88090123" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400" />
            </div>
            <div>
              <label class="block font-bold text-slate-300 mb-1">Pangkat</label>
              <input type="text" placeholder="BRIPKA" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400" />
            </div>
          </div>
          <div>
            <label class="block font-bold text-slate-300 mb-1">Satuan Kerja / Wilayah</label>
            <select class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400">
              <option>Bidang Humas Polda Sulbar</option>
              <option>Polresta Mamuju</option>
              <option>Polres Majene</option>
              <option>Polres Polewali Mandar</option>
              <option>Polres Mamasa</option>
              <option>Polres Pasangkayu</option>
              <option>Polres Mamuju Tengah</option>
            </select>
          </div>
          <div>
            <label class="block font-bold text-slate-300 mb-1">Peran / Hak Akses</label>
            <select class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400">
              <option>Operator Lapangan</option>
              <option>Analis Intelijen Media</option>
              <option>Verifikator Satker</option>
              <option>Superadmin Polda</option>
            </select>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-800">
          <button @click="showAddModal = false" class="px-4 py-2 rounded-xl bg-slate-900 border border-slate-700 text-xs font-bold text-slate-300 hover:text-white">
            Batal
          </button>
          <button @click="showAddModal = false" class="px-4 py-2 rounded-xl bg-amber-400 text-slate-950 text-xs font-black hover:bg-amber-300">
            Simpan Personel
          </button>
        </div>
      </div>
    </div>
  </EnterpriseLayout>
</template>