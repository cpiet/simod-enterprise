<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import {
  Image as ImageIcon,
  Video,
  FileText,
  Filter,
  Eye,
  Download,
  UploadCloud,
  Search,
  HardDrive,
  Camera,
  FolderClosed,
  Shield,
  Clock,
  MapPin,
  CheckCircle2,
  X,
  ExternalLink,
  Lock,
  Sparkles,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  mediaFiles: Object, // Paginated { data: [...], links: [...] }
  polresList: Array,
  filters: Object,
  galleryStats: Object,
  metrics: Object,
});

// Filter states
const search = ref(props.filters?.q || '');
const selectedFormat = ref(props.filters?.format || 'all');
const selectedPolres = ref(props.filters?.polres || 'all');

// Modals
const isLightboxOpen = ref(false);
const activeMedia = ref(null);
const isUploadModalOpen = ref(false);

// Upload form
const uploadForm = ref({
  polda_id: props.selectedPolda?.id || 1,
  polres_id: '',
  title: '',
  caption: '',
  file_type: 'photo',
  file_url: '',
  is_private: true,
});
const isSubmitting = ref(false);

const applyFilters = () => {
  router.get('/gallery', {
    polda: props.selectedCode,
    format: selectedFormat.value,
    polres: selectedPolres.value,
    q: search.value,
  }, {
    preserveState: true,
    preserveScroll: true,
  });
};

const setFormat = (fmt) => {
  selectedFormat.value = fmt;
  applyFilters();
};

const openLightbox = (item) => {
  activeMedia.value = item;
  isLightboxOpen.value = true;
};

const closeLightbox = () => {
  isLightboxOpen.value = false;
  activeMedia.value = null;
};

const submitUpload = () => {
  if (!uploadForm.value.title) {
    alert('Judul media dokumentasi wajib diisi.');
    return;
  }
  isSubmitting.value = true;
  router.post('/gallery', uploadForm.value, {
    preserveScroll: true,
    onSuccess: () => {
      isSubmitting.value = false;
      isUploadModalOpen.value = false;
      uploadForm.value = {
        polda_id: props.selectedPolda?.id || 1,
        polres_id: '',
        title: '',
        caption: '',
        file_type: 'photo',
        file_url: '',
        is_private: true,
      };
    },
    onError: () => {
      isSubmitting.value = false;
    },
  });
};

const formatBytes = (bytes) => {
  if (!bytes || bytes === 0) return '1.2 MB';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
};

