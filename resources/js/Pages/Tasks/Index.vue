<script setup>
import { ref, computed } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import { priorityMeta, taskStatusMeta, isOverdue, formatWitaDate } from '@/utils/issueFlow';
import {
  PlusCircle,
  CheckCircle2,
  Clock,
  Building2,
  User,
  X,
  MessageSquare,
  CheckSquare,
  AlertTriangle,
  ChevronDown,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  tasks: Object, // Paginated
  statusFilter: String,
  priorityFilter: { type: String, default: 'all' },
  metrics: Object,
});

const page = usePage();
const permissions = computed(() => page.props.auth?.permissions || {});
const canDispatch = computed(() => !!permissions.value.tasks_disposition);
const canReport = computed(() => !!permissions.value.tasks_execute || !!permissions.value.tasks_disposition);

const currentStatus = ref(props.statusFilter || 'all');
const currentPriority = ref(props.priorityFilter || 'all');

const TABS = [
  { key: 'all', label: 'Semua', count: () => props.metrics?.total || 0 },
  { key: 'in_progress', label: 'Berjalan', count: () => props.metrics?.in_progress || 0 },
  { key: 'completed', label: 'Selesai', count: () => props.metrics?.completed || 0 },
];

const load = () => {
  router.get('/tasks', { status: currentStatus.value, priority: currentPriority.value }, { preserveState: true, preserveScroll: true });
};
const setStatus = (key) => { currentStatus.value = key; load(); };
const toggleUrgent = () => { currentPriority.value = currentPriority.value === 'urgent' ? 'all' : 'urgent'; load(); };

// Buat disposisi
const emptyForm = () => ({
  polda_id: props.selectedPolda?.id || 1,
  title: '',
  instructions: '',
  assigned_unit: 'Subdit V Siber Ditreskrimsus',
  assigned_officer: '',
  priority: 'high',
  due_at: '',
});
const isCreateModalOpen = ref(false);
const taskForm = ref(emptyForm());
const isSubmitting = ref(false);

const submitTask = () => {
  isSubmitting.value = true;
  router.post('/tasks', taskForm.value, {
    preserveScroll: true,
    onSuccess: () => { isSubmitting.value = false; isCreateModalOpen.value = false; taskForm.value = emptyForm(); },
    onError: () => { isSubmitting.value = false; },
  });
};

// Input progres / selesaikan
const isUpdateModalOpen = ref(false);
const activeTaskForUpdate = ref(null);
const updateNote = ref('');
const markCompleted = ref(false);

const openUpdateModal = (task) => {
  activeTaskForUpdate.value = task;
  updateNote.value = '';
  markCompleted.value = false;
  isUpdateModalOpen.value = true;
};

const submitUpdate = () => {
  if (!activeTaskForUpdate.value) return;
  isSubmitting.value = true;
  router.post('/tasks/' + activeTaskForUpdate.value.id + '/update', {
    note: updateNote.value,
    mark_completed: markCompleted.value,
  }, {
    preserveScroll: true,
    onSuccess: () => { isSubmitting.value = false; isUpdateModalOpen.value = false; },
    onError: () => { isSubmitting.value = false; },
  });
};

// Riwayat progres dilipat: tampilkan yang terbaru saja, sisanya atas permintaan
const expanded = ref({});
const latestUpdates = (t) => (t.updates || []).slice().sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
const visibleUpdates = (t) => (expanded.value[t.id] ? latestUpdates(t) : latestUpdates(t).slice(0, 1));
</script>

