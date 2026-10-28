<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import {
  Flame,
  ArrowLeft,
  Save,
  UploadCloud,
  FileText,
  AlertTriangle,
  Clock,
  MapPin,
  Building2,
  CheckCircle2,
  X,
  Link2,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  polresList: Array,
  metrics: Object,
});

const isSubmitting = ref(false);
const selectedPriority = ref('medium');

const form = ref({
  title: '',
  category: '🚨 Kejadian / Insiden Lapangan',
  priority: 'medium',
  polres_id: '',
  location: '',
  occurred_at: new Date().toISOString().slice(0, 16),
  summary: '',
  source_media: 'Laporan Lapangan',
  source_url: '',
  assigned_unit: 'Bidhumas Polda',
});

const uploadedFiles = ref([]);

const handleFileSelect = (e) => {
  const files = Array.from(e.target.files);
  for (const f of files) {
    if (uploadedFiles.value.length < 5) {
      uploadedFiles.value.push({
        name: f.name,
        size: (f.size / (1024 * 1024)).toFixed(2) + ' MB',
      });
    }
  }
};

const removeFile = (index) => {
  uploadedFiles.value.splice(index, 1);
};

const submitForm = () => {
  if (!form.value.title || !form.value.summary) return;
  isSubmitting.value = true;

  const pId = props.selectedPolda?.id || props.allPoldas?.[0]?.id || 1;

  router.post('/issues', {
    polda_id: pId,
    polres_id: form.value.polres_id || null,
    title: form.value.title,
    category: form.value.category,
    priority: form.value.priority,
    sentiment: 'netral',
    summary: `[Lokasi: ${form.value.location || 'Wilayah Polda Sulbar'}] - ${form.value.summary}`,
    source_media: form.value.source_media || 'Laporan Lapangan',
    source_url: form.value.source_url || '',
    assigned_unit: form.value.assigned_unit || 'Bidhumas Polda',
  }, {
    onSuccess: () => {
      isSubmitting.value = false;
      router.get('/issues?polda=' + props.selectedCode);
    },
    onError: () => {
      isSubmitting.value = false;
    }
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
    <!-- Top Action Navigation -->
    <div class="flex items-center justify-between gap-4">
      <div class="flex items-center gap-3">
        <Link
          :href="`/issues?polda=${selectedCode}`"
          class="p-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-300 hover:text-white transition"
          title="Kembali ke Daftar Laporan"
        >
          <ArrowLeft class="w-4 h-4" />
        </Link>
        <div>
          <div class="flex items-center gap-2">
            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-400 text-slate-950">
              Pelaporan Lapangan
            </span>
            <span class="text-xs font-bold text-slate-400">
              {{ selectedCode === 'ALL' ? 'Mabes Polri' : selectedPolda?.name }}
            </span>
          </div>
          <h1 class="text-2xl md:text-3xl font-black text-white tracking-tight mt-1">
            Input Laporan Isu Baru
          </h1>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <Link
          :href="`/issues?polda=${selectedCode}`"
          class="px-4 py-2 rounded-xl bg-slate-900 border border-slate-700 text-xs font-bold text-slate-300 hover:text-white"
        >
          Batal
        </Link>
        <button
          @click="submitForm"
          :disabled="isSubmitting || !form.title || !form.summary"
          class="flex items-center gap-2 px-5 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-amber-400 hover:from-amber-400 hover:to-amber-300 text-slate-950 text-xs font-black shadow-lg shadow-amber-500/20 transition disabled:opacity-50"
        >
          <Save class="w-4 h-4" />
          <span>{{ isSubmitting ? 'Menyimpan...' : 'Simpan & Teruskan Laporan' }}</span>
        </button>
      </div>
    </div>

    <!-- Form Container -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Main Form (2 Cols) -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Section 1: Informasi Pokok -->
        <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-5">
          <div class="flex items-center gap-2 border-b border-slate-800 pb-3">
            <FileText class="w-4 h-4 text-amber-400" />
            <h2 class="text-sm font-black text-white uppercase tracking-wider">Informasi Pokok Laporan</h2>
          </div>

          <div class="space-y-4 text-xs">
            <div>
              <label class="block font-extrabold text-slate-200 mb-1.5">
                JUDUL LAPORAN <span class="text-rose-400">*</span>
              </label>
              <input
                v-model="form.title"
                type="text"
                placeholder="Contoh: [PATROLI SIBER] Viral Video Keluhan Parkir Liar di Pasar Sentral Majene"
                class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:border-amber-400 font-bold"
              />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block font-extrabold text-slate-200 mb-1.5">
                  KATEGORI LAPORAN <span class="text-rose-400">*</span>
                </label>
                <select
                  v-model="form.category"
                  class="w-full px-3 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400"
                >
                  <option>🚨 Kejadian / Insiden Lapangan</option>
                  <option>🛡️ Kegiatan Positif Kepolisian</option>
                  <option>🗣️ Keluhan Warga (Dumas / Pelayanan)</option>
                  <option>📱 Isu Medsos & Opini Publik (Cyber Patrol)</option>
                </select>
              </div>

              <div>
                <label class="block font-extrabold text-slate-200 mb-1.5">
                  USULAN PRIORITAS ATENSI
                </label>
                <select
                  v-model="form.priority"
                  class="w-full px-3 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400"
                >
                  <option value="low">🟢 Rendah (Kegiatan Rutin)</option>
                  <option value="medium">🟡 Sedang (Perlu Perhatian / Pantau)</option>
                  <option value="high">🔴 Tinggi (Mendesak / Atensi Pimpinan)</option>
                  <option value="critical">🔥 Kritis (Darurat / Krisis Reputasi)</option>
                </select>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block font-extrabold text-slate-200 mb-1.5">
                  WAKTU TEMUAN / KEJADIAN (WITA) <span class="text-rose-400">*</span>
                </label>
                <input
                  v-model="form.occurred_at"
                  type="datetime-local"
                  class="w-full px-3 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400 font-mono"
                />
              </div>

              <div>
                <label class="block font-extrabold text-slate-200 mb-1.5">
                  LOKASI KEJADIAN (DESKRIPTIF)
                </label>
                <input
                  v-model="form.location"
                  type="text"
                  placeholder="Contoh: Jl. Trans Sulawesi, Banggae, Majene"
                  class="w-full px-3 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:border-amber-400"
                />
              </div>
            </div>

            <div>
              <label class="block font-extrabold text-slate-200 mb-1.5">
                URAIAN KRONOLOGI & DESKRIPSI LENGKAP <span class="text-rose-400">*</span>
              </label>
              <textarea
                v-model="form.summary"
                rows="5"
                placeholder="Jelaskan fakta peristiwa 5W+1H, kronologi kejadian, pihak yang terlibat, dampak opini publik, serta rekomendasi awal penanganan..."
                class="w-full p-4 rounded-2xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:border-amber-400 leading-relaxed"
              ></textarea>
            </div>
          </div>
        </div>

        <!-- Section 2: Upload Dokumentasi Bukti -->
        <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
          <div class="flex items-center gap-2 border-b border-slate-800 pb-3">
            <UploadCloud class="w-4 h-4 text-amber-400" />
            <h2 class="text-sm font-black text-white uppercase tracking-wider">Unggah Dokumentasi Foto & Bukti Kejadian</h2>
          </div>

          <label class="border-2 border-dashed border-slate-700 hover:border-amber-400 rounded-3xl p-8 flex flex-col items-center justify-center text-center cursor-pointer transition bg-slate-900/40 group">
            <input type="file" multiple accept="image/*" class="hidden" @change="handleFileSelect" />
            <UploadCloud class="w-10 h-10 text-slate-500 group-hover:text-amber-400 transition mb-3" />
            <p class="text-xs font-bold text-white">Klik atau Seret Berkas Foto / Tangkapan Layar ke Sini</p>
            <p class="text-[11px] text-slate-400 mt-1">Format JPG, PNG, atau WEBP (Maksimal 10 MB per foto, hingga 5 berkas sekaligus)</p>
          </label>

          <!-- Uploaded files list -->
          <div v-if="uploadedFiles.length > 0" class="space-y-2 pt-2">
            <div
              v-for="(f, idx) in uploadedFiles"
              :key="idx"
              class="flex items-center justify-between p-2.5 rounded-xl bg-slate-900 border border-slate-800 text-xs"
            >
              <div class="flex items-center gap-2 truncate">
                <CheckCircle2 class="w-4 h-4 text-emerald-400 shrink-0" />
                <span class="font-bold text-white truncate">{{ f.name }}</span>
                <span class="text-slate-500 font-mono text-[10px]">({{ f.size }})</span>
              </div>
              <button @click="removeFile(idx)" class="text-slate-400 hover:text-rose-400 p-1">
                <X class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Sidebar Form Controls (1 Col) -->
      <div class="space-y-6">
        <!-- Satuan Kerja & PIC -->
        <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
          <div class="flex items-center gap-2 border-b border-slate-800 pb-3">
            <Building2 class="w-4 h-4 text-amber-400" />
            <h3 class="text-sm font-black text-white uppercase tracking-wider">Satwil Penanggung Jawab</h3>
          </div>

          <div class="space-y-3 text-xs">
            <div>
              <label class="block font-bold text-slate-300 mb-1">Polres Terkait</label>
              <select
                v-model="form.polres_id"
                class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400"
              >
                <option value="">Polda Sulawesi Barat (Tingkat Polda)</option>
                <option v-for="p in polresList" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
            </div>

            <div>
              <label class="block font-bold text-slate-300 mb-1">Satker / Satfung Pelaksana</label>
              <input
                v-model="form.assigned_unit"
                type="text"
                placeholder="Contoh: Satlantas Polres Majene"
                class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400"
              />
            </div>
          </div>
        </div>

        <!-- Tautan Sumber Medsos / Berita -->
        <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
          <div class="flex items-center gap-2 border-b border-slate-800 pb-3">
            <Link2 class="w-4 h-4 text-amber-400" />
            <h3 class="text-sm font-black text-white uppercase tracking-wider">Tautan Sumber Berita / Medsos</h3>
          </div>

          <div class="space-y-3 text-xs">
            <div>
              <label class="block font-bold text-slate-300 mb-1">Nama Sumber / Media</label>
              <input
                v-model="form.source_media"
                type="text"
                placeholder="Contoh: Instagram @infomamuju / Detik Sulsel"
                class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400"
              />
            </div>

            <div>
              <label class="block font-bold text-slate-300 mb-1">URL Postingan / Tautan Berita</label>
              <input
                v-model="form.source_url"
                type="url"
                placeholder="https://instagram.com/p/... atau https://..."
                class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white font-mono focus:outline-none focus:border-amber-400"
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  </EnterpriseLayout>
</template>