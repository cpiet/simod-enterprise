/**
 * Sumber tunggal label, warna, dan urutan alur Laporan/Isu serta Tugas.
 *
 * Alur resmi: draft -> monitoring -> investigasi -> klarifikasi -> selesai -> arsip.
 * Status lama (submitted, unit_verified, polda_verified, in_progress, resolved, closed) dipetakan
 * ke tahap yang setara agar data historis tetap terbaca. Harus sejajar dengan `Issue::STATUS_GROUPS`.
 */

export const ISSUE_STEPS = [
  { key: 'monitoring', label: 'Telaah', hint: 'Menunggu telaah & verifikasi' },
  { key: 'investigasi', label: 'Penanganan', hint: 'Terverifikasi, ditangani & ditugaskan' },
  { key: 'klarifikasi', label: 'Klarifikasi', hint: 'Klarifikasi resmi dirilis' },
  { key: 'selesai', label: 'Selesai', hint: 'Kasus dituntaskan' },
  { key: 'arsip', label: 'Arsip', hint: 'Disimpan sebagai arsip' },
];

const LEGACY = {
  submitted: 'monitoring',
  unit_verified: 'monitoring',
  polda_verified: 'investigasi',
  in_progress: 'investigasi',
  resolved: 'selesai',
  closed: 'selesai',
};

/** Normalisasi status lama ke status alur resmi. */
export const normalizeStatus = (status) => LEGACY[status] || status;

const STATUS_META = {
  draft: { label: 'Draf', cls: 'bg-slate-200 text-slate-700 border border-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700' },
  monitoring: { label: 'Perlu Telaah', cls: 'bg-sky-500/15 text-sky-700 border border-sky-500/40 dark:text-sky-300' },
  investigasi: { label: 'Dalam Penanganan', cls: 'bg-amber-400/20 text-amber-700 border border-amber-400/50 dark:text-amber-300' },
  klarifikasi: { label: 'Klarifikasi', cls: 'bg-violet-500/15 text-violet-700 border border-violet-500/40 dark:text-violet-300' },
  selesai: { label: 'Selesai', cls: 'bg-emerald-500/15 text-emerald-700 border border-emerald-500/40 dark:text-emerald-300' },
  arsip: { label: 'Arsip', cls: 'bg-slate-200 text-slate-600 border border-slate-300 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700' },
};

export const statusMeta = (status) => STATUS_META[normalizeStatus(status)] || { label: status || '-', cls: STATUS_META.draft.cls };

/** Indeks tahap (0..4) untuk stepper; draft = -1. */
export const stepIndex = (status) => ISSUE_STEPS.findIndex((s) => s.key === normalizeStatus(status));

/** Tab/kelompok filter daftar laporan (kunci sama dengan `Issue::STATUS_GROUPS`). */
export const ISSUE_GROUPS = [
  { key: 'all', label: 'Semua' },
  { key: 'pending', label: 'Perlu Telaah' },
  { key: 'active', label: 'Dalam Penanganan' },
  { key: 'clarified', label: 'Klarifikasi' },
  { key: 'done', label: 'Selesai' },
];

const PRIORITY_META = {
  critical: { label: 'Kritis', cls: 'bg-rose-500 text-white font-black' },
  urgent: { label: 'Mendesak', cls: 'bg-rose-500 text-white font-black' },
  high: { label: 'Tinggi', cls: 'bg-amber-400 text-slate-950 font-black' },
  medium: { label: 'Sedang', cls: 'bg-sky-500/15 text-sky-700 border border-sky-500/40 dark:text-sky-300' },
  low: { label: 'Rendah', cls: 'bg-slate-200 text-slate-700 border border-slate-300 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700' },
};

export const priorityMeta = (priority) => PRIORITY_META[priority] || { label: priority || '-', cls: PRIORITY_META.low.cls };

/** Status tugas (tabel tasks). */
export const taskStatusMeta = (status) => (
  status === 'completed'
    ? { label: 'Selesai', cls: 'bg-emerald-500/15 text-emerald-700 border border-emerald-500/40 dark:text-emerald-300' }
    : { label: 'Berjalan', cls: 'bg-sky-500/15 text-sky-700 border border-sky-500/40 dark:text-sky-300' }
);

/** True bila tenggat sudah lewat dan tugas belum selesai. */
export const isOverdue = (task) => !!task?.due_at && task.status !== 'completed' && new Date(task.due_at).getTime() < Date.now();

export const formatWitaDate = (value) => {
  if (!value) return '-';
  try {
    return new Date(value).toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) + ' WITA';
  } catch {
    return String(value);
  }
};
