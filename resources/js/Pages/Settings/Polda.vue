<script setup>
import { ref } from 'vue';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import {
  Building2,
  Shield,
  Save,
  Plus,
  MapPin,
  Clock,
  Phone,
  CheckCircle2,
  Users,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  polresList: Array,
  metrics: Object,
});

const isSaved = ref(false);

const form = ref({
  code: props.selectedPolda?.code || 'SULBAR',
  name: props.selectedPolda?.name || 'Polda Sulawesi Barat',
  timezone: props.selectedPolda?.timezone || 'Asia/Makassar',
  address: 'Jl. Aiptu Nurman No. 1, Rimuku, Kec. Mamuju, Kabupaten Mamuju, Sulawesi Barat 91511',
  kapolda: 'Irjen Pol. Drs. R. Adang Ginanjar S., M.M.',
  kabid_humas: 'Kombes Pol. Slamet Wahyudi, S.I.K., M.H.',
  call_center: '110 / (0426) 2321110',
});

const polresData = ref([
  { id: 1, name: 'Polresta Mamuju', code: 'MAMUJU', head: 'Kombes Pol. Iskandar, S.I.K.', kasihumas: 'Aipda Hendra', status: 'AKTIF', staff: 12 },
  { id: 2, name: 'Polres Majene', code: 'MAJENE', head: 'AKBP Toni Sugadri, S.I.K.', kasihumas: 'Bripka Ramli', status: 'AKTIF', staff: 8 },
  { id: 3, name: 'Polres Polewali Mandar', code: 'POLMAN', head: 'AKBP Anjar Purwoko, S.H.', kasihumas: 'Ipda Muh. Firdaus', status: 'AKTIF', staff: 10 },
  { id: 4, name: 'Polres Mamasa', code: 'MAMASA', head: 'AKBP Muhammad Amiruddin', kasihumas: 'Bripka Daniel', status: 'AKTIF', staff: 6 },
  { id: 5, name: 'Polres Pasangkayu', code: 'PASANGKAYU', head: 'AKBP Candra Kurniawan', kasihumas: 'Aipda Usman', status: 'AKTIF', staff: 7 },
  { id: 6, name: 'Polres Mamuju Tengah', code: 'MATENG', head: 'AKBP Hengky Kristanto', kasihumas: 'Briptu Akbar', status: 'AKTIF', staff: 5 },
]);

const saveSettings = () => {
  isSaved.value = true;
  setTimeout(() => (isSaved.value = false), 3000);
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
            Konfigurasi Satker
          </span>
          <span class="text-xs font-bold text-slate-400">
            {{ selectedCode === 'ALL' ? 'Mabes Polri' : selectedPolda?.name }}
          </span>
        </div>
        <h1 class="text-2xl md:text-3xl font-black text-white tracking-tight mt-1">
          Pengaturan Polda & Struktur Satwil
        </h1>
        <p class="text-xs md:text-sm text-slate-300">
          Identitas satuan wilayah, data pimpinan, zona waktu operasi, dan koordinasi 6 Polres jajaran.
        </p>
      </div>

      <button
        @click="saveSettings"
        class="flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black transition shadow-lg shadow-amber-400/20"
      >
        <Save class="w-4 h-4" />
        <span>{{ isSaved ? 'Perubahan Disimpan!' : 'Simpan Pengaturan' }}</span>
      </button>
    </div>

    <!-- 2 Column Settings Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Identitas Polda -->
      <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
        <div class="flex items-center gap-2 border-b border-slate-800 pb-3">
          <Building2 class="w-4 h-4 text-amber-400" />
          <h3 class="text-sm font-black text-white uppercase tracking-wider">Identitas Satuan Wilayah</h3>
        </div>

        <div class="space-y-3 text-xs">
          <div class="grid grid-cols-3 gap-3">
            <div class="col-span-1">
              <label class="block font-bold text-slate-300 mb-1">Kode Satwil</label>
              <input v-model="form.code" type="text" disabled class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-amber-400 font-mono font-bold" />
            </div>
            <div class="col-span-2">
              <label class="block font-bold text-slate-300 mb-1">Nama Satker Resmi</label>
              <input v-model="form.name" type="text" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400" />
            </div>
          </div>

          <div>
            <label class="block font-bold text-slate-300 mb-1">Zona Waktu Operasional</label>
            <input v-model="form.timezone" type="text" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400" />
          </div>

          <div>
            <label class="block font-bold text-slate-300 mb-1">Alamat Markas Komando (Mapolda)</label>
            <textarea v-model="form.address" rows="3" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400"></textarea>
          </div>
        </div>
      </div>

      <!-- Data Pejabat Utama -->
      <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
        <div class="flex items-center gap-2 border-b border-slate-800 pb-3">
          <Shield class="w-4 h-4 text-amber-400" />
          <h3 class="text-sm font-black text-white uppercase tracking-wider">Pejabat Utama & Kontak Siaga</h3>
        </div>

        <div class="space-y-3 text-xs">
          <div>
            <label class="block font-bold text-slate-300 mb-1">Nama & Gelar Kapolda</label>
            <input v-model="form.kapolda" type="text" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400" />
          </div>

          <div>
            <label class="block font-bold text-slate-300 mb-1">Nama & Gelar Kabid Humas</label>
            <input v-model="form.kabid_humas" type="text" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400" />
          </div>

          <div>
            <label class="block font-bold text-slate-300 mb-1">Call Center Piket Humas</label>
            <input v-model="form.call_center" type="text" class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400" />
          </div>
        </div>
      </div>
    </div>

    <!-- Daftar Satwil Polres Jajaran -->
    <div class="rounded-3xl bg-[#0b1426] border border-slate-800 shadow-2xl overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
        <div>
          <h3 class="text-sm font-black text-white uppercase tracking-wider">Struktur Polres Jajaran (6 Satwil)</h3>
          <p class="text-xs text-slate-400">Wilayah hukum di bawah koordinasi operasional Polda Sulawesi Barat.</p>
        </div>
        <button class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-xs font-bold text-amber-400">
          <Plus class="w-3.5 h-3.5" />
          <span>Tambah Polres</span>
        </button>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="border-b border-slate-800 bg-slate-900/60 text-[11px] font-black uppercase text-slate-400">
              <th class="px-6 py-3.5">Nama Satwil</th>
              <th class="px-6 py-3.5">Kode</th>
              <th class="px-6 py-3.5">Kapolres</th>
              <th class="px-6 py-3.5">Kasi Humas</th>
              <th class="px-6 py-3.5">Personel</th>
              <th class="px-6 py-3.5">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800 text-xs">
            <tr v-for="polres in polresData" :key="polres.id" class="hover:bg-slate-900/40 transition">
              <td class="px-6 py-4 font-bold text-white">{{ polres.name }}</td>
              <td class="px-6 py-4 font-mono font-bold text-amber-400">{{ polres.code }}</td>
              <td class="px-6 py-4 text-slate-300">{{ polres.head }}</td>
              <td class="px-6 py-4 text-slate-300">{{ polres.kasihumas }}</td>
              <td class="px-6 py-4 text-slate-300">{{ polres.staff }} Personel</td>
              <td class="px-6 py-4">
                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                  {{ polres.status }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </EnterpriseLayout>
</template>