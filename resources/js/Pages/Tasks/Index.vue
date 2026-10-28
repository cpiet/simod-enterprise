<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import {
  CheckSquare,
  PlusCircle,
  Clock,
  CheckCircle2,
  AlertTriangle,
  User,
  Building2,
  X,
  MessageSquare,
  Send,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  tasks: Object, // Paginated
  statusFilter: String,
  metrics: Object,
});

const isCreateModalOpen = ref(false);
const taskForm = ref({
  polda_id: props.selectedPolda?.id || 1,
  title: '',
  instructions: '',
  assigned_unit: 'Subdit V Siber Ditreskrimsus',
  assigned_officer: '',
  priority: 'high',
  due_at: '',
});

const isSubmitting = ref(false);

const handleStatusFilter = (status) => {
  router.get('/tasks', {
    polda: props.selectedCode,
    status: status,
  }, { preserveState: true, preserveScroll: true });
};

const submitTask = () => {
  isSubmitting.value = true;
  router.post('/tasks', taskForm.value, {
    preserveScroll: true,
    onSuccess: () => {
      isSubmitting.value = false;
      isCreateModalOpen.value = false;
      taskForm.value = {
        polda_id: props.selectedPolda?.id || 1,
        title: '',
        instructions: '',
        assigned_unit: 'Subdit V Siber Ditreskrimsus',
        assigned_officer: '',
        priority: 'high',
        due_at: '',
      };
    },
    onError: () => {
      isSubmitting.value = false;
    },
  });
};

// Update Modal State
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
  router.post(`/tasks/${activeTaskForUpdate.value.id}/update`, {
    note: updateNote.value,
    mark_completed: markCompleted.value,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      isSubmitting.value = false;
      isUpdateModalOpen.value = false;
    },
    onError: () => {
      isSubmitting.value = false;
    },
  });
};
</script>

