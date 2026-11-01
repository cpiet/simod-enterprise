<script setup>
import { ref, computed } from 'vue';
import { useForm, router, Head, Link } from '@inertiajs/vue3';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import SecurityMaintenanceTabs from '@/Components/SecurityMaintenanceTabs.vue';
import axios from 'axios';
import {
  Bot,
  Cpu,
  Key,
  Shield,
  ShieldCheck,
  CheckCircle2,
  AlertTriangle,
  RotateCw,
  Zap,
  Save,
  Clock,
  Sparkles,
  ExternalLink,
  Layers,
  Activity,
  History,
  Eye,
  EyeOff,
  Check,
  Plus,
  Trash2,
  RefreshCw,
  Server,
  Radio,
  Sliders,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: { type: Array, default: () => [] },
  selectedPolda: { type: Object, default: () => ({}) },
  selectedCode: { type: String, default: 'ALL' },
  setting: { type: Object, default: () => ({}) },
  globalSetting: { type: Object, default: () => ({}) },
  telemetry: { type: Object, default: () => ({}) },
  recentReports: { type: Array, default: () => [] },
});

const isDivhumas = props.selectedCode === 'ALL' || props.selectedPolda?.code === 'DIVHUMAS';

// Form State
const showRawKeys = ref(false);
const saveSuccessToast = ref(false);
const saveToastMessage = ref('');

const form = useForm({
  gemini_api_key: props.setting?.raw_api_keys_text || '',
  raw_api_keys_text: props.setting?.raw_api_keys_text || '',
  gemini_model: props.setting?.gemini_model || 'gemini-3.5-flash-lite',
  is_auto_analyze: props.setting?.is_auto_analyze ?? true,
  sync_mode: props.setting?.sync_mode || 'after_rss',
  interval_minutes: props.setting?.interval_minutes || 180,
  scheduled_times: Array.isArray(props.setting?.scheduled_times) && props.setting.scheduled_times.length > 0
    ? [...props.setting.scheduled_times]
    : ['06:00', '12:00', '18:00', '22:00'],
  temperature: props.setting?.temperature ?? 0.20,
  apply_to_all_poldas: true,
});

// Preset Standard Dinas Times
const standardPresetTimes = [
  { time: '06:00', label: 'Apel Pagi' },
  { time: '12:00', label: 'Anev Siang' },
  { time: '18:00', label: 'Piket Malam' },
  { time: '22:00', label: 'Rekap Harian' },
];

const newCustomTime = ref('');

const addCustomTime = () => {
  const t = newCustomTime.value.trim();
  if (!t) return;
  if (/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/.test(t) && !form.scheduled_times.includes(t)) {
    form.scheduled_times.push(t);
    form.scheduled_times.sort();
  }
  newCustomTime.value = '';
};

const removeTime = (t) => {
  form.scheduled_times = form.scheduled_times.filter(item => item !== t);
};

const togglePresetTime = (timeStr) => {
  if (form.scheduled_times.includes(timeStr)) {
    removeTime(timeStr);
  } else {
    form.scheduled_times.push(timeStr);
    form.scheduled_times.sort();
  }
};

// Test Connection State (Single & Pool)
const isTesting = ref(false);
const testResult = ref(null);

const testConnection = async (testPool = true) => {
  isTesting.value = true;
  testResult.value = null;

  try {
    const res = await axios.post(`/settings/ai/test?polda=${props.selectedCode}`, {
      raw_api_keys_text: form.gemini_api_key,
      model: form.gemini_model,
      test_pool: testPool,
    });

    testResult.value = {
      success: res.data.success ?? true,
      message: res.data.message || 'Koneksi Google Gemini API Berhasil!',
      latency: res.data.latency_ms,
      model: res.data.model,
      total: res.data.total,
      active_count: res.data.active_count,
      accounts: res.data.accounts || [],
    };
  } catch (err) {
    const msg = err.response?.data?.message || err.message || 'Gagal menghubungi Google Gemini API';
    testResult.value = {
      success: false,
      message: msg,
      accounts: err.response?.data?.accounts || [],
    };
  } finally {
    isTesting.value = false;
  }
};

// Count Keys helper
const parsedKeysCount = computed(() => {
  if (!form.gemini_api_key) return 0;
  return form.gemini_api_key.split(/[\r\n,;]+/).map(k => k.trim()).filter(Boolean).length;
});

