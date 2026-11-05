<script setup>
/**
 * Dialog konfirmasi serbaguna: judul + penjelasan akibat, alasan opsional, dan konfirmasi ketik kata (mis. HAPUS)
 * untuk tindakan yang tidak dapat dibatalkan. Tombol utama menjelaskan tindakannya ("Pindahkan ke Sampah"),
 * bukan sekadar "OK".
 */
import { ref, computed, watch, nextTick } from 'vue';
import { AlertTriangle, Loader2 } from 'lucide-vue-next';

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, required: true },
  message: { type: String, default: '' },
  confirmLabel: { type: String, default: 'Lanjutkan' },
  tone: { type: String, default: 'danger' }, // 'danger' | 'primary'
  requireText: { type: String, default: '' },
  withReason: { type: Boolean, default: false },
  reasonLabel: { type: String, default: 'Alasan (opsional)' },
  busy: { type: Boolean, default: false },
});
const emit = defineEmits(['confirm', 'cancel']);

const typed = ref('');
const reason = ref('');
const input = ref(null);

watch(() => props.open, async (v) => {
  if (v) {
    typed.value = '';
    reason.value = '';
    await nextTick();
    input.value?.focus();
  }
});

const ok = computed(() => !props.requireText || typed.value.trim().toUpperCase() === props.requireText.toUpperCase());
const submit = () => { if (ok.value && !props.busy) emit('confirm', { reason: reason.value.trim(), confirm: typed.value.trim() }); };
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-[9998] flex items-end sm:items-center justify-center p-0 sm:p-4" role="alertdialog" aria-modal="true" aria-labelledby="confirm-title" @keydown.esc="emit('cancel')">
      <div class="absolute inset-0 bg-slate-950/70 backdrop-blur-sm" @click="emit('cancel')"></div>
      <form @submit.prevent="submit" class="relative w-full sm:max-w-md rounded-t-3xl sm:rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-700 shadow-2xl text-slate-900 dark:text-white">
        <div class="p-5 flex items-start gap-3">
          <div class="w-10 h-10 shrink-0 rounded-xl flex items-center justify-center" :class="tone === 'danger' ? 'bg-rose-100 dark:bg-rose-500/15 text-rose-600 dark:text-rose-300' : 'bg-amber-100 dark:bg-amber-400/15 text-amber-600 dark:text-amber-300'">
            <AlertTriangle class="w-5 h-5" />
          </div>
          <div class="min-w-0 space-y-1.5">
            <h2 id="confirm-title" class="text-base font-black leading-snug">{{ title }}</h2>
            <p v-if="message" class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">{{ message }}</p>
          </div>
        </div>

        <div v-if="withReason || requireText" class="px-5 pb-4 space-y-3">
          <label v-if="withReason" class="block space-y-1">
            <span class="text-[11px] font-bold text-slate-600 dark:text-slate-300">{{ reasonLabel }}</span>
            <textarea ref="input" v-model="reason" rows="2" maxlength="300" class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs outline-none focus:border-amber-400"></textarea>
          </label>
          <label v-if="requireText" class="block space-y-1">
            <span class="text-[11px] font-bold text-slate-600 dark:text-slate-300">Ketik <strong class="text-rose-600 dark:text-rose-300">{{ requireText }}</strong> untuk melanjutkan</span>
            <input :ref="withReason ? undefined : 'input'" v-model="typed" type="text" autocomplete="off" :placeholder="requireText" class="w-full px-3 py-2.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-sm font-mono font-bold outline-none focus:border-rose-400" />
          </label>
        </div>

        <div class="flex justify-end gap-2 p-5 pt-3 border-t border-slate-200 dark:border-slate-800">
          <button type="button" @click="emit('cancel')" class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer">Batal</button>
          <button
            type="submit"
            :disabled="!ok || busy"
            class="px-4 py-2.5 rounded-xl text-xs font-black text-white transition disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer inline-flex items-center gap-1.5"
            :class="tone === 'danger' ? 'bg-rose-600 hover:bg-rose-500 shadow-lg shadow-rose-600/20' : 'bg-amber-500 hover:bg-amber-400 text-slate-950'"
          >
            <Loader2 v-if="busy" class="w-3.5 h-3.5 animate-spin" />
            <span>{{ confirmLabel }}</span>
          </button>
        </div>
      </form>
    </div>
  </Teleport>
</template>