const formatDateWita = (dateStr) => {
  if (!dateStr) return '28 Okt 2026, 14:00 WITA';
  try {
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }) + ' WITA';
  } catch (e) {
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
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
            Penyimpanan Terproteksi
          </span>
          <span class="text-xs font-bold text-slate-400">{{ selectedPolda?.name || 'Polda Sulawesi Barat' }}</span>
        </div>
        <h1 class="text-2xl md:text-3xl font-black text-white tracking-tight mt-1">
          Galeri Dokumentasi Media & Alat Bukti
        </h1>
        <p class="text-xs md:text-sm text-slate-300">
          Repositori foto kegiatan dinas, video peliputan lapangan, dan alat bukti digital Humas Polri multi-satwil.
        </p>
      </div>

      <div class="flex items-center gap-3">
        <button
          @click="isUploadModalOpen = true"
          class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-400 hover:brightness-110 text-slate-950 font-black text-xs shadow-lg shadow-amber-500/20 transition"
        >
          <UploadCloud class="w-4 h-4" />
          <span>Unggah Berkas Baru</span>
        </button>
      </div>
    </div>

    <!-- 4 Stats Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="p-4 sm:p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <div class="flex items-center justify-between">
          <span class="text-[10px] sm:text-[11px] font-black uppercase tracking-wider text-slate-400">Total Berkas</span>
          <FolderClosed class="w-4 h-4 text-amber-400" />
        </div>
        <div class="text-2xl sm:text-3xl font-black text-white">
          {{ galleryStats?.total_media || mediaFiles?.total || 0 }}
        </div>
        <p class="text-[10px] sm:text-[11px] text-slate-400">Foto & Video Terarsip</p>
      </div>

      <div class="p-4 sm:p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <div class="flex items-center justify-between">
          <span class="text-[10px] sm:text-[11px] font-black uppercase tracking-wider text-slate-400">Foto Dokumentasi</span>
          <Camera class="w-4 h-4 text-emerald-400" />
        </div>
        <div class="text-2xl sm:text-3xl font-black text-emerald-400">
          {{ galleryStats?.total_photos || 0 }}
        </div>
        <p class="text-[10px] sm:text-[11px] text-slate-400">Giat Harkamtibmas</p>
      </div>

      <div class="p-4 sm:p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <div class="flex items-center justify-between">
          <span class="text-[10px] sm:text-[11px] font-black uppercase tracking-wider text-slate-400">Video Peliputan</span>
          <Video class="w-4 h-4 text-sky-400" />
        </div>
        <div class="text-2xl sm:text-3xl font-black text-sky-400">
          {{ galleryStats?.total_videos || 0 }}
        </div>
        <p class="text-[10px] sm:text-[11px] text-slate-400">Rekaman Operasional</p>
      </div>

      <div class="p-4 sm:p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-xl space-y-1">
        <div class="flex items-center justify-between">
          <span class="text-[10px] sm:text-[11px] font-black uppercase tracking-wider text-slate-400">Kapasitas Arsip</span>
          <HardDrive class="w-4 h-4 text-amber-400" />
        </div>
        <div class="text-2xl sm:text-3xl font-black text-white">
          {{ galleryStats?.total_size_mb || 14.8 }} <span class="text-sm font-bold text-slate-400">MB</span>
        </div>
        <p class="text-[10px] sm:text-[11px] text-emerald-400 font-semibold">Tersimpan Aman (AES)</p>
      </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="p-4 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-xl flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
      <!-- Format Pills -->
      <div class="flex items-center gap-1.5 overflow-x-auto pb-1 md:pb-0">
        <button
          @click="setFormat('all')"
          class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition whitespace-nowrap"
          :class="selectedFormat === 'all'
            ? 'bg-amber-400 text-slate-950 font-black shadow-md shadow-amber-400/20'
            : 'bg-slate-900 text-slate-300 hover:text-white border border-slate-800'"
        >
          Semua Format
        </button>
        <button
          @click="setFormat('photo')"
          class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold transition whitespace-nowrap"
          :class="selectedFormat === 'photo'
            ? 'bg-amber-400 text-slate-950 font-black'
            : 'bg-slate-900 text-slate-300 hover:text-white border border-slate-800'"
        >
          <Camera class="w-3.5 h-3.5" />
          <span>Foto Dokumentasi</span>
        </button>
        <button
          @click="setFormat('video')"
          class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold transition whitespace-nowrap"
          :class="selectedFormat === 'video'
            ? 'bg-amber-400 text-slate-950 font-black'
            : 'bg-slate-900 text-slate-300 hover:text-white border border-slate-800'"
        >
          <Video class="w-3.5 h-3.5" />
          <span>Rekaman Video</span>
        </button>
      </div>

      <!-- Satwil & Search -->
      <div class="flex flex-col sm:flex-row items-center gap-2">
        <div class="w-full sm:w-48">
          <select
            v-model="selectedPolres"
            @change="applyFilters"
            class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-xs text-white focus:outline-none focus:border-amber-400"
          >
            <option value="all">Semua Satker / Polres</option>
            <option v-for="p in polresList" :key="p.id" :value="p.code">
              {{ p.name }}
            </option>
          </select>
        </div>

        <div class="relative w-full sm:w-64">
          <Search class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <input
            v-model="search"
            @keyup.enter="applyFilters"
            type="text"
            placeholder="Cari nama berkas, giat..."
            class="w-full pl-9 pr-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-amber-400"
          />
        </div>
      </div>
    </div>

    <!-- Gallery Media Grid -->
    <div v-if="mediaFiles?.data && mediaFiles.data.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
      <div
        v-for="item in mediaFiles.data"
        :key="item.id"
        class="group rounded-3xl bg-[#0b1426] border border-slate-800 overflow-hidden shadow-xl hover:border-slate-600 transition flex flex-col justify-between"
      >
        <!-- Media Preview Area -->
        <div class="relative h-48 overflow-hidden bg-slate-950 cursor-pointer" @click="openLightbox(item)">
          <!-- Video or Image -->
          <img
            v-if="item.file_type === 'photo' || !item.file_url?.includes('.mp4')"
            :src="item.file_url || 'https://images.unsplash.com/photo-1541872703-74c5e44368f9?w=600&auto=format&fit=crop&q=80'"
            :alt="item.title"
            class="w-full h-full object-cover group-hover:scale-105 transition duration-500"
          />
          <div v-else class="w-full h-full bg-slate-900 flex items-center justify-center relative">
            <Video class="w-12 h-12 text-sky-400/80 group-hover:scale-110 transition" />
            <span class="absolute bottom-3 left-3 text-[10px] font-mono text-slate-300 bg-slate-950/80 px-2 py-0.5 rounded">
              Video Operasional MP4
            </span>
          </div>

          <!-- Top Badges -->
          <div class="absolute top-3 left-3 right-3 flex items-center justify-between">
            <span
              v-if="item.polres"
              class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-slate-950/80 text-amber-400 border border-slate-700 backdrop-blur-md"
            >
              {{ item.polres.name }}
            </span>
            <span
              v-else
              class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-slate-950/80 text-amber-400 border border-slate-700 backdrop-blur-md"
            >
              Polda Sulbar
            </span>

            <span
              v-if="item.is_private"
              class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-rose-500/80 text-white flex items-center gap-1 shadow"
            >
              <Lock class="w-2.5 h-2.5" /> Bukti
            </span>
            <span
              v-else
              class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-emerald-500/80 text-white flex items-center gap-1 shadow"
            >
              <CheckCircle2 class="w-2.5 h-2.5" /> Publik
            </span>
          </div>

          <!-- Hover Overlay -->
          <div class="absolute inset-0 bg-slate-950/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2">
            <button
              class="w-10 h-10 rounded-full bg-amber-400 text-slate-950 flex items-center justify-center shadow-lg hover:scale-110 transition"
              title="Perbesar / Lightbox"
            >
              <Eye class="w-5 h-5" />
            </button>
          </div>
        </div>

        <!-- Details -->
        <div class="p-4 space-y-2.5 flex-1 flex flex-col justify-between">
          <div class="space-y-1">
            <h3 class="text-sm font-extrabold text-white leading-snug line-clamp-2" :title="item.title">
              {{ item.title }}
            </h3>
            <p v-if="item.caption" class="text-xs text-slate-300 line-clamp-2">
              {{ item.caption }}
            </p>
          </div>

          <!-- Footer Metadata -->
          <div class="pt-2 border-t border-slate-800/80 flex items-center justify-between text-[11px] text-slate-400">
            <span class="font-mono">{{ formatDateWita(item.created_at) }}</span>
            <span class="font-bold text-slate-300">{{ formatBytes(item.size_bytes) }}</span>
          </div>

          <!-- Linked Issue pill if available -->
          <div v-if="item.issue" class="pt-1">
            <Link
              :href="`/issues/${item.issue.id}?polda=${selectedCode}`"
              class="inline-flex items-center gap-1 text-[10px] font-semibold text-amber-400 hover:text-amber-300 truncate max-w-full"
            >
              <ExternalLink class="w-3 h-3 flex-shrink-0" />
              <span class="truncate">Terkait: {{ item.issue.title }}</span>
            </Link>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div
      v-else
      class="rounded-3xl bg-[#0b1426] border border-slate-800 p-12 text-center shadow-xl space-y-3"
    >
      <div class="w-16 h-16 rounded-2xl bg-slate-900 border border-slate-800 text-slate-400 flex items-center justify-center mx-auto">
        <ImageIcon class="w-8 h-8" />
      </div>
      <h3 class="text-base font-extrabold text-white">Belum Ada Berkas Media di Galeri</h3>
      <p class="text-xs text-slate-400 max-w-md mx-auto">
        Media foto kegiatan dan rekaman video yang diunggah personel lapangan atau admin Polda akan muncul di sini secara terpusat.
      </p>
      <button
        @click="isUploadModalOpen = true"
        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-400 text-slate-950 text-xs font-black hover:bg-amber-300 transition"
      >
        <UploadCloud class="w-4 h-4" />
        <span>Unggah Berkas Sekarang</span>
      </button>
    </div>

    <!-- Pagination Links -->
    <div v-if="mediaFiles?.links && mediaFiles.links.length > 3" class="flex items-center justify-center gap-1.5 pt-4">
      <Link
        v-for="(link, i) in mediaFiles.links"
        :key="i"
        :href="link.url || '#'"
        class="px-3 py-1.5 rounded-xl text-xs font-bold transition border"
        :class="link.active
          ? 'bg-amber-400 text-slate-950 border-amber-400 font-black'
          : link.url
            ? 'bg-slate-900 text-slate-300 border-slate-800 hover:text-white hover:border-slate-700'
            : 'bg-slate-950 text-slate-600 border-slate-900 pointer-events-none'"
        v-html="link.label"
      />
    </div>

    <!-- ========================================================================= -->
    <!-- LIGHTBOX PREVIEW MODAL                                                    -->
    <!-- ========================================================================= -->
    <div
      v-if="isLightboxOpen && activeMedia"
      class="fixed inset-0 z-50 bg-black/85 backdrop-blur-md flex items-center justify-center p-4 sm:p-6"
      @click.self="closeLightbox"
    >
      <div class="relative w-full max-w-4xl bg-[#091122] border border-slate-800 rounded-3xl overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
        <!-- Lightbox Header -->
        <div class="px-6 py-4 border-b border-slate-800 bg-[#0b1426] flex items-center justify-between">
          <div class="flex items-center gap-2.5">
            <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-amber-400 text-slate-950">
              {{ activeMedia.file_type }}
            </span>
            <h3 class="text-sm font-extrabold text-white truncate max-w-md">{{ activeMedia.title }}</h3>
          </div>
          <button
            @click="closeLightbox"
            class="w-8 h-8 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center transition"
          >
            <X class="w-4 h-4" />
          </button>
        </div>

        <!-- Lightbox Content -->
        <div class="p-6 overflow-y-auto space-y-4">
          <div class="rounded-2xl overflow-hidden bg-black flex items-center justify-center max-h-[50vh]">
            <img
              v-if="activeMedia.file_type === 'photo' || !activeMedia.file_url?.includes('.mp4')"
              :src="activeMedia.file_url"
              :alt="activeMedia.title"
              class="w-full h-auto max-h-[50vh] object-contain"
            />
            <video
              v-else
              :src="activeMedia.file_url"
              controls
              autoplay
              class="w-full h-auto max-h-[50vh]"
            ></video>
          </div>

          <!-- Metadata Grid -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs bg-slate-950/60 p-4 rounded-2xl border border-slate-800/80">
            <div>
              <span class="text-slate-400 block text-[11px]">Keterangan / Narasi:</span>
              <p class="text-slate-200 font-medium mt-0.5">{{ activeMedia.caption || 'Dokumentasi resmi operasi Humas Polri.' }}</p>
            </div>
            <div>
              <span class="text-slate-400 block text-[11px]">Satwil & Lokasi:</span>
              <p class="text-amber-400 font-bold mt-0.5">{{ activeMedia.polres?.name || 'Polda Sulawesi Barat' }}</p>
            </div>
            <div>
              <span class="text-slate-400 block text-[11px]">Waktu Unggah:</span>
              <p class="text-white font-mono mt-0.5">{{ formatDateWita(activeMedia.created_at) }}</p>
            </div>
            <div>
              <span class="text-slate-400 block text-[11px]">Ukuran Berkas:</span>
              <p class="text-white font-mono mt-0.5">{{ formatBytes(activeMedia.size_bytes) }}</p>
            </div>
          </div>
        </div>

        <!-- Lightbox Footer -->
        <div class="px-6 py-3.5 border-t border-slate-800 bg-[#0b1426] flex items-center justify-between">
          <span class="text-[11px] text-slate-400 font-mono">ID: MED-{{ activeMedia.id }}</span>
          <a
            :href="activeMedia.file_url"
            target="_blank"
            download
            class="flex items-center gap-1.5 px-4 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black transition"
          >
            <Download class="w-4 h-4" />
            <span>Unduh Berkas Asli</span>
          </a>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- UPLOAD MEDIA MODAL                                                        -->
    <!-- ========================================================================= -->
    <div
      v-if="isUploadModalOpen"
      class="fixed inset-0 z-50 bg-black/85 backdrop-blur-md flex items-center justify-center p-4"
      @click.self="isUploadModalOpen = false"
    >
      <div class="relative w-full max-w-lg bg-[#091122] border border-slate-800 rounded-3xl overflow-hidden shadow-2xl flex flex-col">
        <div class="px-6 py-4 border-b border-slate-800 bg-[#0b1426] flex items-center justify-between">
          <div class="flex items-center gap-2">
            <UploadCloud class="w-4 h-4 text-amber-400" />
            <h3 class="text-sm font-extrabold text-white">Unggah Berkas Media Dokumentasi</h3>
          </div>
          <button
            @click="isUploadModalOpen = false"
            class="w-7 h-7 rounded-lg bg-slate-900 hover:bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center"
          >
            <X class="w-4 h-4" />
          </button>
        </div>

        <form @submit.prevent="submitUpload" class="p-6 space-y-4 text-xs">
          <div>
            <label class="block font-bold text-slate-300 mb-1">Judul Dokumentasi *</label>
            <input
              v-model="uploadForm.title"
              type="text"
              required
              placeholder="Contoh: Apel Pengamanan Jalur Pesisir Pasangkayu"
              class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:border-amber-400"
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-300 mb-1">Tipe Format</label>
              <select
                v-model="uploadForm.file_type"
                class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400"
              >
                <option value="photo">Foto Dokumentasi</option>
                <option value="video">Rekaman Video</option>
                <option value="document">Dokumen / PDF</option>
              </select>
            </div>

            <div>
              <label class="block font-bold text-slate-300 mb-1">Satker / Polres</label>
              <select
                v-model="uploadForm.polres_id"
                class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400"
              >
                <option value="">Polda Sulawesi Barat</option>
                <option v-for="p in polresList" :key="p.id" :value="p.id">
                  {{ p.name }}
                </option>
              </select>
            </div>
          </div>

          <div>
            <label class="block font-bold text-slate-300 mb-1">URL Berkas / Media *</label>
            <input
              v-model="uploadForm.file_url"
              type="url"
              required
              placeholder="https://... (URL foto/video)"
              class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:border-amber-400"
            />
            <p class="text-[10px] text-slate-400 mt-1">
              Gunakan tautan aman HTTPS berkas dokumentasi atau hasil upload server TIK.
            </p>
          </div>

          <div>
            <label class="block font-bold text-slate-300 mb-1">Narasi Ringkas / Keterangan</label>
            <textarea
              v-model="uploadForm.caption"
              rows="3"
              placeholder="Jelaskan keterangan kegiatan, waktu, dan personel yang terlibat..."
              class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:border-amber-400"
            ></textarea>
          </div>

          <div class="flex items-center gap-2 pt-1">
            <input
              v-model="uploadForm.is_private"
              type="checkbox"
              id="is_private"
              class="w-4 h-4 rounded bg-slate-900 border-slate-700 text-amber-400 focus:ring-0"
            />
            <label for="is_private" class="text-xs text-slate-300 cursor-pointer">
              Tandai sebagai <strong>Alat Bukti Terenkripsi / Private</strong> (Hanya diakses internal Humas)
            </label>
          </div>

          <div class="pt-3 border-t border-slate-800 flex items-center justify-end gap-2">
            <button
              type="button"
              @click="isUploadModalOpen = false"
              class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-900 dark:hover:bg-slate-800 border border-slate-300 dark:border-slate-700 dark:text-slate-300 font-bold transition"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-5 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-black flex items-center gap-2 transition disabled:opacity-50"
            >
              <UploadCloud class="w-4 h-4" />
              <span>{{ isSubmitting ? 'Menyimpan...' : 'Simpan ke Galeri' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </EnterpriseLayout>
</template>
