<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import {
  ArrowLeft,
  Save,
  Send,
  UploadCloud,
  FileText,
  AlertTriangle,
  Clock,
  MapPin,
  Building2,
  CheckCircle2,
  X,
  Link2,
  Image as ImageIcon,
  Share2,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  polresList: Array,
  metrics: Object,
});

const isSubmitting = ref(false);
const isNational = computed(() => props.selectedCode === 'ALL' || props.selectedPolda?.code === 'DIVHUMAS');

const categories = [
  {
    id: '🚨 Kejadian / Insiden Lapangan',
    title: '🚨 Kejadian / Insiden Lapangan',
    short: 'Kejadian Lapangan',
    desc: 'Gangguan kamtibmas, laka lantas menonjol, bencana, atau kejadian fisik lapangan.',
  },
  {
    id: '🛡️ Kegiatan Positif Kepolisian',
    title: '🛡️ Kegiatan Positif Kepolisian',
    short: 'Kegiatan Positif',
    desc: 'Bakti sosial, Jumat Curhat, patroli dialogis, prestasi, pengamanan kegiatan.',
  },
  {
    id: '🗣️ Keluhan Warga (Dumas / Pelayanan)',
    title: '🗣️ Keluhan Warga (Dumas / Pelayanan)',
    short: 'Keluhan Warga',
    desc: 'Komplain pelayanan SIM/SKCK, aduan perilaku anggota, kendala birokrasi.',
  },
  {
    id: '📱 Isu Medsos & Opini Publik (Cyber Patrol)',
    title: '📱 Isu Medsos & Opini Publik (Cyber Patrol)',
    short: 'Isu Medsos & Opini',
    desc: 'Viralisasi medsos, disinformasi/hoaks, framing sentimen negatif institusi.',
  },
];

const priorities = [
  {
    id: 'low',
    name: '🟢 Rendah (Kegiatan Rutin)',
    desc: 'Monitoring berkala, situasi kondusif.',
    colorClass: 'border-slate-300 dark:border-slate-700 hover:border-emerald-500',
    activeClass: 'ring-2 ring-emerald-500 bg-emerald-500/10 border-emerald-500',
  },
  {
    id: 'medium',
    name: '🟡 Sedang (Perlu Perhatian / Pantau)',
    desc: 'Ada dinamika isu, perlu telaah verifikasi.',
    colorClass: 'border-slate-300 dark:border-slate-700 hover:border-amber-400',
    activeClass: 'ring-2 ring-amber-400 bg-amber-400/10 border-amber-400',
  },
  {
    id: 'high',
    name: '🔴 Tinggi (Mendesak / Atensi Pimpinan)',
    desc: 'EWS Siaga! Eskalasi cepat, potensi viral nasional.',
    colorClass: 'border-slate-300 dark:border-slate-700 hover:border-rose-500',
    activeClass: 'ring-2 ring-rose-500 bg-rose-500/10 border-rose-500',
  },
];

const urlParams = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : null;

const form = ref({
  polda_id: props.selectedPolda?.id || props.allPoldas?.[0]?.id || 1,
  polres_id: '',
  title: urlParams?.get('title') || '',
  category: urlParams?.get('category') || '🚨 Kejadian / Insiden Lapangan',
  priority: urlParams?.get('priority') || 'medium',
  occurred_at: new Date().toISOString().slice(0, 16),
  location_text: urlParams?.get('location_text') || urlParams?.get('location') || '',
  summary: urlParams?.get('summary') || '',
  source_media: urlParams?.get('source_media') || 'Laporan Lapangan',
  source_account: urlParams?.get('source_account') || '',
  source_url: urlParams?.get('source_url') || '',
  assigned_unit: 'Bidhumas Polda',
});

const rawFiles = ref([]);
const filePreviews = ref([]);

const handleFileSelect = (e) => {
  const selected = Array.from(e.target.files || []);
  for (const f of selected) {
    if (rawFiles.value.length < 5) {
      rawFiles.value.push(f);
      filePreviews.value.push({
        name: f.name,
        size: (f.size / (1024 * 1024)).toFixed(2) + ' MB',
        url: URL.createObjectURL(f),
      });
    }
  }
};