<template>
  <EnterpriseLayout
    :all-poldas="allPoldas"
    :selected-polda="selectedPolda"
    :selected-code="selectedCode"
    :metrics="metrics"
  >
    <!-- Top Action Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-500/20 text-emerald-400 border border-emerald-500/40">
            Disposisi Operasional
          </span>
          <span class="text-xs font-bold text-slate-400">
            {{ selectedCode === 'ALL' ? 'Mabes Polri (Nasional)' : selectedPolda?.name }}
          </span>
        </div>
        <h1 class="text-2xl md:text-3xl font-black text-white tracking-tight mt-1">
          Agenda & Disposisi Tugas Personel
        </h1>
        <p class="text-xs md:text-sm text-slate-300">
          Penugasan taktis, instruksi investigasi siber, peliputan lapangan, dan monitoring laporan perkembangan tugas.
        </p>
      </div>

      <div class="flex items-center gap-3">
        <button
          @click="isCreateModalOpen = true"
          class="px-4 py-2.5 rounded-xl text-xs font-black bg-gradient-to-r from-amber-500 to-amber-400 text-slate-950 hover:brightness-110 shadow-lg shadow-amber-400/20 flex items-center gap-2 cursor-pointer transition"
        >
          <PlusCircle class="w-4 h-4" />
          <span>+ Buat Disposisi Tugas</span>
        </button>
      </div>
    </div>

    <!-- Metrics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
        <span class="text-xs font-bold text-slate-400">Total Tugas</span>
        <p class="text-3xl font-black text-white">{{ metrics.total || 0 }}</p>
        <p class="text-[11px] text-slate-400">Semua riwayat</p>
      </div>
      <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
        <span class="text-xs font-bold text-sky-400">Sedang Berjalan</span>
        <p class="text-3xl font-black text-sky-400">{{ metrics.in_progress || 0 }}</p>
        <p class="text-[11px] text-slate-400">Dalam pengerjaan personel</p>
      </div>
      <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
        <span class="text-xs font-bold text-rose-400">Prioritas Mendesak</span>
        <p class="text-3xl font-black text-rose-400">{{ metrics.urgent || 0 }}</p>
        <p class="text-[11px] text-slate-400">Wajib selesai segera</p>
      </div>
      <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
        <span class="text-xs font-bold text-emerald-400">Tugas Selesai</span>
        <p class="text-3xl font-black text-emerald-400">{{ metrics.completed || 0 }}</p>
        <p class="text-[11px] text-slate-400">Laporan diterima</p>
      </div>
    </div>

    <!-- Tasks List Cards -->
    <div v-if="tasks?.data?.length > 0" class="space-y-4">
      <div
        v-for="t in tasks.data"
        :key="t.id"
        class="rounded-3xl bg-[#0b1426] border border-slate-800/90 p-6 shadow-xl space-y-4 hover:border-slate-700 transition"
      >
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 border-b border-slate-800/80 pb-3">
          <div class="flex items-center gap-2">
            <span
              class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider"
              :class="{
                'bg-rose-500 text-white': t.priority === 'urgent',
                'bg-amber-400 text-slate-950 font-black': t.priority === 'high',
                'bg-slate-800 text-slate-300': t.priority === 'medium' || t.priority === 'low',
              }"
            >
              {{ t.priority }}
            </span>
            <span
              class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase"
              :class="t.status === 'completed' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-sky-500/20 text-sky-400'"
            >
              Status: {{ t.status }}
            </span>
          </div>

          <div class="text-xs text-slate-400 font-mono">
            Batas Waktu: <strong class="text-amber-400">{{ t.due_at ? new Date(t.due_at).toLocaleString('id-ID') : 'Tidak ditentukan' }}</strong>
          </div>
        </div>

        <div class="space-y-2">
          <h3 class="text-base md:text-lg font-black text-white leading-snug">
            {{ t.title }}
          </h3>
          <p class="text-xs text-slate-300 leading-relaxed whitespace-pre-line">
            {{ t.instructions }}
          </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs pt-1">
          <div class="p-3 rounded-2xl bg-slate-900 border border-slate-800">
            <span class="text-[10px] text-slate-400 block font-bold">Satker Penanggung Jawab:</span>
            <span class="font-bold text-white">{{ t.assigned_unit }}</span>
          </div>
          <div class="p-3 rounded-2xl bg-slate-900 border border-slate-800">
            <span class="text-[10px] text-slate-400 block font-bold">Personel Pelaksana:</span>
            <span class="font-bold text-amber-400">{{ t.assigned_officer || 'Seluruh Tim Piket' }}</span>
          </div>
        </div>

        <!-- Task Updates Timeline -->
        <div v-if="t.updates && t.updates.length > 0" class="p-4 rounded-2xl bg-slate-900/80 border border-slate-800 space-y-2">
          <span class="text-[11px] font-black text-sky-400 block">Laporan Perkembangan Terkini:</span>
          <div v-for="up in t.updates" :key="up.id" class="text-xs text-slate-200 border-l-2 border-sky-500 pl-3 py-0.5">
            <p>{{ up.note }}</p>
            <span class="text-[10px] text-slate-400 font-mono">{{ new Date(up.created_at).toLocaleString('id-ID') }}</span>
          </div>
        </div>

        <!-- Action Button -->
        <div class="pt-3 border-t border-slate-800/80 flex items-center justify-between">
          <span class="text-[11px] text-slate-400">
            Dibuat: {{ new Date(t.created_at).toLocaleString('id-ID') }}
          </span>
          <button
            v-if="t.status !== 'completed'"
            @click="openUpdateModal(t)"
            class="px-3.5 py-1.5 rounded-xl bg-sky-500/20 hover:bg-sky-500/30 text-sky-400 border border-sky-500/40 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer"
          >
            <MessageSquare class="w-3.5 h-3.5" />
            <span>+ Input Progres / Selesaikan</span>
          </button>
          <span v-else class="text-xs font-bold text-emerald-400 flex items-center gap-1">
            <CheckCircle2 class="w-4 h-4" /> Tugas Telah Selesai
          </span>
        </div>
      </div>
    </div>

    <!-- MODAL BUAT TUGAS BARU -->
    <div
      v-if="isCreateModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm"
    >
      <div class="max-w-xl w-full rounded-3xl bg-[#0d182e] border border-slate-700 p-6 sm:p-8 space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold">
              +
            </div>
            <div>
              <h3 class="text-base font-extrabold text-white">Disposisi Tugas Personel Baru</h3>
              <p class="text-[11px] text-slate-400">Kirim Instruksi Taktis ke Satker / Personel</p>
            </div>
          </div>
          <button @click="isCreateModalOpen = false" class="text-slate-400 hover:text-white p-1">
            <X class="w-5 h-5" />
          </button>
        </div>

        <form @submit.prevent="submitTask" class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1">Judul Tugas / Instruksi</label>
            <input
              v-model="taskForm.title"
              type="text"
              required
              placeholder="Contoh: Patroli Siber Akun Provokatif..."
              class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs font-semibold text-white focus:outline-none focus:border-amber-400"
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1">Satker Pelaksana</label>
              <input
                v-model="taskForm.assigned_unit"
                type="text"
                required
                class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs text-white focus:outline-none focus:border-amber-400"
              />
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1">Nama Personel / PIC</label>
              <input
                v-model="taskForm.assigned_officer"
                type="text"
                placeholder="Contoh: Ipda Wahyu Pratama"
                class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs text-white focus:outline-none focus:border-amber-400"
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1">Tingkat Prioritas</label>
              <select
                v-model="taskForm.priority"
                class="w-full px-3 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs font-bold text-white focus:border-amber-400 outline-none"
              >
                <option value="urgent">Mendesak (Urgent)</option>
                <option value="high">Tinggi (High)</option>
                <option value="medium">Sedang (Medium)</option>
                <option value="low">Rendah (Low)</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1">Batas Waktu (Deadline)</label>
              <input
                v-model="taskForm.due_at"
                type="datetime-local"
                class="w-full px-3 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs text-white focus:outline-none focus:border-amber-400"
              />
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1">Instruksi & Arahan Teknis Lengkap</label>
            <textarea
              v-model="taskForm.instructions"
              rows="4"
              required
              placeholder="Uraikan detail penugasan yang wajib dipenuhi..."
              class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs text-white focus:outline-none focus:border-amber-400"
            ></textarea>
          </div>

          <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
            <button
              type="button"
              @click="isCreateModalOpen = false"
              class="px-4 py-2 rounded-xl text-xs font-bold text-slate-300 hover:bg-slate-800 border border-slate-700 transition"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-5 py-2.5 rounded-xl text-xs font-black bg-amber-400 text-slate-950 hover:bg-amber-300 shadow-md transition"
            >
              {{ isSubmitting ? 'Mengirim...' : 'Kirim Disposisi' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL INPUT UPDATE / SELESAIKAN TUGAS -->
    <div
      v-if="isUpdateModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm"
    >
      <div class="max-w-lg w-full rounded-3xl bg-[#0d182e] border border-slate-700 p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <h3 class="text-base font-extrabold text-white">Input Laporan Progres Tugas</h3>
          <button @click="isUpdateModalOpen = false" class="text-slate-400 hover:text-white p-1">
            <X class="w-5 h-5" />
          </button>
        </div>

        <form @submit.prevent="submitUpdate" class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1">Catatan Perkembangan Lapangan</label>
            <textarea
              v-model="updateNote"
              rows="4"
              required
              placeholder="Uraikan hasil pengerjaan, profiling, atau status koordinasi..."
              class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs text-white focus:outline-none focus:border-amber-400"
            ></textarea>
          </div>

          <label class="flex items-center gap-2 text-xs font-bold text-slate-200 cursor-pointer">
            <input
              v-model="markCompleted"
              type="checkbox"
              class="w-4 h-4 rounded text-emerald-500 focus:ring-emerald-500 bg-slate-800 border-slate-700"
            />
            <span>Tandai Tugas Telah Selesai 100%</span>
          </label>

          <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
            <button
              type="button"
              @click="isUpdateModalOpen = false"
              class="px-4 py-2 rounded-xl text-xs font-bold text-slate-300 hover:bg-slate-800 border border-slate-700"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-5 py-2.5 rounded-xl text-xs font-black bg-emerald-500 text-slate-950 hover:bg-emerald-400 shadow-md transition"
            >
              {{ isSubmitting ? 'Menyimpan...' : 'Simpan Laporan' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </EnterpriseLayout>
</template>

