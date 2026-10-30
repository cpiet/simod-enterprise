<script setup>
import SecurityMaintenanceTabs from '@/Components/SecurityMaintenanceTabs.vue';
import { ref } from 'vue';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import {
  Mail,
  Send,
  Save,
  CheckCircle2,
  Server,
  Lock,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  metrics: Object,
});

const isSaved = ref(false);
const testSent = ref(false);

const mail = ref({
  driver: 'smtp',
  host: 'smtp.polri.go.id',
  port: 587,
  encryption: 'tls',
  username: 'humas.sulbar@polri.go.id',
  password: '••••••••••••',
  from_name: 'SIMOD ENTERPRISE - BIDHUMAS POLDA SULBAR',
  from_address: 'noreply-humas@polri.go.id',
});

const saveMail = () => {
  isSaved.value = true;
  setTimeout(() => (isSaved.value = false), 3000);
};

const sendTest = () => {
  testSent.value = true;
  setTimeout(() => (testSent.value = false), 3500);
};
</script>

<template>
  <EnterpriseLayout
    :all-poldas="allPoldas"
    :selected-polda="selectedPolda"
    :selected-code="selectedCode"
    :metrics="metrics"
  >
    <SecurityMaintenanceTabs :selected-code="selectedCode" />

    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-400 text-slate-950">
            Komunikasi Dinas
          </span>
          <span class="text-xs font-bold text-slate-400">
            {{ selectedCode === 'ALL' ? 'Mabes Polri' : selectedPolda?.name }}
          </span>
        </div>
        <h1 class="text-2xl md:text-3xl font-black text-white tracking-tight mt-1">
          Pengaturan Mail Server (SMTP)
        </h1>
        <p class="text-xs md:text-sm text-slate-300">
          Konfigurasi gateway pengiriman email notifikasi siaga EWS dan pengesahan surat perintah tugas.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="sendTest"
          class="flex items-center gap-2 px-3 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-xs font-bold text-slate-200 transition"
        >
          <Send class="w-3.5 h-3.5 text-amber-400" />
          <span>{{ testSent ? 'Email Terkirim!' : 'Kirim Email Uji Coba' }}</span>
        </button>
        <button
          @click="saveMail"
          class="flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black transition shadow-lg shadow-amber-400/20"
        >
          <Save class="w-4 h-4" />
          <span>{{ isSaved ? 'Tersimpan!' : 'Simpan Konfigurasi' }}</span>
        </button>
      </div>
    </div>

    <!-- SMTP Form Card -->
    <div class="max-w-3xl rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
      <div class="flex items-center gap-2 border-b border-slate-800 pb-3">
        <Server class="w-4 h-4 text-amber-400" />
        <h3 class="text-sm font-black text-white uppercase tracking-wider">Parameter SMTP Kedinasan</h3>
      </div>

      <div class="space-y-4 text-xs">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div class="sm:col-span-2">
            <label class="block font-bold text-slate-300 mb-1">SMTP Host</label>
            <input v-model="mail.host" type="text" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white font-mono focus:outline-none focus:border-amber-400" />
          </div>
          <div>
            <label class="block font-bold text-slate-300 mb-1">Port</label>
            <input v-model="mail.port" type="number" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white font-mono focus:outline-none focus:border-amber-400" />
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-300 mb-1">Username / Akun Email</label>
            <input v-model="mail.username" type="text" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400" />
          </div>
          <div>
            <label class="block font-bold text-slate-300 mb-1">Kata Sandi SMTP</label>
            <input v-model="mail.password" type="password" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400" />
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-300 mb-1">Nama Pengirim (Sender Name)</label>
            <input v-model="mail.from_name" type="text" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400" />
          </div>
          <div>
            <label class="block font-bold text-slate-300 mb-1">Alamat Email Pengirim (Sender Address)</label>
            <input v-model="mail.from_address" type="text" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400" />
          </div>
        </div>
      </div>
    </div>
  </EnterpriseLayout>
</template>