const removeFile = (index) => {
  rawFiles.value.splice(index, 1);
  filePreviews.value.splice(index, 1);
};

const submitReport = (targetStatus = 'submitted') => {
  if (!form.value.title || !form.value.summary) {
    alert('Mohon isi Judul Laporan dan Uraian Kronologi Kejadian.');
    return;
  }

  isSubmitting.value = true;

  const formData = new FormData();
  formData.append('polda_id', form.value.polda_id);
  if (form.value.polres_id) {
    formData.append('polres_id', form.value.polres_id);
  }
  formData.append('title', form.value.title);
  formData.append('category', form.value.category);
  formData.append('priority', form.value.priority);
  formData.append('occurred_at', form.value.occurred_at);
  formData.append('location_text', form.value.location_text || '-');
  formData.append('summary', form.value.summary);
  formData.append('source_media', form.value.source_media || 'Laporan Lapangan');
  formData.append('source_url', form.value.source_url || '');
  formData.append('assigned_unit', form.value.assigned_unit || 'Bidhumas Polda');
  formData.append('sentiment', 'netral');
  formData.append('status', targetStatus);

  for (let i = 0; i < rawFiles.value.length; i++) {
    formData.append(`files[${i}]`, rawFiles.value[i]);
  }

  router.post('/issues', formData, {
    forceFormData: true,
    onSuccess: () => {
      isSubmitting.value = false;
    },
    onError: (errors) => {
      isSubmitting.value = false;
      console.error(errors);
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
    <!-- Top Action Bar & Title (Standardized with SIMOD-HUMAS) -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div class="flex items-center gap-3">
        <Link
          :href="`/issues?polda=${selectedCode}`"
          class="p-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-900 dark:hover:bg-slate-800 dark:border-slate-700 dark:text-slate-300 border border-slate-300 transition"
          title="Kembali ke Daftar Laporan"
        >
          <ArrowLeft class="w-4 h-4" />
        </Link>
        <div>
          <div class="flex items-center gap-2">
            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-400 text-slate-950">
              Formulir Operasional
            </span>
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400">
              {{ selectedCode === 'ALL' ? 'Mabes Polri' : selectedPolda?.name }}
            </span>
          </div>
          <h1 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white tracking-tight mt-1">
            Buat Laporan Baru
          </h1>
          <p class="text-xs md:text-sm text-slate-600 dark:text-slate-300">
            Formulir pelaporan operasional dokumentasi & pemantauan opini publik {{ isNational ? 'Divisi Humas Polri (Mabes Polri)' : (selectedPolda?.name || 'Humas Polda Sulbar') }}.
          </p>
        </div>
      </div>

      <!-- Action Buttons Top -->
      <div class="flex items-center gap-2.5">
        <Link
          :href="`/issues?polda=${selectedCode}`"
          class="px-4 py-2.5 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-900 dark:hover:bg-slate-800 dark:border-slate-700 dark:text-slate-300 border border-slate-300 transition"
        >
          Batal
        </Link>

        <button
          @click="submitReport('draft')"
          type="button"
          :disabled="isSubmitting || !form.title || !form.summary"
          class="px-4 py-2.5 rounded-xl text-xs font-bold bg-slate-200 hover:bg-slate-300 text-slate-800 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-700 transition disabled:opacity-40 cursor-pointer"
        >
          <span>Simpan Draf</span>
        </button>

        <button
          @click="submitReport('submitted')"
          type="button"
          :disabled="isSubmitting || !form.title || !form.summary"
          class="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black shadow-lg shadow-amber-400/25 transition disabled:opacity-40 cursor-pointer"
        >
          <Send class="w-4 h-4 text-slate-950" />
          <span>{{ isSubmitting ? 'Mengirim...' : 'Kirim ke Verifikasi' }}</span>
        </button>
      </div>
    </div>

    <!-- Main Form Grid -->
    <div class="space-y-6">
      <!-- Section 1: Informasi Pokok Laporan -->
      <div class="rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 p-6 md:p-8 shadow-sm space-y-6">
        <div class="flex items-center gap-2.5 border-b border-slate-200 dark:border-slate-800 pb-4">
          <div class="w-8 h-8 rounded-xl bg-amber-100 dark:bg-amber-400/10 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold">
            <FileText class="w-4 h-4" />
          </div>
          <div>
            <h2 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-wider">
              INFORMASI POKOK LAPORAN
            </h2>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">
              Lengkapi identitas laporan, klasifikasi kategori, dan waktu temuan kejadian
            </p>
          </div>
        </div>

        <div class="space-y-5 text-xs">
          <!-- Kesatuan Wilayah / Satker Pelapor -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block font-black text-slate-800 dark:text-slate-200 mb-1.5 uppercase tracking-wider text-[11px]">
                POLDA PENUGASAN <span class="text-rose-500">*</span>
              </label>
              <div class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-bold">
                {{ selectedPolda?.name || 'Polda Sulawesi Barat' }}
              </div>
            </div>

            <div>
              <label class="block font-black text-slate-800 dark:text-slate-200 mb-1.5 uppercase tracking-wider text-[11px]">
                SATWIL / POLRES PELAPOR
              </label>
              <select
                v-model="form.polres_id"
                class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white font-bold outline-none focus:border-amber-400"
              >
                <option value="">Mapolda (Bidhumas Polda)</option>
                <option v-for="pol in polresList" :key="pol.id" :value="pol.id">
                  {{ pol.name }}
                </option>
              </select>
            </div>
          </div>

          <!-- Judul Laporan -->
          <div>
            <label class="block font-black text-slate-800 dark:text-slate-200 mb-1.5 uppercase tracking-wider text-[11px]">
              JUDUL LAPORAN <span class="text-rose-500">*</span>
            </label>
            <input
              v-model="form.title"
              type="text"
              required
              placeholder="Contoh: Beredar video komplain antrean solar di SPBU Simbuang Mamuju"
              class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white font-bold placeholder-slate-400 focus:outline-none focus:border-amber-400 text-sm"
            />
          </div>

          <!-- Kategori Laporan (Grid 4 Kartu Pilihan Visual) -->
          <div>
            <label class="block font-black text-slate-800 dark:text-slate-200 mb-2 uppercase tracking-wider text-[11px]">
              KATEGORI LAPORAN <span class="text-rose-500">*</span>
            </label>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
              <div
                v-for="cat in categories"
                :key="cat.id"
                @click="form.category = cat.id"
                class="p-4 rounded-2xl border cursor-pointer transition select-none flex flex-col justify-between space-y-2"
                :class="form.category === cat.id
                  ? 'bg-amber-50 dark:bg-amber-400/10 border-amber-400 ring-2 ring-amber-400'
                  : 'bg-slate-50 dark:bg-slate-900/60 border-slate-200 dark:border-slate-800 hover:border-slate-400 dark:hover:border-slate-700'"
              >
                <div>
                  <h4 class="font-black text-slate-900 dark:text-white text-xs leading-snug">
                    {{ cat.title }}
                  </h4>
                  <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                    {{ cat.desc }}
                  </p>
                </div>
                <div class="flex items-center justify-end pt-1">
                  <span
                    class="w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold transition"
                    :class="form.category === cat.id ? 'bg-amber-400 text-slate-950' : 'border border-slate-400 text-transparent'"
                  >
                    ✓
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Usulan Prioritas (Grid 3 Kartu Radio) -->
          <div>
            <label class="block font-black text-slate-800 dark:text-slate-200 mb-2 uppercase tracking-wider text-[11px]">
              USULAN PRIORITAS ATENSI
            </label>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div
                v-for="p in priorities"
                :key="p.id"
                @click="form.priority = p.id"
                class="p-4 rounded-2xl border cursor-pointer transition select-none flex flex-col justify-between space-y-1.5"
                :class="form.priority === p.id ? p.activeClass : p.colorClass + ' bg-slate-50 dark:bg-slate-900/60'"
              >
                <div>
                  <h4 class="font-black text-slate-900 dark:text-white text-xs">
                    {{ p.name }}
                  </h4>
                  <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ p.desc }}
                  </p>
                </div>
                <div class="flex items-center justify-end">
                  <span
                    class="w-4 h-4 rounded-full flex items-center justify-center text-[10px] font-bold"
                    :class="form.priority === p.id ? 'bg-amber-400 text-slate-950' : 'border border-slate-400 text-transparent'"
                  >
                    ✓
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Waktu & Lokasi Kejadian -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block font-black text-slate-800 dark:text-slate-200 mb-1.5 uppercase tracking-wider text-[11px]">
                WAKTU KEJADIAN / TEMUAN (WITA) <span class="text-rose-500">*</span>
              </label>
              <input
                v-model="form.occurred_at"
                type="datetime-local"
                required
                class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white font-mono font-bold focus:outline-none focus:border-amber-400"
              />
            </div>

            <div>
              <label class="block font-black text-slate-800 dark:text-slate-200 mb-1.5 uppercase tracking-wider text-[11px]">
                LOKASI KEJADIAN (DESKRIPTIF)
              </label>
              <input
                v-model="form.location_text"
                type="text"
                placeholder="Contoh: SPBU Simbuang, Jl. Jend. Sudirman, Mamuju"
                class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-amber-400 font-semibold"
              />
            </div>
          </div>

          <!-- Uraian Kronologi -->
          <div>
            <label class="block font-black text-slate-800 dark:text-slate-200 mb-1.5 uppercase tracking-wider text-[11px]">
              URAIAN KRONOLOGI / DESKRIPSI KEJADIAN <span class="text-rose-500">*</span>
            </label>
            <textarea
              v-model="form.summary"
              rows="6"
              required
              placeholder="Uraikan fakta kejadian secara objektif mencakup kronologi (5W+1H), pihak yang terlibat, perkembangan terkini, potensi eskalasi opini publik, serta rekomendasi awal penanganan oleh pimpinan..."
              class="w-full p-4 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-amber-400 leading-relaxed font-normal"
            ></textarea>
          </div>
        </div>
      </div>

      <!-- Section 2: Sumber Media & Medsos (Cyber Patrol) -->
      <div class="rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 p-6 md:p-8 shadow-sm space-y-4">
        <div class="flex items-center gap-2.5 border-b border-slate-200 dark:border-slate-800 pb-4">
          <div class="w-8 h-8 rounded-xl bg-sky-100 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center font-bold">
            <Share2 class="w-4 h-4" />
          </div>
          <div>
            <h2 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-wider">
              SUMBER MEDIA & OPINI PUBLIK (OPSIONAL)
            </h2>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">
              Isi bagian ini jika laporan bersumber dari patroli siber medsos atau portal berita
            </p>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1.5">
              Platform / Jenis Sumber
            </label>
            <select
              v-model="form.source_media"
              class="w-full px-3 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white font-bold outline-none focus:border-amber-400"
            >
              <option value="Laporan Lapangan">Laporan Langsung Anggota Lapangan</option>
              <option value="Instagram">Instagram</option>
              <option value="TikTok">TikTok</option>
              <option value="Facebook">Facebook</option>
              <option value="X (Twitter)">X (Twitter)</option>
              <option value="YouTube">YouTube</option>
              <option value="Portal Berita Online">Portal Berita Online</option>
              <option value="Aduan Dumas / SPKT">Aduan Dumas / SPKT</option>
            </select>
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1.5">
              Akun Pengunggah / Nama Portal
            </label>
            <input
              v-model="form.source_account"
              type="text"
              placeholder="Contoh: @wargamamuju / Detikcom"
              class="w-full px-3 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-amber-400 font-semibold"
            />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1.5">
              Tautan / URL Sumber
            </label>
            <input
              v-model="form.source_url"
              type="url"
              placeholder="https://instagram.com/p/..."
              class="w-full px-3 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-amber-400 font-semibold"
            />
          </div>
        </div>
      </div>

      <!-- Section 3: Unggah Dokumentasi Foto & Bukti Kejadian -->
      <div class="rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 p-6 md:p-8 shadow-sm space-y-4">
        <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-4">
          <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-xl bg-emerald-100 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold">
              <UploadCloud class="w-4 h-4" />
            </div>
            <div>
              <h2 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-wider">
                UNGGAH DOKUMENTASI FOTO / BUKTI KEJADIAN
              </h2>
              <p class="text-[11px] text-slate-500 dark:text-slate-400">
                Unggah foto dokumentasi penanganan kejadian di lapangan atau tangkapan layar (screenshot) medsos
              </p>
            </div>
          </div>

          <span class="text-xs font-black px-3 py-1 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700">
            {{ rawFiles.length }} / 5 Berkas Terpilih
          </span>
        </div>

        <!-- Drag & Drop Area -->
        <label
          class="border-2 border-dashed border-slate-300 dark:border-slate-700 hover:border-amber-400 rounded-3xl p-8 flex flex-col items-center justify-center text-center cursor-pointer transition bg-slate-50 dark:bg-slate-900/40 group select-none"
        >
          <input
            type="file"
            multiple
            accept="image/*"
            class="hidden"
            @change="handleFileSelect"
          />
          <UploadCloud class="w-10 h-10 text-slate-400 group-hover:text-amber-500 transition mb-3" />
          <p class="text-xs font-bold text-slate-800 dark:text-white">
            Klik atau Seret Berkas Foto / Tangkapan Layar ke Sini
          </p>
          <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
            Format JPG, PNG, atau WEBP (Maksimal 10 MB per foto, hingga 5 berkas sekaligus)
          </p>
        </label>

        <!-- Preview Uploaded Files -->
        <div v-if="filePreviews.length > 0" class="grid grid-cols-2 sm:grid-cols-5 gap-3 pt-2">
          <div
            v-for="(file, idx) in filePreviews"
            :key="idx"
            class="relative rounded-2xl bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-2 overflow-hidden group shadow-sm"
          >
            <img
              :src="file.url"
              :alt="file.name"
              class="w-full h-24 object-cover rounded-xl"
            />
            <div class="pt-2 text-[10px]">
              <p class="font-bold text-slate-800 dark:text-white truncate">{{ file.name }}</p>
              <p class="text-slate-500 dark:text-slate-400">{{ file.size }}</p>
            </div>
            <button
              @click.prevent="removeFile(idx)"
              type="button"
              class="absolute top-3 right-3 w-6 h-6 rounded-full bg-rose-600 text-white flex items-center justify-center text-xs shadow-md hover:bg-rose-500 transition cursor-pointer"
              title="Hapus foto ini"
            >
              <X class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
      </div>

      <!-- Bottom Action Bar -->
      <div class="flex items-center justify-between p-6 rounded-3xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 shadow-sm">
        <Link
          :href="`/issues?polda=${selectedCode}`"
          class="px-5 py-2.5 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-900 dark:hover:bg-slate-800 dark:border-slate-700 dark:text-slate-300 border border-slate-300 transition"
        >
          Batal
        </Link>

        <div class="flex items-center gap-3">
          <button
            @click="submitReport('draft')"
            type="button"
            :disabled="isSubmitting || !form.title || !form.summary"
            class="px-5 py-2.5 rounded-xl text-xs font-bold bg-slate-200 hover:bg-slate-300 text-slate-800 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-700 transition disabled:opacity-40 cursor-pointer"
          >
            <span>Simpan Draf Laporan</span>
          </button>

          <button
            @click="submitReport('submitted')"
            type="button"
            :disabled="isSubmitting || !form.title || !form.summary"
            class="flex items-center gap-2 px-6 py-2.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black shadow-lg shadow-amber-400/25 transition disabled:opacity-40 cursor-pointer"
          >
            <Send class="w-4 h-4 text-slate-950" />
            <span>{{ isSubmitting ? 'Memproses...' : 'Kirim Laporan / Ajukan Verifikasi' }}</span>
          </button>
        </div>
      </div>
    </div>
  </EnterpriseLayout>
</template>
