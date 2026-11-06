<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import IssueStepper from '@/Components/IssueStepper.vue';
import { statusMeta, priorityMeta, normalizeStatus } from '@/utils/issueFlow';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import {
  Shield,
  ShieldCheck,
  AlertTriangle,
  Clock,
  MapPin,
  Calendar,
  Building2,
  ExternalLink,
  CheckCircle2,
  XCircle,
  RotateCcw,
  PlusCircle,
  FileText,
  Share2,
  CheckSquare,
  MessageSquare,
  TrendingUp,
  Flame,
  User,
  ArrowLeft,
  Image as ImageIcon,
  Video,
  Download,
  Eye,
  X,
  Check,
  Sparkles,
  Info,
  Ellipsis,
  Archive,
  Send,
  Bot,
  ArchiveRestore,
  Trash2,
} from 'lucide-vue-next';

const askSadewa = () => window.dispatchEvent(new CustomEvent('open-sadewa-copilot', { detail: { scope: 'issues', subject: props.issue?.title || '' } }));

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  issue: Object,
  metrics: Object,
  manage: { type: Object, default: () => ({}) },
});

// Modals
const isVerifyModalOpen = ref(false);
const isReturnModalOpen = ref(false);
const isResolveModalOpen = ref(false);
const isClarifyModalOpen = ref(false);
const isTaskModalOpen = ref(false);
const isReleaseModalOpen = ref(false);
const isLightboxOpen = ref(false);
const activeMedia = ref(null);

const verifyForm = ref({
  target_status: 'investigasi',
  priority: props.issue.priority || 'medium',
  reason: 'Laporan telah ditelaah fakta lapangannya dan dinyatakan terverifikasi sah.',
});

const returnForm = ref({
  reason: '',
});

const resolveForm = ref({
  resolved_note: '',
});

const clarifyForm = ref({
  is_hoax: props.issue.is_hoax || false,
  fact_check_result: props.issue.fact_check_result || '',
  official_clarification: props.issue.official_clarification || '',
  clarification_url: props.issue.clarification_url || '',
});

const taskForm = ref({
  title: `Tindak Lanjut: ${props.issue.title}`,
  instructions: 'Lakukan investigasi lapangan, amankan bukti visual, dan laporkan perkembangan berkala ke Bidhumas.',
  assigned_unit: props.issue.assigned_unit || 'Satreskrim',
  assigned_officer: '',
  priority: props.issue.priority === 'critical' ? 'urgent' : props.issue.priority,
  due_at: '',
});

const releaseForm = ref({
  channel: 'tribratanews',
  title: `[KLARIFIKASI RESMI] ${props.issue.title}`,
  content_draft: `SIARAN PERS BIDHUMAS POLDA SULAWESI BARAT\n\nMenanggapi dinamika informasi terkait ${props.issue.title}, Bidang Humas Polda Sulawesi Barat menyampaikan bahwa:\n\n1. Fakta Lapangan: ${props.issue.summary}\n2. Langkah Kepolisian: Telah dilakukan penelusuran dan koordinasi dengan jajaran kewilayahan.\n3. Himbauan: Masyarakat diimbau bijak bermedia sosial dan dapat memanfaatkan layanan 110.\n\nMamuju, ${new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })}.`,
  url: '',
});

const isSubmitting = ref(false);

// ---- Tindakan berikutnya mengikuti tahap alur & izin pengguna ----
const page = usePage();
const perms = computed(() => page.props.auth?.permissions || {});
const stage = computed(() => normalizeStatus(props.issue.status));

const openMenu = ref(null);
const closeFloating = () => { openMenu.value = null; };
onMounted(() => document.addEventListener('click', closeFloating));
onUnmounted(() => document.removeEventListener('click', closeFloating));

const submitDraft = () => {
  router.post('/issues/' + props.issue.id + '/submit', {}, { preserveScroll: true });
};
// ---- Arsip / Sampah: dialog konfirmasi (bukan window.confirm) ----
const confirmState = ref({ open: false, kind: '' });
const confirmBusy = ref(false);
const askAction = (kind) => { confirmState.value = { open: true, kind }; };
const archiveIssue = () => askAction('archive');
const CONFIRMS = {
  archive: { title: 'Arsipkan laporan ini?', message: 'Laporan pindah ke tab Arsip dan dapat dipulihkan ke tahap semula kapan saja.', confirmLabel: 'Arsipkan', tone: 'primary', withReason: true },
  trash: { title: 'Pindahkan laporan ke Sampah?', message: 'Laporan hilang dari daftar dan KPI, tetapi dapat dipulihkan dari tab Sampah.', confirmLabel: 'Pindahkan ke Sampah', tone: 'danger', withReason: true },
  force: { title: 'Hapus permanen laporan ini?', message: 'Laporan beserta foto buktinya dihapus selamanya dan TIDAK dapat dipulihkan.', confirmLabel: 'Hapus permanen', tone: 'danger', requireText: 'HAPUS' },
};
const confirmProps = computed(() => (confirmState.value.open ? CONFIRMS[confirmState.value.kind] : { title: '' }));
const runConfirm = ({ reason, confirm }) => {
  const id = props.issue.id;
  const opts = { preserveScroll: true, onFinish: () => { confirmBusy.value = false; confirmState.value.open = false; } };
  confirmBusy.value = true;
  if (confirmState.value.kind === 'archive') router.post(`/issues/${id}/archive`, { reason }, opts);
  else if (confirmState.value.kind === 'trash') router.delete(`/issues/${id}`, { data: { reason, from_show: 1 }, ...opts });
  else router.delete(`/issues/${id}/force`, { data: { confirm, from_show: 1 }, ...opts });
};
const restoreIssue = () => router.post(`/issues/${props.issue.id}/restore`, {}, { preserveScroll: true });

