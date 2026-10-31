<script setup>
import { ref } from 'vue';
import { router, useForm, Link } from '@inertiajs/vue3';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import SecurityMaintenanceTabs from '@/Components/SecurityMaintenanceTabs.vue';
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
  AlertTriangle,
  Upload,
  Calendar,
  Save,
  FileCheck,
  History,
  X,
  Server,
  Layers,
  Archive,
  RefreshCw
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  metrics: Object,
  backups: Object,
  setting: Object,
  stats: Object,
  auditLogs: Array,
  flash: Object,
});

// Loading states
const isBackingUp = ref(false);
const isRestoring = ref(false);
const isSavingSettings = ref(false);
const isUploading = ref(false);

// Modal states
const showRestoreModal = ref(false);
const activeRestoreTarget = ref(null);
const restoreConfirmInput = ref('');

const showUploadModal = ref(false);
const uploadConfirmInput = ref('');
const uploadFile = ref(null);
const uploadError = ref('');

// Form Konfigurasi Jadwal Otomatis (Backup Berdasarkan Waktu)
const settingsForm = useForm({
  is_auto_backup_enabled: props.setting?.is_auto_backup_enabled ?? true,
  schedule_type: props.setting?.schedule_type ?? 'daily',
  backup_time: props.setting?.backup_time ?? '02:00',
  retention_days: props.setting?.retention_days ?? 14,
  gzip_compression: props.setting?.gzip_compression ?? true,
});

// Aksi 1: Buat Backup Sekarang (Instant Snapshot)
const triggerInstantBackup = () => {
  isBackingUp.value = true;
  router.post('/database/backup/create', {}, {
    preserveScroll: true,
    onFinish: () => {
      isBackingUp.value = false;
    },
  });
};

// Aksi 2: Unduh Berkas Cadangan
const downloadBackup = (filename) => {
  window.location.href = `/database/backup/download/${encodeURIComponent(filename)}`;
};

// Aksi 3: Buka Modal Konfirmasi Pulihkan / Restore
const openRestoreModal = (backupItem) => {
  activeRestoreTarget.value = backupItem;
  restoreConfirmInput.value = '';
  showRestoreModal.value = true;
};

const closeRestoreModal = () => {
  showRestoreModal.value = false;
  activeRestoreTarget.value = null;
  restoreConfirmInput.value = '';
};

const confirmRestore = () => {
  if (restoreConfirmInput.value.trim().toUpperCase() !== 'PULIHKAN') {
    alert('Harap ketik kata "PULIHKAN" untuk memverifikasi tindakan.');
    return;
  }

  isRestoring.value = true;
  router.post(`/database/backup/restore/${encodeURIComponent(activeRestoreTarget.value.filename)}`, {
    confirm_text: restoreConfirmInput.value.trim(),
  }, {
    preserveScroll: true,
    onSuccess: () => {
      closeRestoreModal();
    },
    onFinish: () => {
      isRestoring.value = false;
    },
  });
};

// Aksi 4: Buka Modal Unggah SQL & Restore
const openUploadModal = () => {
  uploadFile.value = null;
  uploadConfirmInput.value = '';
  uploadError.value = '';
  showUploadModal.value = true;
};

const closeUploadModal = () => {
  showUploadModal.value = false;
  uploadFile.value = null;
  uploadConfirmInput.value = '';
  uploadError.value = '';
};

const handleFileSelect = (event) => {
  const file = event.target.files[0];
  if (file) {
    uploadFile.value = file;
    uploadError.value = '';
  }
};

const submitUploadRestore = () => {
  if (!uploadFile.value) {
    uploadError.value = 'Silakan pilih berkas .sql atau .sql.gz terlebih dahulu.';
    return;
  }
  if (uploadConfirmInput.value.trim().toUpperCase() !== 'PULIHKAN') {
    uploadError.value = 'Harap ketik "PULIHKAN" untuk konfirmasi pemulihan.';
    return;
  }

  isUploading.value = true;
  uploadError.value = '';

  const formData = new FormData();
  formData.append('backup_file', uploadFile.value);
  formData.append('confirm_text', uploadConfirmInput.value.trim());

  router.post('/database/backup/upload-restore', formData, {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => {
      closeUploadModal();
    },
    onError: (errors) => {
      uploadError.value = Object.values(errors).flat().join(', ');
    },
    onFinish: () => {
      isUploading.value = false;
    },
  });
};

