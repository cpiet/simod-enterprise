<script setup>
/**
 * Dialog "Bersihkan log" yang aman: pilihan jelas, pratinjau jumlah yang akan terhapus (nyata dari server),
 * ekspor CSV sebelum menghapus, dan konfirmasi ketik "HAPUS" untuk pilihan tanpa batas umur.
 * Dipakai di Audit Log (scope "all") dan Pusat Keamanan (scope "security").
 */
import { ref, computed, watch, onBeforeUnmount } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import { Trash2, Download, X, ShieldAlert, Loader2 } from 'lucide-vue-next';

const props = defineProps({
  open: { type: Boolean, default: false },
  scope: { type: String, default: 'all' }, // 'all' | 'security'
  filters: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['close']);

const mode = ref('older_than');
const days = ref(90);
const confirmText = ref('');
const preview = ref(null);
const loading = ref(false);
const submitting = ref(false);
const error = ref('');

const activeFilters = computed(() => Object.fromEntries(
  Object.entries(props.filters || {}).filter(([k, v]) => ['q', 'kategori', 'aksi', 'hasil', 'from', 'to', 'user'].includes(k) && v !== '' && v !== null && v !== undefined)
));
const hasFilters = computed(() => Object.keys(activeFilters.value).length > 0);
const needsConfirm = computed(() => mode.value !== 'older_than');
const confirmOk = computed(() => !needsConfirm.value || confirmText.value.trim().toUpperCase() === 'HAPUS');
const scopeLabel = computed(() => (props.scope === 'security' ? 'aktivitas keamanan' : 'log audit'));

const payload = computed(() => {
  const p = { mode: mode.value, scope: props.scope };
  if (mode.value === 'older_than') p.days = days.value;
  if (mode.value === 'filtered') Object.assign(p, activeFilters.value);
  return p;
});
const exportHref = computed(() => '/audit-logs/export?' + new URLSearchParams(mode.value === 'filtered' ? activeFilters.value : {}).toString());

let timer = null;
const loadPreview = () => {
  clearTimeout(timer);
  timer = setTimeout(async () => {
    loading.value = true;
    error.value = '';
    try {
      const res = await axios.get('/audit-logs/purge-preview', { params: payload.value });
      preview.value = res.data;
    } catch (e) {
      preview.value = null;
      error.value = 'Pratinjau gagal dimuat. Coba lagi.';
    } finally {
      loading.value = false;
    }
  }, 200);
};
watch([() => props.open, mode, days], () => { if (props.open) { confirmText.value = ''; loadPreview(); } }, { immediate: true });
onBeforeUnmount(() => clearTimeout(timer));

const submit = () => {
  if (!confirmOk.value || submitting.value || !preview.value?.count) return;
  submitting.value = true;
  router.post('/audit-logs/purge', { ...payload.value, confirm: confirmText.value }, {
    preserveScroll: true,
    onSuccess: () => emit('close'),
    onFinish: () => { submitting.value = false; },
  });
};

const options = computed(() => [
  { id: 'older_than', title: 'Hapus yang sudah lama', hint: 'Pilihan aman dan paling sering dipakai.' },
  ...(hasFilters.value ? [{ id: 'filtered', title: 'Hapus sesuai filter yang sedang aktif', hint: 'Hanya log yang tampil di daftar saat ini.' }] : []),
  { id: 'all', title: `Hapus semua ${scopeLabel.value}`, hint: 'Mengosongkan seluruhnya. Tidak dapat dibatalkan.', danger: true },
]);
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-[9998] flex items-end sm:items-center justify-center p-0 sm:p-4" role="dialog" aria-modal="true" aria-labelledby="purge-title">
      <div class="absolute inset-0 bg-slate-950/70 backdrop-blur-sm" @click="emit('close')"></div>

      <div class="relative w-full sm:max-w-lg max-h-[92vh] overflow-y-auto rounded-t-3xl sm:rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-700 shadow-2xl text-slate-900 dark:text-white">
        <div class="flex items-start justify-between gap-3 p-5 border-b border-slate-200 dark:border-slate-800">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-rose-100 dark:bg-rose-500/15 text-rose-600 dark:text-rose-300 flex items-center justify-center"><Trash2 class="w-4.5 h-4.5" /></div>
            <div>
              <h2 id="purge-title" class="text-base font-black">Bersihkan {{ scopeLabel }}</h2>
              <p class="text-[11px] text-slate-500 dark:text-slate-400">Pilih apa yang dihapus. Jumlahnya ditampilkan sebelum Anda memastikan.</p>
            </div>
          </div>
          <button type="button" @click="emit('close')" class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-white cursor-pointer" aria-label="Tutup"><X class="w-4 h-4" /></button>
        </div>

        <div class="p-5 space-y-3">
          <label
            v-for="o in options"
            :key="o.id"
            class="block rounded-2xl border p-3.5 cursor-pointer transition"
            :class="mode === o.id
              ? (o.danger ? 'border-rose-400 bg-rose-50 dark:bg-rose-500/10' : 'border-amber-400 bg-amber-50 dark:bg-amber-400/10')
              : 'border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-500'"
          >
            <div class="flex items-start gap-3">
              <input v-model="mode" type="radio" :value="o.id" class="mt-1 accent-amber-500" />
              <div class="min-w-0 flex-1">
                <p class="text-sm font-black" :class="o.danger ? 'text-rose-700 dark:text-rose-300' : ''">{{ o.title }}</p>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ o.hint }}</p>

                <div v-if="o.id === 'older_than' && mode === 'older_than'" class="mt-2.5 flex flex-wrap gap-1.5" role="group" aria-label="Umur log">
                  <button
                    v-for="d in [7, 30, 90, 180, 365]"
                    :key="d"
                    type="button"
                    @click.prevent="days = d"
                    class="px-3 py-1.5 rounded-lg text-xs font-black border transition cursor-pointer"
                    :class="days === d ? 'bg-amber-400 text-slate-950 border-amber-300' : 'border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:border-amber-400'"
                  >&gt; {{ d }} hari</button>
                </div>
              </div>
            </div>
          </label>

          <div class="rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-3.5 min-h-[3.5rem]" aria-live="polite">
            <p v-if="loading" class="text-xs text-slate-500 flex items-center gap-2"><Loader2 class="w-3.5 h-3.5 animate-spin" /> Menghitung...</p>
            <p v-else-if="error" class="text-xs text-rose-600 dark:text-rose-300">{{ error }}</p>
            <template v-else-if="preview">
              <p class="text-sm">
                Akan menghapus <strong class="text-rose-600 dark:text-rose-300 text-base">{{ preview.count.toLocaleString('id-ID') }}</strong> log.
              </p>
              <p v-if="preview.count" class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Rentang waktu: {{ preview.oldest }} &ndash; {{ preview.newest }}</p>
              <p v-else class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Tidak ada log yang cocok dengan pilihan ini.</p>
            </template>
          </div>

          <div v-if="needsConfirm" class="space-y-1">
            <label for="purge-confirm" class="text-[11px] font-bold text-slate-600 dark:text-slate-300">Ketik <strong class="text-rose-600 dark:text-rose-300">HAPUS</strong> untuk melanjutkan</label>
            <input
              id="purge-confirm"
              v-model="confirmText"
              type="text"
              autocomplete="off"
              placeholder="HAPUS"
              class="w-full px-3 py-2.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-sm font-mono font-bold outline-none focus:border-rose-400"
            />
          </div>

          <div class="flex gap-2 text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
            <ShieldAlert class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" />
            <p>Catatan pembersihan (siapa, kapan, berapa) <strong>selalu tersimpan</strong> dan tidak ikut terhapus. Disarankan mengunduh CSV dulu bila log perlu diarsipkan.</p>
          </div>
        </div>

        <div class="flex flex-col-reverse sm:flex-row sm:items-center justify-between gap-2 p-5 border-t border-slate-200 dark:border-slate-800">
          <a :href="exportHref" class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl text-xs font-bold border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:border-amber-400 transition">
            <Download class="w-3.5 h-3.5" /> Unduh CSV dulu
          </a>
          <div class="flex items-center gap-2">
            <button type="button" @click="emit('close')" class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer">Batal</button>
            <button
              type="button"
              @click="submit"
              :disabled="!confirmOk || submitting || loading || !preview?.count"
              class="px-4 py-2.5 rounded-xl text-xs font-black bg-rose-600 hover:bg-rose-500 text-white shadow-lg shadow-rose-600/20 transition disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer inline-flex items-center gap-1.5"
            >
              <Loader2 v-if="submitting" class="w-3.5 h-3.5 animate-spin" />
              <Trash2 v-else class="w-3.5 h-3.5" />
              <span>{{ submitting ? 'Menghapus...' : `Bersihkan ${preview?.count ? preview.count.toLocaleString('id-ID') + ' log' : ''}` }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
