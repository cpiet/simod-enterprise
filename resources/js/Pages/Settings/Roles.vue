<script setup>
import { ref } from 'vue';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import {
  KeyRound,
  Shield,
  Save,
  CheckCircle2,
  Users,
  Lock,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  metrics: Object,
});

const isSaved = ref(false);

const roles = ['Superadmin', 'Kabid Humas', 'Kasubbid Penmas', 'Kasihumas Polres', 'Operator'];

const permissionsMatrix = ref([
  { module: 'Dashboard & Command Center', perm: [true, true, true, true, true] },
  { module: 'Radar Media Intelligence & RSS', perm: [true, true, true, true, true] },
  { module: 'Input Laporan Isu Lapangan', perm: [true, true, true, true, true] },
  { module: 'Verifikasi & Klarifikasi Isu', perm: [true, true, true, true, false] },
  { module: 'Penerbitan Siaran Pers Resmi', perm: [true, true, true, false, false] },
  { module: 'Disposisi & Penugasan Personel', perm: [true, true, true, false, false] },
  { module: 'Akses Galeri Media & Bukti Privat', perm: [true, true, true, true, true] },
  { module: 'Unduh Laporan Anev & Eksekutif', perm: [true, true, true, true, false] },
  { module: 'Manajemen Akun Personalia', perm: [true, true, false, false, false] },
  { module: 'Pusat Keamanan & Turnstile', perm: [true, false, false, false, false] },
  { module: 'Backup & Pemulihan Basis Data', perm: [true, false, false, false, false] },
]);

const saveMatrix = () => {
  isSaved.value = true;
  setTimeout(() => (isSaved.value = false), 3000);
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
            RBAC Berjenjang
          </span>
          <span class="text-xs font-bold text-slate-400">
            {{ selectedCode === 'ALL' ? 'Mabes Polri' : selectedPolda?.name }}
          </span>
        </div>
        <h1 class="text-2xl md:text-3xl font-black text-white tracking-tight mt-1">
          Matriks Hak Akses & Peran (RBAC)
        </h1>
        <p class="text-xs md:text-sm text-slate-300">
          Otorisasi granular fitur sistem berdasarkan hierarki kepangkatan dan fungsi komando Polri.
        </p>
      </div>

      <button
        @click="saveMatrix"
        class="flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black transition shadow-lg shadow-amber-400/20"
      >
        <Save class="w-4 h-4" />
        <span>{{ isSaved ? 'Matriks Disimpan!' : 'Simpan Hak Akses' }}</span>
      </button>
    </div>

    <!-- Matrix Table -->
    <div class="rounded-3xl bg-[#0b1426] border border-slate-800 shadow-2xl overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
        <h3 class="text-sm font-black text-white uppercase tracking-wider">Tabel Otorisasi Fitur</h3>
        <span class="text-xs text-slate-400">Centang untuk memberikan izin operasional modul</span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="border-b border-slate-800 bg-slate-900/60 text-[11px] font-black uppercase text-slate-400">
              <th class="px-6 py-3.5 min-w-[240px]">Modul / Kapabilitas Sistem</th>
              <th v-for="r in roles" :key="r" class="px-4 py-3.5 text-center">{{ r }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800 text-xs">
            <tr v-for="row in permissionsMatrix" :key="row.module" class="hover:bg-slate-900/40 transition">
              <td class="px-6 py-4 font-bold text-white">{{ row.module }}</td>
              <td v-for="(val, idx) in row.perm" :key="idx" class="px-4 py-4 text-center">
                <input
                  type="checkbox"
                  v-model="row.perm[idx]"
                  class="w-4 h-4 rounded text-amber-400 focus:ring-0 bg-slate-900 border-slate-700 cursor-pointer"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </EnterpriseLayout>
</template>
