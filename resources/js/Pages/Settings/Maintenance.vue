<script setup>
import SecurityMaintenanceTabs from '@/Components/SecurityMaintenanceTabs.vue';
import { ref } from 'vue';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import {
  Wrench,
  AlertTriangle,
  Save,
  CheckCircle2,
  Lock,
  Globe,
  Radio,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  metrics: Object,
});

const isSaved = ref(false);

const maintenance = ref({
  is_active: false,
  message: 'Sistem SIMOD Enterprise sedang dalam peningkatan infrastruktur server Mabes Polri. Layanan operasional kembali normal sesaat lagi.',
  retry_after_minutes: 60,
  bypass_secret: 'polri-maintenance-bypass-2026',
  allowed_ips: '127.0.0.1\n10.12.4.15\n182.1.204.88',
});

const toggleMaintenance = () => {
  maintenance.value.is_active = !maintenance.value.is_active;
  isSaved.value = true;
  setTimeout(() => (isSaved.value = false), 3000);
};

const saveSettings = () => {
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
    <SecurityMaintenanceTabs :selected-code="selectedCode" />

    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-400 text-slate-950">
            Kendali Operasional
          </span>
          <span class="text-xs font-bold text-slate-400">
            {{ selectedCode === 'ALL' ? 'Mabes Polri' : selectedPolda?.name }}
          </span>
        </div>
        <h1 class="text-2xl md:text-3xl font-black text-white tracking-tight mt-1">
          Mode Pemeliharaan Sistem (Maintenance)
        </h1>
        <p class="text-xs md:text-sm text-slate-300">
          Kontrol ketersediaan sistem, penutupan jalur API sementara, dan whitelist akses tim IT command center.
        </p>
      </div>

      <button
        @click="saveSettings"
        class="flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black transition shadow-lg shadow-amber-400/20"
      >
        <Save class="w-4 h-4" />
        <span>{{ isSaved ? 'Pengaturan Disimpan!' : 'Simpan Parameter' }}</span>
      </button>
    </div>

    <!-- Status Banner -->
    <div
      class="p-6 rounded-3xl border transition duration-300 flex flex-col md:flex-row items-center justify-between gap-4 shadow-xl"
      :class="maintenance.is_active
        ? 'bg-rose-500/10 border-rose-500/40 text-rose-300'
        : 'bg-emerald-500/10 border-emerald-500/40 text-emerald-300'"
    >
      <div class="flex items-center gap-4">
        <div
          class="w-12 h-12 rounded-2xl flex items-center justify-center font-black text-xl"
          :class="maintenance.is_active ? 'bg-rose-500 text-white' : 'bg-emerald-500 text-slate-950'"
        >
          <Wrench class="w-6 h-6" />
        </div>
        <div>
          <h2 class="text-lg font-black tracking-tight">
            Status Layanan: {{ maintenance.is_active ? 'MODE PEMELIHARAAN AKTIF' : 'OPERASIONAL NORMAL' }}
          </h2>
          <p class="text-xs opacity-80">
            {{ maintenance.is_active ? 'Sistem dikunci sementara. Hanya IP yang diizinkan yang dapat mengakses portal.' : 'Seluruh satwil dan personel dapat mengakses sistem SIMOD secara penuh.' }}
          </p>
        </div>
      </div>

      <button
        @click="toggleMaintenance"
        class="px-5 py-2.5 rounded-xl font-black text-xs uppercase tracking-wider transition"
        :class="maintenance.is_active
          ? 'bg-emerald-400 hover:bg-emerald-300 text-slate-950'
          : 'bg-rose-500 hover:bg-rose-600 text-white shadow-lg shadow-rose-500/30'"
      >
        {{ maintenance.is_active ? 'Matikan Mode Maintenance' : 'Aktifkan Mode Maintenance' }}
      </button>
    </div>

    <!-- Parameter Form -->
    <div class="max-w-3xl rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
      <div class="flex items-center gap-2 border-b border-slate-800 pb-3">
        <Lock class="w-4 h-4 text-amber-400" />
        <h3 class="text-sm font-black text-white uppercase tracking-wider">Parameter Pemeliharaan</h3>
      </div>

      <div class="space-y-4 text-xs">
        <div>
          <label class="block font-bold text-slate-300 mb-1">Pesan Pemberitahuan untuk Pengguna</label>
          <textarea
            v-model="maintenance.message"
            rows="3"
            class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400"
          ></textarea>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-300 mb-1">Estimasi Durasi (Menit)</label>
            <input
              v-model="maintenance.retry_after_minutes"
              type="number"
              class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400"
            />
          </div>
          <div>
            <label class="block font-bold text-slate-300 mb-1">Kunci Rahasia Bypass (Secret Key)</label>
            <input
              v-model="maintenance.bypass_secret"
              type="text"
              class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-amber-400 font-mono font-bold"
            />
          </div>
        </div>

        <div>
          <label class="block font-bold text-slate-300 mb-1">Daftar IP Diizinkan (Whitelist, 1 per baris)</label>
          <textarea
            v-model="maintenance.allowed_ips"
            rows="3"
            class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white font-mono focus:outline-none focus:border-amber-400"
          ></textarea>
        </div>
      </div>
    </div>
  </EnterpriseLayout>
</template>
