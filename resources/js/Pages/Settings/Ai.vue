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
  RotateCcw,
  Globe,
  Lock,
  MessageSquare,
  Share2,
  Code,
  Copy,
  FileText,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: { type: Array, default: () => [] },
  selectedPolda: { type: Object, default: () => ({}) },
  selectedCode: { type: String, default: 'ALL' },
  setting: { type: Object, default: () => ({}) },
  globalSetting: { type: Object, default: () => ({}) },
  telemetry: { type: Object, default: () => ({}) },
  recentReports: { type: Array, default: () => [] },
  socialSetting: { type: Object, default: () => ({}) },
});

const isDivhumas = props.selectedCode === 'ALL' || props.selectedPolda?.code === 'DIVHUMAS';

// Sub-Tab State: 'gemini' | 'apify'
const activeAiTab = ref('gemini');

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

// Apify Scraper Form State
const apifyForm = useForm({
  apify_api_token: props.socialSetting?.apify_api_token || '',
  keywords_tracked: props.socialSetting?.keywords_tracked || 'Polri, Humas Polri, Polisi Presisi, Dumas Presisi',
  accounts_tracked: props.socialSetting?.accounts_tracked || '@divisihumaspri, @humaspolri, @polripresisi, @halo_polisi',
  sync_frequency: props.socialSetting?.sync_frequency || 'every_3_hours',
  auto_sync_enabled: props.socialSetting?.auto_sync_enabled ?? true,
  apply_to_all_poldas: true,
});

const isTestingApify = ref(false);
const apifyTestResult = ref(null);
const showApifyRawToken = ref(false);

const testApifyConnection = async () => {
  if (!apifyForm.apify_api_token) {
    apifyTestResult.value = {
      success: false,
      message: 'Masukkan Personal API Token Apify terlebih dahulu sebelum melakukan pengujian koneksi.',
    };
    return;
  }
  isTestingApify.value = true;
  apifyTestResult.value = null;
  try {
    const res = await axios.post('/settings/ai/test-apify', {
      apify_api_token: apifyForm.apify_api_token,
    });
    apifyTestResult.value = res.data;
  } catch (err) {
    apifyTestResult.value = {
      success: false,
      message: err.response?.data?.message || 'Gagal menghubungi server backend atau Apify.',
    };
  } finally {
    isTestingApify.value = false;
  }
};

const submitApifySettings = () => {
  apifyForm.post('/settings/ai/apify', {
    preserveScroll: true,
    onSuccess: () => {
      saveSuccessToast.value = true;
      saveToastMessage.value = 'Konfigurasi Mesin Scraper Apify & Parameter Patroli Medsos Berhasil Disimpan!';
      setTimeout(() => {
        saveSuccessToast.value = false;
      }, 5000);
    },
  });
};

const apifyTokensCount = computed(() => {
  if (!apifyForm.apify_api_token) return 0;
  return apifyForm.apify_api_token.split(/[\r\n,;]+/).map(t => t.trim()).filter(Boolean).length;
});

// Prompt Studio Form State & Computed Helpers
const promptForm = useForm({
  custom_prompt_template: props.setting?.custom_prompt_template || props.setting?.default_prompt_template || '',
  apply_to_all_poldas: false,
});

const isPromptCustomized = computed(() => {
  return Boolean(props.setting?.has_custom_prompt || (promptForm.custom_prompt_template && promptForm.custom_prompt_template !== props.setting?.default_prompt_template));
});

const hasMandatoryNewsPlaceholder = computed(() => {
  return (promptForm.custom_prompt_template || '').includes('[Tempelkan teks berita, rangkuman patroli siber, atau link isu di sini]');
});

const promptLineCount = computed(() => {
  if (!promptForm.custom_prompt_template) return 0;
  return promptForm.custom_prompt_template.split('\n').length;
});

const promptCharCount = computed(() => {
  return (promptForm.custom_prompt_template || '').length;
});

const copyPromptToClipboard = () => {
  if (navigator?.clipboard) {
    navigator.clipboard.writeText(promptForm.custom_prompt_template);
    saveSuccessToast.value = true;
    saveToastMessage.value = 'Naskah Prompt AI Berhasil Disalin ke Clipboard!';
    setTimeout(() => {
      saveSuccessToast.value = false;
    }, 4000);
  }
};

const loadStandardTemplate = () => {
  if (props.setting?.default_prompt_template) {
    promptForm.custom_prompt_template = props.setting.default_prompt_template;
    saveSuccessToast.value = true;
    saveToastMessage.value = 'Template standar resmi dimuat ke editor. Klik "Simpan Perubahan Prompt" jika ingin menetapkannya.';
    setTimeout(() => {
      saveSuccessToast.value = false;
    }, 4000);
  }
};

const submitPromptSettings = () => {
  promptForm.post('/settings/ai', {
    preserveScroll: true,
    onSuccess: () => {
      saveSuccessToast.value = true;
      saveToastMessage.value = 'Template Prompt Intelijen AI Berhasil Disimpan & Berlaku Dinamis!';
      setTimeout(() => {
        saveSuccessToast.value = false;
      }, 5000);
    },
  });
};

