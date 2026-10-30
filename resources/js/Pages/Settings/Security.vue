<script setup>
import SecurityMaintenanceTabs from '@/Components/SecurityMaintenanceTabs.vue';
import { ref } from 'vue';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import {
  ShieldAlert,
  ShieldCheck,
  Lock,
  Smartphone,
  Save,
  CheckCircle2,
  AlertTriangle,
  Flame,
  Globe,
  Clock,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  metrics: Object,
});

const isSaved = ref(false);

const security = ref({
  enable_turnstile: true,
  turnstile_site_key: '0x4AAAAAAAiH8s-xxxxxxxx',
  turnstile_secret_key: '0x4AAAAAAAiH8s-yyyyyyyyyyyyyyyyyyyyyyyy',
  max_login_attempts: 5,
  lockout_minutes: 15,
  enable_pin: true,
  pin_expiry_days: 90,
  session_timeout_minutes: 30,
  private_media_storage: true,
});

const saveSecurity = () => {
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
            Pertahanan Siber
          </span>
          <span class="text-xs font-bold text-slate-400">
            {{ selectedCode === 'ALL' ? 'Mabes Polri' : selectedPolda?.name }}
          </span>
        </div>
        <h1 class="text-2xl md:text-3xl font-black text-white tracking-tight mt-1">
          Pusat Keamanan Sistem (Security Center)
        </h1>
        <p class="text-xs md:text-sm text-slate-300">
          Parameter mitigasi brute force, perlindungan bot, isolasi file private, dan standar kepatuhan BSSN / Mabes Polri.
        </p>
      </div>

      <button
        @click="saveSecurity"
        class="flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black transition shadow-lg shadow-amber-400/20"
      >
        <Save class="w-4 h-4" />
        <span>{{ isSaved ? 'Pengaturan Disimpan!' : 'Simpan Parameter' }}</span>
      </button>
    </div>

    <!-- 4 Security KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <span class="text-[11px] font-black uppercase text-slate-400">Indeks Keamanan</span>
        <div class="text-3xl font-black text-emerald-400">98 / 100</div>
        <p class="text-[11px] text-emerald-400">Kategori Sangat Aman</p>
      </div>

      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <span class="text-[11px] font-black uppercase text-slate-400">Turnstile CAPTCHA</span>
        <div class="text-3xl font-black text-white">Aktif</div>
        <p class="text-[11px] text-slate-400">Cloudflare Managed Challenge</p>
      </div>

      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <span class="text-[11px] font-black uppercase text-slate-400">Brute Force Protection</span>
        <div class="text-3xl font-black text-amber-400">5x Limit</div>
        <p class="text-[11px] text-slate-400">Cooldown 15 Menit Otomatis</p>
      </div>

      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <span class="text-[11px] font-black uppercase text-slate-400">Isolasi Media Privat</span>
        <div class="text-3xl font-black text-white">Terenkripsi</div>
        <p class="text-[11px] text-slate-400">Tidak Dapat Diakses Publik</p>
      </div>
    </div>

    <!-- Security Form Cards -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Turnstile -->
      <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <Globe class="w-4 h-4 text-amber-400" />
            <h3 class="text-sm font-black text-white uppercase tracking-wider">Cloudflare Turnstile Anti-Bot</h3>
          </div>
          <input
            v-model="security.enable_turnstile"
            type="checkbox"
            class="w-4 h-4 rounded text-amber-400 focus:ring-0 bg-slate-900 border-slate-700"
          />
        </div>

        <div class="space-y-3 text-xs">
          <div>
            <label class="block font-bold text-slate-300 mb-1">Turnstile Site Key</label>
            <input
              v-model="security.turnstile_site_key"
              type="text"
              class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white font-mono"
            />
          </div>
          <div>
            <label class="block font-bold text-slate-300 mb-1">Turnstile Secret Key</label>
            <input
              v-model="security.turnstile_secret_key"
              type="password"
              class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white font-mono"
            />
          </div>
          <p class="text-[11px] text-slate-400">
            Melindungi halaman login dari serangan credential stuffing dan request otomatis.
          </p>
        </div>
      </div>

      <!-- Brute Force & Session -->
      <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
        <div class="flex items-center gap-2 border-b border-slate-800 pb-3">
          <Lock class="w-4 h-4 text-amber-400" />
          <h3 class="text-sm font-black text-white uppercase tracking-wider">Batas Percobaan Login & Sesi</h3>
        </div>

        <div class="space-y-3 text-xs">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-300 mb-1">Max Percobaan Gagal</label>
              <input
                v-model="security.max_login_attempts"
                type="number"
                class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white"
              />
            </div>
            <div>
              <label class="block font-bold text-slate-300 mb-1">Cooldown (Menit)</label>
              <input
                v-model="security.lockout_minutes"
                type="number"
                class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white"
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-300 mb-1">Timeout Sesi (Menit)</label>
              <input
                v-model="security.session_timeout_minutes"
                type="number"
                class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white"
              />
            </div>
            <div>
              <label class="block font-bold text-slate-300 mb-1">Masa Aktif PIN (Hari)</label>
              <input
                v-model="security.pin_expiry_days"
                type="number"
                class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white"
              />
            </div>
          </div>
          <p class="text-[11px] text-slate-400">
            Sesi inaktif akan diputus otomatis untuk mencegah pembajakan terminal di posko operasi.
          </p>
        </div>
      </div>
    </div>
  </EnterpriseLayout>
</template>
