<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import {
  Share2,
  PlusCircle,
  ExternalLink,
  Filter,
  CheckCircle2,
  Clock,
  Instagram,
  Youtube,
  Radio,
  X,
  MessageCircle,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  publications: Object, // Paginated
  channelFilter: String,
  metrics: Object,
});

const isModalOpen = ref(false);
const form = ref({
  polda_id: props.selectedPolda?.id || 1,
  channel: 'instagram',
  title: '',
  content_draft: '',
  url: '',
});

const isSubmitting = ref(false);

const handleChannelFilter = (channel) => {
  router.get('/publications', {
    polda: props.selectedCode,
    channel: channel,
  }, { preserveState: true, preserveScroll: true });
};

const submitPublication = () => {
  isSubmitting.value = true;
  router.post('/publications', form.value, {
    preserveScroll: true,
    onSuccess: () => {
      isSubmitting.value = false;
      isModalOpen.value = false;
      form.value = {
        polda_id: props.selectedPolda?.id || 1,
        channel: 'instagram',
        title: '',
        content_draft: '',
        url: '',
      };
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
          <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-sky-500/20 text-sky-400 border border-sky-500/40">
            Multi-Kanal Media
          </span>
          <span class="text-xs font-bold text-slate-400">
            {{ selectedCode === 'ALL' ? 'Mabes Polri (Nasional)' : selectedPolda?.name }}
          </span>
        </div>
        <h1 class="text-2xl md:text-3xl font-black text-white tracking-tight mt-1">
          Publikasi Konten & Rilis Media
        </h1>
        <p class="text-xs md:text-sm text-slate-300">
          Orkestrasi penerbitan konten publikasi Tribratanews, Instagram, TikTok, Facebook, dan Siaran Pers Satwil.
        </p>
      </div>

      <div class="flex items-center gap-3">
        <button
          @click="isModalOpen = true"
          class="px-4 py-2.5 rounded-xl text-xs font-black bg-gradient-to-r from-amber-500 to-amber-400 text-slate-950 hover:brightness-110 shadow-lg shadow-amber-400/20 flex items-center gap-2 cursor-pointer transition"
        >
          <PlusCircle class="w-4 h-4" />
          <span>+ Buat Publikasi Baru</span>
        </button>
      </div>
    </div>

    <!-- Metrics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
        <span class="text-xs font-bold text-slate-400">Total Publikasi</span>
        <p class="text-3xl font-black text-white">{{ metrics.total || 0 }}</p>
        <p class="text-[11px] text-slate-400">Semua kanal aktif</p>
      </div>
      <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
        <span class="text-xs font-bold text-sky-400">Tribratanews Portal</span>
        <p class="text-3xl font-black text-sky-400">{{ metrics.tribratanews || 0 }}</p>
        <p class="text-[11px] text-slate-400">Website resmi</p>
      </div>
      <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
        <span class="text-xs font-bold text-amber-400">Instagram Feed & Reels</span>
        <p class="text-3xl font-black text-amber-400">{{ metrics.instagram || 0 }}</p>
        <p class="text-[11px] text-slate-400">Visual edukasi</p>
      </div>
      <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
        <span class="text-xs font-bold text-rose-400">TikTok Short Video</span>
        <p class="text-3xl font-black text-rose-400">{{ metrics.tiktok || 0 }}</p>
        <p class="text-[11px] text-slate-400">Konten humanis viral</p>
      </div>
    </div>

    <!-- Channel Filter Pills -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1">
      <button
        @click="handleChannelFilter('all')"
        class="px-4 py-2 rounded-xl text-xs font-bold transition shrink-0 cursor-pointer"
        :class="channelFilter === 'all' ? 'bg-amber-400 text-slate-950 font-black' : 'bg-slate-900 border border-slate-700 text-slate-300 hover:text-white'"
      >
        Semua Kanal
      </button>
      <button
        @click="handleChannelFilter('tribratanews')"
        class="px-4 py-2 rounded-xl text-xs font-bold transition shrink-0 cursor-pointer"
        :class="channelFilter === 'tribratanews' ? 'bg-amber-400 text-slate-950 font-black' : 'bg-slate-900 border border-slate-700 text-slate-300 hover:text-white'"
      >
        Tribratanews
      </button>
      <button
        @click="handleChannelFilter('instagram')"
        class="px-4 py-2 rounded-xl text-xs font-bold transition shrink-0 cursor-pointer"
        :class="channelFilter === 'instagram' ? 'bg-amber-400 text-slate-950 font-black' : 'bg-slate-900 border border-slate-700 text-slate-300 hover:text-white'"
      >
        Instagram
      </button>
      <button
        @click="handleChannelFilter('tiktok')"
        class="px-4 py-2 rounded-xl text-xs font-bold transition shrink-0 cursor-pointer"
        :class="channelFilter === 'tiktok' ? 'bg-amber-400 text-slate-950 font-black' : 'bg-slate-900 border border-slate-700 text-slate-300 hover:text-white'"
      >
        TikTok
      </button>
    </div>

    <!-- Publications Grid -->
    <div v-if="publications?.data?.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <div
        v-for="pub in publications.data"
        :key="pub.id"
        class="rounded-3xl bg-[#0b1426] border border-slate-800/90 p-5 shadow-xl flex flex-col justify-between space-y-4 hover:border-slate-700 transition"
      >
        <div class="space-y-3">
          <div class="flex items-center justify-between">
            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-slate-900 border border-slate-700 text-amber-400">
              {{ pub.channel }}
            </span>
            <span class="text-[11px] text-slate-400 font-mono">
              {{ new Date(pub.created_at).toLocaleDateString('id-ID') }}
            </span>
          </div>

          <h3 class="font-extrabold text-white text-base leading-snug">
            {{ pub.title }}
          </h3>

          <p class="text-xs text-slate-300 leading-relaxed whitespace-pre-line line-clamp-4">
            {{ pub.content_draft }}
          </p>
        </div>

        <div class="pt-3 border-t border-slate-800/80 flex items-center justify-between text-xs">
          <span class="text-emerald-400 font-bold flex items-center gap-1">
            <CheckCircle2 class="w-3.5 h-3.5" /> Terbit Resmi
          </span>
          <a
            v-if="pub.url"
            :href="pub.url"
            target="_blank"
            class="text-sky-400 hover:text-sky-300 font-bold flex items-center gap-1"
          >
            <ExternalLink class="w-3.5 h-3.5" /> Buka Tautan
          </a>
        </div>
      </div>
    </div>
    <div v-else class="p-12 rounded-3xl bg-[#0b1426] border border-slate-800 text-center text-slate-400 text-xs">
      Belum ada konten publikasi untuk kanal ini.
    </div>

    <!-- MODAL BUAT PUBLIKASI BARU -->
    <div
      v-if="isModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm"
    >
      <div class="max-w-xl w-full rounded-3xl bg-[#0d182e] border border-slate-700 p-6 sm:p-8 space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-xl bg-sky-500/20 text-sky-400 flex items-center justify-center font-bold">
              +
            </div>
            <div>
              <h3 class="text-base font-extrabold text-white">Buat Publikasi Konten Baru</h3>
              <p class="text-[11px] text-slate-400">Jadwalkan atau Terbitkan ke Media Sosial / Website</p>
            </div>
          </div>
          <button @click="isModalOpen = false" class="text-slate-400 hover:text-white p-1">
            <X class="w-5 h-5" />
          </button>
        </div>

        <form @submit.prevent="submitPublication" class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1">Kanal Publikasi</label>
            <select
              v-model="form.channel"
              class="w-full px-3 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs font-bold text-white focus:border-amber-400 outline-none"
            >
              <option value="tribratanews">Tribratanews (Portal Web Resmi)</option>
              <option value="instagram">Instagram Feed / Story</option>
              <option value="tiktok">TikTok Video</option>
              <option value="facebook">Facebook Fanpage</option>
              <option value="twitter">X / Twitter</option>
              <option value="youtube">YouTube Video / Shorts</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1">Judul / Headline Publikasi</label>
            <input
              v-model="form.title"
              type="text"
              required
              placeholder="Contoh: Sosialisasi Tertib Lalin Polresta Mamuju..."
              class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs font-semibold text-white focus:outline-none focus:border-amber-400"
            />
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1">Draf Teks Narasi / Caption & Hashtag</label>
            <textarea
              v-model="form.content_draft"
              rows="4"
              required
              placeholder="Tuliskan draf caption lengkap dengan hashtag resmi kepolisian..."
              class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs font-normal text-white focus:outline-none focus:border-amber-400"
            ></textarea>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1">Tautan / Link Postingan (Opsional)</label>
            <input
              v-model="form.url"
              type="url"
              placeholder="https://instagram.com/p/..."
              class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-xs text-white focus:outline-none focus:border-amber-400"
            />
          </div>

          <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
            <button
              type="button"
              @click="isModalOpen = false"
              class="px-4 py-2 rounded-xl text-xs font-bold text-slate-300 hover:bg-slate-800 border border-slate-700 transition"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-5 py-2.5 rounded-xl text-xs font-black bg-amber-400 text-slate-950 hover:bg-amber-300 shadow-md transition"
            >
              {{ isSubmitting ? 'Menerbitkan...' : 'Terbitkan Publikasi' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </EnterpriseLayout>
</template>

