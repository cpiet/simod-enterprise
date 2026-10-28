<script setup>
import { ref } from 'vue';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import {
  History,
  Shield,
  Download,
  Search,
  Filter,
  CheckCircle2,
  AlertTriangle,
  Lock,
  Globe,
  Terminal,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  logs: Object, // Paginated
  metrics: Object,
});

const searchQuery = ref('');
const filterAction = ref('ALL');

const auditEntries = ref([
  {
    id: 104,
    time: '28 Okt 2026, 21:42:15 WITA',
    user: 'Kombes Pol. M. Memo, S.I.K',
    role: 'SUPERADMIN',
    action: 'DISPATCH_TASK',
    module: 'Disposisi Tugas',
    ip: '10.12.4.15',
    status: 'SUCCESS',
    detail: 'Menugaskan Kasihumas Polresta Mamuju untuk monitoring arus mudik Pilkada pesisir.',
  },
  {
    id: 103,
    time: '28 Okt 2026, 21:15:30 WITA',
    user: 'AKP Syamsuddin, S.H.',
    role: 'VERIFIKATOR',
    action: 'VERIFY_ISSUE',
    module: 'Manajemen Isu',
    ip: '10.12.4.22',
    status: 'SUCCESS',
    detail: 'Verifikasi klarifikasi fakta laporan penemuan pohon tumbang di jalur trans-Sulawesi Majene.',
  },
  {
    id: 102,
    time: '28 Okt 2026, 20:50:11 WITA',
    user: 'Ipda Muhammad Ridwan',
    role: 'ANALIS',
    action: 'SCRAPE_RSS_SYNC',
    module: 'Media Intelligence',
    ip: '127.0.0.1 (Worker)',
    status: 'SUCCESS',
    detail: 'Sinkronisasi 14 portal berita RSS: 611 artikel masuk ke database polda SULBAR.',
  },
  {
    id: 101,
    time: '28 Okt 2026, 19:30:05 WITA',
    user: 'Bripka Hendra Gunawan',
    role: 'OPERATOR',
    action: 'AUTH_PIN_LOGIN',
    module: 'Autentikasi',
    ip: '182.1.204.88',
    status: 'SUCCESS',
    detail: 'Login cepat menggunakan PIN dinas 6-digit berhasil dari perangkat lapangan.',
  },
  {
    id: 100,
    time: '28 Okt 2026, 18:12:44 WITA',
    user: 'System Worker',
    role: 'SYSTEM',
    action: 'DAILY_BACKUP',
    module: 'Backup Basis Data',
    ip: '127.0.0.1',
    status: 'SUCCESS',
    detail: 'Otomatisasi backup database simod_enterprise berhasil: simod_sulbar_20261028.sql.gz (14.2 MB)',
  },
]);
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
            Audit Trail Terenkripsi
          </span>
          <span class="text-xs font-bold text-slate-400">
            {{ selectedCode === 'ALL' ? 'Mabes Polri' : selectedPolda?.name }}
          </span>
        </div>
        <h1 class="text-2xl md:text-3xl font-black text-white tracking-tight mt-1">
          Audit Logs & Jejak Rekam Digital
        </h1>
        <p class="text-xs md:text-sm text-slate-300">
          Pencatatan append-only seluruh mutasi data, otorisasi tugas, dan login pengguna untuk akuntabilitas hukum.
        </p>
      </div>

      <button
        class="flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-200 text-xs font-black transition"
      >
        <Download class="w-4 h-4 text-amber-400" />
        <span>Export Log (CSV)</span>
      </button>
    </div>

    <!-- 4 Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <span class="text-[11px] font-black uppercase text-slate-400">Total Log Tersimpan</span>
        <div class="text-3xl font-black text-white">1,842</div>
        <p class="text-[11px] text-slate-400">Bulan Berjalan</p>
      </div>

      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <span class="text-[11px] font-black uppercase text-slate-400">Aktivitas Hari Ini</span>
        <div class="text-3xl font-black text-amber-400">84 Event</div>
        <p class="text-[11px] text-slate-400">Semua Satwil Aktif</p>
      </div>

      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <span class="text-[11px] font-black uppercase text-slate-400">Akses Ditolak / Anomali</span>
        <div class="text-3xl font-black text-emerald-400">0 Insiden</div>
        <p class="text-[11px] text-emerald-400">Integritas 100% Aman</p>
      </div>

      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <span class="text-[11px] font-black uppercase text-slate-400">Status Penyimpanan</span>
        <div class="text-3xl font-black text-white">Append-Only</div>
        <p class="text-[11px] text-slate-400">Kepatuhan UU ITE & Polri</p>
      </div>
    </div>

    <!-- Table of Audit Logs -->
    <div class="rounded-3xl bg-[#0b1426] border border-slate-800 shadow-2xl overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
        <h3 class="text-sm font-black text-white uppercase tracking-wider">Aktivitas Terakhir Sistem</h3>
        <span class="text-xs text-slate-400">Waktu disinkronkan dengan NTP Server Mabes Polri</span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="border-b border-slate-800 bg-slate-900/60 text-[11px] font-black uppercase text-slate-400">
              <th class="px-6 py-3.5">Waktu (WITA)</th>
              <th class="px-6 py-3.5">Personel / Pelaksana</th>
              <th class="px-6 py-3.5">Aksi / Operasi</th>
              <th class="px-6 py-3.5">Modul Terkait</th>
              <th class="px-6 py-3.5">IP Address</th>
              <th class="px-6 py-3.5">Status</th>
              <th class="px-6 py-3.5">Deskripsi Ringkas</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800 text-xs">
            <tr v-for="entry in auditEntries" :key="entry.id" class="hover:bg-slate-900/40 transition">
              <td class="px-6 py-4 font-mono text-slate-400 whitespace-nowrap">{{ entry.time }}</td>
              <td class="px-6 py-4">
                <p class="font-bold text-white">{{ entry.user }}</p>
                <span class="text-[10px] text-amber-400 font-semibold">{{ entry.role }}</span>
              </td>
              <td class="px-6 py-4">
                <span class="px-2 py-0.5 rounded font-mono text-[10px] font-extrabold bg-slate-800 text-amber-400 border border-slate-700">
                  {{ entry.action }}
                </span>
              </td>
              <td class="px-6 py-4 text-slate-300 font-semibold">{{ entry.module }}</td>
              <td class="px-6 py-4 font-mono text-slate-400">{{ entry.ip }}</td>
              <td class="px-6 py-4">
                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                  {{ entry.status }}
                </span>
              </td>
              <td class="px-6 py-4 text-slate-300 max-w-xs truncate">{{ entry.detail }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </EnterpriseLayout>
</template>