const ACTIONS = {
  submit: { label: 'Ajukan ke Verifikasi', icon: Send, run: submitDraft },
  verify: { label: 'Verifikasi Laporan', icon: ShieldCheck, run: () => { isVerifyModalOpen.value = true; } },
  clarify: { label: 'Rilis Klarifikasi / Hoaks', icon: AlertTriangle, run: () => { isClarifyModalOpen.value = true; } },
  dispatch: { label: 'Disposisi Tugas', icon: CheckSquare, run: () => { isTaskModalOpen.value = true; } },
  release: { label: 'Draf Siaran Pers', icon: Share2, run: () => { isReleaseModalOpen.value = true; } },
  returnIt: { label: 'Kembalikan (Revisi)', icon: RotateCcw, run: () => { isReturnModalOpen.value = true; } },
  resolve: { label: 'Selesaikan Kasus', icon: CheckCircle2, run: () => { isResolveModalOpen.value = true; } },
  archive: { label: 'Arsipkan', icon: Archive, run: archiveIssue },
  restore: { label: 'Pulihkan', icon: ArchiveRestore, run: restoreIssue },
  trash: { label: 'Hapus (ke Sampah)', icon: Trash2, run: () => askAction('trash') },
  force: { label: 'Hapus permanen', icon: Trash2, run: () => askAction('force') },
};

const PLAN = {
  draft: { primary: 'submit', secondary: null, menu: [] },
  monitoring: { primary: 'verify', secondary: null, menu: ['returnIt', 'clarify', 'dispatch', 'release', 'resolve'] },
  investigasi: { primary: 'clarify', secondary: 'dispatch', menu: ['release', 'returnIt', 'resolve'] },
  klarifikasi: { primary: 'resolve', secondary: 'dispatch', menu: ['release'] },
  selesai: { primary: 'archive', secondary: null, menu: ['release'] },
  arsip: { primary: null, secondary: null, menu: [] },
};

const allowed = (key) => {
  if (key === 'submit') return !!perms.value.issues_create;
  if (key === 'dispatch') return !!perms.value.tasks_disposition;
  return !!perms.value.issues_verify;
};
const actionPlan = computed(() => {
  const m = props.manage || {};
  // Laporan di Sampah: hanya pulihkan / hapus permanen.
  if (m.trashed) {
    return { primary: m.can_restore ? ACTIONS.restore : null, secondary: null, menu: m.can_force ? [{ key: 'force', ...ACTIONS.force }] : [] };
  }

  const plan = PLAN[stage.value] || PLAN.arsip;
  const primary = plan.primary && allowed(plan.primary) ? ACTIONS[plan.primary] : null;
  const menu = plan.menu.filter(allowed).map((k) => ({ key: k, ...ACTIONS[k] }));

  // Pengelolaan laporan (izin dihitung server): arsipkan di tahap mana pun, pulihkan dari arsip, hapus ke Sampah.
  if (m.can_archive && plan.primary !== 'archive') menu.push({ key: 'archive', ...ACTIONS.archive });
  if (m.can_restore) {
    if (stage.value === 'arsip') return { primary: ACTIONS.restore, secondary: null, menu: m.can_delete ? [{ key: 'trash', ...ACTIONS.trash }] : [] };
  }
  if (m.can_delete) menu.push({ key: 'trash', ...ACTIONS.trash });

  return {
    primary,
    secondary: plan.secondary && allowed(plan.secondary) ? ACTIONS[plan.secondary] : null,
    menu,
  };
});

const submitVerify = () => {
  isSubmitting.value = true;
  router.post(`/issues/${props.issue.id}/verify`, verifyForm.value, {
    preserveScroll: true,
    onSuccess: () => {
      isSubmitting.value = false;
      isVerifyModalOpen.value = false;
    },
    onError: () => { isSubmitting.value = false; },
  });
};

const submitReturn = () => {
  isSubmitting.value = true;
  router.post(`/issues/${props.issue.id}/return`, returnForm.value, {
    preserveScroll: true,
    onSuccess: () => {
      isSubmitting.value = false;
      isReturnModalOpen.value = false;
    },
    onError: () => { isSubmitting.value = false; },
  });
};

const submitResolve = () => {
  isSubmitting.value = true;
  router.post(`/issues/${props.issue.id}/resolve`, resolveForm.value, {
    preserveScroll: true,
    onSuccess: () => {
      isSubmitting.value = false;
      isResolveModalOpen.value = false;
    },
    onError: () => { isSubmitting.value = false; },
  });
};

const submitClarification = () => {
  isSubmitting.value = true;
  router.post(`/issues/${props.issue.id}/clarify`, clarifyForm.value, {
    preserveScroll: true,
    onSuccess: () => {
      isSubmitting.value = false;
      isClarifyModalOpen.value = false;
    },
    onError: () => { isSubmitting.value = false; },
  });
};

const submitTask = () => {
  isSubmitting.value = true;
  router.post(`/issues/${props.issue.id}/task`, taskForm.value, {
    preserveScroll: true,
    onSuccess: () => {
      isSubmitting.value = false;
      isTaskModalOpen.value = false;
    },
    onError: () => { isSubmitting.value = false; },
  });
};

