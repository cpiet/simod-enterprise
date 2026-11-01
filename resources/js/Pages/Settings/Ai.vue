<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
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
const showApiKeyText = ref(false);
const form = useForm({
  gemini_api_key: '',
  gemini_model: props.setting?.gemini_model || 'gemini-2.5-pro',
  is_auto_analyze: props.setting?.is_auto_analyze ?? true,
  temperature: props.setting?.temperature ?? 0.20,
  apply_to_all_poldas: true,
});

// Test Connection State
const isTesting = ref(false);
const testResult = ref(null);

const testConnection = async () => {
  isTesting.value = true;
  testResult.value = null;

  try {
    const res = await axios.post(`/settings/ai/test?polda=${props.selectedCode}`, {
      api_key: form.gemini_api_key,
      model: form.gemini_model,
    });
    testResult.value = {
      success: true,
      message: res.data.message || 'Koneksi Google Gemini API Berhasil!',
      latency: res.data.latency_ms,
      model: res.data.model,
    };
  } catch (err) {
    const msg = err.response?.data?.message || err.message || 'Gagal menghubungi Google Gemini API';
    testResult.value = {
      success: false,
      message: msg,
    };
  } finally {
    isTesting.value = false;
  }
};

const submitSettings = () => {
  form.post(`/settings/ai?polda=${props.selectedCode}`, {
    preserveScroll: true,
    onSuccess: () => {
      form.gemini_api_key = '';
    },
  });
};
</script>

