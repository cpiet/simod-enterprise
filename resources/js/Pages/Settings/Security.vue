<script setup>
import { ref, computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import SecurityMaintenanceTabs from '@/Components/SecurityMaintenanceTabs.vue';
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
  RotateCcw,
  History,
  KeyRound,
  FileCheck2,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  metrics: Object,
  security: Object,
  scoreData: Object,
  securityLogs: Array,
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const toastMessage = ref('');

const form = useForm({
  enable_turnstile: props.security?.enable_turnstile ?? true,
  turnstile_site_key: props.security?.turnstile_site_key ?? '0x4AAAAAAAiH8s-xxxxxxxx',
  turnstile_secret_key: props.security?.turnstile_secret_key ?? '0x4AAAAAAAiH8s-yyyyyyyyyyyyyyyyyyyyyyyy',
  max_login_attempts: props.security?.max_login_attempts ?? 5,
  lockout_minutes: props.security?.lockout_minutes ?? 15,
  enable_pin: props.security?.enable_pin ?? true,
  pin_expiry_days: props.security?.pin_expiry_days ?? 90,
  session_timeout_minutes: props.security?.session_timeout_minutes ?? 30,
  private_media_storage: props.security?.private_media_storage ?? true,
  ip_whitelist: props.security?.ip_whitelist ?? "127.0.0.1\n10.12.4.15\n182.1.204.88",
});

// Dynamic security score calculated in real-time as user changes inputs
const dynamicScore = computed(() => {
  let score = 40;
  if (form.enable_turnstile) {
    score += 15;
    if (form.turnstile_site_key && form.turnstile_secret_key) score += 5;
  }
  if (form.max_login_attempts <= 3) score += 15;
  else if (form.max_login_attempts <= 5) score += 10;
  else if (form.max_login_attempts <= 10) score += 5;

  if (form.lockout_minutes >= 30) score += 10;
  else if (form.lockout_minutes >= 15) score += 8;
  else if (form.lockout_minutes >= 5) score += 4;

  if (form.session_timeout_minutes <= 15) score += 10;
  else if (form.session_timeout_minutes <= 30) score += 8;
  else if (form.session_timeout_minutes <= 60) score += 4;

  if (form.enable_pin) {
    score += 6;
    if (form.pin_expiry_days <= 90) score += 4;
  }

  if (form.private_media_storage) score += 5;

  score = Math.min(100, Math.max(0, score));

  let category = 'Kategori Sangat Aman';
  let badgeColor = 'text-emerald-400';
  let progressBg = 'bg-emerald-500';

  if (score < 60) {
    category = 'Kategori Rentan (Perlu Penguatan)';
    badgeColor = 'text-rose-400';
    progressBg = 'bg-rose-500';
  } else if (score < 80) {
    category = 'Kategori Standar (Cukup)';
    badgeColor = 'text-amber-400';
    progressBg = 'bg-amber-400';
  }

  return { score, category, badgeColor, progressBg };
});

const submitSecurity = () => {
  form.post('/security', {
    preserveScroll: true,
    onSuccess: () => {
      toastMessage.value = '✓ Parameter Pusat Keamanan berhasil disimpan ke database!';
      setTimeout(() => (toastMessage.value = ''), 4000);
    },
  });
};

const resetToStandards = () => {
  form.enable_turnstile = true;
  form.turnstile_site_key = '0x4AAAAAAAiH8s-xxxxxxxx';
  form.turnstile_secret_key = '0x4AAAAAAAiH8s-yyyyyyyyyyyyyyyyyyyyyyyy';
  form.max_login_attempts = 5;
  form.lockout_minutes = 15;
  form.enable_pin = true;
  form.pin_expiry_days = 90;
  form.session_timeout_minutes = 30;
  form.private_media_storage = true;
  form.ip_whitelist = "127.0.0.1\n10.12.4.15\n182.1.204.88";
  toastMessage.value = 'Parameter dikembalikan ke rekomendasi standar BSSN / Mabes Polri.';
  setTimeout(() => (toastMessage.value = ''), 3500);
};
</script>

<template>
  <EnterpriseLayout
    :all-poldas="allPoldas"
    :selected-polda="selectedPolda"
    :selected-code="selectedCode"
    :metrics="metrics"
  >
    <!-- Floating Success Toast -->
    <transition
      enter-active-class="transform ease-out duration-300 transition"
      enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
      enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="toastMessage || flashSuccess"
        class="fixed bottom-6 right-6 z-50 max-w-md bg-emerald-950 border border-emerald-500 text-emerald-100 p-4 rounded-2xl shadow-2xl flex items-center gap-3 backdrop-blur-xl"
      >
        <CheckCircle2 class="w-6 h-6 text-emerald-400 shrink-0" />
        <p class="text-xs font-bold leading-relaxed">{{ toastMessage || flashSuccess }}</p>
      </div>
    </transition>

    <SecurityMaintenanceTabs :selected-code="selectedCode" />

    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-6 rounded-3xl bg-gradient-to-r from-[#0d182e] via-[#0b1426] to-[#070d1d] border border-slate-800 shadow-xl">
      <div class="space-y-1">
        <div class="flex items-center gap-2">
          <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-rose-500/20 text-rose-300 border border-rose-500/40">
            Pertahanan Siber &amp; Enkripsi
          </span>
          <span class="text-xs font-bold text-slate-400">
            {{ selectedCode === 'ALL' ? 'Mabes Polri (Konsolidasi)' : selectedPolda?.name }}
          </span>
        </div>
        <h1 class="text-2xl md:text-3xl font-black text-white tracking-tight flex items-center gap-2">
          <ShieldAlert class="w-6 h-6 text-amber-400" />
          <span>Pusat Keamanan Sistem (Security Center)</span>
        </h1>
        <p class="text-xs md:text-sm text-slate-300 max-w-2xl leading-relaxed">
          Parameter mitigasi brute force, perlindungan bot Turnstile, isolasi media private, dan kepatuhan standar BSSN / Mabes Polri. Data tersimpan permanen di database.
        </p>
      </div>

      <div class="flex items-center gap-2 shrink-0">
        <button
          type="button"
          @click="resetToStandards"
          class="flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-300 hover:text-white text-xs font-bold transition"
          title="Kembalikan ke standar acuan BSSN"
        >
          <RotateCcw class="w-3.5 h-3.5" />
          <span>Standar BSSN</span>
        </button>

        <button
          type="button"
          @click="submitSecurity"
          :disabled="form.processing"
          class="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black transition shadow-lg shadow-amber-400/20 disabled:opacity-50"
        >
          <Save class="w-4 h-4" />
          <span>{{ form.processing ? 'Menyimpan...' : 'Simpan Parameter' }}</span>
        </button>
      </div>
    </div>

    <!-- 4 Security KPI Cards (Real-time dynamic from form state) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-2">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
          <span class="text-[11px] font-black uppercase">Indeks Keamanan</span>
          <ShieldCheck class="w-4 h-4 text-emerald-400" />
        </div>
        <div class="text-3xl font-black" :class="dynamicScore.badgeColor">
          {{ dynamicScore.score }} <span class="text-xs text-slate-400 font-normal">/ 100</span>
        </div>
        <div class="w-full h-1.5 bg-slate-800 rounded-full overflow-hidden">
          <div :class="dynamicScore.progressBg" class="h-full transition-all duration-300" :style="{ width: dynamicScore.score + '%' }"></div>
        </div>
        <p class="text-[11px] font-bold" :class="dynamicScore.badgeColor">{{ dynamicScore.category }}</p>
      </div>

      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
          <span class="text-[11px] font-black uppercase">Turnstile Anti-Bot</span>
          <Globe class="w-4 h-4 text-amber-400" />
        </div>
        <div class="text-3xl font-black" :class="form.enable_turnstile ? 'text-white' : 'text-slate-500'">
          {{ form.enable_turnstile ? 'Aktif' : 'Nonaktif' }}
        </div>
        <p class="text-[11px] text-slate-400">Cloudflare Managed Challenge</p>
      </div>

      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
          <span class="text-[11px] font-black uppercase">Brute Force Limit</span>
          <Lock class="w-4 h-4 text-amber-400" />
        </div>
        <div class="text-3xl font-black text-amber-400">
          {{ form.max_login_attempts }}x Limit
        </div>
        <p class="text-[11px] text-slate-400">Cooldown {{ form.lockout_minutes }} Menit Otomatis</p>
      </div>

      <div class="p-5 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
          <span class="text-[11px] font-black uppercase">Isolasi Media Privat</span>
          <FileCheck2 class="w-4 h-4 text-sky-400" />
        </div>
        <div class="text-3xl font-black" :class="form.private_media_storage ? 'text-emerald-400' : 'text-slate-500'">
          {{ form.private_media_storage ? 'Terenkripsi' : 'Terbuka' }}
        </div>
        <p class="text-[11px] text-slate-400">
          {{ form.private_media_storage ? 'Akses via URL Bertanda Tangan' : 'Dapat Diakses Publik' }}
        </p>
      </div>
    </div>

    <!-- Security Form Cards -->
    <form @submit.prevent="submitSecurity" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Turnstile Cloudflare -->
      <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <Globe class="w-4 h-4 text-amber-400" />
            <h3 class="text-sm font-black text-white uppercase tracking-wider">Cloudflare Turnstile Anti-Bot</h3>
          </div>
          <label class="relative inline-flex items-center cursor-pointer">
            <input
              v-model="form.enable_turnstile"
              type="checkbox"
              class="sr-only peer"
            />
            <div class="w-9 h-5 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-amber-400"></div>
          </label>
        </div>

        <div class="space-y-3 text-xs">
          <div>
            <label class="block font-bold text-slate-300 mb-1">Turnstile Site Key</label>
            <input
              v-model="form.turnstile_site_key"
              type="text"
              class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white font-mono"
              placeholder="0x4AAAAAAAiH8s-xxxxxxxx"
            />
            <p v-if="form.errors.turnstile_site_key" class="text-rose-400 text-[10px] mt-1">{{ form.errors.turnstile_site_key }}</p>
          </div>
          <div>
            <label class="block font-bold text-slate-300 mb-1">Turnstile Secret Key</label>
            <input
              v-model="form.turnstile_secret_key"
              type="password"
              class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white font-mono"
              placeholder="0x4AAAAAAAiH8s-yyyyyyyyyyyyyyyyyyyyyyyy"
            />
            <p v-if="form.errors.turnstile_secret_key" class="text-rose-400 text-[10px] mt-1">{{ form.errors.turnstile_secret_key }}</p>
          </div>
          <p class="text-[11px] text-slate-400 leading-relaxed">
            Melindungi endpoint login dari serangan brute force otomatis dan bot tanpa mengganggu pengguna dengan captcha rumit.
          </p>
        </div>
      </div>

      <!-- Brute Force, Session, & PIN -->
      <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <Lock class="w-4 h-4 text-amber-400" />
            <h3 class="text-sm font-black text-white uppercase tracking-wider">Batas Percobaan Login &amp; Sesi</h3>
          </div>
          <label class="relative inline-flex items-center cursor-pointer">
            <input
              v-model="form.enable_pin"
              type="checkbox"
              class="sr-only peer"
            />
            <div class="w-9 h-5 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-amber-400"></div>
          </label>
        </div>

        <div class="space-y-3 text-xs">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-300 mb-1">Max Percobaan Gagal</label>
              <input
                v-model.number="form.max_login_attempts"
                type="number"
                min="1"
                max="20"
                class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white font-bold"
              />
              <p v-if="form.errors.max_login_attempts" class="text-rose-400 text-[10px] mt-1">{{ form.errors.max_login_attempts }}</p>
            </div>
            <div>
              <label class="block font-bold text-slate-300 mb-1">Cooldown (Menit)</label>
              <input
                v-model.number="form.lockout_minutes"
                type="number"
                min="1"
                max="1440"
                class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white font-bold"
              />
              <p v-if="form.errors.lockout_minutes" class="text-rose-400 text-[10px] mt-1">{{ form.errors.lockout_minutes }}</p>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-300 mb-1">Timeout Sesi (Menit)</label>
              <input
                v-model.number="form.session_timeout_minutes"
                type="number"
                min="5"
                max="480"
                class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white font-bold"
              />
              <p v-if="form.errors.session_timeout_minutes" class="text-rose-400 text-[10px] mt-1">{{ form.errors.session_timeout_minutes }}</p>
            </div>
            <div>
              <label class="block font-bold text-slate-300 mb-1">Masa Aktif PIN (Hari)</label>
              <input
                v-model.number="form.pin_expiry_days"
                type="number"
                min="1"
                max="365"
                class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white font-bold"
              />
              <p v-if="form.errors.pin_expiry_days" class="text-rose-400 text-[10px] mt-1">{{ form.errors.pin_expiry_days }}</p>
            </div>
          </div>

          <!-- IP Whitelist -->
          <div>
            <label class="block font-bold text-slate-300 mb-1">IP Whitelist Terminal Komando (opsional)</label>
            <textarea
              v-model="form.ip_whitelist"
              rows="2"
              class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white font-mono text-[11px]"
              placeholder="127.0.0.1&#10;192.168.1.100"
            ></textarea>
          </div>

          <div class="flex items-center justify-between pt-1">
            <span class="text-[11px] text-slate-400">Isolasi Folder Storage Bukti / Dokumen Terenkripsi</span>
            <input
              v-model="form.private_media_storage"
              type="checkbox"
              class="w-4 h-4 rounded text-amber-400 focus:ring-0 bg-slate-900 border-slate-700 cursor-pointer"
            />
          </div>
        </div>
      </div>
    </form>

    <!-- Real Security Audit Trail Log (Verified from Database) -->
    <div class="rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl overflow-hidden space-y-0">
      <div class="p-5 bg-gradient-to-r from-slate-900 via-[#0b1426] to-[#080f20] border-b border-slate-800 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <History class="w-4 h-4 text-amber-400" />
          <h3 class="text-sm font-black text-white uppercase tracking-wider">
            Jejak Audit Keamanan Terakhir (Database Logs)
          </h3>
        </div>
        <span class="text-xs text-slate-400 font-mono">Tersinkron Realtime</span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-xs">
          <thead>
            <tr class="text-left text-slate-400 border-b border-slate-800/80 bg-slate-900/40">
              <th class="px-5 py-3 font-bold">Aktivitas / Event</th>
              <th class="px-5 py-3 font-bold">Pelaksana</th>
              <th class="px-5 py-3 font-bold">Alamat IP</th>
              <th class="px-5 py-3 font-bold">Waktu</th>
              <th class="px-5 py-3 font-bold">Detail Parameter</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800/60">
            <tr
              v-for="log in securityLogs"
              :key="log.id"
              class="hover:bg-slate-900/40 transition"
            >
              <td class="px-5 py-3 font-mono font-bold text-amber-300">
                <span class="px-2 py-0.5 rounded text-[10px] bg-slate-800 text-slate-200 border border-slate-700">
                  {{ log.action }}
                </span>
              </td>
              <td class="px-5 py-3 font-semibold text-white">{{ log.user }}</td>
              <td class="px-5 py-3 font-mono text-slate-400">{{ log.ip }}</td>
              <td class="px-5 py-3 text-slate-400">{{ log.time_ago }} ({{ log.created_at }})</td>
              <td class="px-5 py-3 font-mono text-[11px] text-slate-400 truncate max-w-xs">
                {{ log.metadata ? JSON.stringify(log.metadata) : '-' }}
              </td>
            </tr>
            <tr v-if="!securityLogs || securityLogs.length === 0">
              <td colspan="5" class="px-5 py-6 text-center text-slate-500 italic">
                Belum ada rekaman audit log keamanan. Simpan parameter di atas untuk mencatat log pertama.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </EnterpriseLayout>
</template>
