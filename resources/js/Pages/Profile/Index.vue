<script setup>
import { ref } from 'vue';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import {
  UserCheck,
  Shield,
  Key,
  Lock,
  Smartphone,
  Save,
  CheckCircle2,
  AlertCircle,
  Building2,
  Phone,
  Mail,
  Clock,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  metrics: Object,
});

const formPassword = ref({
  current: '',
  new: '',
  confirm: '',
});

const pinDinas = ref('740901');
const pinNotification = ref(false);
const passNotification = ref(false);

const updatePin = () => {
  pinNotification.value = true;
  setTimeout(() => (pinNotification.value = false), 3000);
};

const updatePassword = () => {
  passNotification.value = true;
  formPassword.value = { current: '', new: '', confirm: '' };
  setTimeout(() => (passNotification.value = false), 3000);
};
</script>

<template>
  <EnterpriseLayout
    :all-poldas="allPoldas"
    :selected-polda="selectedPolda"
    :selected-code="selectedCode"
    :metrics="metrics"
  >
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-400 text-slate-950">
            Identitas Anggota
          </span>
          <span class="text-xs font-bold text-slate-400">
            {{ selectedCode === 'ALL' ? 'Mabes Polri' : selectedPolda?.name }}
          </span>
        </div>
        <h1 class="text-2xl md:text-3xl font-black text-white tracking-tight mt-1">
          Profil Saya & Keamanan Dinas
        </h1>
        <p class="text-xs md:text-sm text-slate-300">
          Informasi identitas personel, kredensial autentikasi, serta konfigurasi PIN cepat 6-digit.
        </p>
      </div>
    </div>

    <!-- Main Grid: 2 Columns -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Col 1: Profile Card -->
      <div class="lg:col-span-1 space-y-6">
        <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-6 text-center relative overflow-hidden">
          <div class="absolute top-0 left-0 right-0 h-20 bg-gradient-to-r from-amber-500/20 to-blue-500/20 border-b border-slate-800"></div>

          <div class="relative pt-6">
            <div class="w-24 h-24 mx-auto rounded-3xl bg-gradient-to-tr from-amber-500 to-amber-300 p-1 shadow-2xl">
              <div class="w-full h-full rounded-[22px] bg-[#091122] flex items-center justify-center text-3xl font-black text-amber-400">
                M
              </div>
            </div>
            <h2 class="text-lg font-black text-white mt-4">Kombes Pol. M. Memo, S.I.K</h2>
            <p class="text-xs font-bold text-amber-400">Kasubbid Penmas Bidhumas</p>
            <span class="inline-block mt-2 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
              Akun Aktif & Terverifikasi
            </span>
          </div>

          <div class="border-t border-slate-800 pt-4 space-y-3 text-left text-xs">
            <div class="flex items-center justify-between">
              <span class="text-slate-400">Pangkat / Korps</span>
              <span class="font-bold text-white">KOMBES POL</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-slate-400">NRP</span>
              <span class="font-mono font-bold text-amber-400">74090123</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-slate-400">Satuan Kerja</span>
              <span class="font-bold text-white">Bidhumas Polda Sulbar</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-slate-400">Email Kedinasan</span>
              <span class="font-bold text-slate-300">memo.humas@polri.go.id</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-slate-400">Nomor WhatsApp</span>
              <span class="font-bold text-slate-300">+62 811-4200-XXXX</span>
            </div>
          </div>
        </div>

        <!-- Session Status -->
        <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-5 shadow-xl space-y-3">
          <h3 class="text-xs font-black text-white uppercase tracking-wider flex items-center gap-2">
            <Clock class="w-4 h-4 text-amber-400" />
            <span>Sesi Perangkat Aktif</span>
          </h3>
          <div class="p-3 rounded-2xl bg-slate-900 border border-slate-800 text-xs space-y-1">
            <div class="flex items-center justify-between">
              <span class="font-bold text-white">Windows 11 (Chrome Desktop)</span>
              <span class="text-[10px] font-bold text-emerald-400">Aktif</span>
            </div>
            <p class="text-[10px] text-slate-400">IP: 127.0.0.1 (Laragon Local) &bull; Waktu WITA</p>
          </div>
        </div>
      </div>

      <!-- Col 2: Settings (Password & PIN) -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Form Ganti Password -->
        <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
          <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <div class="flex items-center gap-2">
              <Lock class="w-4 h-4 text-amber-400" />
              <h3 class="text-sm font-black text-white uppercase tracking-wider">Perbarui Kata Sandi Akun</h3>
            </div>
            <span v-if="passNotification" class="text-xs font-bold text-emerald-400 flex items-center gap-1">
              <CheckCircle2 class="w-3.5 h-3.5" /> Kata sandi berhasil diperbarui!
            </span>
          </div>

          <div class="space-y-3 text-xs">
            <div>
              <label class="block font-bold text-slate-300 mb-1">Kata Sandi Saat Ini</label>
              <input
                v-model="formPassword.current"
                type="password"
                placeholder="••••••••••••"
                class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400"
              />
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block font-bold text-slate-300 mb-1">Kata Sandi Baru</label>
                <input
                  v-model="formPassword.new"
                  type="password"
                  placeholder="Min. 8 Karakter Kombinasi"
                  class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400"
                />
              </div>
              <div>
                <label class="block font-bold text-slate-300 mb-1">Konfirmasi Kata Sandi Baru</label>
                <input
                  v-model="formPassword.confirm"
                  type="password"
                  placeholder="Ulangi kata sandi baru"
                  class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400"
                />
              </div>
            </div>
          </div>

          <div class="flex justify-end pt-2">
            <button
              @click="updatePassword"
              class="flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-black text-xs transition"
            >
              <Save class="w-4 h-4" />
              <span>Simpan Kata Sandi</span>
            </button>
          </div>
        </div>

        <!-- Form Pengaturan PIN Cepat 6-Digit -->
        <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
          <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <div class="flex items-center gap-2">
              <Smartphone class="w-4 h-4 text-amber-400" />
              <div>
                <h3 class="text-sm font-black text-white uppercase tracking-wider">PIN Dinas Cepat 6-Digit</h3>
                <p class="text-[11px] text-slate-400">Digunakan untuk akses taktis dan input laporan cepat tanpa kata sandi panjang.</p>
              </div>
            </div>
            <span v-if="pinNotification" class="text-xs font-bold text-emerald-400 flex items-center gap-1">
              <CheckCircle2 class="w-3.5 h-3.5" /> PIN tersimpan!
            </span>
          </div>

          <div class="space-y-3 text-xs">
            <div class="max-w-xs">
              <label class="block font-bold text-slate-300 mb-1">PIN 6-Digit Anda</label>
              <input
                v-model="pinDinas"
                type="password"
                maxlength="6"
                placeholder="740901"
                class="w-full tracking-[0.5em] text-center text-lg font-mono font-black px-4 py-2 rounded-xl bg-slate-900 border border-slate-700 text-amber-400 focus:outline-none focus:border-amber-400"
              />
            </div>
            <p class="text-[11px] text-slate-400">
              PIN ini terenkripsi hash SHA-256 dan hanya berlaku untuk perangkat yang telah terdaftar pada database SIMOD Enterprise.
            </p>
          </div>

          <div class="flex justify-end pt-2">
            <button
              @click="updatePin"
              class="flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-black text-xs transition"
            >
              <Save class="w-4 h-4" />
              <span>Perbarui PIN Dinas</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </EnterpriseLayout>
</template>