// Submit Form with Toast Notification
const submitSettings = () => {
  form.raw_api_keys_text = form.gemini_api_key;
  form.post(`/settings/ai?polda=${props.selectedCode}`, {
    preserveScroll: true,
    onSuccess: () => {
      saveSuccessToast.value = true;
      saveToastMessage.value = `Konfigurasi AI, Multi-Key Pool (${parsedKeysCount.value || props.setting?.total_keys || 1} Kunci) & Jadwal Otomasi Berhasil Disimpan!`;
      setTimeout(() => {
        saveSuccessToast.value = false;
      }, 5000);
    },
  });
};
</script>

<template>
  <Head title="Mesin Intelijen AI & Google Gemini - Keamanan & Pemeliharaan" />

  <EnterpriseLayout
    :all-poldas="allPoldas"
    :selected-polda="selectedPolda"
    :selected-code="selectedCode"
  >
    <div class="space-y-6">
      <!-- Sub-Navigation: Keamanan & Pemeliharaan Tabs -->
      <SecurityMaintenanceTabs :selected-code="selectedCode" />

      <!-- Save Success Toast Notification -->
      <transition
        enter-active-class="transform ease-out duration-300 transition"
        enter-from-class="-translate-y-4 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition ease-in duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="saveSuccessToast"
          class="p-4 rounded-2xl bg-gradient-to-r from-emerald-600 via-emerald-700 to-teal-800 text-white shadow-2xl flex items-center justify-between gap-4 border border-emerald-400/40"
        >
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-xl bg-white/20 flex items-center justify-center shrink-0">
              <CheckCircle2 class="w-5 h-5 text-white" />
            </div>
            <div>
              <p class="font-black text-sm tracking-wide">Penyimpanan Berhasil!</p>
              <p class="text-xs text-emerald-100">{{ saveToastMessage }}</p>
            </div>
          </div>
          <button
            type="button"
            @click="saveSuccessToast = false"
            class="text-emerald-200 hover:text-white px-2 py-1 rounded text-xs font-bold"
          >
            ✕ Tutup
          </button>
        </div>
      </transition>

      <!-- Header Section Banner -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-6 rounded-3xl bg-gradient-to-r from-[#0d182e] via-[#0b1426] to-[#070d1d] border border-slate-800 shadow-2xl relative overflow-hidden">
        <div class="space-y-1.5 relative z-10">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-400 text-slate-950 shadow-sm">
              Admin Pusat &bull; Intelijen Siber
            </span>
            <span
              class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider border flex items-center gap-1.5"
              :class="props.setting?.has_api_key ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40' : 'bg-rose-500/20 text-rose-300 border-rose-500/40'"
            >
              <span class="w-1.5 h-1.5 rounded-full" :class="props.setting?.has_api_key ? 'bg-emerald-400 animate-pulse' : 'bg-rose-400'"></span>
              <span>{{ props.setting?.has_api_key ? `Pool Aktif (${props.setting?.total_keys || 1} Kunci)` : 'Belum Dikonfigurasi' }}</span>
            </span>
            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-sky-500/10 text-sky-300 border border-sky-500/30">
              Multi-API Failover Ready
            </span>
          </div>

          <h1 class="text-xl md:text-2xl font-black text-white tracking-tight flex items-center gap-2.5">
            <Bot class="w-6 h-6 text-amber-400" />
            <span>Mesin Intelijen AI &amp; Google Gemini Pro</span>
          </h1>

          <p class="text-xs text-slate-300 max-w-3xl leading-relaxed">
            Pusat orkestrasi kredensial Google AI Studio, multi-API failover pool, dan penjadwalan analisis berkala untuk menghasilkan Executive Briefing, polarisasi opini publik, Early Warning System (EWS), serta Naskah WhatsApp dinas di seluruh {{ isDivhumas ? 'Divisi Humas Polri (Skala Nasional)' : (selectedPolda?.name || 'Polda') }}.
          </p>
        </div>

        <div class="flex items-center gap-2 shrink-0 relative z-10">
          <button
            type="button"
            @click="testConnection(true)"
            :disabled="isTesting"
            class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 hover:border-amber-400/50 text-slate-200 text-xs font-bold transition cursor-pointer disabled:opacity-50 shadow-md"
          >
            <RotateCw v-if="isTesting" class="w-4 h-4 animate-spin text-amber-400" />
            <Zap v-else class="w-4 h-4 text-amber-400" />
            <span>{{ isTesting ? 'Menguji Semua Kunci...' : 'Uji Koneksi Pool Kunci' }}</span>
          </button>
        </div>
      </div>

      <!-- KPI Summary Cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card 1: Multi-API Pool Status -->
        <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
          <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
            <span>Pool Kunci API</span>
            <Key class="w-4 h-4 text-amber-400" />
          </div>
          <p class="text-lg md:text-xl font-black text-white font-mono flex items-baseline gap-1.5">
            <span>{{ props.setting?.total_keys || (props.setting?.has_api_key ? 1 : 0) }}</span>
            <span class="text-xs text-slate-400 font-sans font-semibold">Kunci Terdaftar</span>
          </p>
          <p class="text-[11px] font-mono truncate" :class="props.setting?.has_api_key ? 'text-emerald-400' : 'text-amber-400'">
            {{ props.setting?.has_api_key ? `Utama: ${props.setting?.masked_api_key}` : 'Belum ada API Key' }}
          </p>
        </div>

        <!-- Card 2: Model LLM Utama -->
        <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
          <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
            <span>Model LLM Utama</span>
            <Cpu class="w-4 h-4 text-amber-400" />
          </div>
          <p class="text-lg md:text-xl font-black text-white truncate">
            {{ props.setting?.gemini_model || 'gemini-3.5-flash-lite' }}
          </p>
          <p class="text-[11px] text-slate-400">Respon kilat &amp; nalar intelijen</p>
        </div>

        <!-- Card 3: Otomasi Jadwal -->
        <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
          <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
            <span>Otomasi Jadwal</span>
            <Clock class="w-4 h-4 text-amber-400" />
          </div>
          <p class="text-lg md:text-xl font-black text-white truncate">
            <template v-if="form.sync_mode === 'after_rss'">Tiap Tarik RSS</template>
            <template v-else-if="form.sync_mode === 'scheduled_times'">{{ form.scheduled_times.length }} Jam Dinas</template>
            <template v-else-if="form.sync_mode === 'interval'">Tiap {{ form.interval_minutes / 60 }} Jam</template>
            <template v-else>Manual On-Demand</template>
          </p>
          <p class="text-[11px] text-slate-400">
            {{ form.is_auto_analyze ? 'Analisis otomatis aktif' : 'Manual saat tombol diklik' }}
          </p>
        </div>

        <!-- Card 4: Total Laporan AI -->
        <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
          <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
            <span>Total Briefing AI</span>
            <Sparkles class="w-4 h-4 text-amber-400" />
          </div>
          <p class="text-lg md:text-xl font-black text-white">
            {{ telemetry?.total_reports || 0 }} Laporan
          </p>
          <p class="text-[11px] text-slate-400 truncate">
            Terakhir: {{ setting?.last_analyzed_at || 'Belum pernah' }}
          </p>
        </div>
      </div>

      <!-- Test Connection Result Banner & Accounts Detail -->
      <transition
        enter-active-class="transform ease-out duration-300 transition"
        enter-from-class="-translate-y-2 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
      >
        <div
          v-if="testResult"
          class="p-4 rounded-2xl border text-xs backdrop-blur-xl shadow-xl space-y-3"
          :class="testResult.success ? 'bg-emerald-950/70 text-emerald-200 border-emerald-500/50' : 'bg-rose-950/70 text-rose-200 border-rose-500/50'"
        >
          <div class="flex items-start justify-between gap-4">
            <div class="flex items-start gap-3">
              <CheckCircle2 v-if="testResult.success" class="w-5 h-5 text-emerald-400 shrink-0 mt-0.5" />
              <AlertTriangle v-else class="w-5 h-5 text-rose-400 shrink-0 mt-0.5" />
              <div class="space-y-0.5">
                <p class="font-black text-sm text-white">
                  {{ testResult.success ? 'Uji Koneksi Berhasil!' : 'Uji Koneksi Gagal!' }}
                </p>
                <p class="text-xs leading-relaxed opacity-90">{{ testResult.message }}</p>
              </div>
            </div>
            <div v-if="testResult.latency" class="text-right shrink-0">
              <span class="font-mono text-sm font-black text-emerald-400">{{ testResult.latency }}ms</span>
              <span class="block text-[10px] opacity-75">Latensi Uji</span>
            </div>
          </div>

          <!-- Individual Token Results Cards -->
          <div v-if="testResult.accounts && testResult.accounts.length" class="space-y-2 pt-2 border-t border-slate-700/50">
            <div class="text-[11px] font-bold text-slate-300 flex items-center justify-between">
              <span>Status Kunci Individual (Pool Failover):</span>
              <span class="font-mono">{{ testResult.accounts.filter(a => a.status === 'connected').length }} / {{ testResult.accounts.length }} Siap</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
              <div
                v-for="acc in testResult.accounts"
                :key="acc.index"
                class="p-3 rounded-xl bg-slate-900/90 border flex flex-col justify-between gap-1.5"
                :class="acc.status === 'connected' ? 'border-emerald-500/40 text-emerald-300' : 'border-rose-500/40 text-rose-300'"
              >
                <div class="flex items-center justify-between gap-2">
                  <div class="flex items-center gap-2">
                    <span
                      class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider"
                      :class="acc.is_primary ? 'bg-amber-400 text-slate-950' : 'bg-slate-800 text-slate-300'"
                    >
                      {{ acc.is_primary ? 'Kunci #1 Utama' : `Kunci #${acc.index} Cadangan` }}
                    </span>
                    <span class="font-mono text-xs font-bold text-white">{{ acc.token_masked }}</span>
                  </div>
                  <div class="flex items-center gap-2">
                    <span v-if="acc.latency_ms" class="text-[11px] font-mono text-slate-400">{{ acc.latency_ms }}ms</span>
                    <span
                      class="px-2 py-0.5 rounded text-[10px] font-bold"
                      :class="acc.status === 'connected' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-400 border border-rose-500/30'"
                    >
                      {{ acc.status === 'connected' ? 'Terhubung' : 'Gagal' }}
                    </span>
                  </div>
                </div>
                <p v-if="acc.message" class="text-[10px] text-slate-400 leading-tight">
                  {{ acc.message }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </transition>

      <!-- Main Form: Pengaturan Google Gemini AI & Multi-Key Pool -->
      <form @submit.prevent="submitSettings" class="space-y-6">
        <!-- Section 1: Multi-API Key Pool & Model Settings -->
        <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 md:p-8 shadow-2xl space-y-6">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-800 pb-4 gap-2">
            <div class="flex items-center gap-2.5">
              <ShieldCheck class="w-5 h-5 text-amber-400" />
              <div>
                <h2 class="text-base font-black text-white uppercase tracking-wider">
                  1. Pool Kunci API Google AI Studio &amp; Multi-Token Failover
                </h2>
                <p class="text-xs text-slate-400">
                  Kredensial disimpan terenkripsi di basis data lokal server SIMOD Enterprise
                </p>
              </div>
            </div>
            <a
              href="https://aistudio.google.com/app/apikey"
              target="_blank"
              rel="noopener"
              class="text-xs text-amber-400 hover:text-amber-300 font-bold inline-flex items-center gap-1.5 transition"
            >
              <span>Dapatkan Google Gemini API Key Gratis</span>
              <ExternalLink class="w-3.5 h-3.5" />
            </a>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left 2 Cols: Multi-Key Textarea & Active Keys Detail -->
            <div class="lg:col-span-2 space-y-4">
              <div>
                <div class="flex items-center justify-between mb-1.5">
                  <label class="text-xs font-bold text-slate-200 flex items-center gap-1.5">
                    <Key class="w-3.5 h-3.5 text-amber-400" />
                    <span>DAFTAR GOOGLE GEMINI API KEY (MULTI-KEY POOL)</span>
                  </label>
                  <div class="flex items-center gap-2">
                    <button
                      type="button"
                      @click="showRawKeys = !showRawKeys"
                      class="text-[11px] text-slate-400 hover:text-amber-300 flex items-center gap-1 transition cursor-pointer"
                    >
                      <EyeOff v-if="showRawKeys" class="w-3.5 h-3.5" />
                      <Eye v-else class="w-3.5 h-3.5" />
                      <span>{{ showRawKeys ? 'Sembunyikan Karakter' : 'Tampilkan Karakter' }}</span>
                    </button>
                    <span class="text-[11px] font-mono px-2 py-0.5 rounded bg-amber-400/10 text-amber-300 border border-amber-400/30">
                      {{ parsedKeysCount }} Kunci Terbaca
                    </span>
                  </div>
                </div>

                <div class="relative">
                  <textarea
                    v-model="form.gemini_api_key"
                    rows="4"
                    :class="[
                      'w-full px-4 py-3 rounded-2xl bg-slate-900 border text-xs font-mono placeholder-slate-500 focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400 leading-relaxed transition',
                      showRawKeys ? 'text-amber-300' : 'text-slate-300 [text-security:disc] [-webkit-text-security:disc]',
                      form.gemini_api_key ? 'border-slate-700' : 'border-amber-500/50'
                    ]"
                    placeholder="AIzaSy... (Kunci Utama 1)&#10;AIzaSy... (Kunci Cadangan 2)&#10;AIzaSy... (Kunci Cadangan 3)&#10;(Tempel 1 atau beberapa API key dipisahkan baris baru atau koma)"
                  ></textarea>
                </div>

                <div class="p-3.5 rounded-xl bg-slate-900/70 border border-slate-800 text-[11px] text-slate-300 leading-relaxed space-y-1">
                  <p class="font-bold text-amber-400 flex items-center gap-1.5">
                    <Zap class="w-3.5 h-3.5" />
                    <span>Prinsip Kerja Multi-API Failover Pool:</span>
                  </p>
                  <p class="text-slate-400">
                    Kunci baris pertama akan dijadikan <strong>Kunci Utama</strong>. Jika Kunci Utama mengalami <em>Rate Limit (HTTP 429)</em> atau kehabisan kuota gratis harian, sistem secara instan beralih ke Kunci Cadangan #2, #3, dan seterusnya tanpa kegagalan pada laporan pimpinan.
                  </p>
                </div>
              </div>

              <!-- Registered Active Keys Badges List -->
              <div v-if="props.setting?.keys_detail && props.setting.keys_detail.length > 0" class="space-y-2">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">
                  Kunci Tersimpan Saat Ini di Basis Data:
                </span>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                  <div
                    v-for="k in props.setting.keys_detail"
                    :key="k.index"
                    class="p-2.5 rounded-xl bg-slate-900 border border-slate-800 flex items-center justify-between text-xs font-mono"
                  >
                    <div class="flex items-center gap-2">
                      <span
                        class="px-1.5 py-0.5 rounded text-[10px] font-black uppercase"
                        :class="k.is_primary ? 'bg-amber-400 text-slate-950' : 'bg-slate-800 text-slate-300'"
                      >
                        #{{ k.index }} {{ k.is_primary ? 'Utama' : 'Cadangan' }}
                      </span>
                      <span class="text-slate-200 font-bold">{{ k.masked }}</span>
                    </div>
                    <span class="text-[10px] text-emerald-400 font-sans font-bold flex items-center gap-1">
                      <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                      <span>Tersimpan</span>
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Right 1 Col: Model Selection & Creativity -->
            <div class="space-y-4">
              <!-- Model Selection -->
              <div>
                <label class="block text-xs font-bold text-slate-200 mb-1.5 flex items-center gap-1.5">
                  <Cpu class="w-3.5 h-3.5 text-amber-400" />
                  <span>Pilihan Model Gemini LLM</span>
                </label>
                <select
                  v-model="form.gemini_model"
                  class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white text-xs focus:outline-none focus:border-amber-400 cursor-pointer"
                >
                  <option value="gemini-3.5-flash-lite">gemini-3.5-flash-lite (Rekomendasi Utama &bull; ~800ms &bull; Kuota Bebas)</option>
                  <option value="gemini-flash-lite-latest">gemini-flash-lite-latest (Stabil &bull; Cepat)</option>
                  <option value="gemini-3.8-flash">gemini-3.8-flash (Generasi Terkini)</option>
                  <option value="gemini-3.1-pro-preview">gemini-3.1-pro-preview (Nalar Intelijen Mendalam &bull; Pro)</option>
                  <option value="gemini-2.5-pro">gemini-2.5-pro (Tier Khusus)</option>
                </select>
                <p class="text-[11px] text-slate-400 mt-1">
                  Sistem otomatis melakukan fallback model jika model pilihan mengalami kendala jaringan.
                </p>
              </div>

              <!-- Temperature Slider -->
              <div>
                <div class="flex items-center justify-between mb-1.5">
                  <label class="text-xs font-bold text-slate-200 flex items-center gap-1.5">
                    <Activity class="w-3.5 h-3.5 text-amber-400" />
                    <span>Kreativitas / Temperature Model</span>
                  </label>
                  <span class="text-xs font-mono font-bold text-amber-400">{{ form.temperature }}</span>
                </div>
                <input
                  type="range"
                  v-model.number="form.temperature"
                  min="0.0"
                  max="1.0"
                  step="0.05"
                  class="w-full accent-amber-400 cursor-pointer"
                />
                <div class="flex justify-between text-[10px] text-slate-400 mt-0.5 font-medium">
                  <span>0.0 (Presisi Faktual)</span>
                  <span>0.20 (Standar Humas)</span>
                  <span>1.0 (Variatif)</span>
                </div>
              </div>

              <!-- Master Key for All Poldas -->
              <div class="p-3.5 rounded-xl bg-slate-900/60 border border-slate-800 space-y-2">
                <div class="flex items-start gap-2.5">
                  <input
                    id="apply-all-poldas"
                    type="checkbox"
                    v-model="form.apply_to_all_poldas"
                    class="mt-1 rounded border-slate-700 text-amber-400 focus:ring-amber-400 bg-slate-800 cursor-pointer"
                  />
                  <label for="apply-all-poldas" class="text-xs cursor-pointer select-none">
                    <strong class="text-amber-300 block font-bold">Sinkronkan ke Seluruh Satwil Polda Jajaran</strong>
                    <span class="text-slate-400 text-[11px] leading-relaxed block mt-0.5">
                      Gunakan pool kunci ini sebagai Master Key Nasional untuk Divhumas Mabes dan jajaran Polda.
                    </span>
                  </label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Section 2: Penjadwalan & Waktu Otomasi Analisis Intelijen AI -->
        <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 md:p-8 shadow-2xl space-y-6">
          <div class="border-b border-slate-800 pb-4 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
              <Clock class="w-5 h-5 text-amber-400" />
              <div>
                <h2 class="text-base font-black text-white uppercase tracking-wider">
                  2. Mode Penjadwalan &amp; Waktu Otomasi Analisis AI
                </h2>
                <p class="text-xs text-slate-400">
                  Tentukan kapan mesin AI mengeksekusi penulisan Executive Briefing dan analisis polarisasi opini publik
                </p>
              </div>
            </div>

            <div class="flex items-center gap-2">
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="form.is_auto_analyze" class="sr-only peer">
                <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                <span class="ml-2 text-xs font-bold text-slate-300 hidden sm:inline">
                  {{ form.is_auto_analyze ? 'Otomasi Aktif' : 'Otomasi Nonaktif' }}
                </span>
              </label>
            </div>
          </div>

          <div class="space-y-4">
            <!-- Mode 1: Otomatis Setiap Sinkronisasi Berita RSS Selesai (Rekomendasi Utama) -->
            <label
              class="flex items-start gap-3.5 p-4 rounded-2xl border transition cursor-pointer select-none"
              :class="form.sync_mode === 'after_rss'
                ? 'bg-amber-500/10 border-amber-400 shadow-sm'
                : 'bg-slate-900/60 border-slate-800 hover:border-slate-700'"
            >
              <input
                type="radio"
                value="after_rss"
                v-model="form.sync_mode"
                class="mt-1 text-amber-400 focus:ring-amber-400"
              />
              <div class="space-y-1 flex-1">
                <div class="text-sm font-bold text-white flex items-center gap-2">
                  <span>1. Mode Sinkronisasi Setelah Tarik Berita RSS (Rekomendasi Utama Mabes)</span>
                  <span v-if="form.sync_mode === 'after_rss'" class="px-2 py-0.5 rounded text-[10px] bg-amber-400 text-slate-950 font-black">
                    Dipilih
                  </span>
                </div>
                <p class="text-xs text-slate-400 leading-relaxed">
                  Setiap kali background worker selesai menarik berita terbaru dari 22 portal resmi Divhumas / 29 portal daerah, server secara otomatis memicu Gemini untuk merangkum berita baru tersebut ke dalam arsip laporan briefing. Pimpinan selalu mendapatkan analisis mutakhir seketika.
                </p>
              </div>
            </label>

            <!-- Mode 2: Jam Dinas Tetap / Apel Dinas Polri -->
            <label
              class="flex items-start gap-3.5 p-4 rounded-2xl border transition cursor-pointer select-none"
              :class="form.sync_mode === 'scheduled_times'
                ? 'bg-amber-500/10 border-amber-400 shadow-sm'
                : 'bg-slate-900/60 border-slate-800 hover:border-slate-700'"
            >
              <input
                type="radio"
                value="scheduled_times"
                v-model="form.sync_mode"
                class="mt-1 text-amber-400 focus:ring-amber-400"
              />
              <div class="space-y-3 flex-1">
                <div class="text-sm font-bold text-white flex items-center gap-2">
                  <span>2. Mode Jam Dinas Spesifik Polri (Sesuai Jam Kerja &amp; Briefing Pimpinan)</span>
                  <span v-if="form.sync_mode === 'scheduled_times'" class="px-2 py-0.5 rounded text-[10px] bg-amber-400 text-slate-950 font-black">
                    Dipilih
                  </span>
                </div>
                <p class="text-xs text-slate-400 leading-relaxed">
                  Analisis otomatis dijalankan tepat pada jam-jam pergantian piket atau sebelum pelaksanaan Anev pimpinan (WIB / WITA).
                </p>

                <!-- Jadwal Jam Dinas Controls -->
                <div v-if="form.sync_mode === 'scheduled_times'" class="space-y-3 pt-2">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs font-bold text-slate-300">Preset Jam Dinas:</span>
                    <button
                      v-for="preset in standardPresetTimes"
                      :key="preset.time"
                      type="button"
                      @click="togglePresetTime(preset.time)"
                      class="px-3 py-1.5 rounded-lg text-xs font-bold border transition cursor-pointer flex items-center gap-1.5"
                      :class="form.scheduled_times.includes(preset.time)
                        ? 'bg-amber-400 text-slate-950 border-amber-400 font-black shadow-sm'
                        : 'bg-slate-900 text-slate-300 border-slate-700 hover:text-white'"
                    >
                      <Check v-if="form.scheduled_times.includes(preset.time)" class="w-3.5 h-3.5" />
                      <span>{{ preset.time }} &bull; {{ preset.label }}</span>
                    </button>
                  </div>

                  <!-- Active Times Badges & Custom Time Adder -->
                  <div class="p-3.5 rounded-xl bg-slate-900 border border-slate-800 space-y-2">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                      <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                        Jadwal Jam Analisis Aktif:
                      </span>

                      <!-- Custom Time Input Form -->
                      <div class="flex items-center gap-1.5">
                        <input
                          type="time"
                          v-model="newCustomTime"
                          class="px-2.5 py-1 rounded-lg bg-slate-800 border border-slate-700 text-white text-xs font-mono focus:outline-none focus:border-amber-400"
                        />
                        <button
                          type="button"
                          @click="addCustomTime"
                          class="px-3 py-1 rounded-lg bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-bold flex items-center gap-1 transition cursor-pointer"
                        >
                          <Plus class="w-3.5 h-3.5" />
                          <span>Tambah Jam</span>
                        </button>
                      </div>
                    </div>

                    <div class="flex items-center gap-2 flex-wrap pt-1">
                      <span
                        v-for="t in form.scheduled_times"
                        :key="t"
                        class="px-2.5 py-1 rounded-md text-xs font-mono font-bold bg-[#0b1426] border border-amber-400/40 text-amber-300 flex items-center gap-1.5 shadow-sm"
                      >
                        <Clock class="w-3 h-3 text-amber-400" />
                        <span>Pukul {{ t }}</span>
                        <button
                          type="button"
                          @click.stop="removeTime(t)"
                          class="hover:text-rose-400 ml-1 font-bold text-sm"
                          title="Hapus jam ini"
                        >
                          &times;
                        </button>
                      </span>

                      <span v-if="form.scheduled_times.length === 0" class="text-xs text-rose-400 font-medium">
                        Belum ada jam yang dipilih. Klik preset di atas atau tambah jam kustom.
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </label>

            <!-- Mode 3: Interval Berkala -->
            <label
              class="flex items-start gap-3.5 p-4 rounded-2xl border transition cursor-pointer select-none"
              :class="form.sync_mode === 'interval'
                ? 'bg-amber-500/10 border-amber-400 shadow-sm'
                : 'bg-slate-900/60 border-slate-800 hover:border-slate-700'"
            >
              <input
                type="radio"
                value="interval"
                v-model="form.sync_mode"
                class="mt-1 text-amber-400 focus:ring-amber-400"
              />
              <div class="space-y-3 flex-1">
                <div class="text-sm font-bold text-white flex items-center gap-2">
                  <span>3. Mode Interval Berkala (Waktu Dinamis)</span>
                  <span v-if="form.sync_mode === 'interval'" class="px-2 py-0.5 rounded text-[10px] bg-amber-400 text-slate-950 font-black">
                    Dipilih
                  </span>
                </div>
                <p class="text-xs text-slate-400 leading-relaxed">
                  Mesin AI secara otomatis menganalisis dan memperbarui naskah setiap X jam sekali secara reguler.
                </p>

                <!-- Interval Pills -->
                <div v-if="form.sync_mode === 'interval'" class="grid grid-cols-2 sm:grid-cols-5 gap-2 pt-2">
                  <button
                    v-for="opt in [
                      { label: 'Tiap 1 Jam', val: 60, desc: 'Breaking News' },
                      { label: 'Tiap 3 Jam', val: 180, desc: 'Operasional Ideal' },
                      { label: 'Tiap 6 Jam', val: 360, desc: 'Shift Pergantian' },
                      { label: 'Tiap 12 Jam', val: 720, desc: 'Santai' },
                      { label: 'Tiap 24 Jam', val: 1440, desc: 'Rekap Harian' },
                    ]"
                    :key="opt.val"
                    type="button"
                    @click="form.interval_minutes = opt.val"
                    class="p-2.5 rounded-xl border text-center transition cursor-pointer"
                    :class="form.interval_minutes === opt.val
                      ? 'bg-amber-400 text-slate-950 border-amber-400 font-black shadow-md'
                      : 'bg-slate-900 text-slate-300 border-slate-700 hover:bg-slate-800'"
                  >
                    <div class="text-xs font-bold">{{ opt.label }}</div>
                    <div class="text-[10px] opacity-80 font-medium">{{ opt.desc }}</div>
                  </button>
                </div>
              </div>
            </label>

            <!-- Mode 4: Manual On-Demand Murni -->
            <label
              class="flex items-start gap-3.5 p-4 rounded-2xl border transition cursor-pointer select-none"
              :class="form.sync_mode === 'manual'
                ? 'bg-amber-500/10 border-amber-400 shadow-sm'
                : 'bg-slate-900/60 border-slate-800 hover:border-slate-700'"
            >
              <input
                type="radio"
                value="manual"
                v-model="form.sync_mode"
                class="mt-1 text-amber-400 focus:ring-amber-400"
              />
              <div class="space-y-1 flex-1">
                <div class="text-sm font-bold text-white flex items-center gap-2">
                  <span>4. Mode Manual On-Demand (Hanya Saat Tombol Diklik)</span>
                  <span v-if="form.sync_mode === 'manual'" class="px-2 py-0.5 rounded text-[10px] bg-amber-400 text-slate-950 font-black">
                    Dipilih
                  </span>
                </div>
                <p class="text-xs text-slate-400 leading-relaxed">
                  Tidak ada eksekusi otomatis di latar belakang. Analisis hanya dijalankan ketika operator mengklik tombol <em>"⚡ Analisis Otomatis (Gemini Pro)"</em> di halaman Radar Media Online.
                </p>
              </div>
            </label>
          </div>
        </div>

        <!-- Footer Actions Bar -->
        <div class="p-6 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl flex flex-col sm:flex-row items-center justify-between gap-4">
          <div class="flex items-center gap-2 text-xs text-slate-400">
            <Shield class="w-4 h-4 text-emerald-400 shrink-0" />
            <span>Kredensial dilindungi enkripsi AES-256 dan hanya dapat dikelola oleh Administrator.</span>
          </div>

          <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
            <button
              type="submit"
              :disabled="form.processing"
              class="flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-gradient-to-r from-amber-400 to-amber-500 hover:from-amber-300 hover:to-amber-400 text-slate-950 text-xs font-black transition shadow-lg shadow-amber-400/20 cursor-pointer disabled:opacity-50 w-full sm:w-auto"
            >
              <Save class="w-4 h-4" />
              <span>{{ form.processing ? 'Menyimpan Pengaturan...' : 'Simpan Konfigurasi & Jadwal AI' }}</span>
            </button>
          </div>
        </div>
      </form>

      <!-- Riwayat Laporan Intelijen AI Terkini -->
      <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <History class="w-4 h-4 text-amber-400" />
            <h3 class="text-sm font-black text-white uppercase tracking-wider">
              Arsip Riwayat Analisis Intelijen AI Terakhir
            </h3>
          </div>
          <span class="text-xs text-slate-400">{{ recentReports.length }} Rekapitulasi Tercatat</span>
        </div>

        <div v-if="recentReports.length > 0" class="divide-y divide-slate-800/80 text-xs">
          <div
            v-for="rep in recentReports"
            :key="rep.id"
            class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2 hover:bg-slate-900/40 px-3 rounded-xl transition"
          >
            <div class="space-y-0.5">
              <p class="font-bold text-white leading-snug">{{ rep.title }}</p>
              <p class="text-[11px] text-slate-400 font-mono">
                {{ rep.report_date }} &bull; {{ rep.report_time }}
              </p>
            </div>
            <div class="flex items-center gap-3">
              <span
                class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider border shrink-0"
                :class="[
                  (rep.snapshot_national_status || '').includes('MERAH')
                    ? 'bg-rose-500/20 text-rose-300 border-rose-500/40'
                    : (rep.snapshot_national_status || '').includes('HIJAU')
                      ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40'
                      : 'bg-amber-400/20 text-amber-300 border-amber-400/40'
                ]"
              >
                {{ rep.snapshot_national_status || 'KONDUSIF' }}
              </span>
            </div>
          </div>
        </div>
        <div v-else class="text-center py-6 text-slate-400 text-xs">
          Belum ada arsip analisis intelijen AI. Klik tombol analisis di Radar Media Online untuk membuat laporan pertama.
        </div>
      </div>
    </div>
  </EnterpriseLayout>
</template>
