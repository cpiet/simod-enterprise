<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import SecurityMaintenanceTabs from '@/Components/SecurityMaintenanceTabs.vue';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import { Save, Lock, Clock, KeyRound, ShieldCheck } from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  metrics: Object,
  security: Object,
  securityLogs: { type: Array, default: () => [] },
});

const form = ref({
  max_login_attempts: props.security?.max_login_attempts ?? 5,
  lockout_minutes: props.security?.lockout_minutes ?? 15,
  session_timeout_minutes: props.security?.session_timeout_minutes ?? 60,
});

const isSaving = ref(false);
const isDirty = computed(() =>
  form.value.max_login_attempts !== props.security?.max_login_attempts
  || form.value.lockout_minutes !== props.security?.lockout_minutes
  || form.value.session_timeout_minutes !== props.security?.session_timeout_minutes
);

const save = () => {
  isSaving.value = true;
  router.post('/security', form.value, {
    preserveScroll: true,
    onFinish: () => { isSaving.value = false; },
  });
};

const ACTION_LABEL = {
  'auth.login': { label: 'Berhasil masuk', cls: 'text-emerald-600 dark:text-emerald-400' },
  'auth.login_failed': { label: 'Gagal masuk', cls: 'text-rose-600 dark:text-rose-400' },
  'auth.logout': { label: 'Keluar', cls: 'text-slate-500 dark:text-slate-400' },
  'auth.password_changed': { label: 'Ganti kata sandi', cls: 'text-amber-600 dark:text-amber-400' },
  'security.update': { label: 'Ubah pengaturan keamanan', cls: 'text-sky-600 dark:text-sky-400' },
  'user.created': { label: 'Akun dibuat', cls: 'text-sky-600 dark:text-sky-400' },
  'polda.update': { label: 'Ubah data Polda', cls: 'text-sky-600 dark:text-sky-400' },
};
const actionMeta = (a) => ACTION_LABEL[a] || { label: a, cls: 'text-slate-500' };

const field = 'w-full px-3 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-sm font-bold text-slate-900 dark:text-white outline-none focus:border-amber-400';
</script>

<template>
  <EnterpriseLayout
    :all-poldas="allPoldas"
    :selected-polda="selectedPolda"
    :selected-code="selectedCode"
    :metrics="metrics"
  >
    <SecurityMaintenanceTabs :selected-code="selectedCode" />

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div class="min-w-0">
        <h1 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white tracking-tight">Keamanan Akun</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Hanya pengaturan yang benar-benar diterapkan aplikasi: penguncian login dan batas sesi tidak aktif.</p>
      </div>

      <button
        type="button"
        @click="save"
        :disabled="isSaving || !isDirty"
        class="px-5 py-2.5 rounded-xl text-xs font-black bg-amber-400 hover:bg-amber-300 text-slate-950 shadow-lg shadow-amber-400/25 flex items-center gap-2 transition disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer shrink-0"
      >
        <Save class="w-4 h-4" />
        <span>{{ isSaving ? 'Menyimpan...' : 'Simpan Pengaturan' }}</span>
      </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
      <section class="rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 p-5 md:p-6 shadow-sm space-y-4">
        <div class="flex items-center gap-2.5">
          <div class="w-8 h-8 rounded-xl bg-rose-100 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center"><Lock class="w-4 h-4" /></div>
          <div>
            <h2 class="text-sm font-black text-slate-900 dark:text-white">Penguncian login</h2>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">Mencegah tebak kata sandi. Dihitung per akun dan alamat IP.</p>
          </div>
        </div>

        <label class="block space-y-1">
          <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400">Batas percobaan gagal (1&ndash;20)</span>
          <input v-model.number="form.max_login_attempts" type="number" min="1" max="20" :class="field" />
        </label>
        <label class="block space-y-1">
          <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400">Durasi kunci (menit, 1&ndash;1440)</span>
          <input v-model.number="form.lockout_minutes" type="number" min="1" max="1440" :class="field" />
        </label>
      </section>

      <section class="rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 p-5 md:p-6 shadow-sm space-y-4">
        <div class="flex items-center gap-2.5">
          <div class="w-8 h-8 rounded-xl bg-sky-100 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center"><Clock class="w-4 h-4" /></div>
          <div>
            <h2 class="text-sm font-black text-slate-900 dark:text-white">Batas sesi tidak aktif</h2>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">Pengguna otomatis keluar bila tidak ada aktivitas selama durasi ini.</p>
          </div>
        </div>

        <label class="block space-y-1">
          <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400">Batas tidak aktif (menit, 5&ndash;480)</span>
          <input v-model.number="form.session_timeout_minutes" type="number" min="5" max="480" :class="field" />
        </label>

        <div class="rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-3 text-[11px] text-slate-600 dark:text-slate-300 flex gap-2">
          <ShieldCheck class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" />
          <p>Berlaku segera untuk login dan permintaan berikutnya. Pengguna dapat mengganti kata sandi sendiri di menu <strong>Profil Saya</strong>; administrator dapat mengatur ulang kata sandi di <strong>Personalia</strong>.</p>
        </div>
      </section>
    </div>

    <section class="rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
      <div class="px-5 md:px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center gap-2">
        <KeyRound class="w-4 h-4 text-amber-500" />
        <h2 class="text-sm font-black text-slate-900 dark:text-white">Aktivitas keamanan terbaru</h2>
      </div>

      <div v-if="securityLogs.length === 0" class="p-8 text-center text-xs text-slate-500">Belum ada aktivitas tercatat.</div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="text-[11px] font-black uppercase text-slate-500 border-b border-slate-200 dark:border-slate-800">
              <th class="px-5 md:px-6 py-2.5">Peristiwa</th>
              <th class="px-3 py-2.5">Pengguna</th>
              <th class="px-3 py-2.5">IP</th>
              <th class="px-5 md:px-6 py-2.5 text-right">Waktu</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <tr v-for="log in securityLogs" :key="log.id">
              <td class="px-5 md:px-6 py-2.5 font-bold" :class="actionMeta(log.action).cls">{{ actionMeta(log.action).label }}</td>
              <td class="px-3 py-2.5 text-slate-700 dark:text-slate-200">{{ log.user }}</td>
              <td class="px-3 py-2.5 font-mono text-slate-500 dark:text-slate-400">{{ log.ip }}</td>
              <td class="px-5 md:px-6 py-2.5 text-right text-slate-500 dark:text-slate-400" :title="log.created_at">{{ log.time_ago }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </EnterpriseLayout>
</template>
