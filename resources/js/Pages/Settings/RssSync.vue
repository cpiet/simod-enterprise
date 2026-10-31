<script setup>
import { ref, computed } from 'vue';
import { useForm, router, Head, Link } from '@inertiajs/vue3';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import SecurityMaintenanceTabs from '@/Components/SecurityMaintenanceTabs.vue';
import {
  Radio,
  Clock,
  Shield,
  ShieldCheck,
  ShieldAlert,
  Server,
  Database,
  RotateCw,
  Play,
  Save,
  CheckCircle2,
  AlertTriangle,
  History,
  Activity,
  Cpu,
  Layers,
  Sparkles,
  Bookmark,
  Check,
  Plus,
  Trash2,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: { type: Array, default: () => [] },
  selectedPolda: { type: Object, default: () => ({}) },
  selectedCode: { type: String, default: 'SULBAR' },
  setting: { type: Object, default: () => ({}) },
  globalSetting: { type: Object, default: () => ({}) },
  telemetry: { type: Object, default: () => ({}) },
  recentLogs: { type: Array, default: () => [] },
});

// Inertia Form Setup
const form = useForm({
  polda_id: props.selectedPolda?.id || null,
  sync_mode: props.setting?.sync_mode || 'interval',
  interval_minutes: props.setting?.interval_minutes || 180,
  scheduled_times: Array.isArray(props.setting?.scheduled_times) ? [...props.setting.scheduled_times] : ['06:00', '12:00', '18:00'],
  max_articles_per_feed: props.setting?.max_articles_per_feed || 25,
  max_retained_articles_per_polda: props.setting?.max_retained_articles_per_polda || 1000,
  auto_prune_days: props.setting?.auto_prune_days || 90,
  timeout_seconds: props.setting?.timeout_seconds || 8,
  is_active: props.setting?.is_active ?? true,
  apply_to_all_poldas: false,
});

// Preset Standard Times
const standardPresetTimes = ['06:00', '12:00', '18:00', '22:00'];
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

const isTriggering = ref(false);
const triggerScope = ref('polda');

const runManualTrigger = (scope = 'polda') => {
  isTriggering.value = true;
  triggerScope.value = scope;
  router.post('/maintenance/rss-sync/trigger', {
    polda: props.selectedCode,
    scope: scope,
  }, {
    preserveScroll: true,
    onFinish: () => {
      isTriggering.value = false;
    },
  });
};

const saveSettings = () => {
  form.post('/maintenance/rss-sync', {
    preserveScroll: true,
  });
};
</script>

