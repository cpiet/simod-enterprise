<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import { Key, Building2, Phone, Mail, BadgeCheck, Save, UserCheck } from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  metrics: Object,
  profile: Object,
  errors: { type: Object, default: () => ({}) },
});

const ROLE_LABEL = {
  kadiv_humas: 'Kadivhumas Polri',
  super_admin_mabes: 'Super Administrator Mabes',
  superadmin: 'Super Administrator',
  polda_leader: 'Pimpinan Polda (Kabid Humas)',
  verifikator: 'Verifikator',
  analis: 'Analis Intelijen Media & Siber',
  operator: 'Operator Satwil',
};

const form = ref({ current_password: '', password: '', password_confirmation: '' });
const isSaving = ref(false);

const canSubmit = computed(() =>
  form.value.current_password && form.value.password.length >= 10 && form.value.password === form.value.password_confirmation
);

const submit = () => {
  isSaving.value = true;
  router.post('/profile/password', form.value, {
    preserveScroll: true,
    onSuccess: () => { form.value = { current_password: '', password: '', password_confirmation: '' }; },
    onFinish: () => { isSaving.value = false; },
  });
};

const field = 'w-full px-3 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-sm text-slate-900 dark:text-white outline-none focus:border-amber-400';
</script>

<template>
  <EnterpriseLayout
    :all-poldas="allPoldas"
    :selected-polda="selectedPolda"
    :selected-code="selectedCode"
    :metrics="metrics"
  >
    <div class="min-w-0">
      <h1 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white tracking-tight">Profil Saya</h1>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Identitas akun dan penggantian kata sandi.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-5">
      <section class="lg:col-span-2 rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 p-5 md:p-6 shadow-sm space-y-4">
        <div class="flex items-center gap-3">
          <div class="w-12 h-12 rounded-2xl bg-amber-400/15 text-amber-600 dark:text-amber-400 flex items-center justify-center"><UserCheck class="w-6 h-6" /></div>
          <div class="min-w-0">
            <p class="text-base font-black text-slate-900 dark:text-white truncate">{{ profile?.name }}</p>
            <p class="text-xs font-bold text-amber-600 dark:text-amber-400">{{ ROLE_LABEL[profile?.role] || profile?.role }}</p>
          </div>
        </div>

        <dl class="space-y-2.5 text-xs">
          <div class="flex items-center gap-2"><BadgeCheck class="w-4 h-4 text-slate-400 shrink-0" /><dt class="text-slate-500 dark:text-slate-400 w-24 shrink-0">Username</dt><dd class="font-bold text-slate-900 dark:text-white truncate">{{ profile?.username }}</dd></div>
          <div v-if="profile?.badge_number" class="flex items-center gap-2"><BadgeCheck class="w-4 h-4 text-slate-400 shrink-0" /><dt class="text-slate-500 dark:text-slate-400 w-24 shrink-0">NRP</dt><dd class="font-bold text-slate-900 dark:text-white">{{ profile.badge_number }}</dd></div>
          <div class="flex items-center gap-2"><Building2 class="w-4 h-4 text-slate-400 shrink-0" /><dt class="text-slate-500 dark:text-slate-400 w-24 shrink-0">Satuan</dt><dd class="font-bold text-slate-900 dark:text-white">{{ profile?.polres || profile?.polda || 'Divhumas Polri (Mabes)' }}</dd></div>
          <div class="flex items-center gap-2"><Mail class="w-4 h-4 text-slate-400 shrink-0" /><dt class="text-slate-500 dark:text-slate-400 w-24 shrink-0">Email</dt><dd class="font-bold text-slate-900 dark:text-white truncate">{{ profile?.email }}</dd></div>
          <div v-if="profile?.phone" class="flex items-center gap-2"><Phone class="w-4 h-4 text-slate-400 shrink-0" /><dt class="text-slate-500 dark:text-slate-400 w-24 shrink-0">Telepon</dt><dd class="font-bold text-slate-900 dark:text-white">{{ profile.phone }}</dd></div>
        </dl>

        <p class="text-[11px] text-slate-500 dark:text-slate-400 border-t border-slate-200 dark:border-slate-800 pt-3">Perubahan data identitas dilakukan oleh administrator melalui menu Personalia.</p>
      </section>

      <section class="lg:col-span-3 rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 p-5 md:p-6 shadow-sm space-y-4">
        <div class="flex items-center gap-2.5">
          <div class="w-8 h-8 rounded-xl bg-rose-100 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center"><Key class="w-4 h-4" /></div>
          <div>
            <h2 class="text-sm font-black text-slate-900 dark:text-white">Ganti kata sandi</h2>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">Minimal 10 karakter dan berbeda dari kata sandi saat ini.</p>
          </div>
        </div>

        <form @submit.prevent="submit" class="space-y-3.5">
          <label class="block space-y-1">
            <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400">Kata sandi saat ini</span>
            <input v-model="form.current_password" type="password" autocomplete="current-password" :class="field" />
            <span v-if="errors.current_password" class="text-[11px] font-bold text-rose-500">{{ errors.current_password }}</span>
          </label>

          <label class="block space-y-1">
            <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400">Kata sandi baru</span>
            <input v-model="form.password" type="password" autocomplete="new-password" :class="field" />
            <span v-if="errors.password" class="text-[11px] font-bold text-rose-500">{{ errors.password }}</span>
          </label>

          <label class="block space-y-1">
            <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400">Ulangi kata sandi baru</span>
            <input v-model="form.password_confirmation" type="password" autocomplete="new-password" :class="field" />
            <span v-if="form.password_confirmation && form.password !== form.password_confirmation" class="text-[11px] font-bold text-rose-500">Konfirmasi belum sama.</span>
          </label>

          <div class="flex justify-end pt-2">
            <button
              type="submit"
              :disabled="isSaving || !canSubmit"
              class="px-5 py-2.5 rounded-xl text-xs font-black bg-amber-400 hover:bg-amber-300 text-slate-950 shadow-lg shadow-amber-400/25 flex items-center gap-2 transition disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
            >
              <Save class="w-4 h-4" />
              <span>{{ isSaving ? 'Menyimpan...' : 'Simpan Kata Sandi' }}</span>
            </button>
          </div>
        </form>
      </section>
    </div>
  </EnterpriseLayout>
</template>