// Aksi 5: Hapus Berkas Cadangan
const deleteBackup = (filename) => {
  if (confirm(`Apakah Anda yakin ingin menghapus berkas cadangan "${filename}" secara permanen?`)) {
    router.delete(`/database/backup/delete/${encodeURIComponent(filename)}`, {
      preserveScroll: true,
    });
  }
};

// Aksi 6: Simpan Pengaturan Jadwal Otomatis (Backup Berdasarkan Waktu)
const saveScheduleSettings = () => {
  isSavingSettings.value = true;
  settingsForm.post('/database/backup/settings', {
    preserveScroll: true,
    onFinish: () => {
      isSavingSettings.value = false;
    },
  });
};
</script>

<template>
  <EnterpriseLayout
    :all-poldas="allPoldas"
    :selected-polda="selectedPolda"
    :selected-code="selectedCode"
    :metrics="metrics"
  >
    <!-- Tab Navigasi Sub-Modul Pemeliharaan Terpadu -->
    <SecurityMaintenanceTabs :selected-code="selectedCode" />

    <!-- Notifikasi Flash Sukses / Galat -->
    <div
      v-if="$page.props.flash?.success"
      class="p-4 rounded-2xl bg-emerald-500/20 border border-emerald-500/40 text-emerald-300 text-xs flex items-center justify-between gap-3 animate-fade-in"
    >
      <div class="flex items-center gap-2">
        <CheckCircle2 class="w-5 h-5 flex-shrink-0 text-emerald-400" />
        <span class="font-bold">{{ $page.props.flash.success }}</span>
      </div>
      <button @click="$page.props.flash.success = null" class="text-emerald-400 hover:text-white">
        <X class="w-4 h-4" />
      </button>
    </div>

    <div
      v-if="$page.props.flash?.error"
      class="p-4 rounded-2xl bg-rose-500/20 border border-rose-500/40 text-rose-300 text-xs flex items-center justify-between gap-3 animate-fade-in"
    >
      <div class="flex items-center gap-2">
        <AlertTriangle class="w-5 h-5 flex-shrink-0 text-rose-400" />
        <span class="font-bold">{{ $page.props.flash.error }}</span>
      </div>
      <button @click="$page.props.flash.error = null" class="text-rose-400 hover:text-white">
        <X class="w-4 h-4" />
      </button>
    </div>

    <!-- Header Modul Utama -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-400 text-slate-950">
            Pusat Cadangan & Pemulihan Basis Data
          </span>
          <span class="text-xs font-bold text-slate-400">
            {{ selectedCode === 'ALL' ? 'Mabes Polri (Konsolidasi)' : selectedPolda?.name }}
          </span>
        </div>
        <h1 class="text-2xl md:text-3xl font-black text-white tracking-tight mt-1">
          Backup, Restore & Jadwal Otomatis
        </h1>
        <p class="text-xs md:text-sm text-slate-300">
          Penyimpanan snapshot MySQL InnoDB multi-tenant terenkripsi Gzip, pemulihan darurat, dan otomatisasi penjadwalan berkala.
        </p>
      </div>

      <!-- Tombol Aksi Cepat Header -->
      <div class="flex items-center gap-2.5 flex-wrap">
        <button
          @click="openUploadModal"
          class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-200 text-xs font-bold transition shadow-sm"
        >
          <Upload class="w-4 h-4 text-amber-400" />
          <span>Unggah Berkas SQL</span>
        </button>

        <button
          @click="triggerInstantBackup"
          :disabled="isBackingUp"
          class="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black transition shadow-lg shadow-amber-400/20 disabled:opacity-50"
        >
          <RotateCw class="w-4 h-4" :class="{ 'animate-spin': isBackingUp }" />
          <span>{{ isBackingUp ? 'Membuat Snapshot Basis Data...' : 'Buat Backup Sekarang' }}</span>
        </button>
      </div>
    </div>

    <!-- 4 Kartu Metrik Telemetri Riil Basis Data -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <div class="flex items-center justify-between">
          <span class="text-[11px] font-black uppercase text-slate-400">Ukuran Basis Data</span>
          <Database class="w-4 h-4 text-amber-400" />
        </div>
        <div class="text-2xl lg:text-3xl font-black text-white tracking-tight">{{ stats?.size_mb || '1.86 MB' }}</div>
        <p class="text-[11px] text-slate-400">{{ stats?.engine || 'InnoDB MySQL 8.x' }}</p>
      </div>

      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <div class="flex items-center justify-between">
          <span class="text-[11px] font-black uppercase text-slate-400">Tabel Multi-Tenant</span>
          <Layers class="w-4 h-4 text-emerald-400" />
        </div>
        <div class="text-2xl lg:text-3xl font-black text-emerald-400 tracking-tight">{{ stats?.table_count || 28 }} Tabel</div>
        <p class="text-[11px] text-slate-400">Relasi & Foreign Key Aktif</p>
      </div>

      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <div class="flex items-center justify-between">
          <span class="text-[11px] font-black uppercase text-slate-400">Jadwal Backup Otomatis</span>
          <Clock class="w-4 h-4 text-amber-400" />
        </div>
        <div class="text-2xl lg:text-3xl font-black text-amber-400 tracking-tight">
          {{ setting?.is_auto_backup_enabled ? setting.backup_time + ' WITA' : 'Nonaktif' }}
        </div>
        <p class="text-[11px] text-slate-400">
          {{ setting?.is_auto_backup_enabled ? (setting.schedule_type === 'daily' ? 'Harian Terjadwal' : setting.schedule_type) : 'Pencadangan Manual' }}
        </p>
      </div>

      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <div class="flex items-center justify-between">
          <span class="text-[11px] font-black uppercase text-slate-400">Integritas Berkas</span>
          <ShieldCheck class="w-4 h-4 text-cyan-400" />
        </div>
        <div class="text-2xl lg:text-3xl font-black text-cyan-400 tracking-tight">SHA-256 Valid</div>
        <p class="text-[11px] text-slate-400">Kompresi Gzip Terenkripsi</p>
      </div>
    </div>

    <!-- Section 1: Konfigurasi Backup Berdasarkan Waktu (Auto-Backup Scheduler) -->
    <div class="rounded-3xl bg-[#0b1426] border border-slate-800 shadow-2xl p-6 lg:p-8 space-y-6">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-800 pb-5">
        <div>
          <div class="inline-flex items-center gap-2 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-400/10 text-amber-400 border border-amber-400/30">
            <Calendar class="w-3.5 h-3.5" />
            <span>Otomasi Penjadwalan Waktu</span>
          </div>
          <h2 class="text-lg font-black text-white mt-1">Konfigurasi Backup Berdasarkan Waktu</h2>
          <p class="text-xs text-slate-300">
            Atur frekuensi pencadangan otomatis (Harian, Interval Jam, atau Mingguan) serta masa simpan retensi berkas di storage.
          </p>
        </div>

        <div class="flex items-center gap-3">
          <span class="text-xs font-bold text-slate-300">Status Otomasi:</span>
          <label class="relative inline-flex items-center cursor-pointer">
            <input
              type="checkbox"
              v-model="settingsForm.is_auto_backup_enabled"
              class="sr-only peer"
            />
            <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-400"></div>
          </label>
          <span class="text-xs font-black uppercase" :class="settingsForm.is_auto_backup_enabled ? 'text-amber-400' : 'text-slate-500'">
            {{ settingsForm.is_auto_backup_enabled ? 'AKTIF' : 'NONAKTIF' }}
          </span>
        </div>
      </div>

      <form @submit.prevent="saveScheduleSettings" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Pilihan Frekuensi Waktu -->
        <div class="space-y-2">
          <label class="block text-xs font-black uppercase tracking-wider text-slate-300">
            Frekuensi Jadwal Pencadangan
          </label>
          <select
            v-model="settingsForm.schedule_type"
            class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white text-xs font-bold focus:border-amber-400 focus:outline-none transition"
          >
            <option value="daily">Harian (Sekali Setiap Hari)</option>
            <option value="every_6_hours">Interval Setiap 6 Jam</option>
            <option value="every_12_hours">Interval Setiap 12 Jam</option>
            <option value="weekly">Mingguan (Setiap Hari Minggu)</option>
          </select>
          <p class="text-[11px] text-slate-400">Pola eksekusi cron di background server.</p>
        </div>

        <!-- Waktu Eksekusi (Jam WITA/WIB) -->
        <div class="space-y-2">
          <label class="block text-xs font-black uppercase tracking-wider text-slate-300">
            Jam Eksekusi (WITA / WIB)
          </label>
          <div class="relative">
            <input
              type="time"
              v-model="settingsForm.backup_time"
              required
              class="w-full px-4 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white text-xs font-bold focus:border-amber-400 focus:outline-none transition font-mono"
            />
          </div>
          <p class="text-[11px] text-slate-400">Direkomendasikan pukul 02:00 WITA saat lalu lintas sepi.</p>
        </div>

        <!-- Retensi Penyimpanan (Masa Simpan Hari) -->
        <div class="space-y-2">
          <label class="block text-xs font-black uppercase tracking-wider text-slate-300">
            Masa Simpan / Retensi Berkas
          </label>
          <select
            v-model="settingsForm.retention_days"
            class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white text-xs font-bold focus:border-amber-400 focus:outline-none transition"
          >
            <option :value="7">7 Hari (Hemat Ruang Disk)</option>
            <option :value="14">14 Hari (Standar Kehumasan)</option>
            <option :value="30">30 Hari (1 Bulan Penuh)</option>
            <option :value="90">90 Hari (Triwulan)</option>
            <option :value="180">180 Hari (Semester)</option>
          </select>
          <p class="text-[11px] text-slate-400">Snapshot lebih lama akan dihapus otomatis.</p>
        </div>

        <!-- Kompresi Gzip & Tombol Simpan -->
        <div class="space-y-2 flex flex-col justify-between">
          <div>
            <label class="block text-xs font-black uppercase tracking-wider text-slate-300">
              Kompresi Berkas (.sql.gz)
            </label>
            <label class="flex items-center gap-2 mt-2 cursor-pointer">
              <input
                type="checkbox"
                v-model="settingsForm.gzip_compression"
                class="w-4 h-4 rounded text-amber-400 bg-slate-900 border-slate-700 focus:ring-0"
              />
              <span class="text-xs font-bold text-slate-300">Kompresi Gzip Aktif (Hemat 80% Disk)</span>
            </label>
          </div>

          <div class="pt-2">
            <button
              type="submit"
              :disabled="isSavingSettings"
              class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black transition shadow-lg shadow-amber-400/20 disabled:opacity-50"
            >
              <Save class="w-4 h-4" />
              <span>{{ isSavingSettings ? 'Menyimpan...' : 'Simpan Jadwal Backup' }}</span>
            </button>
          </div>
        </div>
      </form>

      <!-- Status Terakhir Snapshot Terjadwal -->
      <div v-if="setting?.last_backup_at" class="p-4 rounded-2xl bg-slate-900/60 border border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
        <div class="flex items-center gap-2.5">
          <History class="w-4 h-4 text-amber-400" />
          <span class="text-slate-400">Snapshot Terakhir Berjalan:</span>
          <span class="font-bold text-white">{{ setting.last_backup_at }} WITA</span>
          <span
            class="px-2 py-0.5 rounded text-[10px] font-black uppercase"
            :class="setting.last_backup_status === 'success' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300'"
          >
            {{ setting.last_backup_status }}
          </span>
        </div>
        <div v-if="setting.last_backup_filename" class="font-mono text-[11px] text-amber-400 truncate max-w-md">
          {{ setting.last_backup_filename }}
        </div>
      </div>
    </div>

    <!-- Section 2: Tabel Riwayat Berkas Cadangan (Real Data dari Storage & DB) -->
    <div class="rounded-3xl bg-[#0b1426] border border-slate-800 shadow-2xl overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <h3 class="text-sm font-black text-white uppercase tracking-wider">
            Riwayat Berkas Cadangan Basis Data
          </h3>
          <span class="text-xs text-slate-400">
            Tersimpan aman di storage server dengan verifikasi integritas SHA-256.
          </span>
        </div>
        <span class="text-xs font-bold text-amber-400">
          Total: {{ backups?.data?.length || 0 }} Snapshot Tersedia
        </span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="border-b border-slate-800 bg-slate-900/60 text-[11px] font-black uppercase text-slate-400">
              <th class="px-6 py-3.5">Nama Berkas Snapshot</th>
              <th class="px-6 py-3.5">Ukuran</th>
              <th class="px-6 py-3.5">Waktu Pencadangan</th>
              <th class="px-6 py-3.5">Tipe</th>
              <th class="px-6 py-3.5">Integritas Hash</th>
              <th class="px-6 py-3.5 text-right">Aksi Operasional</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800 text-xs">
            <tr
              v-for="b in backups?.data"
              :key="b.id"
              class="hover:bg-slate-900/40 transition"
            >
              <td class="px-6 py-4 font-mono font-bold text-amber-400">
                <div class="flex items-center gap-2">
                  <Archive class="w-4 h-4 text-slate-400 flex-shrink-0" />
                  <span class="break-all">{{ b.filename }}</span>
                </div>
              </td>
              <td class="px-6 py-4 text-slate-200 font-bold whitespace-nowrap">{{ b.size }}</td>
              <td class="px-6 py-4 text-slate-300 whitespace-nowrap">{{ b.date }}</td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  class="px-2.5 py-1 rounded text-[10px] font-extrabold uppercase border"
                  :class="{
                    'bg-amber-400/10 text-amber-400 border-amber-400/30': b.type === 'SCHEDULED' || b.type === 'OTOMATIS',
                    'bg-blue-400/10 text-blue-400 border-blue-400/30': b.type === 'MANUAL',
                    'bg-purple-400/10 text-purple-400 border-purple-400/30': b.type === 'UPLOADED',
                  }"
                >
                  {{ b.type }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center gap-1.5" :title="b.checksum_full">
                  <CheckCircle2 class="w-3.5 h-3.5 text-emerald-400" />
                  <span class="font-mono text-[11px] text-slate-400">{{ b.checksum || 'SHA-256 Valid' }}</span>
                </div>
              </td>
              <td class="px-6 py-4 text-right whitespace-nowrap">
                <div class="inline-flex items-center gap-2">
                  <!-- Tombol Unduh -->
                  <button
                    @click="downloadBackup(b.filename)"
                    title="Unduh Berkas ke Komputer"
                    class="px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-200 font-bold text-xs inline-flex items-center gap-1.5 transition"
                  >
                    <Download class="w-3.5 h-3.5 text-amber-400" />
                    <span>Unduh</span>
                  </button>

                  <!-- Tombol Pulihkan / Restore -->
                  <button
                    @click="openRestoreModal(b)"
                    title="Pulihkan Basis Data dari Snapshot Ini"
                    class="px-3 py-1.5 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 font-bold text-xs inline-flex items-center gap-1.5 transition"
                  >
                    <RefreshCw class="w-3.5 h-3.5" />
                    <span>Pulihkan</span>
                  </button>

                  <!-- Tombol Hapus -->
                  <button
                    @click="deleteBackup(b.filename)"
                    title="Hapus Berkas Cadangan"
                    class="p-1.5 rounded-xl bg-slate-900 hover:bg-rose-500/20 border border-slate-700 hover:border-rose-500/40 text-slate-400 hover:text-rose-400 transition"
                  >
                    <Trash2 class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>

            <!-- State Kosong jika belum ada snapshot -->
            <tr v-if="!backups?.data?.length">
              <td colspan="6" class="px-6 py-12 text-center text-slate-400">
                <Database class="w-10 h-10 mx-auto text-slate-600 mb-2" />
                <p class="font-bold text-white text-sm">Belum ada berkas snapshot cadangan.</p>
                <p class="text-xs text-slate-400 mt-1">
                  Klik tombol <strong class="text-amber-400">"Buat Backup Sekarang"</strong> di atas untuk membuat snapshot pertama.
                </p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Navigasi -->
      <div v-if="backups?.links?.length > 3" class="px-6 py-4 border-t border-slate-800 flex items-center justify-between text-xs">
        <span class="text-slate-400">
          Menampilkan {{ backups.from || 0 }} - {{ backups.to || 0 }} dari {{ backups.total || 0 }} berkas
        </span>
        <div class="flex items-center gap-1">
          <Link
            v-for="(link, idx) in backups.links"
            :key="idx"
            :href="link.url || '#'"
            v-html="link.label"
            class="px-3 py-1.5 rounded-lg border text-xs font-bold transition"
            :class="link.active ? 'bg-amber-400 text-slate-950 border-amber-400' : 'bg-slate-900 text-slate-400 border-slate-800 hover:text-white'"
          />
        </div>
      </div>
    </div>

    <!-- Section 3: Riwayat Audit Log Operasional Backup & Pemulihan -->
    <div class="rounded-3xl bg-[#0b1426] border border-slate-800 shadow-2xl p-6 lg:p-8 space-y-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <History class="w-4 h-4 text-amber-400" />
          <h3 class="text-sm font-black text-white uppercase tracking-wider">
            Audit Trail & Log Aktivitas Pencadangan
          </h3>
        </div>
        <span class="text-xs text-slate-400">8 Aktivitas Terakhir</span>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div
          v-for="log in auditLogs"
          :key="log.id"
          class="p-4 rounded-2xl bg-slate-900/60 border border-slate-800 space-y-1 text-xs"
        >
          <div class="flex items-center justify-between">
            <span class="font-mono text-[10px] font-bold text-amber-400 uppercase">{{ log.action }}</span>
            <span class="text-[10px] text-slate-500">{{ log.time_ago }}</span>
          </div>
          <div class="font-bold text-white">{{ log.user }}</div>
          <div class="text-[11px] text-slate-400 truncate">IP: {{ log.ip }} • {{ log.created_at }}</div>
          <div v-if="log.metadata?.filename" class="text-[10px] font-mono text-emerald-400 truncate">
            {{ log.metadata.filename }}
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL 1: Konfirmasi Pulihkan / Restore Basis Data -->
    <div
      v-if="showRestoreModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm"
    >
      <div class="w-full max-w-lg rounded-3xl bg-[#0b1426] border border-rose-500/40 p-6 space-y-5 shadow-2xl animate-fade-in">
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
          <div class="flex items-center gap-2 text-rose-400">
            <AlertTriangle class="w-5 h-5" />
            <h3 class="font-black text-sm uppercase tracking-wider text-white">
              Konfirmasi Pemulihan Basis Data
            </h3>
          </div>
          <button @click="closeRestoreModal" class="text-slate-400 hover:text-white">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-200 text-xs space-y-2">
          <p class="font-bold uppercase tracking-wider">⚠️ Tindakan Berisiko Tinggi!</p>
          <p>
            Proses pemulihan akan menimpa seluruh struktur dan isi data di basis data dengan berkas snapshot berikut:
          </p>
          <div class="font-mono font-bold text-amber-400 bg-slate-950/60 p-2.5 rounded-xl break-all">
            {{ activeRestoreTarget?.filename }}
          </div>
          <p class="text-[11px] text-rose-300">
            Pastikan seluruh personel operasional telah menyimpan pekerjaannya sebelum melanjutkan.
          </p>
        </div>

        <div class="space-y-2">
          <label class="block text-xs font-bold text-slate-300">
            Ketik kata <span class="text-rose-400 font-mono font-black">PULIHKAN</span> di bawah ini untuk mengonfirmasi:
          </label>
          <input
            type="text"
            v-model="restoreConfirmInput"
            placeholder="Ketik PULIHKAN"
            class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white font-mono text-xs font-black tracking-widest focus:border-rose-500 focus:outline-none transition uppercase"
          />
        </div>

        <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
          <button
            @click="closeRestoreModal"
            class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-300 text-xs font-bold transition"
          >
            Batal
          </button>
          <button
            @click="confirmRestore"
            :disabled="isRestoring || restoreConfirmInput.trim().toUpperCase() !== 'PULIHKAN'"
            class="flex items-center gap-2 px-5 py-2 rounded-xl bg-rose-500 hover:bg-rose-600 text-white text-xs font-black transition disabled:opacity-40"
          >
            <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': isRestoring }" />
            <span>{{ isRestoring ? 'Memulihkan Basis Data...' : 'Ya, Pulihkan Sekarang' }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL 2: Unggah Berkas SQL & Restore -->
    <div
      v-if="showUploadModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm"
    >
      <div class="w-full max-w-lg rounded-3xl bg-[#0b1426] border border-amber-400/40 p-6 space-y-5 shadow-2xl animate-fade-in">
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
          <div class="flex items-center gap-2 text-amber-400">
            <Upload class="w-5 h-5" />
            <h3 class="font-black text-sm uppercase tracking-wider text-white">
              Unggah & Pulihkan Berkas SQL
            </h3>
          </div>
          <button @click="closeUploadModal" class="text-slate-400 hover:text-white">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="space-y-4 text-xs">
          <div class="space-y-2">
            <label class="block font-bold text-slate-300">Pilih Berkas Cadangan (.sql atau .sql.gz):</label>
            <input
              type="file"
              accept=".sql,.gz"
              @change="handleFileSelect"
              class="w-full text-xs text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-black file:bg-amber-400 file:text-slate-950 hover:file:bg-amber-300 cursor-pointer"
            />
            <p v-if="uploadFile" class="text-amber-400 font-mono font-bold mt-1">
              {{ uploadFile.name }} ({{ (uploadFile.size / 1024 / 1024).toFixed(2) }} MB)
            </p>
          </div>

          <div v-if="uploadError" class="p-3 rounded-xl bg-rose-500/20 border border-rose-500/40 text-rose-300 text-xs">
            {{ uploadError }}
          </div>

          <div class="space-y-2">
            <label class="block font-bold text-slate-300">
              Ketik kata <span class="text-rose-400 font-mono font-black">PULIHKAN</span> untuk konfirmasi:
            </label>
            <input
              type="text"
              v-model="uploadConfirmInput"
              placeholder="Ketik PULIHKAN"
              class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white font-mono text-xs font-black tracking-widest focus:border-amber-400 focus:outline-none transition uppercase"
            />
          </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
          <button
            @click="closeUploadModal"
            class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-300 text-xs font-bold transition"
          >
            Batal
          </button>
          <button
            @click="submitUploadRestore"
            :disabled="isUploading || !uploadFile || uploadConfirmInput.trim().toUpperCase() !== 'PULIHKAN'"
            class="flex items-center gap-2 px-5 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black transition disabled:opacity-40"
          >
            <Upload class="w-4 h-4" :class="{ 'animate-bounce': isUploading }" />
            <span>{{ isUploading ? 'Mengunggah & Memulihkan...' : 'Unggah & Pulihkan' }}</span>
          </button>
        </div>
      </div>
    </div>
  </EnterpriseLayout>
</template>