<template>
  <EnterpriseLayout
    :all-poldas="allPoldas"
    :selected-polda="selectedPolda"
    :selected-code="selectedCode"
  >
    <div class="space-y-6">
      <!-- Sub-Navigation: Keamanan & Pemeliharaan Tabs -->
      <SecurityMaintenanceTabs :selected-code="selectedCode" />

      <!-- Header Section -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-6 rounded-3xl bg-gradient-to-r from-[#0d182e] via-[#0b1426] to-[#070d1d] border border-slate-800 shadow-2xl">
        <div class="space-y-1.5">
          <div class="flex items-center gap-2">
            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-400 text-slate-950">
              Admin Pusat &bull; Intelijen Siber
            </span>
            <span
              class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider border"
              :class="props.setting?.has_api_key ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40' : 'bg-rose-500/20 text-rose-300 border-rose-500/40'"
            >
              {{ props.setting?.has_api_key ? '● API Aktif & Terhubung' : '○ Belum Dikonfigurasi' }}
            </span>
          </div>
          <h1 class="text-xl md:text-2xl font-black text-white tracking-tight flex items-center gap-2.5">
            <Bot class="w-6 h-6 text-amber-400" />
            <span>Mesin Intelijen AI & Google Gemini Pro</span>
          </h1>
          <p class="text-xs text-slate-400 max-w-3xl leading-relaxed">
            Konfigurasi tersentralisasi kunci API Google AI Studio untuk analisis otomatis 1-klik, pembuatan narasi eksekutif harian pimpinan, dan kalkulasi sentimen publik di seluruh {{ isDivhumas ? 'Divisi Humas Polri (Nasional)' : (selectedPolda?.name || 'Polda') }}.
          </p>
        </div>

        <div class="flex items-center gap-2 shrink-0">
          <button
            type="button"
            @click="testConnection"
            :disabled="isTesting"
            class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 hover:border-amber-400/50 text-slate-200 text-xs font-bold transition cursor-pointer disabled:opacity-50"
          >
            <RotateCw v-if="isTesting" class="w-4 h-4 animate-spin text-amber-400" />
            <Zap v-else class="w-4 h-4 text-amber-400" />
            <span>{{ isTesting ? 'Menguji API...' : 'Uji Koneksi API' }}</span>
          </button>
        </div>
      </div>

      <!-- KPI Summary Cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card 1: Status Kunci API -->
        <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
          <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
            <span>Kredensial API Key</span>
            <Key class="w-4 h-4 text-amber-400" />
          </div>
          <p class="text-lg md:text-xl font-black text-white font-mono">
            {{ props.setting?.has_api_key ? (props.setting?.masked_api_key || 'Terkonfigurasi') : 'Belum Ada' }}
          </p>
          <p class="text-[11px]" :class="props.setting?.has_api_key ? 'text-emerald-400' : 'text-amber-400'">
            {{ props.setting?.has_api_key ? 'Kunci aktif siap melayani analisis' : 'Perlu input API Key Google Gemini' }}
          </p>
        </div>

        <!-- Card 2: Model LLM Aktif -->
        <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
          <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
            <span>Model LLM Utama</span>
            <Cpu class="w-4 h-4 text-amber-400" />
          </div>
          <p class="text-lg md:text-xl font-black text-white">
            {{ props.setting?.gemini_model || 'gemini-2.5-pro' }}
          </p>
          <p class="text-[11px] text-slate-400">Nalar mendalam analisis intelijen</p>
        </div>

        <!-- Card 3: Otomasi Latar Belakang -->
        <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
          <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
            <span>Otomasi Jadwal</span>
            <Clock class="w-4 h-4 text-amber-400" />
          </div>
          <p class="text-lg md:text-xl font-black text-white">
            {{ props.setting?.is_auto_analyze ? 'Aktif (Otomatis)' : 'Manual On-Demand' }}
          </p>
          <p class="text-[11px] text-slate-400">Sinkronisasi saat penarikan RSS selesai</p>
        </div>

        <!-- Card 4: Total Laporan AI Tersimpan -->
        <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
          <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
            <span>Total Briefing AI</span>
            <Sparkles class="w-4 h-4 text-amber-400" />
          </div>
          <p class="text-lg md:text-xl font-black text-white">
            {{ telemetry?.total_reports || 0 }} Laporan
          </p>
          <p class="text-[11px] text-slate-400">
            Terakhir: {{ setting?.last_analyzed_at || 'Belum pernah' }}
          </p>
        </div>
      </div>

      <!-- Test Connection Result Banner -->
      <transition
        enter-active-class="transform ease-out duration-300 transition"
        enter-from-class="-translate-y-2 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
      >
        <div
          v-if="testResult"
          class="p-4 rounded-2xl border text-xs flex items-center justify-between gap-4 backdrop-blur-xl shadow-xl"
          :class="testResult.success ? 'bg-emerald-950/70 text-emerald-200 border-emerald-500/50' : 'bg-rose-950/70 text-rose-200 border-rose-500/50'"
        >
          <div class="flex items-center gap-3">
            <CheckCircle2 v-if="testResult.success" class="w-5 h-5 text-emerald-400 shrink-0" />
            <AlertTriangle v-else class="w-5 h-5 text-rose-400 shrink-0" />
            <div>
              <p class="font-bold text-sm text-white">{{ testResult.success ? 'Koneksi Berhasil!' : 'Koneksi Gagal!' }}</p>
              <p class="text-xs leading-relaxed">{{ testResult.message }}</p>
            </div>
          </div>
          <div v-if="testResult.latency" class="text-right shrink-0">
            <span class="font-mono text-sm font-black text-emerald-400">{{ testResult.latency }}ms</span>
            <span class="block text-[10px] text-slate-400">Latensi API</span>
          </div>
        </div>
      </transition>

      <!-- Main Form: Pengaturan Google Gemini API -->
      <form @submit.prevent="submitSettings" class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 md:p-8 shadow-2xl space-y-6">
        <div class="flex items-center justify-between border-b border-slate-800/80 pb-4">
          <div class="flex items-center gap-2.5">
            <ShieldCheck class="w-5 h-5 text-amber-400" />
            <div>
              <h2 class="text-base font-black text-white uppercase tracking-wider">
                Konfigurasi Kredensial &amp; Model AI
              </h2>
              <p class="text-xs text-slate-400">Kunci API tersimpan di database lokal terenkripsi server SIMOD Enterprise</p>
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

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Kolom 1: API Key & Model -->
          <div class="space-y-4">
            <!-- Google Gemini API Key -->
            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1.5 flex items-center justify-between">
                <span class="flex items-center gap-1.5">
                  <Key class="w-3.5 h-3.5 text-amber-400" />
                  <span>Google Gemini API Key (Master Key)</span>
                </span>
                <span v-if="props.setting?.has_api_key" class="text-[10px] font-mono text-emerald-400">
                  Tersimpan: {{ props.setting?.masked_api_key }}
                </span>
              </label>
              <div class="relative">
                <input
                  :type="showApiKeyText ? 'text' : 'password'"
                  v-model="form.gemini_api_key"
                  :placeholder="props.setting?.has_api_key ? 'Ketik API Key baru untuk mengganti (' + props.setting?.masked_api_key + ')' : 'Contoh: AIzaSyB...'"
                  class="w-full pl-3.5 pr-10 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-amber-300 font-mono text-xs placeholder-slate-500 focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400"
                />
                <button
                  type="button"
                  @click="showApiKeyText = !showApiKeyText"
                  class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white"
                  title="Tampilkan / Sembunyikan API Key"
                >
                  <EyeOff v-if="showApiKeyText" class="w-4 h-4" />
                  <Eye v-else class="w-4 h-4" />
                </button>
              </div>
              <p class="text-[11px] text-slate-400 mt-1.5 leading-relaxed">
                Kunci API ini digunakan secara otomatis oleh <strong>seluruh personel dan pimpinan</strong> saat melihat Radar Media Online tanpa perlu mereka menyetel apapun.
              </p>
            </div>

            <!-- Model Selection -->
            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1.5 flex items-center gap-1.5">
                <Cpu class="w-3.5 h-3.5 text-amber-400" />
                <span>Pilihan Model Gemini LLM</span>
              </label>
              <select
                v-model="form.gemini_model"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white text-xs focus:outline-none focus:border-amber-400"
              >
                <option value="gemini-2.5-pro">gemini-2.5-pro (Rekomendasi • Nalar Intelijen Paling Akurat & Mendalam)</option>
                <option value="gemini-1.5-pro">gemini-1.5-pro (Stabil • Analisis Komprehensif & Cepat)</option>
                <option value="gemini-2.5-flash">gemini-2.5-flash (Kecepatan Tinggi • Sangat Responsif)</option>
                <option value="gemini-1.5-flash">gemini-1.5-flash (Ekonomis • Respon Kilat)</option>
              </select>
              <p class="text-[11px] text-slate-400 mt-1">
                Sistem memiliki *automatic fallback* bertingkat jika model tertentu mengalami *rate limit*.
              </p>
            </div>
          </div>

          <!-- Kolom 2: Parameter Nalar & Otomasi Jadwal -->
          <div class="space-y-4">
            <!-- Temperature -->
            <div>
              <div class="flex items-center justify-between mb-1.5">
                <label class="text-xs font-bold text-slate-300 flex items-center gap-1.5">
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
              <div class="flex justify-between text-[10px] text-slate-400 mt-0.5">
                <span>0.0 (Presisi Faktual Intelijen)</span>
                <span>0.20 (Default Standar Polri)</span>
                <span>1.0 (Kreatif / Variatif)</span>
              </div>
            </div>

            <!-- Otomasi Background Checkbox -->
            <div class="p-4 rounded-2xl bg-slate-900/70 border border-slate-800 space-y-2">
              <div class="flex items-start gap-3">
                <input
                  id="is-auto-analyze"
                  type="checkbox"
                  v-model="form.is_auto_analyze"
                  class="mt-1 rounded border-slate-700 text-amber-400 focus:ring-amber-400 bg-slate-800 cursor-pointer"
                />
                <label for="is-auto-analyze" class="text-xs cursor-pointer select-none">
                  <strong class="text-white block font-bold">Aktifkan Analisis Otomatis di Latar Belakang</strong>
                  <span class="text-slate-400 text-[11px] leading-relaxed block mt-0.5">
                    Setiap sinkronisasi berita RSS mendeteksi berita baru, server otomatis menjalankan Gemini Pro di latar belakang sehingga laporan briefing pimpinan selalu siap tanpa harus diklik manual.
                  </span>
                </label>
              </div>

              <!-- Master Key for All Poldas -->
              <div class="flex items-start gap-3 pt-2 border-t border-slate-800/80">
                <input
                  id="apply-all-poldas"
                  type="checkbox"
                  v-model="form.apply_to_all_poldas"
                  class="mt-1 rounded border-slate-700 text-amber-400 focus:ring-amber-400 bg-slate-800 cursor-pointer"
                />
                <label for="apply-all-poldas" class="text-xs cursor-pointer select-none">
                  <strong class="text-amber-300 block font-bold">Terapkan Kunci Ini untuk Seluruh 34 Polda Jajaran</strong>
                  <span class="text-slate-400 text-[11px] leading-relaxed block mt-0.5">
                    Gunakan 1 Kunci API ini sebagai Master Key Nasional untuk Divhumas Mabes dan seluruh Polda yang belum memiliki API key khusus.
                  </span>
                </label>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer Actions -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-4 border-t border-slate-800">
          <div class="flex items-center gap-2 text-xs text-slate-400">
            <Shield class="w-4 h-4 text-emerald-400" />
            <span>Kredensial dilindungi enkripsi AES-256 dan hanya dapat dikelola oleh Administrator.</span>
          </div>

          <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
            <button
              type="submit"
              :disabled="form.processing"
              class="flex items-center gap-2 px-6 py-2.5 rounded-xl bg-gradient-to-r from-amber-400 to-amber-500 hover:from-amber-300 hover:to-amber-400 text-slate-950 text-xs font-black transition shadow-lg shadow-amber-400/20 cursor-pointer disabled:opacity-50"
            >
              <Save class="w-4 h-4" />
              <span>{{ form.processing ? 'Menyimpan...' : 'Simpan Konfigurasi AI' }}</span>
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
