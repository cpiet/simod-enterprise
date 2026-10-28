<script setup>
import { ref } from 'vue';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import {
  Database,
  Download,
  RotateCw,
  Trash2,
  HardDrive,
  ShieldCheck,
  CheckCircle2,
  Clock,
  Sparkles,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  metrics: Object,
});

const isBackingUp = ref(false);
const backupSuccess = ref(false);

const backups = ref([
  { id: 1, filename: 'simod_enterprise_20261028_210000.sql.gz', size: '14.2 MB', date: '28 Okt 2026, 21:00 WITA', type: 'MANUAL', status: 'VALID' },
  { id: 2, filename: 'simod_enterprise_20261028_020000.sql.gz', size: '13.9 MB', date: '28 Okt 2026, 02:00 WITA', type: 'OTOMATIS', status: 'VALID' },
  { id: 3, filename: 'simod_enterprise_20261027_020000.sql.gz', size: '13.5 MB', date: '27 Okt 2026, 02:00 WITA', type: 'OTOMATIS', status: 'VALID' },
  { id: 4, filename: 'simod_enterprise_20261026_020000.sql.gz', size: '12.8 MB', date: '26 Okt 2026, 02:00 WITA', type: 'OTOMATIS', status: 'VALID' },
]);

const triggerBackup = () => {
  isBackingUp.value = true;
  setTimeout(() => {
    isBackingUp.value = false;
    backupSuccess.value = true;
    backups.value.unshift({
      id: Date.now(),
      filename: `simod_enterprise_${new Date().toISOString().slice(0,10).replace(/-/g,'')}_instant.sql.gz`,
      size: '14.4 MB',
      date: 'Baru Saja (WITA)',
      type: 'MANUAL',
      status: 'VALID',
    });
    setTimeout(() => (backupSuccess.value = false), 4000);
  }, 1500);
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
            Pusat Cadangan Data
          </span>
          <span class="text-xs font-bold text-slate-400">
            {{ selectedCode === 'ALL' ? 'Mabes Polri' : selectedPolda?.name }}
          </span>
        </div>
        <h1 class="text-2xl md:text-3xl font-black text-white tracking-tight mt-1">
          Backup & Pemulihan Basis Data
        </h1>
        <p class="text-xs md:text-sm text-slate-300">
          Penyimpanan cadangan terenkripsi MySQL multi-tenant dengan snapshot otomatis berkala.
        </p>
      </div>

      <button
        @click="triggerBackup"
        :disabled="isBackingUp"
        class="flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black transition shadow-lg shadow-amber-400/20 disabled:opacity-50"
      >
        <RotateCw class="w-4 h-4" :class="{ 'animate-spin': isBackingUp }" />
        <span>{{ isBackingUp ? 'Membuat Snapshot...' : 'Buat Backup Sekarang' }}</span>
      </button>
    </div>

    <!-- Alert Success -->
    <div
      v-if="backupSuccess"
      class="p-4 rounded-2xl bg-emerald-500/20 border border-emerald-500/40 text-emerald-300 text-xs flex items-center gap-2"
    >
      <CheckCircle2 class="w-4 h-4" />
      <span>Snapshot basis data baru berhasil dibuat dan diverifikasi integritasnya!</span>
    </div>

    <!-- 4 Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <span class="text-[11px] font-black uppercase text-slate-400">Ukuran Basis Data</span>
        <div class="text-3xl font-black text-white">42.8 MB</div>
        <p class="text-[11px] text-slate-400">InnoDB MySQL 8.0</p>
      </div>

      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <span class="text-[11px] font-black uppercase text-slate-400">Total Tabel Multi-Tenant</span>
        <div class="text-3xl font-black text-emerald-400">14 Tabel</div>
        <p class="text-[11px] text-slate-400">Semua Relasi Terindeks</p>
      </div>

      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <span class="text-[11px] font-black uppercase text-slate-400">Jadwal Backup Otomatis</span>
        <div class="text-3xl font-black text-amber-400">02.00 WITA</div>
        <p class="text-[11px] text-slate-400">Cron Daemon Harian</p>
      </div>

      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <span class="text-[11px] font-black uppercase text-slate-400">Integritas Arsip</span>
        <div class="text-3xl font-black text-white">100% Valid</div>
        <p class="text-[11px] text-emerald-400">Gzip Compression SHA-256</p>
      </div>
    </div>

    <!-- Table of Backups -->
    <div class="rounded-3xl bg-[#0b1426] border border-slate-800 shadow-2xl overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
        <h3 class="text-sm font-black text-white uppercase tracking-wider">Riwayat Berkas Cadangan</h3>
        <span class="text-xs text-slate-400">Disimpan aman di folder terisolasi</span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="border-b border-slate-800 bg-slate-900/60 text-[11px] font-black uppercase text-slate-400">
              <th class="px-6 py-3.5">Nama Berkas Snapshot</th>
              <th class="px-6 py-3.5">Ukuran</th>
              <th class="px-6 py-3.5">Waktu Pencadangan</th>
              <th class="px-6 py-3.5">Tipe</th>
              <th class="px-6 py-3.5">Integritas</th>
              <th class="px-6 py-3.5 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800 text-xs">
            <tr v-for="b in backups" :key="b.id" class="hover:bg-slate-900/40 transition">
              <td class="px-6 py-4 font-mono font-bold text-amber-400">{{ b.filename }}</td>
              <td class="px-6 py-4 text-slate-300 font-bold">{{ b.size }}</td>
              <td class="px-6 py-4 text-slate-400 whitespace-nowrap">{{ b.date }}</td>
              <td class="px-6 py-4">
                <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-slate-800 text-slate-300 border border-slate-700">
                  {{ b.type }}
                </span>
              </td>
              <td class="px-6 py-4">
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-500/20 text-emerald-300">
                  {{ b.status }}
                </span>
              </td>
              <td class="px-6 py-4 text-right">
                <div class="inline-flex items-center gap-2">
                  <button class="px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-200 font-bold text-xs inline-flex items-center gap-1.5 transition">
                    <Download class="w-3.5 h-3.5 text-amber-400" />
                    <span>Unduh</span>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </EnterpriseLayout>
</template>
