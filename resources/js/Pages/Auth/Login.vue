<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import {
  Shield,
  Lock,
  User,
  KeyRound,
  Eye,
  EyeOff,
  Building2,
  CheckCircle2,
  AlertCircle,
  Fingerprint,
} from 'lucide-vue-next';

const props = defineProps({
  poldas: Array,
  errors: Object,
});

const selectedPoldaId = ref('ALL');
const showPassword = ref(false);

const form = ref({
  username: '',
  password: '',
  remember: true,
});

const isSubmitting = ref(false);

const fillAccount = (user, poldaId) => {
  form.value.username = user;
  form.value.password = '';
  selectedPoldaId.value = poldaId;
};

const handleLogin = () => {
  isSubmitting.value = true;
  let poldaCode = 'ALL';
  if (selectedPoldaId.value !== 'ALL') {
    const chosenPolda = props.poldas?.find((p) => p.id === selectedPoldaId.value);
    poldaCode = chosenPolda ? chosenPolda.code : 'SULBAR';
  }

  router.post('/login', {
    username: form.value.username,
    password: form.value.password,
    remember: form.value.remember,
    polda_id: selectedPoldaId.value,
    polda_code: poldaCode,
  }, {
    onFinish: () => {
      isSubmitting.value = false;
    },
  });
};
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-[#050b14] via-[#091322] to-[#040810] text-slate-100 flex flex-col justify-between p-4 selection:bg-amber-400 selection:text-slate-950 font-sans">
    <!-- Header Ambient Light -->
    <div class="fixed top-0 left-1/2 -translate-x-1/2 w-[600px] h-96 bg-amber-500/10 rounded-full blur-[120px] pointer-events-none"></div>

    <!-- Top Minimal Bar -->
    <div class="max-w-6xl w-full mx-auto flex items-center justify-between py-4 relative z-10">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-amber-400/20 border border-amber-400/50 flex items-center justify-center text-amber-400 shadow-lg shadow-amber-400/10">
          <Shield class="w-5 h-5" />
        </div>
        <div>
          <span class="text-sm font-extrabold text-white tracking-wider uppercase block">SIMOD ENTERPRISE</span>
          <span class="text-[10px] font-bold text-amber-400 uppercase tracking-widest">Multi-Polda Command Center</span>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
        <span class="text-xs font-bold text-emerald-400">Server Ready</span>
      </div>
    </div>

    <!-- Main Login Card Container -->
    <div class="max-w-md w-full mx-auto my-auto relative z-10 py-6">
      <div class="rounded-3xl bg-[#0b1426]/95 border border-slate-700/80 shadow-[0_20px_50px_rgba(0,0,0,0.6)] backdrop-blur-2xl p-7 sm:p-9 space-y-6">
        <!-- Logo & Title -->
        <div class="text-center space-y-2">
          <div class="w-16 h-16 mx-auto rounded-2xl bg-amber-400/15 border border-amber-400/40 flex items-center justify-center text-amber-400 shadow-xl shadow-amber-400/10 mb-3">
            <Shield class="w-9 h-9" />
          </div>
          <h1 class="text-2xl font-black text-white tracking-tight">
            SIMOD HUMAS
          </h1>
          <p class="text-xs font-bold text-slate-300">
            Sistem Informasi Manajemen Operasional Dokumentasi
          </p>

          <!-- Polda Tenant Selector Pill -->
          <div class="pt-2">
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-900 border border-slate-700 shadow-inner">
              <Building2 class="w-3.5 h-3.5 text-amber-400" />
              <select
                v-model="selectedPoldaId"
                class="bg-transparent text-xs font-extrabold text-amber-300 outline-none cursor-pointer pr-1"
              >
                <option value="ALL" class="bg-slate-900 text-amber-400 font-black">
                  🇮🇩 Divhumas Polri (Komando Nasional)
                </option>
                <option
                  v-for="polda in poldas"
                  :key="polda.id"
                  :value="polda.id"
                  class="bg-slate-900 text-white font-bold"
                >
                  🏛️ {{ polda.name }}
                </option>
              </select>
            </div>
          </div>
        </div>

        <!-- Quick Account Selector Buttons -->
        <div class="space-y-1.5">
          <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 text-center">Pilih Akun (isi kata sandi dinas):</p>
          <div class="grid grid-cols-3 gap-2">
            <button
              type="button"
              @click="fillAccount('kadivhumas', 'ALL')"
              class="p-2 rounded-xl border text-center transition cursor-pointer"
              :class="form.username === 'kadivhumas' ? 'bg-amber-400/20 border-amber-400 text-amber-300' : 'bg-slate-900/60 border-slate-800 text-slate-400 hover:text-white'"
            >
              <p class="text-[10px] font-black">⭐⭐ Kadivhumas</p>
              <p class="text-[8px] opacity-75">Irjen Johnny Isir</p>
            </button>
            <button
              type="button"
              @click="fillAccount('superadmin', 'ALL')"
              class="p-2 rounded-xl border text-center transition cursor-pointer"
              :class="form.username === 'superadmin' ? 'bg-rose-500/20 border-rose-400 text-rose-300' : 'bg-slate-900/60 border-slate-800 text-slate-400 hover:text-white'"
            >
              <p class="text-[10px] font-black">Superadmin</p>
              <p class="text-[8px] opacity-75">Mabes Polri</p>
            </button>
            <button
              type="button"
              @click="fillAccount('kabid_sulbar', (poldas?.[0]?.id || 1))"
              class="p-2 rounded-xl border text-center transition cursor-pointer"
              :class="form.username === 'kabid_sulbar' ? 'bg-sky-500/20 border-sky-400 text-sky-300' : 'bg-slate-900/60 border-slate-800 text-slate-400 hover:text-white'"
            >
              <p class="text-[10px] font-black">Kabid Sulbar</p>
              <p class="text-[8px] opacity-75">Kombes Slamet</p>
            </button>
          </div>
        </div>

        <!-- Form Login -->
        <form @submit.prevent="handleLogin" class="space-y-4">
          <div
            v-if="errors?.username"
            class="p-3 rounded-xl bg-rose-500/15 border border-rose-500/40 text-rose-300 text-xs font-bold flex items-center gap-2"
          >
            <AlertCircle class="w-4 h-4 text-rose-400 shrink-0" />
            <span>{{ errors.username }}</span>
          </div>

          <!-- Password Mode Inputs -->
          <div class="space-y-4">
            <!-- Username Input -->
            <div class="space-y-1.5">
              <label class="block text-xs font-bold text-slate-100 tracking-wide">
                Nama Pengguna (Username / NRP)
              </label>
              <div class="relative">
                <User class="w-4 h-4 text-slate-400 absolute left-3.5 top-3.5 pointer-events-none" />
                <input
                  v-model="form.username"
                  type="text"
                  placeholder="Masukkan username atau NRP dinas"
                  class="w-full pl-10 pr-4 py-3 rounded-xl bg-slate-900/90 border border-slate-700 text-sm font-semibold text-white placeholder-slate-400 focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition"
                  required
                />
              </div>
            </div>

            <!-- Password Input -->
            <div class="space-y-1.5">
              <label class="block text-xs font-bold text-slate-100 tracking-wide">
                Kata Sandi (Password)
              </label>
              <div class="relative">
                <Lock class="w-4 h-4 text-slate-400 absolute left-3.5 top-3.5 pointer-events-none" />
                <input
                  v-model="form.password"
                  :type="showPassword ? 'text' : 'password'"
                  placeholder="Masukkan kata sandi dinas"
                  class="w-full pl-10 pr-11 py-3 rounded-xl bg-slate-900/90 border border-slate-700 text-sm font-semibold text-white placeholder-slate-400 focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition"
                  required
                />
                <button
                  type="button"
                  @click="showPassword = !showPassword"
                  class="absolute right-3.5 top-3 text-slate-400 hover:text-white transition p-0.5 cursor-pointer"
                >
                  <EyeOff v-if="showPassword" class="w-4 h-4" />
                  <Eye v-else class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>

          <!-- Submit Button -->
          <button
            type="submit"
            :disabled="isSubmitting"
            class="w-full py-3.5 px-4 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-black text-sm tracking-wide shadow-xl shadow-amber-400/20 flex items-center justify-center gap-2 transition duration-200 cursor-pointer disabled:opacity-50 mt-2"
          >
            <Shield class="w-4 h-4" />
            <span>{{ isSubmitting ? 'Memverifikasi Akses...' : 'Masuk ke Portal Komando' }}</span>
          </button>
        </form>

        <!-- Security Badge Footer -->
        <div class="pt-4 border-t border-slate-800 text-center space-y-2">
          <div class="flex items-center justify-center gap-2 text-slate-300 text-xs font-medium">
            <Fingerprint class="w-4 h-4 text-emerald-400" />
            <span>Sistem terenkripsi & diawasi terpusat oleh Div TIK Polri</span>
          </div>
        </div>
      </div>

      <!-- Copyright -->
      <div class="text-center mt-6 text-xs text-slate-400 font-medium">
        &copy; 2026 <span class="text-amber-400 font-bold">SIMOD ENTERPRISE POLRI</span>. Hak cipta dilindungi undang-undang.
      </div>
    </div>

    <!-- Bottom Spacer -->
    <div class="h-6"></div>
  </div>
</template>