<template>
  <EnterpriseLayout
    :all-poldas="allPoldas"
    :selected-polda="selectedPolda"
    :selected-code="selectedCode"
    :metrics="metrics"
  >
    <!-- Header ringkas -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div class="min-w-0">
        <p class="text-xs font-bold text-slate-400">{{ selectedCode === 'ALL' ? 'Mabes Polri (Nasional)' : selectedPolda?.name }}</p>
        <h1 class="text-2xl md:text-3xl font-black text-white tracking-tight">Disposisi Tugas</h1>
      </div>

      <button
        v-if="canDispatch"
        @click="isCreateModalOpen = true"
        class="px-4 py-2.5 rounded-xl text-xs font-black bg-gradient-to-r from-amber-500 to-amber-400 text-slate-950 hover:brightness-110 shadow-lg shadow-amber-400/20 flex items-center gap-2 cursor-pointer transition shrink-0"
      >
        <PlusCircle class="w-4 h-4" />
        <span>Buat Disposisi</span>
      </button>
    </div>

    <!-- Tab status + hitungan (menggantikan 4 kartu KPI) -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1">
      <button
        v-for="t in TABS"
        :key="t.key"
        type="button"
        @click="setStatus(t.key)"
        class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 cursor-pointer select-none whitespace-nowrap border"
        :class="currentStatus === t.key
          ? 'bg-amber-400 text-slate-950 font-black border-amber-300 shadow-md'
          : 'bg-slate-900 text-slate-300 hover:text-white hover:bg-slate-800 border-slate-700'"
      >
        <span>{{ t.label }}</span>
        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono" :class="currentStatus === t.key ? 'bg-slate-950 text-amber-300 font-black' : 'bg-slate-800 text-slate-300 font-bold'">{{ t.count() }}</span>
      </button>

      <span class="h-5 w-px bg-slate-700 shrink-0"></span>

      <button
        type="button"
        @click="toggleUrgent"
        class="px-3.5 py-2 rounded-xl text-xs font-black transition flex items-center gap-1.5 cursor-pointer select-none whitespace-nowrap border"
        :class="currentPriority === 'urgent' ? 'bg-rose-600 text-white border-rose-500 shadow-md' : 'bg-slate-900 text-rose-400 border-rose-900/60 hover:bg-rose-950/30'"
      >
        <AlertTriangle class="w-3.5 h-3.5" />
        <span>Mendesak ({{ metrics?.urgent || 0 }})</span>
      </button>
    </div>

    <!-- Daftar tugas -->
    <div v-if="tasks?.data?.length > 0" class="space-y-3">
      <div
        v-for="t in tasks.data"
        :key="t.id"
        class="rounded-2xl bg-[#0b1426] border border-slate-800/90 p-4 md:p-5 shadow-lg space-y-3 hover:border-slate-700 transition"
      >
        <div class="flex flex-wrap items-center gap-2">
          <span class="px-2 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-wider" :class="priorityMeta(t.priority).cls">{{ priorityMeta(t.priority).label }}</span>
          <span class="px-2 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-wider" :class="taskStatusMeta(t.status).cls">{{ taskStatusMeta(t.status).label }}</span>
          <span v-if="isOverdue(t)" class="px-2 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-wider bg-rose-600 text-white">Lewat tenggat</span>

          <span class="ml-auto text-[11px] text-slate-400 font-mono flex items-center gap-1">
            <Clock class="w-3.5 h-3.5 text-amber-400" />
            Tenggat: <strong :class="isOverdue(t) ? 'text-rose-400' : 'text-amber-400'">{{ t.due_at ? formatWitaDate(t.due_at) : 'Tidak ditentukan' }}</strong>
          </span>
        </div>

        <div class="space-y-1">
          <h3 class="text-base font-black text-white leading-snug">{{ t.title }}</h3>
          <p class="text-xs text-slate-300 leading-relaxed whitespace-pre-line line-clamp-3">{{ t.instructions }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-x-5 gap-y-1 text-[11px] text-slate-400">
          <span class="flex items-center gap-1.5"><Building2 class="w-3.5 h-3.5 text-sky-400" /> <strong class="text-slate-200">{{ t.assigned_unit }}</strong></span>
          <span class="flex items-center gap-1.5"><User class="w-3.5 h-3.5 text-amber-400" /> <strong class="text-slate-200">{{ t.assigned_officer || 'Seluruh tim piket' }}</strong></span>
          <Link v-if="t.issue" :href="'/issues/' + t.issue.id" class="flex items-center gap-1.5 text-sky-400 hover:underline">
            <CheckSquare class="w-3.5 h-3.5" /> Laporan {{ t.issue.report_code || 'SIM-' + t.issue.id }}
          </Link>
        </div>

        <!-- Progres terbaru (lipat) -->
        <div v-if="t.updates && t.updates.length > 0" class="p-3 rounded-xl bg-slate-900/80 border border-slate-800 space-y-1.5">
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-black text-sky-400">Laporan perkembangan ({{ t.updates.length }})</span>
            <button
              v-if="t.updates.length > 1"
              type="button"
              @click="expanded[t.id] = !expanded[t.id]"
              class="text-[11px] font-bold text-slate-400 hover:text-white flex items-center gap-1 cursor-pointer"
            >
              <span>{{ expanded[t.id] ? 'Ringkas' : 'Lihat semua' }}</span>
              <ChevronDown class="w-3 h-3 transition-transform" :class="expanded[t.id] ? 'rotate-180' : ''" />
            </button>
          </div>
          <div v-for="up in visibleUpdates(t)" :key="up.id" class="text-xs text-slate-200 border-l-2 border-sky-500 pl-3 py-0.5">
            <p>{{ up.note }}</p>
            <span class="text-[10px] text-slate-400 font-mono">{{ formatWitaDate(up.created_at) }}</span>
          </div>
        </div>

        <div class="pt-2.5 border-t border-slate-800/80 flex items-center justify-between gap-3">
          <span class="text-[11px] text-slate-500">Dibuat {{ formatWitaDate(t.created_at) }}</span>
          <button
            v-if="t.status !== 'completed' && canReport"
            @click="openUpdateModal(t)"
            class="px-3.5 py-1.5 rounded-xl bg-sky-500/20 hover:bg-sky-500/30 text-sky-300 border border-sky-500/40 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer"
          >
            <MessageSquare class="w-3.5 h-3.5" />
            <span>Input Progres / Selesaikan</span>
          </button>
          <span v-else-if="t.status === 'completed'" class="text-xs font-bold text-emerald-400 flex items-center gap-1">
            <CheckCircle2 class="w-4 h-4" /> Selesai
          </span>
        </div>
      </div>

      <!-- Paginasi -->
      <div v-if="tasks?.links && tasks.links.length > 3" class="flex items-center justify-between gap-3 p-3 rounded-2xl bg-[#0b1426] border border-slate-800">
        <p class="text-xs text-slate-400">Halaman {{ tasks.current_page }} dari {{ tasks.last_page }} &bull; {{ tasks.total }} tugas</p>
        <div class="flex items-center gap-1 flex-wrap">
          <template v-for="(link, idx) in tasks.links" :key="idx">
            <Link
              v-if="link.url"
              :href="link.url"
              class="px-3 py-1.5 rounded-xl text-xs font-bold transition"
              :class="link.active ? 'bg-amber-400 text-slate-950 font-black' : 'bg-slate-900 text-slate-300 hover:bg-slate-800 border border-slate-700'"
              v-html="link.label"
            />
            <span v-else class="px-3 py-1.5 rounded-xl text-xs font-bold text-slate-600 bg-slate-950 border border-slate-900 select-none" v-html="link.label" />
          </template>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="rounded-3xl bg-[#0b1426] border border-slate-800 p-12 text-center space-y-3">
      <CheckSquare class="w-12 h-12 mx-auto text-slate-500" />
      <h3 class="text-base font-bold text-white">Belum ada tugas pada tampilan ini</h3>
      <p class="text-xs text-slate-400 max-w-sm mx-auto">Ubah tab di atas{{ canDispatch ? ', atau terbitkan disposisi baru.' : '.' }}</p>
    </div>

    <!-- Modal buat tugas -->
    <div v-if="isCreateModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
      <div class="max-w-xl w-full rounded-3xl bg-[#0d182e] border border-slate-700 p-6 sm:p-8 space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div>
            <h3 class="text-base font-extrabold text-white">Disposisi Tugas Baru</h3>
            <p class="text-[11px] text-slate-400">Kirim instruksi ke Satker / personel</p>
          </div>
          <button @click="isCreateModalOpen = false" class="text-slate-400 hover:text-white p-1 cursor-pointer"><X class="w-5 h-5" /></button>
        </div>

        <form @submit.prevent="submitTask" class="space-y-4">
          <label class="block">
            <span class="block text-xs font-bold text-slate-300 mb-1">Judul tugas</span>
            <input v-model="taskForm.title" type="text" required placeholder="Contoh: Patroli siber akun provokatif" class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs font-semibold text-white focus:outline-none focus:border-amber-400" />
          </label>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <label class="block">
              <span class="block text-xs font-bold text-slate-300 mb-1">Satker pelaksana</span>
              <input v-model="taskForm.assigned_unit" type="text" required class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs text-white focus:outline-none focus:border-amber-400" />
            </label>
            <label class="block">
              <span class="block text-xs font-bold text-slate-300 mb-1">PIC / personel (opsional)</span>
              <input v-model="taskForm.assigned_officer" type="text" placeholder="Contoh: Ipda Wahyu Pratama" class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs text-white focus:outline-none focus:border-amber-400" />
            </label>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <label class="block">
              <span class="block text-xs font-bold text-slate-300 mb-1">Prioritas</span>
              <select v-model="taskForm.priority" class="w-full px-3 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs font-bold text-white focus:border-amber-400 outline-none">
                <option value="urgent">Mendesak</option>
                <option value="high">Tinggi</option>
                <option value="medium">Sedang</option>
                <option value="low">Rendah</option>
              </select>
            </label>
            <label class="block">
              <span class="block text-xs font-bold text-slate-300 mb-1">Tenggat</span>
              <input v-model="taskForm.due_at" type="datetime-local" class="w-full px-3 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs text-white focus:outline-none focus:border-amber-400" />
            </label>
          </div>

          <label class="block">
            <span class="block text-xs font-bold text-slate-300 mb-1">Instruksi &amp; arahan teknis</span>
            <textarea v-model="taskForm.instructions" rows="4" required placeholder="Uraikan detail penugasan yang wajib dipenuhi..." class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs text-white focus:outline-none focus:border-amber-400"></textarea>
          </label>

          <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
            <button type="button" @click="isCreateModalOpen = false" class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-800 text-slate-300 hover:text-white border border-slate-700 transition cursor-pointer">Batal</button>
            <button type="submit" :disabled="isSubmitting" class="px-5 py-2.5 rounded-xl text-xs font-black bg-amber-400 text-slate-950 hover:bg-amber-300 shadow-md transition disabled:opacity-50 cursor-pointer">
              {{ isSubmitting ? 'Mengirim...' : 'Kirim Disposisi' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal input progres -->
    <div v-if="isUpdateModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
      <div class="max-w-lg w-full rounded-3xl bg-[#0d182e] border border-slate-700 p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <h3 class="text-base font-extrabold text-white">Laporan Progres Tugas</h3>
          <button @click="isUpdateModalOpen = false" class="text-slate-400 hover:text-white p-1 cursor-pointer"><X class="w-5 h-5" /></button>
        </div>

        <form @submit.prevent="submitUpdate" class="space-y-4">
          <label class="block">
            <span class="block text-xs font-bold text-slate-300 mb-1">Catatan perkembangan lapangan</span>
            <textarea v-model="updateNote" rows="4" required placeholder="Uraikan hasil pengerjaan, profiling, atau status koordinasi..." class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs text-white focus:outline-none focus:border-amber-400"></textarea>
          </label>

          <label class="flex items-center gap-2 text-xs font-bold text-slate-200 cursor-pointer">
            <input v-model="markCompleted" type="checkbox" class="w-4 h-4 rounded text-emerald-500 focus:ring-emerald-500 bg-slate-800 border-slate-700" />
            <span>Tandai tugas selesai 100%</span>
          </label>

          <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
            <button type="button" @click="isUpdateModalOpen = false" class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-800 text-slate-300 hover:text-white border border-slate-700 transition cursor-pointer">Batal</button>
            <button type="submit" :disabled="isSubmitting" class="px-5 py-2.5 rounded-xl text-xs font-black bg-emerald-500 text-slate-950 hover:bg-emerald-400 shadow-md transition disabled:opacity-50 cursor-pointer">
              {{ isSubmitting ? 'Menyimpan...' : 'Simpan Laporan' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </EnterpriseLayout>
</template>