const submitRelease = () => {
  isSubmitting.value = true;
  router.post(`/issues/${props.issue.id}/release`, releaseForm.value, {
    preserveScroll: true,
    onSuccess: () => {
      isSubmitting.value = false;
      isReleaseModalOpen.value = false;
    },
    onError: () => { isSubmitting.value = false; },
  });
};

const openLightbox = (media) => {
  activeMedia.value = media;
  isLightboxOpen.value = true;
};

const formatWita = (dateStr) => {
  if (!dateStr) return '-';
  try {
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }) + ' WITA';
  } catch {
    return dateStr;
  }
};
</script>

<template>
  <EnterpriseLayout
    :all-poldas="allPoldas"
    :selected-polda="selectedPolda"
    :selected-code="selectedCode"
    :metrics="metrics"
  >
    <div v-if="manage.trashed" class="p-4 rounded-2xl bg-rose-950/40 border border-rose-500/40 flex flex-wrap items-center justify-between gap-3 text-xs text-rose-100">
      <p class="flex items-center gap-2"><Trash2 class="w-4 h-4 text-rose-300 shrink-0" /> Laporan ini berada di <strong>Sampah</strong>: tersembunyi dari daftar dan KPI.</p>
      <button v-if="manage.can_restore" type="button" @click="restoreIssue" class="px-3.5 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black cursor-pointer">Pulihkan laporan</button>
    </div>

    <!-- Top Breadcrumb & Return -->
    <div class="flex items-center justify-between gap-4 pb-2 border-b border-slate-800">
      <Link
        href="/issues"
        class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-xs font-bold text-slate-300 hover:text-white transition"
      >
        <ArrowLeft class="w-3.5 h-3.5" />
        <span>Kembali ke Daftar Laporan</span>
      </Link>

      <div class="flex items-center gap-2 text-xs">
        <span class="text-slate-400">Kode Laporan:</span>
        <span class="px-2.5 py-1 rounded-lg bg-amber-400/20 text-amber-300 font-mono font-bold border border-amber-400/40">
          {{ issue.report_code || `SIM-2026-${String(issue.id).padStart(5, '0')}` }}
        </span>
      </div>
    </div>

    <!-- Hero Header of Issue -->
    <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 md:p-8 shadow-2xl space-y-5">
      <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div class="space-y-2 max-w-4xl">
          <div class="flex flex-wrap items-center gap-2.5">
            <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider" :class="priorityMeta(issue.priority).cls">
              Prioritas: {{ priorityMeta(issue.priority).label }}
            </span>

            <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider" :class="statusMeta(issue.status).cls">
              {{ statusMeta(issue.status).label }}
            </span>

            <!-- Category -->
            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-900 border border-slate-700 text-slate-300">
              {{ issue.category }}
            </span>

            <!-- Hoax Flag -->
            <span
              v-if="issue.is_hoax"
              class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-rose-600 text-white shadow-md animate-pulse"
            >
              ⚠️ TERKONFIRMASI HOAKS
            </span>
          </div>

          <h1 class="text-xl md:text-2xl font-black text-white leading-tight">
            {{ issue.title }}
          </h1>

          <div class="flex flex-wrap items-center gap-4 text-xs text-slate-400 pt-1">
            <span class="flex items-center gap-1.5 text-sky-400 font-semibold">
              <Building2 class="w-3.5 h-3.5" />
              {{ issue.polres?.name || issue.polda?.name || 'Polda Sulbar' }}
            </span>
            <span class="flex items-center gap-1.5">
              <MapPin class="w-3.5 h-3.5 text-amber-400" />
              {{ issue.location_text || 'Wilayah Hukum Polda Sulbar' }}
            </span>
            <span class="flex items-center gap-1.5 font-mono">
              <Clock class="w-3.5 h-3.5 text-emerald-400" />
              Waktu Kejadian: {{ formatWita(issue.occurred_at || issue.created_at) }}
            </span>
          </div>
        </div>

        <!-- Tindakan: langkah berikutnya + menu lainnya -->
        <div class="flex items-center flex-wrap gap-2.5 shrink-0">
          <button
            v-if="actionPlan.primary"
            type="button"
            @click="actionPlan.primary.run()"
            class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 text-xs font-black transition shadow-lg shadow-emerald-500/20 cursor-pointer"
          >
            <component :is="actionPlan.primary.icon" class="w-4 h-4" />
            <span>{{ actionPlan.primary.label }}</span>
          </button>

          <button
            v-if="actionPlan.secondary"
            type="button"
            @click="actionPlan.secondary.run()"
            class="flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-200 hover:text-white text-xs font-bold transition cursor-pointer"
          >
            <component :is="actionPlan.secondary.icon" class="w-4 h-4 text-sky-400" />
            <span>{{ actionPlan.secondary.label }}</span>
          </button>

          <button
            type="button"
            @click="askSadewa"
            class="flex items-center gap-1.5 px-3 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-300 hover:text-white text-xs font-bold transition cursor-pointer"
            title="Tanya SADEWA AI tentang laporan ini"
          >
            <Bot class="w-4 h-4 text-amber-400" />
            <span class="hidden sm:inline">SADEWA</span>
          </button>

          <div v-if="actionPlan.menu.length" class="relative">
            <button
              type="button"
              @click.stop="openMenu = openMenu === 'issue' ? null : 'issue'"
              class="flex items-center gap-1.5 px-3 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-300 hover:text-white text-xs font-bold transition cursor-pointer"
              title="Tindakan lainnya"
            >
              <Ellipsis class="w-4 h-4" />
              <span class="hidden sm:inline">Lainnya</span>
            </button>
            <div
              v-if="openMenu === 'issue'"
              @click.stop
              class="absolute right-0 top-full mt-2 z-30 w-60 rounded-xl bg-[#0b1426] border border-slate-700 shadow-2xl p-1.5"
            >
              <button
                v-for="m in actionPlan.menu"
                :key="m.key"
                type="button"
                @click="m.run(); openMenu = null"
                class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-bold text-slate-200 hover:bg-slate-800 hover:text-white transition cursor-pointer text-left"
              >
                <component :is="m.icon" class="w-4 h-4 text-amber-400" />
                <span>{{ m.label }}</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Tahapan penanganan -->
      <div class="pt-4 border-t border-slate-800">
        <IssueStepper :status="issue.status" />
      </div>
    </div>

    <!-- Main Content 2-Column Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left Column: Details & Evidence (2 Cols) -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Card 1: Kronologi & Fakta Lapangan -->
        <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
          <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <h3 class="text-sm font-black text-white uppercase tracking-wider flex items-center gap-2">
              <FileText class="w-4 h-4 text-amber-400" />
              <span>Kronologi & Rincian Fakta Lapangan</span>
            </h3>
            <span class="text-xs text-slate-400 font-mono">
              Dilaporkan oleh: {{ issue.creator?.name || 'Operator Humas' }}
            </span>
          </div>

          <div class="text-xs md:text-sm text-slate-200 leading-relaxed whitespace-pre-line bg-slate-900/60 p-5 rounded-2xl border border-slate-800/80">
            {{ issue.summary }}
          </div>

          <!-- Metadata Box -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs pt-1">
            <div class="p-3.5 rounded-xl bg-slate-900/80 border border-slate-800 space-y-1">
              <span class="text-[10px] text-slate-400 font-bold uppercase">Sumber Pantauan Media</span>
              <p class="font-bold text-white">{{ issue.source_media || 'Laporan Lapangan' }}</p>
            </div>
            <div class="p-3.5 rounded-xl bg-slate-900/80 border border-slate-800 space-y-1">
              <span class="text-[10px] text-slate-400 font-bold uppercase">Satuan Penanggung Jawab (PIC)</span>
              <p class="font-bold text-sky-400">{{ issue.assigned_unit || 'Ditreskrimsus / Satreskrim' }}</p>
            </div>
            <div class="p-3.5 rounded-xl bg-slate-900/80 border border-slate-800 space-y-1">
              <span class="text-[10px] text-slate-400 font-bold uppercase">Tautan Dokumen / Konten</span>
              <p class="font-bold text-amber-400 truncate">
                <a
                  v-if="issue.source_url"
                  :href="issue.source_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="hover:underline flex items-center gap-1"
                >
                  <span class="truncate">{{ issue.source_url }}</span>
                  <ExternalLink class="w-3 h-3 shrink-0" />
                </a>
                <span v-else class="text-slate-500">Tidak ada tautan web</span>
              </p>
            </div>
          </div>
        </div>

        <!-- Card 2: Bukti Media & Dokumentasi Visual (MediaFiles) -->
        <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
          <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <div class="flex items-center gap-2">
              <ImageIcon class="w-4 h-4 text-sky-400" />
              <h3 class="text-sm font-black text-white uppercase tracking-wider">
                Dokumentasi Media & Alat Bukti Visual
              </h3>
            </div>
            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-900 text-slate-400 border border-slate-700">
              {{ issue.media_files?.length || 0 }} Berkas Terlampir
            </span>
          </div>

          <div v-if="issue.media_files && issue.media_files.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div
              v-for="media in issue.media_files"
              :key="media.id"
              class="group rounded-2xl bg-slate-900 border border-slate-800 overflow-hidden hover:border-amber-400/50 transition cursor-pointer"
              @click="openLightbox(media)"
            >
              <div class="relative h-44 bg-slate-950 overflow-hidden">
                <img
                  :src="media.file_url"
                  :alt="media.title"
                  class="w-full h-full object-cover group-hover:scale-105 transition duration-500"
                />
                <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-transparent to-transparent"></div>
                <div class="absolute top-2.5 right-2.5 flex items-center gap-1.5">
                  <span class="p-1 rounded-lg bg-black/70 text-white backdrop-blur-md">
                    <Eye class="w-3.5 h-3.5" />
                  </span>
                </div>
                <span class="absolute bottom-2 left-2 px-2 py-0.5 rounded text-[9px] font-black uppercase bg-black/80 text-amber-300 border border-amber-400/30">
                  {{ media.file_type }}
                </span>
              </div>
              <div class="p-3.5 space-y-1">
                <h4 class="text-xs font-extrabold text-white truncate">{{ media.title }}</h4>
                <p class="text-[11px] text-slate-400 line-clamp-1">{{ media.caption || 'Tanpa keterangan' }}</p>
                <div class="pt-1 flex items-center justify-between text-[10px] text-slate-500 font-mono">
                  <span>{{ (media.size_bytes / 1024 / 1024).toFixed(1) }} MB</span>
                  <span>{{ formatWita(media.created_at) }}</span>
                </div>
              </div>
            </div>
          </div>

          <div v-else class="p-8 rounded-2xl bg-slate-900/50 border border-slate-800 text-center space-y-2">
            <ImageIcon class="w-8 h-8 mx-auto text-slate-600" />
            <p class="text-xs text-slate-400">Belum ada berkas foto atau video bukti yang terlampir pada laporan ini.</p>
          </div>
        </div>

        <!-- Card 3: Klarifikasi Resmi / Stempel Hoaks (Jika Ada) -->
        <div
          v-if="issue.official_clarification || issue.is_hoax"
          class="rounded-3xl border p-6 shadow-xl space-y-3"
          :class="issue.is_hoax ? 'bg-rose-950/20 border-rose-500/40' : 'bg-emerald-950/20 border-emerald-500/40'"
        >
          <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <div class="flex items-center gap-2">
              <ShieldCheck class="w-5 h-5 text-emerald-400" v-if="!issue.is_hoax" />
              <AlertTriangle class="w-5 h-5 text-rose-400" v-else />
              <h3 class="text-sm font-black text-white uppercase tracking-wider">
                {{ issue.is_hoax ? 'Hasil Pemeriksaan Fakta: TERKONFIRMASI HOAKS' : 'Klarifikasi & Fakta Terkonfirmasi' }}
              </h3>
            </div>
            <span class="text-xs font-mono text-slate-400">{{ formatWita(issue.clarified_at) }}</span>
          </div>

          <div class="space-y-2 text-xs">
            <p class="text-slate-300">
              <strong class="text-white block mb-1">Hasil Fact-Checking Humas:</strong>
              {{ issue.fact_check_result }}
            </p>
            <p class="text-slate-300">
              <strong class="text-white block mb-1">Pernyataan Resmi Kepolisian:</strong>
              {{ issue.official_clarification }}
            </p>
          </div>

          <div v-if="issue.clarification_url" class="pt-2">
            <a
              :href="issue.clarification_url"
              target="_blank"
              class="inline-flex items-center gap-1.5 text-xs text-amber-400 hover:underline font-bold"
            >
              <span>Buka Tautan Klarifikasi Resmi Humas Polri</span>
              <ExternalLink class="w-3.5 h-3.5" />
            </a>
          </div>
        </div>

        <!-- Card 4: Catatan Penyelesaian (Jika Selesai) -->
        <div
          v-if="issue.resolved_note"
          class="rounded-3xl bg-emerald-950/30 border border-emerald-500/40 p-6 shadow-xl space-y-2"
        >
          <div class="flex items-center gap-2 text-emerald-400 font-black text-sm">
            <CheckCircle2 class="w-4 h-4" />
            <span>Catatan Hasil Penanganan Kasus (SELESAI)</span>
          </div>
          <p class="text-xs text-slate-200 leading-relaxed whitespace-pre-line">
            {{ issue.resolved_note }}
          </p>
        </div>
      </div>

      <!-- Right Column: Sidebar Actions & History (1 Col) -->
      <div class="space-y-6">
        <!-- Card 5: Intelijen Sentimen & Viralisasi -->
        <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
          <h3 class="text-xs font-black text-white uppercase tracking-wider flex items-center gap-2">
            <TrendingUp class="w-4 h-4 text-amber-400" />
            <span>Indikator Intelijen Media</span>
          </h3>

          <div class="grid grid-cols-2 gap-3 text-center">
            <div class="p-3 rounded-2xl bg-slate-900 border border-slate-800 space-y-1">
              <span class="text-[10px] text-slate-400 font-bold uppercase">Skor Viralisasi</span>
              <p class="text-2xl font-black text-amber-400">{{ issue.virality_score }}%</p>
              <p class="text-[9px] text-slate-500">Tingkat atensi netizen</p>
            </div>
            <div class="p-3 rounded-2xl bg-slate-900 border border-slate-800 space-y-1">
              <span class="text-[10px] text-slate-400 font-bold uppercase">Sentimen Publik</span>
              <p
                class="text-2xl font-black capitalize"
                :class="{
                  'text-emerald-400': issue.sentiment === 'positif',
                  'text-rose-400': issue.sentiment === 'negatif',
                  'text-amber-400': issue.sentiment === 'netral',
                }"
              >
                {{ issue.sentiment }}
              </p>
              <p class="text-[9px] text-slate-500">Analisis NLP AI</p>
            </div>
          </div>
        </div>

        <!-- Card 6: Linimasa Status & Audit Trail -->
        <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
          <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <h3 class="text-xs font-black text-white uppercase tracking-wider flex items-center gap-2">
              <Clock class="w-4 h-4 text-emerald-400" />
              <span>Linimasa Riwayat Status (Audit)</span>
            </h3>
            <span class="text-[10px] text-slate-500 font-mono">{{ issue.status_histories?.length || 0 }} Rekaman</span>
          </div>

          <div class="space-y-4 relative before:absolute before:inset-0 before:left-3 before:w-0.5 before:bg-slate-800">
            <div
              v-for="(hist, idx) in (issue.status_histories || [])"
              :key="hist.id || idx"
              class="relative pl-7 space-y-1 text-xs"
            >
              <span class="absolute left-1.5 top-1.5 w-3 h-3 rounded-full bg-emerald-500 border-2 border-slate-950 shadow-sm"></span>
              <div class="flex items-center justify-between">
                <span class="font-extrabold text-white uppercase text-[11px] tracking-wide">
                  {{ hist.from_status }} &rarr; {{ hist.to_status }}
                </span>
                <span class="text-[10px] text-slate-400 font-mono">{{ formatWita(hist.created_at) }}</span>
              </div>
              <p class="text-slate-300 text-[11px] leading-relaxed">{{ hist.reason || 'Perubahan status dicatat sistem.' }}</p>
              <p class="text-[10px] text-slate-500">Aktor: {{ hist.actor?.name || 'Sistem / Pimpinan' }}</p>
            </div>
          </div>
        </div>

        <!-- Card 7: Disposisi Tugas Terkait -->
        <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-3">
          <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <h3 class="text-xs font-black text-white uppercase tracking-wider flex items-center gap-2">
              <CheckSquare class="w-4 h-4 text-sky-400" />
              <span>Disposisi Tugas Terkait</span>
            </h3>
            <button
              @click="isTaskModalOpen = true"
              class="text-[11px] font-bold text-amber-400 hover:underline"
            >
              + Disposisi
            </button>
          </div>

          <div v-if="issue.tasks && issue.tasks.length > 0" class="space-y-2">
            <div
              v-for="t in issue.tasks"
              :key="t.id"
              class="p-3 rounded-2xl bg-slate-900 border border-slate-800 space-y-1 text-xs"
            >
              <div class="flex items-center justify-between font-bold text-white">
                <span class="truncate">{{ t.title }}</span>
                <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase bg-sky-500/20 text-sky-300">
                  {{ t.status }}
                </span>
              </div>
              <p class="text-[11px] text-slate-400">Unit PIC: {{ t.assigned_unit }}</p>
            </div>
          </div>
          <div v-else class="text-center py-4 text-xs text-slate-500">
            Belum ada penugasan personel untuk laporan ini.
          </div>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 1: VERIFIKASI & SETUJUI LAPORAN                                     -->
    <!-- ========================================================================= -->
    <div
      v-if="isVerifyModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
    >
      <div class="w-full max-w-lg rounded-3xl bg-[#0b1426] border border-slate-700 shadow-2xl p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <ShieldCheck class="w-5 h-5 text-emerald-400" />
            <h3 class="text-base font-extrabold text-white">Verifikasi & Setujui Laporan</h3>
          </div>
          <button @click="isVerifyModalOpen = false" class="text-slate-400 hover:text-white">
            <X class="w-5 h-5" />
          </button>
        </div>

        <p class="text-xs text-slate-300">
          Sebagai Verifikator Humas, pastikan dokumentasi visual dan kronologi telah memenuhi standar akurasi fakta sebelum diteruskan ke pimpinan.
        </p>

        <div class="space-y-3 text-xs">
          <div>
            <label class="block font-bold text-slate-300 mb-1">Status Tujuan Laporan</label>
            <select
              v-model="verifyForm.target_status"
              class="w-full p-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white font-bold outline-none focus:border-amber-400"
            >
              <option value="investigasi">Investigasi / Penanganan Satwil</option>
              <option value="klarifikasi">Klarifikasi Siaran Pers</option>
              <option value="selesai">Selesai (Nihil Eskalasi)</option>
            </select>
          </div>

          <div>
            <label class="block font-bold text-slate-300 mb-1">Tingkat Prioritas Akhir</label>
            <select
              v-model="verifyForm.priority"
              class="w-full p-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white font-bold outline-none focus:border-amber-400"
            >
              <option value="critical">Kritis (Atensi Pimpinan / Merah)</option>
              <option value="high">Tinggi (Kuning)</option>
              <option value="medium">Sedang (Standar)</option>
              <option value="low">Rendah (Hijau)</option>
            </select>
          </div>

          <div>
            <label class="block font-bold text-slate-300 mb-1">Catatan Telaah Verifikasi</label>
            <textarea
              v-model="verifyForm.reason"
              rows="3"
              class="w-full p-3 rounded-xl bg-slate-900 border border-slate-700 text-slate-200 outline-none focus:border-amber-400"
            ></textarea>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
          <button
            @click="isVerifyModalOpen = false"
            class="px-4 py-2 rounded-xl bg-slate-900 text-xs text-slate-300 hover:text-white"
          >
            Batal
          </button>
          <button
            @click="submitVerify"
            :disabled="isSubmitting"
            class="px-5 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black text-xs transition disabled:opacity-50"
          >
            {{ isSubmitting ? 'Memproses...' : 'Setujui Verifikasi' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 2: KEMBALIKAN UNTUK REVISI                                          -->
    <!-- ========================================================================= -->
    <div
      v-if="isReturnModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
    >
      <div class="w-full max-w-lg rounded-3xl bg-[#0b1426] border border-slate-700 shadow-2xl p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <RotateCcw class="w-5 h-5 text-amber-400" />
            <h3 class="text-base font-extrabold text-white">Kembalikan Laporan untuk Revisi</h3>
          </div>
          <button @click="isReturnModalOpen = false" class="text-slate-400 hover:text-white">
            <X class="w-5 h-5" />
          </button>
        </div>

        <p class="text-xs text-slate-300">
          Cantumkan instruksi perbaikan atau data tambahan yang wajib dilengkapi oleh operator pelapor kewilayahan.
        </p>

        <div>
          <label class="block text-xs font-bold text-slate-300 mb-1">Catatan Revisi Wajib</label>
          <textarea
            v-model="returnForm.reason"
            rows="4"
            placeholder="Contoh: Mohon lengkapi dokumentasi foto resolusi tinggi dan koordinat presisi lokasi kejadian..."
            class="w-full p-3 rounded-xl bg-slate-900 border border-slate-700 text-xs text-slate-200 outline-none focus:border-amber-400"
          ></textarea>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
          <button
            @click="isReturnModalOpen = false"
            class="px-4 py-2 rounded-xl bg-slate-900 text-xs text-slate-300 hover:text-white"
          >
            Batal
          </button>
          <button
            @click="submitReturn"
            :disabled="isSubmitting || !returnForm.reason.trim()"
            class="px-5 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-black text-xs transition disabled:opacity-50"
          >
            {{ isSubmitting ? 'Mengirim...' : 'Kirim Catatan Revisi' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 3: SELESAIKAN LAPORAN                                               -->
    <!-- ========================================================================= -->
    <div
      v-if="isResolveModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
    >
      <div class="w-full max-w-lg rounded-3xl bg-[#0b1426] border border-slate-700 shadow-2xl p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <CheckCircle2 class="w-5 h-5 text-emerald-400" />
            <h3 class="text-base font-extrabold text-white">Selesaikan Penanganan Laporan</h3>
          </div>
          <button @click="isResolveModalOpen = false" class="text-slate-400 hover:text-white">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-300 mb-1">Catatan Hasil Penanganan Kasus</label>
          <textarea
            v-model="resolveForm.resolved_note"
            rows="4"
            placeholder="Jelaskan secara ringkas hasil penanganan di lapangan, mediasi, rilis klarifikasi, atau situasi terkini yang telah kondusif..."
            class="w-full p-3 rounded-xl bg-slate-900 border border-slate-700 text-xs text-slate-200 outline-none focus:border-amber-400"
          ></textarea>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
          <button
            @click="isResolveModalOpen = false"
            class="px-4 py-2 rounded-xl bg-slate-900 text-xs text-slate-300 hover:text-white"
          >
            Batal
          </button>
          <button
            @click="submitResolve"
            :disabled="isSubmitting || !resolveForm.resolved_note.trim()"
            class="px-5 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black text-xs transition disabled:opacity-50"
          >
            {{ isSubmitting ? 'Menyimpan...' : 'Tandai Selesai' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 4: KLARIFIKASI HOAKS / FACT-CHECK                                    -->
    <!-- ========================================================================= -->
    <div
      v-if="isClarifyModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
    >
      <div class="w-full max-w-xl rounded-3xl bg-[#0b1426] border border-slate-700 shadow-2xl p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <AlertTriangle class="w-5 h-5 text-rose-400" />
            <h3 class="text-base font-extrabold text-white">Fact-Checking & Klarifikasi Hoaks</h3>
          </div>
          <button @click="isClarifyModalOpen = false" class="text-slate-400 hover:text-white">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="space-y-3 text-xs">
          <div class="p-3 rounded-xl bg-slate-900 border border-slate-800 flex items-center justify-between">
            <span class="font-bold text-white">Stempel Status Hoaks</span>
            <label class="flex items-center gap-2 cursor-pointer font-bold text-rose-400">
              <input
                type="checkbox"
                v-model="clarifyForm.is_hoax"
                class="rounded bg-slate-800 border-slate-700 text-rose-500 focus:ring-0 w-4 h-4"
              />
              <span>Tandai Terbukti Hoaks</span>
            </label>
          </div>

          <div>
            <label class="block font-bold text-slate-300 mb-1">Hasil Fact-Checking Humas</label>
            <textarea
              v-model="clarifyForm.fact_check_result"
              rows="2"
              placeholder="Fakta hasil pengecekan langsung ke TKP / sumber terkait..."
              class="w-full p-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white outline-none focus:border-amber-400"
            ></textarea>
          </div>

          <div>
            <label class="block font-bold text-slate-300 mb-1">Narasi Klarifikasi Resmi Kepolisian</label>
            <textarea
              v-model="clarifyForm.official_clarification"
              rows="3"
              placeholder="Pernyataan resmi kepolisian untuk disebarkan ke media siber..."
              class="w-full p-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white outline-none focus:border-amber-400"
            ></textarea>
          </div>

          <div>
            <label class="block font-bold text-slate-300 mb-1">Tautan URL Rilis Resmi (Tribratanews)</label>
            <input
              type="text"
              v-model="clarifyForm.clarification_url"
              placeholder="https://tribratanews.sulbar.polri.go.id/klarifikasi-..."
              class="w-full p-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white outline-none focus:border-amber-400"
            />
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
          <button
            @click="isClarifyModalOpen = false"
            class="px-4 py-2 rounded-xl bg-slate-900 text-xs text-slate-300 hover:text-white"
          >
            Batal
          </button>
          <button
            @click="submitClarification"
            :disabled="isSubmitting"
            class="px-5 py-2 rounded-xl bg-rose-500 hover:bg-rose-400 text-white font-black text-xs transition disabled:opacity-50"
          >
            {{ isSubmitting ? 'Menyimpan...' : 'Terapkan Klarifikasi' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 5: DISPOSISI TUGAS CEPAT                                            -->
    <!-- ========================================================================= -->
    <div
      v-if="isTaskModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
    >
      <div class="w-full max-w-xl rounded-3xl bg-[#0b1426] border border-slate-700 shadow-2xl p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <CheckSquare class="w-5 h-5 text-sky-400" />
            <h3 class="text-base font-extrabold text-white">Terbitkan Disposisi Tugas Personel</h3>
          </div>
          <button @click="isTaskModalOpen = false" class="text-slate-400 hover:text-white">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="space-y-3 text-xs">
          <div>
            <label class="block font-bold text-slate-300 mb-1">Judul Penugasan</label>
            <input
              type="text"
              v-model="taskForm.title"
              class="w-full p-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white outline-none focus:border-amber-400 font-bold"
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-300 mb-1">Satuan Pelaksana (Unit PIC)</label>
              <input
                type="text"
                v-model="taskForm.assigned_unit"
                placeholder="Contoh: Ditreskrimsus / Polresta Mamuju"
                class="w-full p-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white outline-none focus:border-amber-400"
              />
            </div>
            <div>
              <label class="block font-bold text-slate-300 mb-1">Tingkat Urgensi</label>
              <select
                v-model="taskForm.priority"
                class="w-full p-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white outline-none focus:border-amber-400"
              >
                <option value="urgent">Mendesak / Siaga 1</option>
                <option value="high">Prioritas Tinggi</option>
                <option value="medium">Standar / Normal</option>
                <option value="low">Rendah</option>
              </select>
            </div>
          </div>

          <div>
            <label class="block font-bold text-slate-300 mb-1">Instruksi Kerja Pimpinan</label>
            <textarea
              v-model="taskForm.instructions"
              rows="3"
              class="w-full p-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white outline-none focus:border-amber-400"
            ></textarea>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
          <button
            @click="isTaskModalOpen = false"
            class="px-4 py-2 rounded-xl bg-slate-900 text-xs text-slate-300 hover:text-white"
          >
            Batal
          </button>
          <button
            @click="submitTask"
            :disabled="isSubmitting"
            class="px-5 py-2 rounded-xl bg-sky-500 hover:bg-sky-400 text-slate-950 font-black text-xs transition disabled:opacity-50"
          >
            {{ isSubmitting ? 'Menerbitkan...' : 'Terbitkan Disposisi' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 6: DRAF SIARAN PERS & PUBLIKASI                                     -->
    <!-- ========================================================================= -->
    <div
      v-if="isReleaseModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
    >
      <div class="w-full max-w-2xl rounded-3xl bg-[#0b1426] border border-slate-700 shadow-2xl p-6 space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <Share2 class="w-5 h-5 text-amber-400" />
            <h3 class="text-base font-extrabold text-white">Draf Siaran Pers & Publikasi Multi-Kanal</h3>
          </div>
          <button @click="isReleaseModalOpen = false" class="text-slate-400 hover:text-white">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="space-y-3 text-xs">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-300 mb-1">Saluran Distribusi</label>
              <select
                v-model="releaseForm.channel"
                class="w-full p-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white font-bold outline-none focus:border-amber-400"
              >
                <option value="tribratanews">Tribrata News Resmi</option>
                <option value="siaran_pers">Siaran Pers Rekanan Media</option>
                <option value="instagram">Instagram Humas</option>
                <option value="facebook">Facebook Fanpage</option>
                <option value="twitter">X / Twitter</option>
                <option value="tiktok">TikTok Patroli</option>
              </select>
            </div>
            <div>
              <label class="block font-bold text-slate-300 mb-1">Judul Rilis</label>
              <input
                type="text"
                v-model="releaseForm.title"
                class="w-full p-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white font-bold outline-none focus:border-amber-400"
              />
            </div>
          </div>

          <div>
            <label class="block font-bold text-slate-300 mb-1">Teks Narasi Rilis</label>
            <textarea
              v-model="releaseForm.content_draft"
              rows="8"
              class="w-full p-3 rounded-xl bg-slate-900 border border-slate-700 text-xs font-mono text-amber-200 outline-none focus:border-amber-400 leading-relaxed"
            ></textarea>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
          <button
            @click="isReleaseModalOpen = false"
            class="px-4 py-2 rounded-xl bg-slate-900 text-xs text-slate-300 hover:text-white"
          >
            Batal
          </button>
          <button
            @click="submitRelease"
            :disabled="isSubmitting"
            class="px-5 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-black text-xs transition disabled:opacity-50"
          >
            {{ isSubmitting ? 'Menyimpan...' : 'Simpan Draf Publikasi' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL LIGHTBOX FOTO & VIDEO                                               -->
    <!-- ========================================================================= -->
    <div
      v-if="isLightboxOpen && activeMedia"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/90 backdrop-blur-md"
      @mousedown.self="isLightboxOpen = false"
    >
      <div class="w-full max-w-4xl rounded-3xl bg-[#0b1426] border border-slate-700 shadow-2xl overflow-hidden">
        <div class="p-4 bg-slate-950 flex items-center justify-between border-b border-slate-800">
          <div class="space-y-0.5">
            <h3 class="text-sm font-extrabold text-white truncate max-w-xl">{{ activeMedia.title }}</h3>
            <p class="text-[11px] text-slate-400">{{ activeMedia.caption || 'Dokumentasi bukti lapangan' }}</p>
          </div>
          <button @click="isLightboxOpen = false" class="text-slate-400 hover:text-white p-1">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="max-h-[70vh] bg-black flex items-center justify-center p-2">
          <img
            v-if="activeMedia.file_type === 'photo'"
            :src="activeMedia.file_url"
            :alt="activeMedia.title"
            class="max-h-[65vh] max-w-full object-contain rounded-xl"
          />
          <video
            v-else
            :src="activeMedia.file_url"
            controls
            class="max-h-[65vh] max-w-full rounded-xl"
          ></video>
        </div>

        <div class="p-4 bg-slate-900 flex items-center justify-between text-xs text-slate-300">
          <span class="font-mono text-[11px] text-slate-400">
            Ukuran: {{ (activeMedia.size_bytes / 1024 / 1024).toFixed(2) }} MB &bull; Waktu: {{ formatWita(activeMedia.created_at) }}
          </span>
          <a
            :href="activeMedia.file_url"
            target="_blank"
            download
            class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-black text-xs"
          >
            <Download class="w-3.5 h-3.5" />
            <span>Unduh Berkas</span>
          </a>
        </div>
      </div>
    </div>
    <ConfirmDialog :open="confirmState.open" :busy="confirmBusy" v-bind="confirmProps" @confirm="runConfirm" @cancel="confirmState.open = false" />
  </EnterpriseLayout>
</template>

