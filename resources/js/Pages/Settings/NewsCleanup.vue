<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import SecurityMaintenanceTabs from '@/Components/SecurityMaintenanceTabs.vue';
import { Eraser, Bookmark, Database, AlertTriangle, Trash2, Info } from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  metrics: Object,
  perPolda: Array,
  days: Number,
  target: String,
  preview: Number,
  retentionDays: Number,
});

const days = ref(props.days);
const target = ref(props.target);
const isDeleting = ref(false);

const dayOptions = [
  { v: 7, t: 'Lebih dari 7 hari' },
  { v: 30, t: 'Lebih dari 30 hari' },
  { v: 90, t: 'Lebih dari 90 hari' },
  { v: 180, t: 'Lebih dari 180 hari' },
  { v: 365, t: 'Lebih dari 1 tahun' },
  { v: 0, t: 'Semua (kecuali bookmark)' },
];

const refreshPreview = () => {
  router.get('/maintenance/news-cleanup', { polda: props.selectedCode, days: days.value, target: target.value }, {
    preserveScroll: true,
    preserveState: true,
    only: ['perPolda', 'preview', 'days', 'target'],
  });
};

const targetLabel = () => (target.value === 'ALL' ? 'SEMUA Polda' : props.perPolda?.find((p) => p.code === target.value)?.name || target.value);

const purge = () => {
  if (!props.preview) return;
  const rule = days.value === 0 ? 'semua berita' : `berita lebih dari ${days.value} hari`;
  if (!confirm(`Hapus ${props.preview} ${rule} (non-bookmark) milik ${targetLabel()}?\n\nBookmark tidak akan dihapus. Tindakan ini tidak dapat dibatalkan.`)) return;
  isDeleting.value = true;
  router.post('/maintenance/news-cleanup', { days: days.value, target: target.value }, {
    preserveScroll: true,
    onFinish: () => (isDeleting.value = false),
  });
};
</script>

<template>
  <EnterpriseLayout :all-poldas="allPoldas" :selected-polda="selectedPolda" :selected-code="selectedCode" :metrics="metrics">
    <SecurityMaintenanceTabs :selected-code="selectedCode" />

    <div class="rounded-3xl bg-gradient-to-r from-[#0d182e] via-[#0b1426] to-[#070d1d] border border-slate-800 p-6 shadow-xl space-y-1">
      <h1 class="text-xl font-black text-white flex items-center gap-2">
        <Eraser class="w-5 h-5 text-amber-400" /> Pembersihan Berita
      </h1>
      <p class="text-xs text-slate-300 max-w-3xl leading-relaxed">
        Hapus artikel hasil agregasi RSS yang sudah lama agar database tetap ringan. Berita yang sudah di-<strong>bookmark</strong> tidak pernah ikut terhapus.
      </p>
      <p class="text-[11px] text-slate-400 flex items-center gap-1.5 pt-1">
        <Info class="w-3.5 h-3.5 text-sky-400" />
        <template v-if="retentionDays > 0">Pembersihan otomatis aktif: setiap hari 02.00, artikel non-bookmark &gt; {{ retentionDays }} hari dihapus (atur <code>MEDIA_RETENTION_DAYS</code> di .env, 0 = nonaktif; perlu <code>schedule:run</code> berjalan).</template>
        <template v-else>Pembersihan otomatis nonaktif (MEDIA_RETENTION_DAYS=0).</template>
      </p>
    </div>

    <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
      <h2 class="text-sm font-black text-white uppercase tracking-wider">Bersihkan Sekarang</h2>
      <div class="flex flex-wrap items-end gap-3 text-xs">
        <div>
          <label class="block font-bold text-slate-300 mb-1">Polda</label>
          <select v-model="target" @change="refreshPreview" class="px-3 py-2 rounded-xl bg-[#070d19] border border-slate-700 text-white font-bold">
            <option value="ALL">Semua Polda</option>
            <option v-for="p in perPolda" :key="p.code" :value="p.code">{{ p.name }}</option>
          </select>
        </div>
        <div>
          <label class="block font-bold text-slate-300 mb-1">Umur berita</label>
          <select v-model.number="days" @change="refreshPreview" class="px-3 py-2 rounded-xl bg-[#070d19] border border-slate-700 text-white font-bold">
            <option v-for="o in dayOptions" :key="o.v" :value="o.v">{{ o.t }}</option>
          </select>
        </div>
        <div class="px-4 py-2 rounded-xl bg-amber-400/10 border border-amber-400/40 text-amber-300 font-bold">
          Akan dihapus: <span class="text-white font-black text-sm">{{ preview }}</span> berita
        </div>
        <button
          @click="purge"
          :disabled="!preview || isDeleting"
          class="flex items-center gap-2 px-4 py-2 rounded-xl bg-rose-500/20 hover:bg-rose-500/30 border border-rose-500/50 text-rose-300 font-black disabled:opacity-40"
        >
          <Trash2 class="w-4 h-4" />
          <span>{{ isDeleting ? 'Membersihkan...' : 'Bersihkan Sekarang' }}</span>
        </button>
      </div>
      <p class="text-[11px] text-slate-400 flex items-center gap-1.5">
        <AlertTriangle class="w-3.5 h-3.5 text-amber-400" /> Tindakan tidak dapat dibatalkan dan dicatat di Audit Log. Disarankan Backup Data terlebih dahulu.
      </p>
    </div>

    <div class="rounded-3xl bg-[#0b1426] border border-slate-800 overflow-hidden shadow-xl">
      <div class="px-5 py-3 bg-slate-900/80 border-b border-slate-800 text-[11px] font-black uppercase text-slate-400 flex items-center gap-2">
        <Database class="w-4 h-4 text-sky-400" /> Isi Database per Polda
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-xs">
          <thead>
            <tr class="text-left text-slate-400 border-b border-slate-800">
              <th class="px-5 py-2.5 font-bold">Polda</th>
              <th class="px-5 py-2.5 font-bold text-right">Total Berita</th>
              <th class="px-5 py-2.5 font-bold text-right"><Bookmark class="w-3 h-3 inline text-amber-400" /> Bookmark</th>
              <th class="px-5 py-2.5 font-bold text-right">Bisa Dibersihkan</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <tr v-for="p in perPolda" :key="p.code" class="hover:bg-slate-900/40">
              <td class="px-5 py-2.5 font-bold text-white">{{ p.name }}</td>
              <td class="px-5 py-2.5 text-right text-slate-200">{{ p.total }}</td>
              <td class="px-5 py-2.5 text-right text-amber-300">{{ p.bookmarked }}</td>
              <td class="px-5 py-2.5 text-right text-rose-300 font-bold">{{ p.deletable }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </EnterpriseLayout>
</template>