<template>
  <Head title="Otomasi Sinkron Berita RSS - Keamanan & Pemeliharaan" />

  <EnterpriseLayout
    :all-poldas="allPoldas"
    :selected-polda="selectedPolda"
    :selected-code="selectedCode"
  >
    <div class="space-y-6">
      <!-- Tabs Navigasi Keamanan & Pemeliharaan -->
      <SecurityMaintenanceTabs :selected-code="selectedCode" />

      <!-- Top Header Banner -->
      <div class="p-6 rounded-3xl bg-gradient-to-r from-[#0d182e] via-[#0b1426] to-[#070d1d] border border-slate-800 shadow-2xl relative overflow-hidden flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <div class="space-y-2 relative z-10">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-amber-400 text-slate-950 shadow-md">
              Otomasi Latar Belakang
            </span>
            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
              MULTI-TENANT GUARD AKTIF
            </span>
            <span class="text-xs text-slate-400 font-mono">
              Wilayah: <strong class="text-white">{{ selectedPolda?.name || selectedCode }}</strong>
            </span>
          </div>

          <h1 class="text-xl md:text-2xl font-black text-white tracking-tight flex items-center gap-2">
            <Radio class="w-6 h-6 text-amber-400" />
            <span>Otomasi Sinkron RSS &amp; Manajemen Kestabilan Sistem</span>
          </h1>

          <p class="text-xs md:text-sm text-slate-300 max-w-3xl leading-relaxed">
            Konfigurasi penjadwalan otomatis penarikan feed RSS untuk intelijen media. Dilengkapi perlindungan <strong>Mutex Lock</strong> anti-tumpang tindih, pembatasan memori, dan <strong>Auto-Capping Database</strong> agar performa server tetap cepat dan stabil untuk seluruh jajaran Polda.
          </p>
        </div>

        <!-- Quick Action Trigger Card -->
        <div class="flex flex-col sm:flex-row items-center gap-3 shrink-0 relative z-10">
          <button
            @click="runManualTrigger('polda')"
            :disabled="isTriggering"
            class="w-full sm:w-auto flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border-2 border-amber-400/90 text-amber-300 text-xs font-black transition shadow-lg shadow-amber-400/10 cursor-pointer disabled:opacity-50 select-none"
          >
            <RotateCw class="w-4 h-4 text-amber-400" :class="{ 'animate-spin': isTriggering && triggerScope === 'polda' }" />
            <span>{{ isTriggering && triggerScope === 'polda' ? 'Menyinkronkan...' : 'Uji Sinkron Polda Ini' }}</span>
          </button>

          <button
            @click="runManualTrigger('all')"
            :disabled="isTriggering"
            class="w-full sm:w-auto flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black transition shadow-lg shadow-amber-400/20 cursor-pointer disabled:opacity-50 select-none"
          >
            <Play class="w-4 h-4" :class="{ 'animate-spin': isTriggering && triggerScope === 'all' }" />
            <span>{{ isTriggering && triggerScope === 'all' ? 'Menyinkronkan Semua...' : 'Uji Sinkron Semua Polda' }}</span>
          </button>
        </div>
      </div>

      <!-- Telemetry & Health Grid (4 Cards) -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card 1: Mutex Lock Status -->
        <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-2">
          <div class="flex items-center justify-between text-xs text-slate-400 font-bold">
            <span>Status Antrean Server</span>
            <ShieldCheck v-if="!telemetry.is_locked" class="w-4 h-4 text-emerald-400" />
            <ShieldAlert v-else class="w-4 h-4 text-amber-400 animate-pulse" />
          </div>
          <div class="flex items-baseline gap-2">
            <span
              class="text-lg font-black tracking-tight"
              :class="!telemetry.is_locked ? 'text-emerald-400' : 'text-amber-400'"
            >
              {{ !telemetry.is_locked ? 'SIAGA (AMAN)' : 'PROSES BERJALAN' }}
            </span>
          </div>
          <p class="text-[11px] text-slate-400 leading-snug">
            {{ !telemetry.is_locked ? 'Mutex Lock aktif mencegah proses bertumpuk saat sinkronisasi.' : 'Sinkronisasi sedang aktif di latar belakang.' }}
          </p>
        </div>

        <!-- Card 2: Kapasitas Berita & Auto-Capping -->
        <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-2">
          <div class="flex items-center justify-between text-xs text-slate-400 font-bold">
            <span>Kapasitas Berita Polda</span>
            <Database class="w-4 h-4 text-amber-400" />
          </div>
          <div class="flex items-baseline gap-2">
            <span class="text-2xl font-black text-white">{{ telemetry.polda_articles }}</span>
            <span class="text-xs text-slate-400 font-semibold">/ maks {{ setting.max_retained_articles_per_polda }}</span>
          </div>
          <p class="text-[11px] text-emerald-400/90 font-medium leading-snug">
            Auto-Cap aktif: berita non-bookmark tertua dipangkas otomatis jika penuh.
          </p>
        </div>

        <!-- Card 3: Berita Ter-Bookmark (Kebal Hapus) -->
        <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-2">
          <div class="flex items-center justify-between text-xs text-slate-400 font-bold">
            <span>Arsip Bookmark (Aman)</span>
            <Bookmark class="w-4 h-4 text-amber-400 fill-amber-400" />
          </div>
          <div class="flex items-baseline gap-2">
            <span class="text-2xl font-black text-amber-400">{{ telemetry.polda_bookmarked }}</span>
            <span class="text-xs text-slate-400 font-semibold">Berita</span>
          </div>
          <p class="text-[11px] text-slate-400 leading-snug">
            100% terlindungi secara permanen &amp; tidak akan dihapus oleh auto-prune.
          </p>
        </div>

        <!-- Card 4: Waktu Sinkronisasi Terakhir -->
        <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-2">
          <div class="flex items-center justify-between text-xs text-slate-400 font-bold">
            <span>Sinkronisasi Terakhir</span>
            <Clock class="w-4 h-4 text-sky-400" />
          </div>
          <div class="text-sm font-black text-white truncate">
            {{ setting.last_sync_at }}
          </div>
          <p class="text-[11px] text-slate-400 truncate">
            Status: <strong class="text-amber-400 uppercase font-black">{{ setting.last_sync_status }}</strong> ({{ setting.last_articles_count }} berita)
          </p>
        </div>
      </div>

      <!-- Main Configuration Form -->
      <form @submit.prevent="saveSettings" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left: Scheduler Settings (2 cols) -->
        <div class="lg:col-span-2 space-y-6">
          <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-6">
            <div class="border-b border-slate-800 pb-4 flex items-center justify-between">
              <div class="flex items-center gap-2">
                <Clock class="w-5 h-5 text-amber-400" />
                <h3 class="text-base font-bold text-white">Mode &amp; Penjadwalan Sinkronisasi</h3>
              </div>
              <div class="flex items-center gap-2">
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="form.is_active" class="sr-only peer">
                  <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                  <span class="ml-2 text-xs font-bold text-slate-300">
                    {{ form.is_active ? 'Otomasi Aktif' : 'Otomasi Nonaktif' }}
                  </span>
                </label>
              </div>
            </div>

            <!-- Radio 1: Manual Only -->
            <label
              class="flex items-start gap-3 p-4 rounded-2xl border transition cursor-pointer select-none"
              :class="form.sync_mode === 'manual'
                ? 'bg-amber-500/10 border-amber-400/80 shadow-sm'
                : 'bg-slate-900/60 border-slate-800 hover:border-slate-700'"
            >
              <input
                type="radio"
                value="manual"
                v-model="form.sync_mode"
                class="mt-1 text-amber-400 focus:ring-amber-400"
              />
              <div class="space-y-1">
                <div class="text-sm font-bold text-white flex items-center gap-2">
                  <span>1. Mode Manual Murni (Sesuai Kebutuhan)</span>
                  <span v-if="form.sync_mode === 'manual'" class="px-2 py-0.5 rounded text-[10px] bg-amber-400 text-slate-950 font-black">Dipilih</span>
                </div>
                <p class="text-xs text-slate-400 leading-relaxed">
                  Sistem tidak akan menarik berita secara otomatis. Berita hanya diperbarui ketika operator mengklik tombol <em>"Sinkron Berita"</em> di halaman Monitoring Media Online. Sangat hemat bandwidth dan CPU.
                </p>
              </div>
            </label>

            <!-- Radio 2: Interval Mode -->
            <label
              class="flex items-start gap-3 p-4 rounded-2xl border transition cursor-pointer select-none"
              :class="form.sync_mode === 'interval'
                ? 'bg-amber-500/10 border-amber-400/80 shadow-sm'
                : 'bg-slate-900/60 border-slate-800 hover:border-slate-700'"
            >
              <input
                type="radio"
                value="interval"
                v-model="form.sync_mode"
                class="mt-1 text-amber-400 focus:ring-amber-400"
              />
              <div class="space-y-3 flex-1">
                <div class="flex items-center justify-between flex-wrap gap-2">
                  <div class="text-sm font-bold text-white flex items-center gap-2">
                    <span>2. Mode Interval Berkala (Rekomendasi Operasional)</span>
                    <span v-if="form.sync_mode === 'interval'" class="px-2 py-0.5 rounded text-[10px] bg-amber-400 text-slate-950 font-black">Dipilih</span>
                  </div>
                </div>
                <p class="text-xs text-slate-400 leading-relaxed">
                  Berita ditarik secara teratur setiap rentang waktu tertentu melalui Laravel Task Scheduler di latar belakang.
                </p>

                <!-- Interval Options Pills -->
                <div v-if="form.sync_mode === 'interval'" class="grid grid-cols-2 sm:grid-cols-5 gap-2 pt-2">
                  <button
                    v-for="opt in [
                      { label: 'Tiap 1 Jam', val: 60, desc: 'Breaking' },
                      { label: 'Tiap 3 Jam', val: 180, desc: 'Ideal' },
                      { label: 'Tiap 6 Jam', val: 360, desc: 'Shift' },
                      { label: 'Tiap 12 Jam', val: 720, desc: 'Santai' },
                      { label: 'Tiap 24 Jam', val: 1440, desc: 'Arsip' },
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

            <!-- Radio 3: Scheduled Times Mode (Jam Dinas Spesifik Polri) -->
            <label
              class="flex items-start gap-3 p-4 rounded-2xl border transition cursor-pointer select-none"
              :class="form.sync_mode === 'scheduled_times'
                ? 'bg-amber-500/10 border-amber-400/80 shadow-sm'
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
                  <span>3. Mode Jam Dinas Spesifik (Sesuai Jam Kerja Polri)</span>
                  <span v-if="form.sync_mode === 'scheduled_times'" class="px-2 py-0.5 rounded text-[10px] bg-amber-400 text-slate-950 font-black">Dipilih</span>
                </div>
                <p class="text-xs text-slate-400 leading-relaxed">
                  Menarik berita tepat pada jam-jam pergantian dinas atau sebelum briefing pimpinan (WITA/WIB).
                </p>

                <div v-if="form.sync_mode === 'scheduled_times'" class="space-y-3 pt-2">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs font-bold text-slate-300">Preset Jam Dinas:</span>
                    <button
                      v-for="timeStr in standardPresetTimes"
                      :key="timeStr"
                      type="button"
                      @click="togglePresetTime(timeStr)"
                      class="px-3 py-1.5 rounded-lg text-xs font-bold border transition cursor-pointer flex items-center gap-1.5"
                      :class="form.scheduled_times.includes(timeStr)
                        ? 'bg-amber-400 text-slate-950 border-amber-400 font-black'
                        : 'bg-slate-900 text-slate-300 border-slate-700 hover:text-white'"
                    >
                      <Check v-if="form.scheduled_times.includes(timeStr)" class="w-3.5 h-3.5" />
                      <span>Pukul {{ timeStr }}</span>
                    </button>
                  </div>

                  <!-- Custom Selected Times Badges -->
                  <div class="p-3 rounded-xl bg-slate-900 border border-slate-800 space-y-2">
                    <span class="text-[11px] font-bold text-slate-400">Jadwal Jam Aktif:</span>
                    <div class="flex items-center gap-2 flex-wrap">
                      <span
                        v-for="t in form.scheduled_times"
                        :key="t"
                        class="px-2.5 py-1 rounded-md text-xs font-mono font-bold bg-[#0b1426] border border-amber-400/40 text-amber-400 flex items-center gap-1.5 shadow-sm"
                      >
                        <Clock class="w-3 h-3 text-amber-400" />
                        <span>{{ t }}</span>
                        <button
                          type="button"
                          @click.stop="removeTime(t)"
                          class="hover:text-rose-400 ml-1"
                        >
                          &times;
                        </button>
                      </span>

                      <!-- Add Custom Time Input -->
                      <div class="flex items-center gap-1 ml-auto">
                        <input
                          type="time"
                          v-model="newCustomTime"
                          class="px-2 py-1 rounded-md text-xs bg-slate-800 border border-slate-700 text-white focus:outline-none"
                        />
                        <button
                          type="button"
                          @click="addCustomTime"
                          class="px-2.5 py-1 rounded-md bg-amber-400 text-slate-950 font-black text-xs hover:bg-amber-300 cursor-pointer"
                        >
                          + Tambah
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </label>
          </div>
        </div>

        <!-- Right: Stability & Resource Guards (1 col) -->
        <div class="space-y-6">
          <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-5">
            <div class="border-b border-slate-800 pb-3 flex items-center gap-2">
              <Shield class="w-5 h-5 text-emerald-400" />
              <h3 class="text-base font-bold text-white">Keamanan &amp; Kestabilan Server</h3>
            </div>

            <!-- Field 1: Max Retained Articles Per Polda (Auto-Capping) -->
            <div class="space-y-1.5">
              <label class="text-xs font-bold text-slate-200 flex items-center justify-between">
                <span>Batas Maksimum Berita / Polda</span>
                <span class="text-amber-400 font-mono font-extrabold">{{ form.max_retained_articles_per_polda }} Berita</span>
              </label>
              <input
                type="range"
                v-model.number="form.max_retained_articles_per_polda"
                min="300"
                max="3000"
                step="100"
                class="w-full accent-amber-400 cursor-pointer"
              />
              <p class="text-[10px] text-slate-400 leading-tight">
                Mencegah database penuh saat ada 34 Polda. Berita unbookmarked paling usang akan dipangkas otomatis jika melampaui angka ini.
              </p>
            </div>

            <!-- Field 2: Max Articles Per Feed -->
            <div class="space-y-1.5">
              <label class="text-xs font-bold text-slate-200 flex items-center justify-between">
                <span>Maks. Berita per Sumber Feed</span>
                <span class="text-amber-400 font-mono font-extrabold">{{ form.max_articles_per_feed }} Item</span>
              </label>
              <input
                type="number"
                v-model.number="form.max_articles_per_feed"
                min="5"
                max="100"
                class="w-full px-3 py-2 rounded-xl text-xs bg-slate-900 border border-slate-700 text-white font-bold"
              />
            </div>

            <!-- Field 3: Auto-Prune Age (Days) -->
            <div class="space-y-1.5">
              <label class="text-xs font-bold text-slate-200 flex items-center justify-between">
                <span>Masa Simpan Berita Lama</span>
                <span class="text-amber-400 font-mono font-extrabold">{{ form.auto_prune_days }} Hari</span>
              </label>
              <input
                type="number"
                v-model.number="form.auto_prune_days"
                min="7"
                max="365"
                class="w-full px-3 py-2 rounded-xl text-xs bg-slate-900 border border-slate-700 text-white font-bold"
              />
            </div>

            <!-- Field 4: Timeout Per Feed -->
            <div class="space-y-1.5">
              <label class="text-xs font-bold text-slate-200 flex items-center justify-between">
                <span>Batas Waktu Timeout Request</span>
                <span class="text-amber-400 font-mono font-extrabold">{{ form.timeout_seconds }} Detik</span>
              </label>
              <input
                type="number"
                v-model.number="form.timeout_seconds"
                min="3"
                max="30"
                class="w-full px-3 py-2 rounded-xl text-xs bg-slate-900 border border-slate-700 text-white font-bold"
              />
              <p class="text-[10px] text-slate-400 leading-tight">
                Jika portal media mengalami gangguan/down, proses akan di-skip setelah batas detik ini tanpa menghambat portal lain.
              </p>
            </div>

            <!-- Multi-Polda Global Checkbox -->
            <div class="pt-3 border-t border-slate-800">
              <label class="flex items-start gap-2 cursor-pointer select-none">
                <input
                  type="checkbox"
                  v-model="form.apply_to_all_poldas"
                  class="mt-1 rounded text-amber-400 focus:ring-amber-400"
                />
                <span class="text-xs text-slate-300 font-semibold leading-snug">
                  Terapkan konfigurasi kestabilan ini ke <strong>seluruh jajaran Polda</strong> (Nasional).
                </span>
              </label>
            </div>

            <!-- Save Button -->
            <button
              type="submit"
              :disabled="form.processing"
              class="w-full flex items-center justify-center gap-2 px-5 py-3 rounded-2xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-black text-xs transition shadow-lg shadow-amber-400/20 cursor-pointer disabled:opacity-50 select-none"
            >
              <Save class="w-4 h-4" />
              <span>{{ form.processing ? 'Menyimpan Konfigurasi...' : 'Simpan Pengaturan Otomasi' }}</span>
            </button>
          </div>
        </div>
      </form>

      <!-- Section: Telemetry & Activity Audit Logs -->
      <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between flex-wrap gap-2 border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <History class="w-5 h-5 text-amber-400" />
            <h3 class="text-base font-bold text-white">Rekam Jejak Telemetri &amp; Log Sinkronisasi Terkini</h3>
          </div>
          <span class="text-xs text-slate-400 font-mono">
            Memori PHP: {{ telemetry.memory_limit }} &bull; Max Execution: {{ telemetry.max_execution_time }}s
          </span>
        </div>

        <div v-if="recentLogs.length > 0" class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-slate-900/80 text-slate-400 text-[11px] font-black uppercase tracking-wider border-b border-slate-800">
              <tr>
                <th class="py-3 px-4">Waktu Eksekusi</th>
                <th class="py-3 px-4">Cakupan Wilayah</th>
                <th class="py-3 px-4">Tipe Pemicu</th>
                <th class="py-3 px-4">Status</th>
                <th class="py-3 px-4 text-center">Feed Diproses</th>
                <th class="py-3 px-4 text-center">Berita Disimpan</th>
                <th class="py-3 px-4 text-center">Berita Dipangkas</th>
                <th class="py-3 px-4 text-right">Durasi &amp; RAM</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60 font-medium">
              <tr
                v-for="log in recentLogs"
                :key="log.id"
                class="hover:bg-slate-900/40 transition"
              >
                <td class="py-3 px-4 font-mono text-slate-300">
                  <div>{{ log.created_at }}</div>
                  <div class="text-[10px] text-slate-300">{{ log.time_ago }}</div>
                </td>
                <td class="py-3 px-4 text-white font-bold">
                  {{ log.polda_name }}
                </td>
                <td class="py-3 px-4">
                  <span
                    class="px-2 py-0.5 rounded text-[10px] font-bold uppercase font-mono"
                    :class="log.sync_type === 'automatic' ? 'bg-sky-500/20 text-sky-300' : 'bg-purple-500/20 text-purple-300'"
                  >
                    {{ log.sync_type }}
                  </span>
                </td>
                <td class="py-3 px-4">
                  <span
                    class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase inline-flex items-center gap-1"
                    :class="log.status === 'success'
                      ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30'
                      : (log.status === 'skipped_locked' ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-rose-500/20 text-rose-300 border border-rose-500/30')"
                  >
                    <CheckCircle2 v-if="log.status === 'success'" class="w-3 h-3 text-emerald-400" />
                    <AlertTriangle v-else class="w-3 h-3 text-amber-400" />
                    <span>{{ log.status }}</span>
                  </span>
                </td>
                <td class="py-3 px-4 text-center font-bold text-slate-300">
                  {{ log.feeds_processed }}
                </td>
                <td class="py-3 px-4 text-center font-black text-amber-400">
                  +{{ log.articles_stored }}
                </td>
                <td class="py-3 px-4 text-center font-bold text-rose-400">
                  -{{ log.articles_pruned }}
                </td>
                <td class="py-3 px-4 text-right font-mono">
                  <div class="text-white font-bold">{{ log.duration_ms }} ms</div>
                  <div class="text-[10px] text-slate-300">{{ log.memory_usage_mb }} MB</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="p-8 text-center text-slate-300 text-xs">
          Belum ada riwayat aktivitas sinkronisasi. Klik tombol <strong>"Uji Sinkron Sekarang"</strong> di atas untuk menjalankan uji coba pertama.
        </div>
      </div>
    </div>
  </EnterpriseLayout>
</template>