const resetPromptToDefault = () => {
  if (!confirm('Apakah Anda yakin ingin mereset prompt ke standar resmi bawaan Divhumas Polri? Kustomisasi sebelumnya akan dihapus dari basis data.')) {
    return;
  }
  router.post('/settings/ai/reset-prompt', {
    apply_to_all_poldas: promptForm.apply_to_all_poldas,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      promptForm.custom_prompt_template = props.setting?.default_prompt_template || '';
      saveSuccessToast.value = true;
      saveToastMessage.value = 'Template Prompt AI Berhasil Direset ke Standar Resmi Polri!';
      setTimeout(() => {
        saveSuccessToast.value = false;
      }, 5000);
    },
  });
};

const insertPlaceholder = (ph) => {
  promptForm.custom_prompt_template += (promptForm.custom_prompt_template.endsWith('\n') ? '' : '\n') + ph;
};

const insertSuggestedTopicsBlock = () => {
  const snippet = `
  "suggested_topics": [
    {
      "title": "Judul Isu Potensial / Topik Tren Media Baru 1",
      "priority": "urgent / high",
      "velocity": "+25 Berita / Tren Cepat",
      "keywords": "kata_kunci_1, kata_kunci_2, kata_kunci_3",
      "rationale": "Analisis intelijen mengapa isu ini berpotensi menjadi perhatian publik besar dan langkah antisipasi awal..."
    }
  ],`;
  insertPlaceholder(snippet);
};

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
    const res = await axios.post('/settings/ai/test', {
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

// Format Validation Warning helper
const hasKeyFormatWarning = computed(() => {
  if (!form.gemini_api_key) return false;
  const lines = form.gemini_api_key.split(/[\r\n,;]+/).map(s => s.trim()).filter(Boolean);
  return lines.some(l => l.includes(' ') || (l.startsWith('AIzaSy') && l.length !== 39));
});

const restoreSavedKeys = () => {
  if (props.setting?.raw_api_keys_text) {
    form.gemini_api_key = props.setting.raw_api_keys_text;
  }
};

// Submit Form with Toast Notification
const submitSettings = () => {
  form.raw_api_keys_text = form.gemini_api_key;
  form.post('/settings/ai', {
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
            v-if="activeAiTab === 'gemini'"
            type="button"
            @click="testConnection(true)"
            :disabled="isTesting"
            class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 hover:border-amber-400/50 text-slate-200 text-xs font-bold transition cursor-pointer disabled:opacity-50 shadow-md"
          >
            <RotateCw v-if="isTesting" class="w-4 h-4 animate-spin text-amber-400" />
            <Zap v-else class="w-4 h-4 text-amber-400" />
            <span>{{ isTesting ? 'Menguji Semua Kunci...' : 'Uji Koneksi Pool Kunci' }}</span>
          </button>

          <button
            v-else-if="activeAiTab === 'prompt'"
            type="button"
            @click="copyPromptToClipboard"
            class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 hover:border-amber-400/50 text-slate-200 text-xs font-bold transition cursor-pointer shadow-md"
          >
            <Copy class="w-4 h-4 text-amber-400" />
            <span>Salin Naskah Prompt</span>
          </button>

          <button
            v-else-if="activeAiTab === 'apify'"
            type="button"
            @click="testApifyConnection"
            :disabled="isTestingApify || !apifyForm.apify_api_token"
            class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 hover:border-amber-400/50 text-slate-200 text-xs font-bold transition cursor-pointer disabled:opacity-50 shadow-md"
          >
            <RotateCw v-if="isTestingApify" class="w-4 h-4 animate-spin text-amber-400" />
            <Globe v-else class="w-4 h-4 text-sky-400" />
            <span>{{ isTestingApify ? 'Menguji Akun Apify...' : 'Uji Koneksi Token Apify' }}</span>
          </button>
        </div>
      </div>

      <!-- Sub-Tabs Switcher: Google Gemini LLM vs Apify Scraper -->
      <div class="p-1.5 rounded-2xl bg-[#08101e] border border-slate-800 flex flex-wrap gap-2 shadow-lg">
        <button
          type="button"
          @click="activeAiTab = 'gemini'"
          class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-black transition cursor-pointer"
          :class="activeAiTab === 'gemini'
            ? 'bg-amber-400 text-slate-950 shadow-md shadow-amber-400/20'
            : 'text-slate-300 hover:text-white hover:bg-slate-800/60'"
        >
          <Bot class="w-4 h-4" />
          <span>Mesin Intelijen AI (Google Gemini LLM)</span>
          <span
            class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider"
            :class="props.setting?.has_api_key ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' : 'bg-rose-500/20 text-rose-300 border border-rose-500/40'"
          >
            {{ props.setting?.has_api_key ? `${props.setting?.total_keys || 1} Kunci Aktif` : 'Belum Ada Kunci' }}
          </span>
        </button>

        <button
          type="button"
          @click="activeAiTab = 'prompt'"
          class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-black transition cursor-pointer"
          :class="activeAiTab === 'prompt'
            ? 'bg-amber-400 text-slate-950 shadow-md shadow-amber-400/20'
            : 'text-slate-300 hover:text-white hover:bg-slate-800/60'"
        >
          <Code class="w-4 h-4" />
          <span>Template Prompt Intelijen (Editor Dinamis)</span>
          <span
            class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider"
            :class="isPromptCustomized ? 'bg-amber-500/20 text-amber-300 border border-amber-500/40' : 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40'"
          >
            {{ isPromptCustomized ? '⚡ Kustom Dinas' : '✓ Standar Polri' }}
          </span>
        </button>

        <button
          type="button"
          @click="activeAiTab = 'apify'"
          class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-black transition cursor-pointer"
          :class="activeAiTab === 'apify'
            ? 'bg-amber-400 text-slate-950 shadow-md shadow-amber-400/20'
            : 'text-slate-300 hover:text-white hover:bg-slate-800/60'"
        >
          <Globe class="w-4 h-4" />
          <span>Mesin Scraper Patroli Siber (Apify Cloud)</span>
          <span
            class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider"
            :class="socialSetting?.has_token ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' : 'bg-rose-500/20 text-rose-300 border border-rose-500/40'"
          >
            {{ socialSetting?.has_token ? `${socialSetting.total_tokens || 1} Token Aktif` : 'Belum Ada Token' }}
          </span>
        </button>
      </div>

      <!-- ============================================================== -->
      <!-- TAB 1: MESIN INTELIJEN AI (GOOGLE GEMINI)                      -->
      <!-- ============================================================== -->
      <div v-if="activeAiTab === 'gemini'" class="space-y-6">
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
                      v-if="props.setting?.raw_api_keys_text && form.gemini_api_key !== props.setting.raw_api_keys_text"
                      type="button"
                      @click="restoreSavedKeys"
                      class="text-[11px] text-amber-400 hover:text-amber-300 flex items-center gap-1 transition cursor-pointer font-bold"
                      title="Pulihkan kunci tersimpan dari basis data"
                    >
                      <RotateCcw class="w-3.5 h-3.5" />
                      <span>Pulihkan Kunci DB</span>
                    </button>
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
                      hasKeyFormatWarning ? 'border-rose-500 focus:border-rose-400 focus:ring-rose-400' : (form.gemini_api_key ? 'border-slate-700' : 'border-amber-500/50')
                    ]"
                    placeholder="AIzaSy... (Kunci Utama 1)&#10;AIzaSy... (Kunci Cadangan 2)&#10;AIzaSy... (Kunci Cadangan 3)&#10;(Tempel 1 atau beberapa API key dipisahkan baris baru atau koma)"
                  ></textarea>

                  <!-- Real-time format warning -->
                  <div v-if="hasKeyFormatWarning" class="p-2.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-[11px] flex items-center gap-2 mt-1.5 animate-pulse">
                    <AlertTriangle class="w-4 h-4 shrink-0 text-rose-400" />
                    <span>Format Kunci Tidak Standar: Terdeteksi spasi atau karakter terpotong. Google Gemini API Key resmi wajib persis 39 karakter tanpa spasi.</span>
                  </div>
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
                  <option value="gemini-3.5-flash-lite">gemini-3.5-flash-lite (Rekomendasi &bull; cepat &bull; tersedia di paket gratis)</option>
                  <option value="gemini-flash-lite-latest">gemini-flash-lite-latest (alias terbaru &bull; cepat)</option>
                  <option value="gemini-3.8-flash">gemini-3.8-flash (lebih mutakhir &bull; paket gratis hanya ~20 permintaan/hari)</option>
                  <option value="gemini-3.1-pro-preview">gemini-3.1-pro-preview (Pro &bull; wajib penagihan aktif)</option>
                  <option value="gemini-2.5-pro">gemini-2.5-pro (Pro &bull; wajib penagihan aktif)</option>
                </select>
                <p class="text-[11px] text-slate-400 mt-1">
                  Model ini dicoba lebih dulu. Jika gagal (kuota habis, model tidak tersedia, atau server Google sibuk), sistem mencoba gemini-3.5-flash-lite lalu gemini-3.8-flash. Model Pro hanya berfungsi bila akun Google Anda memakai penagihan aktif (bukan paket gratis).
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
      </div> <!-- End of Tab 1: Gemini -->

      <!-- ============================================================== -->
      <!-- TAB 2: TEMPLATE PROMPT INTELIJEN AI (EDITOR DINAMIS)           -->
      <!-- ============================================================== -->
      <div v-if="activeAiTab === 'prompt'" class="space-y-6">
        <!-- 4 KPI Summary Cards for Prompt Studio -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <!-- Card 1: Status Template -->
          <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
            <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
              <span>Status Template</span>
              <FileText class="w-4 h-4 text-amber-400" />
            </div>
            <p class="text-lg md:text-xl font-black truncate" :class="isPromptCustomized ? 'text-amber-400' : 'text-emerald-400'">
              {{ isPromptCustomized ? 'Kustomisasi Aktif' : 'Standar Mabes Polri' }}
            </p>
            <p class="text-[11px] text-slate-400">
              {{ isPromptCustomized ? 'Naskah modifikasi tersimpan di database' : 'Menggunakan doktrin bawaan resmi' }}
            </p>
          </div>

          <!-- Card 2: Lingkup Tenant -->
          <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
            <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
              <span>Lingkup Satker</span>
              <ShieldCheck class="w-4 h-4 text-amber-400" />
            </div>
            <p class="text-lg md:text-xl font-black text-white truncate">
              {{ isDivhumas ? 'Divhumas Mabes' : (selectedPolda?.name || 'Polda') }}
            </p>
            <p class="text-[11px] text-slate-400">
              {{ isDivhumas ? 'Orkestrasi Skala Nasional' : 'Kewilayahan ' + (selectedPolda?.code || '') }}
            </p>
          </div>

          <!-- Card 3: Metrik Naskah -->
          <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
            <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
              <span>Ukuran Naskah</span>
              <Layers class="w-4 h-4 text-amber-400" />
            </div>
            <p class="text-lg md:text-xl font-black text-white font-mono flex items-baseline gap-1.5">
              <span>{{ promptLineCount }}</span>
              <span class="text-xs text-slate-400 font-sans font-semibold">Baris</span>
              <span class="text-xs text-amber-400 font-mono">({{ promptCharCount.toLocaleString() }} char)</span>
            </p>
            <p class="text-[11px] text-slate-400">Siap diumpankan ke Gemini</p>
          </div>

          <!-- Card 4: Validasi Injeksi Berita -->
          <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
            <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
              <span>Token Injeksi Berita</span>
              <Zap class="w-4 h-4" :class="hasMandatoryNewsPlaceholder ? 'text-emerald-400' : 'text-rose-500'" />
            </div>
            <p class="text-lg md:text-xl font-black flex items-center gap-1.5" :class="hasMandatoryNewsPlaceholder ? 'text-emerald-400' : 'text-rose-400'">
              <CheckCircle2 v-if="hasMandatoryNewsPlaceholder" class="w-5 h-5 text-emerald-400 shrink-0" />
              <AlertTriangle v-else class="w-5 h-5 text-rose-500 shrink-0 animate-bounce" />
              <span>{{ hasMandatoryNewsPlaceholder ? 'Valid & Siap' : 'Hilang / Error' }}</span>
            </p>
            <p class="text-[11px]" :class="hasMandatoryNewsPlaceholder ? 'text-slate-400' : 'text-rose-300 font-bold'">
              {{ hasMandatoryNewsPlaceholder ? 'Titik 30 berita terdeteksi' : 'Wajib mencantumkan placeholder berita!' }}
            </p>
          </div>
        </div>

        <!-- Dynamic Placeholders Palette Card -->
        <div class="p-6 rounded-3xl bg-[#0b1426] border border-amber-500/30 shadow-2xl space-y-3">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div class="space-y-0.5">
              <div class="flex items-center gap-2">
                <span class="flex h-2.5 w-2.5 relative">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500"></span>
                </span>
                <h3 class="text-sm font-extrabold text-white flex items-center gap-1.5">
                  <span>Variabel Dinamis &amp; Token Injeksi Otomatis</span>
                  <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-amber-400/20 text-amber-300 border border-amber-400/30">
                    Live Tags
                  </span>
                </h3>
              </div>
              <p class="text-xs text-slate-400">
                Klik salah satu token di bawah ini untuk menyalin atau menyisipkannya langsung ke dalam editor naskah prompt.
              </p>
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-2 pt-1">
            <!-- Mandatory News Ingestion Placeholder -->
            <button
              type="button"
              @click="insertPlaceholder('[Tempelkan teks berita, rangkuman patroli siber, atau link isu di sini]')"
              class="px-3 py-1.5 rounded-xl text-xs font-mono font-bold bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 border border-rose-500/50 flex items-center gap-1.5 transition cursor-pointer shadow-sm"
              title="Wajib Ada: Tempat 30 artikel berita disuntikkan oleh sistem secara otomatis"
            >
              <span>📌 [Tempelkan teks berita, rangkuman patroli siber, atau link isu di sini]</span>
              <span class="text-[9px] uppercase px-1.5 py-0.2 rounded bg-rose-600 text-white font-sans font-black">Wajib</span>
            </button>

            <!-- Command Name Tag -->
            <button
              type="button"
              @click="insertPlaceholder('{COMMAND_NAME}')"
              class="px-3 py-1.5 rounded-xl text-xs font-mono font-bold bg-slate-900 hover:bg-slate-800 text-amber-300 border border-amber-500/40 flex items-center gap-1.5 transition cursor-pointer"
              title="Nama Satker / Polda otomatis: Divisi Humas Polri atau Polda Sulawesi Barat"
            >
              <span>🏷️ {COMMAND_NAME}</span>
            </button>

            <!-- Leader Title Tag -->
            <button
              type="button"
              @click="insertPlaceholder('{LEADER_TITLE}')"
              class="px-3 py-1.5 rounded-xl text-xs font-mono font-bold bg-slate-900 hover:bg-slate-800 text-amber-300 border border-amber-500/40 flex items-center gap-1.5 transition cursor-pointer"
              title="Gelar Pimpinan: Kapolri & Kadivhumas atau Kapolda & Kabid Humas"
            >
              <span>⭐ {LEADER_TITLE}</span>
            </button>

            <!-- Timezone Tag -->
            <button
              type="button"
              @click="insertPlaceholder('{TIMEZONE}')"
              class="px-3 py-1.5 rounded-xl text-xs font-mono font-bold bg-slate-900 hover:bg-slate-800 text-cyan-300 border border-cyan-500/40 flex items-center gap-1.5 transition cursor-pointer"
              title="Zona waktu kedinasan: WIB atau WITA"
            >
              <span>🕒 {TIMEZONE}</span>
            </button>

            <!-- Polres List Tag -->
            <button
              type="button"
              @click="insertPlaceholder('{POLRES_LIST}')"
              class="px-3 py-1.5 rounded-xl text-xs font-mono font-bold bg-slate-900 hover:bg-slate-800 text-slate-300 border border-slate-700 flex items-center gap-1.5 transition cursor-pointer"
              title="Daftar nama Polres jajaran kewilayahan"
            >
              <span>🏛️ {POLRES_LIST}</span>
            </button>

            <!-- Suggested Topics JSON Contract Tag -->
            <button
              type="button"
              @click="insertSuggestedTopicsBlock"
              class="px-3 py-1.5 rounded-xl text-xs font-mono font-bold bg-violet-500/20 hover:bg-violet-500/30 text-violet-300 border border-violet-500/50 flex items-center gap-1.5 transition cursor-pointer shadow-sm"
              title="Sisipkan blok skema JSON 'suggested_topics' untuk mengaktifkan deteksi tren cerdas Google News"
            >
              <span>⚡ + Blok suggested_topics (Tren Google News)</span>
            </button>
          </div>
        </div>

        <!-- Kontrak Skema Output JSON AI Card -->
        <div class="p-4 rounded-2xl bg-[#070d19]/90 border border-violet-500/30 text-xs text-slate-300 space-y-2">
          <div class="flex items-center justify-between gap-2 flex-wrap">
            <span class="font-extrabold text-white flex items-center gap-1.5">
              <Sparkles class="w-3.5 h-3.5 text-cyan-400" />
              <span>Kontrak Skema Output JSON AI (Google News Trend Ingestion)</span>
            </span>
            <span class="px-2 py-0.5 rounded-full text-[9px] font-mono font-bold bg-cyan-400/20 text-cyan-300 border border-cyan-400/40">
              Contract Validated
            </span>
          </div>
          <p class="text-[11px] text-slate-400">
            Pastikan output JSON selalu menyertakan array <code>"suggested_topics"</code> dengan atribut <code>title</code>, <code>priority</code>, <code>velocity</code>, <code>keywords</code>, dan <code>rationale</code>. Fitur ini menggerakkan bilah <strong>Sugesti Tren Cerdas</strong> di Smart Dossier Radar Media Online.
          </p>
        </div>

        <!-- Code Editor Container -->
        <div class="p-6 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-2xl space-y-4">
          <!-- Editor Action Toolbar -->
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-800">
            <div class="flex items-center gap-2">
              <Code class="w-4 h-4 text-amber-400" />
              <h3 class="text-sm font-bold text-white">Naskah System &amp; Task Prompt (Dapat Diedit Langsung)</h3>
              <span
                class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider"
                :class="isPromptCustomized ? 'bg-amber-400/20 text-amber-300 border border-amber-400/30' : 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'"
              >
                {{ isPromptCustomized ? 'Kustom Aktif' : 'Default Polri' }}
              </span>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
              <!-- Muat Template Standar -->
              <button
                type="button"
                @click="loadStandardTemplate"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-300 hover:text-white border border-slate-700 text-xs font-bold transition cursor-pointer"
                title="Muat teks template standar resmi Divhumas Polri ke dalam kotak editor"
              >
                <RotateCcw class="w-3.5 h-3.5 text-amber-400" />
                <span>Muat Template Standar</span>
              </button>

              <!-- Salin Prompt -->
              <button
                type="button"
                @click="copyPromptToClipboard"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-300 hover:text-white border border-slate-700 text-xs font-bold transition cursor-pointer"
                title="Salin seluruh teks naskah prompt ke clipboard"
              >
                <Copy class="w-3.5 h-3.5 text-cyan-400" />
                <span>Salin Naskah</span>
              </button>
            </div>
          </div>

          <!-- Warning Callout if Placeholder Missing -->
          <div
            v-if="!hasMandatoryNewsPlaceholder"
            class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/40 text-rose-200 text-xs flex items-center justify-between gap-3 flex-wrap"
          >
            <div class="flex items-center gap-2">
              <AlertTriangle class="w-4 h-4 text-rose-400 shrink-0" />
              <p>
                <strong>Peringatan Kritis:</strong> Naskah prompt belum memiliki token <code>[Tempelkan teks berita, rangkuman patroli siber, atau link isu di sini]</code>. Token ini wajib ada agar SIMOD dapat menyuntikkan 30 berita terbaru secara otomatis.
              </p>
            </div>
            <button
              type="button"
              @click="insertPlaceholder('[Tempelkan teks berita, rangkuman patroli siber, atau link isu di sini]')"
              class="px-3 py-1.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs cursor-pointer shadow-sm"
            >
              + Sisipkan Token Sekarang
            </button>
          </div>

          <!-- Textarea Editor -->
          <div class="relative">
            <textarea
              v-model="promptForm.custom_prompt_template"
              rows="24"
              spellcheck="false"
              class="w-full p-4 rounded-2xl bg-[#070d19] border text-white font-mono text-xs leading-relaxed focus:outline-none transition shadow-inner scrollbar-thin"
              :class="!hasMandatoryNewsPlaceholder ? 'border-rose-500/70 focus:border-rose-400' : 'border-slate-700 focus:border-amber-400'"
              placeholder="Ketik atau tempelkan template prompt intelijen AI kustom Anda di sini..."
            ></textarea>
          </div>

          <!-- Bottom Footer Bar & Actions -->
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-3 border-t border-slate-800">
            <!-- Apply to All Poldas Checkbox -->
            <label class="flex items-center gap-2.5 text-xs text-slate-300 cursor-pointer select-none">
              <input
                type="checkbox"
                v-model="promptForm.apply_to_all_poldas"
                class="rounded border-slate-700 bg-slate-900 text-amber-500 focus:ring-amber-400"
              />
              <span>Terapkan template kustom ini ke seluruh 34 Polda Jajaran (Konsolidasi Nasional)</span>
            </label>

            <!-- Action Buttons -->
            <div class="flex items-center gap-2.5 shrink-0 flex-wrap">
              <!-- Reset Button -->
              <button
                type="button"
                @click="resetPromptToDefault"
                :disabled="promptForm.processing"
                class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-rose-950/60 border border-slate-700 hover:border-rose-500/50 text-slate-300 hover:text-rose-400 text-xs font-bold transition cursor-pointer disabled:opacity-50"
                title="Hapus kustomisasi dan kembalikan ke template standar resmi Polri"
              >
                <Trash2 class="w-4 h-4 text-rose-500" />
                <span>Reset ke Standar Resmi Polri</span>
              </button>

              <!-- Save Button -->
              <button
                type="button"
                @click="submitPromptSettings"
                :disabled="promptForm.processing || !hasMandatoryNewsPlaceholder"
                class="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-400 to-amber-500 hover:from-amber-300 hover:to-amber-400 text-slate-950 text-xs font-black shadow-lg shadow-amber-400/25 transition cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed select-none"
              >
                <Save class="w-4 h-4 text-slate-950" />
                <span>{{ promptForm.processing ? 'Menyimpan...' : 'Simpan Perubahan Prompt' }}</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ============================================================== -->
      <!-- TAB 2: MESIN SCRAPER PATROLI SIBER (APIFY API)                 -->
      <!-- ============================================================== -->
      <div v-if="activeAiTab === 'apify'" class="space-y-6">
        <!-- Apify KPI Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <!-- Card 1: Token Apify Terdaftar -->
          <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
            <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
              <span>Pool Token Apify</span>
              <Key class="w-4 h-4 text-amber-400" />
            </div>
            <p class="text-lg md:text-xl font-black text-white font-mono flex items-baseline gap-1.5">
              <span>{{ apifyTokensCount }}</span>
              <span class="text-xs text-slate-400 font-sans font-semibold">Akun Terdaftar</span>
            </p>
            <p class="text-[11px] font-mono truncate" :class="socialSetting?.has_token ? 'text-emerald-400' : 'text-amber-400'">
              {{ socialSetting?.has_token ? `Aktif (${socialSetting.total_tokens || 1} Token Pool)` : 'Belum Dikonfigurasi' }}
            </p>
          </div>

          <!-- Card 2: Cakupan Multi-Platform -->
          <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
            <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
              <span>Cakupan Platform</span>
              <Globe class="w-4 h-4 text-sky-400" />
            </div>
            <p class="text-lg md:text-xl font-black text-white font-mono">
              5 Platform
            </p>
            <p class="text-[11px] text-slate-400 truncate">
              𝕏, TikTok, Instagram, YouTube, FB
            </p>
          </div>

          <!-- Card 3: Frekuensi Otomasi -->
          <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
            <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
              <span>Frekuensi Crawl</span>
              <Clock class="w-4 h-4 text-purple-400" />
            </div>
            <p class="text-sm md:text-base font-black text-white truncate font-mono">
              {{ apifyForm.sync_frequency || 'every_3_hours' }}
            </p>
            <p class="text-[11px] text-emerald-400 font-medium truncate">
              {{ apifyForm.auto_sync_enabled ? 'Otomasi Aktif di Latar Belakang' : 'Mode Sinkron Manual' }}
            </p>
          </div>

          <!-- Card 4: Waktu Sinkronisasi Terakhir -->
          <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
            <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
              <span>Pembaruan Terakhir</span>
              <Activity class="w-4 h-4 text-emerald-400" />
            </div>
            <p class="text-sm md:text-base font-black text-white truncate font-mono">
              {{ socialSetting?.last_synced_at || 'Belum Pernah' }}
            </p>
            <p class="text-[11px] text-slate-400 truncate">
              Wilayah: <strong class="text-white">{{ selectedPolda?.name || selectedCode }}</strong>
            </p>
          </div>
        </div>

        <!-- Apify Configuration Form -->
        <form @submit.prevent="submitApifySettings" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Left: Apify Multi-Token Pool & Connection (2 cols) -->
          <div class="lg:col-span-2 space-y-6">
            <div class="p-6 md:p-8 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-6">
              <!-- Card Header -->
              <div class="flex items-center justify-between border-b border-slate-800 pb-4">
                <div class="flex items-center gap-2.5">
                  <Globe class="w-5 h-5 text-amber-400" />
                  <div>
                    <h3 class="text-base font-bold text-white">Personal API Token Apify (Multi-Account Pool)</h3>
                    <p class="text-xs text-slate-400">Rotasi otomatis token akun Apify Free Tier ($5/bulan) untuk patroli media sosial.</p>
                  </div>
                </div>

                <button
                  type="button"
                  @click="testApifyConnection"
                  :disabled="isTestingApify || !apifyForm.apify_api_token"
                  class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-sky-500/10 hover:bg-sky-500/20 text-sky-400 border border-sky-500/30 text-xs font-bold transition disabled:opacity-40 cursor-pointer"
                >
                  <RefreshCw :class="['w-3.5 h-3.5', isTestingApify ? 'animate-spin' : '']" />
                  <span>{{ isTestingApify ? 'Menguji Semua...' : 'Uji Koneksi Token' }}</span>
                </button>
              </div>

              <!-- Token Pool Input -->
              <div class="space-y-2">
                <div class="flex items-center justify-between">
                  <label class="text-xs font-bold text-slate-300">
                    Daftar Personal API Token Apify (Pisahkan Koma atau Baris Baru):
                  </label>
                  <button
                    type="button"
                    @click="showApifyRawToken = !showApifyRawToken"
                    class="text-[11px] text-slate-400 hover:text-white inline-flex items-center gap-1 cursor-pointer"
                  >
                    <Eye v-if="!showApifyRawToken" class="w-3.5 h-3.5" />
                    <EyeOff v-else class="w-3.5 h-3.5" />
                    <span>{{ showApifyRawToken ? 'Sembunyikan Token' : 'Lihat Token Mentah' }}</span>
                  </button>
                </div>

                <textarea
                  v-model="apifyForm.apify_api_token"
                  :type="showApifyRawToken ? 'text' : 'password'"
                  rows="4"
                  placeholder="apify_api_xxxxxxx1&#10;apify_api_xxxxxxx2 (masukkan beberapa token untuk rotasi kuota)"
                  class="w-full px-4 py-3 rounded-2xl bg-slate-900 border border-slate-700 text-white font-mono text-xs focus:outline-none focus:border-amber-400 leading-relaxed"
                ></textarea>

                <p class="text-[11px] text-slate-400 leading-relaxed">
                  <strong class="text-amber-400">Smart Failover Pool:</strong> Sistem secara otomatis menggabungkan seluruh akun gratisan Apify yang dimasukkan di atas. Apabila kuota $5 akun pertama habis, sistem seketika beralih ke token berikutnya tanpa mengganggu operasional radar.
                </p>
              </div>

              <!-- Test Result Box -->
              <div
                v-if="apifyTestResult"
                class="p-4 rounded-2xl border text-xs space-y-3"
                :class="apifyTestResult.success ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300' : 'bg-rose-500/10 border-rose-500/30 text-rose-300'"
              >
                <div class="flex items-center gap-2">
                  <CheckCircle2 v-if="apifyTestResult.success" class="w-4 h-4 shrink-0 text-emerald-400" />
                  <AlertTriangle v-else class="w-4 h-4 shrink-0 text-rose-400" />
                  <p class="font-bold">{{ apifyTestResult.message }}</p>
                </div>

                <!-- Accounts Breakdown -->
                <div v-if="apifyTestResult.accounts && apifyTestResult.accounts.length > 0" class="space-y-1.5 pt-2 border-t border-slate-800 font-mono">
                  <div
                    v-for="(acc, i) in apifyTestResult.accounts"
                    :key="i"
                    class="p-2.5 rounded-xl bg-slate-950/60 border border-slate-800 flex items-center justify-between text-[11px]"
                  >
                    <div>
                      <span class="text-slate-400 mr-2">#{{ i + 1 }}</span>
                      <strong class="text-white">{{ acc.username || acc.token_masked }}</strong>
                      <span v-if="acc.email" class="text-slate-400 ml-1.5">({{ acc.email }})</span>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="px-2 py-0.5 rounded text-[10px] bg-sky-500/20 text-sky-300 font-bold">
                        {{ acc.plan || 'Free Tier' }}
                      </span>
                      <span
                        class="px-2 py-0.5 rounded text-[10px] font-black uppercase"
                        :class="acc.status === 'connected' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300'"
                      >
                        {{ acc.status }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Keywords and Accounts Monitored -->
              <div class="space-y-4 pt-4 border-t border-slate-800">
                <div class="space-y-1.5">
                  <label class="text-xs font-bold text-slate-200">
                    Kata Kunci Standar Pemantauan Opini (Keywords Tracked):
                  </label>
                  <textarea
                    v-model="apifyForm.keywords_tracked"
                    rows="2"
                    placeholder="Polri, Kapolri, Humas Polri, Polisi Presisi, Dumas Presisi"
                    class="w-full px-3.5 py-2.5 rounded-xl text-xs bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400"
                  ></textarea>
                </div>

                <div class="space-y-1.5">
                  <label class="text-xs font-bold text-slate-200">
                    Akun Media Sosial Kedinasan Target (Accounts Tracked):
                  </label>
                  <textarea
                    v-model="apifyForm.accounts_tracked"
                    rows="2"
                    placeholder="@divisihumaspri, @humaspolri, @polripresisi, @halo_polisi, @ccicpolri"
                    class="w-full px-3.5 py-2.5 rounded-xl text-xs bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400"
                  ></textarea>
                </div>
              </div>
            </div>
          </div>

          <!-- Right: Frequency & Governance Guards (1 col) -->
          <div class="space-y-6">
            <div class="p-6 rounded-3xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-5">
              <div class="border-b border-slate-800 pb-3 flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <Clock class="w-5 h-5 text-purple-400" />
                  <h3 class="text-base font-bold text-white">Frekuensi &amp; Otomasi</h3>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="apifyForm.auto_sync_enabled" class="sr-only peer">
                  <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                </label>
              </div>

              <!-- Frequency Options -->
              <div class="space-y-2">
                <label class="text-xs font-bold text-slate-300">Pilih Interval Penarikan Data:</label>
                <div class="grid grid-cols-1 gap-2">
                  <button
                    v-for="freq in [
                      { val: 'every_hour', label: 'Tiap 1 Jam', desc: 'Pemantauan Intensif / Breaking' },
                      { val: 'every_3_hours', label: 'Tiap 3 Jam', desc: 'Rekomendasi Operasional (Ideal)' },
                      { val: 'every_6_hours', label: 'Tiap 6 Jam', desc: 'Pergantian Shift Piket' },
                      { val: 'daily', label: 'Tiap 24 Jam', desc: 'Rekapitulasi Harian Pagi' },
                    ]"
                    :key="freq.val"
                    type="button"
                    @click="apifyForm.sync_frequency = freq.val"
                    class="p-3 rounded-xl border text-left transition cursor-pointer"
                    :class="apifyForm.sync_frequency === freq.val
                      ? 'bg-amber-400 text-slate-950 border-amber-400 font-black shadow-md'
                      : 'bg-slate-900 text-slate-300 border-slate-800 hover:bg-slate-800'"
                  >
                    <div class="text-xs font-bold">{{ freq.label }}</div>
                    <div class="text-[10px] opacity-80 font-medium">{{ freq.desc }}</div>
                  </button>
                </div>
              </div>

              <!-- Multi-Polda Global Checkbox -->
              <div class="pt-3 border-t border-slate-800">
                <label class="flex items-start gap-2 cursor-pointer select-none">
                  <input
                    type="checkbox"
                    v-model="apifyForm.apply_to_all_poldas"
                    class="mt-1 rounded text-amber-400 focus:ring-amber-400"
                  />
                  <span class="text-xs text-slate-300 font-semibold leading-snug">
                    Terapkan token &amp; konfigurasi Apify ini ke <strong>seluruh jajaran Polda</strong> (Nasional).
                  </span>
                </label>
              </div>

              <!-- Submit Button -->
              <button
                type="submit"
                :disabled="apifyForm.processing"
                class="w-full flex items-center justify-center gap-2 px-5 py-3 rounded-2xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-black text-xs transition shadow-lg shadow-amber-400/20 cursor-pointer disabled:opacity-50 select-none"
              >
                <Save class="w-4 h-4" />
                <span>{{ apifyForm.processing ? 'Menyimpan...' : 'Simpan Konfigurasi Scraper' }}</span>
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </EnterpriseLayout>
</template>
