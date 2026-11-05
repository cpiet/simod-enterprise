<script setup>
/**
 * SADEWA AI - asisten percakapan lintas sumber (Media Online, Media Sosial, Isu Internal).
 * Data dan riwayat dikelola server (/sadewa/*): lingkup mengikuti hak akses pengguna,
 * riwayat tersimpan per akun, dan setiap jawaban memuat rujukan sumber yang dapat dibuka.
 */
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';
import { Link, usePage } from '@inertiajs/vue3';
import { Marked } from 'marked';
import {
  Bot, X, Send, Square, MoreHorizontal, Plus, History, Volume2, VolumeX, Copy, Check,
  RotateCcw, Trash2, ArrowLeft, ExternalLink, MessageSquareText, Newspaper, Share2, ClipboardList, ChevronRight,
} from 'lucide-vue-next';

// Parser markdown terisolasi dan aman: HTML mentah dari model/data dilepas, hanya tautan http(s) yang aktif.
const escapeHtml = (t) => String(t).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const md = new Marked({
  gfm: true,
  breaks: true,
  renderer: {
    html: ({ text }) => escapeHtml(text),
    image: () => '',
    link({ href, tokens }) {
      const inner = this.parser.parseInline(tokens);
      if (!/^https?:\/\//i.test(href || '')) return inner;
      return `<a href="${escapeHtml(href)}" target="_blank" rel="noopener noreferrer">${inner}</a>`;
    },
  },
});
const renderMarkdown = (text) => {
  if (!text) return '';
  return md.parse(text).replace(/\[([MSIE]\d{1,3})\]/g, '<sup class="sdw-cite">$1</sup>');
};

const page = usePage();
const MUTE_KEY = 'simod_sadewa_autospeak_v3'; // kenyamanan per perangkat saja; bukan data

// ── State ────────────────────────────────────────────────────────────────
const isOpen = ref(false);
const view = ref('chat'); // 'chat' | 'history'
const menuOpen = ref(false);
const meta = ref(null);
const usage = ref(null); // { used, limit, remaining, blocked }
const scope = ref('all');
const messages = ref([]);
const conversationId = ref(null);
const input = ref('');
const subject = ref('');
const thinking = ref(false);
const history = ref([]);
const historyLoading = ref(false);
const confirmClear = ref(false);
const copiedIdx = ref(null);
const speakingIdx = ref(null);
const autoSpeak = ref(false);
const bodyEl = ref(null);
const inputEl = ref(null);
let abortCtl = null;
let lastQuery = null;

const scopes = computed(() => meta.value?.scopes || []);
const limitReached = computed(() => !!usage.value && usage.value.limit > 0 && usage.value.remaining === 0);
const usageText = computed(() => {
  const u = usage.value;
  if (!u) return '';
  if (u.limit > 0) return `Sisa ${u.remaining} dari ${u.limit} pertanyaan AI hari ini`;
  return '';
});
const scopeOptions = computed(() => (scopes.value.length > 1 ? [{ key: 'all', label: 'Semua' }, ...scopes.value] : []));
const scopeLabel = computed(() => {
  if (scope.value === 'all') return scopes.value.map((s) => s.label).join(' + ') || 'Data terkini';
  return scopes.value.find((s) => s.key === scope.value)?.label || '';
});

const firstName = computed(() => (page.props.auth?.user?.name || '').split(' ')[0] || '');

const statChips = computed(() => {
  const st = meta.value?.stats || {};
  const on = (k) => scope.value === 'all' || scope.value === k;
  const out = [];
  if (on('media') && 'articles_24h' in st) out.push(`${st.articles_24h} berita 24 jam`, `${st.portals} portal aktif`);
  if (on('social') && 'posts_24h' in st) out.push(`${st.posts_24h} postingan medsos 24 jam`);
  if (on('issues') && 'active_issues' in st) out.push(`${st.active_issues} isu aktif`);
  return out;
});

const SUGGESTIONS = {
  all: [
    { icon: ClipboardList, t: 'Ringkasan situasi 24 jam', q: 'Berikan ringkasan eksekutif situasi terkini dari seluruh sumber data dalam 24 jam terakhir.' },
    { icon: MessageSquareText, t: 'Isu yang perlu atensi pimpinan', q: 'Isu apa yang memerlukan atensi khusus pimpinan hari ini dan bagaimana rekomendasi penanganannya?' },
    { icon: Newspaper, t: 'Bandingkan media online dan medsos', q: 'Bandingkan narasi dominan di media online dengan percakapan di media sosial. Adakah perbedaan sentimen?' },
    { icon: Share2, t: 'Rekomendasi respons Humas', q: 'Apa rekomendasi langkah komunikasi kehumasan yang perlu segera dilakukan untuk mengendalikan opini publik?' },
  ],
  media: [
    { icon: MessageSquareText, t: 'Isu negatif terkini', q: 'Tampilkan isu bersentimen negatif yang menonjol di media online beserta potensi kerawanannya.' },
    { icon: ClipboardList, t: 'Narasi dominan di media', q: 'Apa narasi dominan di pemberitaan media online saat ini dan portal mana yang paling banyak memberitakannya?' },
    { icon: Newspaper, t: 'Ringkasan 24 jam', q: 'Berikan ringkasan pemberitaan media online dan sentimen publik dalam 24 jam terakhir.' },
    { icon: Share2, t: 'Rekomendasi rilis', q: 'Topik apa yang sebaiknya dijawab dengan rilis atau klarifikasi resmi berdasarkan pemberitaan terkini?' },
  ],
  social: [
    { icon: MessageSquareText, t: 'Tren percakapan warganet', q: 'Apa tren percakapan dan sentimen warganet di media sosial saat ini?' },
    { icon: ClipboardList, t: 'Akun berpengaruh', q: 'Akun atau tokoh mana yang paling berpengaruh dalam percakapan terbaru dan bagaimana sikapnya?' },
    { icon: Newspaper, t: 'Potensi viral dan hoaks', q: 'Adakah unggahan dengan potensi viral atau indikasi hoaks yang perlu diwaspadai?' },
    { icon: Share2, t: 'Narasi tandingan', q: 'Susun poin narasi tandingan yang tepat untuk isu negatif yang sedang berkembang di media sosial.' },
  ],
  issues: [
    { icon: MessageSquareText, t: 'Isu aktif prioritas tinggi', q: 'Sebutkan isu aktif berprioritas tinggi atau kritis beserta statusnya.' },
    { icon: ClipboardList, t: 'Menunggu verifikasi', q: 'Isu mana yang masih menunggu telaah atau verifikasi?' },
    { icon: Newspaper, t: 'Peringatan dini aktif', q: 'Apa saja peringatan dini yang masih aktif dan langkah penanganannya?' },
    { icon: Share2, t: 'Rekomendasi tindak lanjut', q: 'Apa tindak lanjut yang paling mendesak untuk isu-isu yang sedang berjalan?' },
  ],
};
const suggestions = computed(() => SUGGESTIONS[scope.value] || SUGGESTIONS.all);

// Lingkup awal mengikuti halaman yang sedang dibuka (jika diizinkan).
const defaultScopeForPage = () => {
  const url = (page.url || '').split('?')[0];
  const want = url.startsWith('/social-intelligence') ? 'social' : url.startsWith('/media-intelligence') ? 'media' : url.startsWith('/issues') ? 'issues' : 'all';
  return want === 'all' || scopes.value.some((s) => s.key === want) ? want : 'all';
};

// ── Data server ──────────────────────────────────────────────────────────
const loadMeta = async () => {
  try {
    const res = await axios.get('/sadewa/meta');
    meta.value = res.data;
    usage.value = res.data.usage || null;
  } catch (e) {
    meta.value = { scopes: [], stats: {} };
  }
};

const loadHistory = async () => {
  historyLoading.value = true;
  try {
    history.value = (await axios.get('/sadewa/conversations')).data.data || [];
  } catch (e) {
    history.value = [];
  } finally {
    historyLoading.value = false;
  }
};

const openHistory = async () => {
  menuOpen.value = false;
  view.value = 'history';
  confirmClear.value = false;
  await loadHistory();
};

const resume = async (item) => {
  try {
    const res = await axios.get(`/sadewa/conversations/${item.id}`);
    stopAll();
    conversationId.value = res.data.id;
    messages.value = res.data.messages || [];
    scope.value = scopes.value.some((s) => s.key === res.data.scope) || res.data.scope === 'all' ? res.data.scope : 'all';
    view.value = 'chat';
    scrollBottom();
  } catch (e) {
    toast('Percakapan tidak dapat dibuka.', 'error');
  }
};

const removeConversation = async (item) => {
  try {
    await axios.delete(`/sadewa/conversations/${item.id}`);
    history.value = history.value.filter((h) => h.id !== item.id);
    if (conversationId.value === item.id) newChat();
  } catch (e) {
    toast('Gagal menghapus percakapan.', 'error');
  }
};

const clearAll = async () => {
  if (!confirmClear.value) {
    confirmClear.value = true;
    return;
  }
  try {
    await axios.delete('/sadewa/conversations');
    history.value = [];
    newChat();
    confirmClear.value = false;
  } catch (e) {
    toast('Gagal menghapus riwayat.', 'error');
  }
};

const newChat = () => {
  stopAll();
  conversationId.value = null;
  messages.value = [];
  subject.value = '';
  view.value = 'chat';
  menuOpen.value = false;
  nextTick(() => inputEl.value?.focus());
};

// ── Kirim pesan ──────────────────────────────────────────────────────────
const toast = (message, type = 'success') => window.dispatchEvent(new CustomEvent('simod-toast', { detail: { message, type } }));

const scrollBottom = () => nextTick(() => {
  if (bodyEl.value) bodyEl.value.scrollTop = bodyEl.value.scrollHeight;
});

const send = async (text = null) => {
  const q = (text ?? input.value).trim();
  if (!q || thinking.value) return;
  view.value = 'chat';
  lastQuery = q;
  messages.value.push({ role: 'user', text: q, time: new Date().toISOString() });
  input.value = '';
  thinking.value = true;
  scrollBottom();

  abortCtl = new AbortController();
  try {
    const res = await axios.post('/sadewa/chat', {
      message: q,
      scope: scope.value,
      conversation_id: conversationId.value,
      subject: subject.value || undefined,
    }, { signal: abortCtl.signal });

    if (res.data?.success) {
      conversationId.value = res.data.conversation_id;
      if (res.data.usage) usage.value = res.data.usage;
      messages.value.push({ role: 'assistant', text: res.data.reply, sources: res.data.sources || [], time: res.data.time });
      if (autoSpeak.value) speak(res.data.reply, messages.value.length - 1);
    } else {
      pushError('Jawaban tidak dapat diproses. Silakan coba lagi.');
    }
  } catch (err) {
    if (axios.isCancel(err) || err.code === 'ERR_CANCELED') {
      messages.value.pop(); // pertanyaan dibatalkan: kembalikan ke kolom input
      input.value = q;
    } else {
      if (err.response?.data?.usage) usage.value = err.response.data.usage;
      pushError(err.response?.data?.message || 'Tidak dapat terhubung ke mesin analisis. Periksa konfigurasi AI lalu coba lagi.');
    }
  } finally {
    thinking.value = false;
    abortCtl = null;
    scrollBottom();
  }
};

const pushError = (text) => messages.value.push({ role: 'assistant', error: true, text, time: new Date().toISOString() });

const stopThinking = () => abortCtl?.abort();

const retry = () => {
  if (!lastQuery || thinking.value) return;
  while (messages.value.length && messages.value[messages.value.length - 1].error) messages.value.pop();
  if (messages.value.length && messages.value[messages.value.length - 1].role === 'user') messages.value.pop();
  send(lastQuery);
};

const copyMessage = async (m, idx) => {
  try {
    await navigator.clipboard.writeText(m.text.replace(/\[[MSIE]\d{1,3}\]/g, '').trim());
    copiedIdx.value = idx;
    setTimeout(() => { if (copiedIdx.value === idx) copiedIdx.value = null; }, 1500);
  } catch (e) {
    toast('Tidak dapat menyalin teks.', 'error');
  }
};

const makeWhatsapp = () => send('Ubah jawaban terakhir menjadi naskah WhatsApp singkat untuk pimpinan: poin utama, sumber, dan rekomendasi.');

// ── Suara (opsional) ─────────────────────────────────────────────────────
const stopSpeech = () => {
  if (typeof window !== 'undefined' && 'speechSynthesis' in window) window.speechSynthesis.cancel();
  speakingIdx.value = null;
};

const pickVoice = () => {
  const voices = window.speechSynthesis.getVoices();
  return voices.find((v) => /^(id|in)/i.test(v.lang)) || null;
};

const speak = (text, idx) => {
  if (typeof window === 'undefined' || !('speechSynthesis' in window)) return;
  if (speakingIdx.value === idx) return stopSpeech();
  stopSpeech();
  const clean = text.replace(/\[[MSIE]\d{1,3}\]/g, '').replace(/[#*`_~]/g, '').trim();
  if (!clean) return;
  const u = new SpeechSynthesisUtterance(clean);
  u.lang = 'id-ID';
  u.rate = 1;
  u.pitch = 0.85;
  const v = pickVoice();
  if (v) u.voice = v;
  u.onend = u.onerror = () => { speakingIdx.value = null; };
  speakingIdx.value = idx;
  window.speechSynthesis.speak(u);
};

const stopAll = () => {
  stopSpeech();
  abortCtl?.abort();
};

watch(autoSpeak, (v) => {
  try { localStorage.setItem(MUTE_KEY, v ? '1' : '0'); } catch (e) {}
  if (!v) stopSpeech();
});

// ── Buka/tutup ───────────────────────────────────────────────────────────
const open = async (detail = {}) => {
  isOpen.value = true;
  view.value = 'chat';
  if (!meta.value) {
    await loadMeta();
    scope.value = defaultScopeForPage();
  }
  if (detail.scope && (detail.scope === 'all' || scopes.value.some((s) => s.key === detail.scope))) scope.value = detail.scope;
  if (detail.subject) subject.value = detail.subject;
  if (detail.query) await send(detail.query);
  else nextTick(() => inputEl.value?.focus());
};

const toggle = () => (isOpen.value ? close() : open());
const close = () => {
  isOpen.value = false;
  menuOpen.value = false;
  stopSpeech();
};

const onGlobalOpen = (e) => open(e.detail || {});
const onDocClick = (e) => {
  if (menuOpen.value && !e.target.closest?.('[data-sdw-menu]')) menuOpen.value = false;
};
const onKey = (e) => {
  if (e.key === 'Escape' && isOpen.value) close();
};

onMounted(() => {
  try { autoSpeak.value = localStorage.getItem(MUTE_KEY) === '1'; } catch (e) {}
  window.addEventListener('open-sadewa-copilot', onGlobalOpen);
  document.addEventListener('click', onDocClick);
  document.addEventListener('keydown', onKey);
});
onBeforeUnmount(() => {
  stopAll();
  window.removeEventListener('open-sadewa-copilot', onGlobalOpen);
  document.removeEventListener('click', onDocClick);
  document.removeEventListener('keydown', onKey);
});

// ── Util tampilan ────────────────────────────────────────────────────────
const fmtTime = (iso) => {
  if (!iso) return '';
  const d = new Date(iso);
  return isNaN(d) ? '' : d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
};
const fmtDate = (iso) => {
  const d = new Date(iso);
  if (isNaN(d)) return '';
  const today = new Date();
  return d.toDateString() === today.toDateString()
    ? fmtTime(iso)
    : d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short' });
};
const sourceTypeLabel = { media: 'Media', social: 'Medsos', issue: 'Isu', ews: 'EWS' };
</script>

<template>
  <!-- Peluncur -->
  <div v-show="!isOpen" class="fixed bottom-6 right-4 sm:right-6 z-40 no-print">
    <button
      type="button"
      @click="toggle"
      class="group flex items-center gap-2.5 pl-2.5 pr-4 py-2.5 rounded-full bg-[#0b1426] hover:bg-[#10203d] text-white font-bold text-xs shadow-xl border border-amber-500/40 hover:border-amber-400 transition cursor-pointer select-none"
      title="Tanya SADEWA AI"
    >
      <span class="relative flex items-center justify-center w-8 h-8 rounded-full bg-[#16274a] text-amber-400">
        <Bot class="w-4 h-4" />
        <span class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-emerald-400 border border-[#0b1426]"></span>
      </span>
      <span class="text-amber-300 tracking-wide">SADEWA AI</span>
    </button>
  </div>

  <Teleport to="body">
    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 translate-y-6"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 translate-y-6"
    >
      <section
        v-if="isOpen"
        class="fixed z-50 inset-0 sm:inset-auto sm:bottom-6 sm:right-6 sm:w-[440px] sm:h-[640px] sm:max-h-[calc(100vh-48px)] flex flex-col overflow-hidden bg-white dark:bg-[#070d1d] text-slate-800 dark:text-slate-100 sm:rounded-2xl sm:border border-slate-300 dark:border-amber-500/25 shadow-2xl no-print"
        role="dialog"
        aria-label="SADEWA AI"
      >
        <!-- Header -->
        <header class="shrink-0 flex items-center gap-3 px-4 py-3 bg-[#0b1426] text-white border-b border-amber-500/25">
          <button v-if="view === 'history'" type="button" @click="view = 'chat'" class="p-1.5 -ml-1 rounded-lg hover:bg-white/10 cursor-pointer" title="Kembali ke percakapan">
            <ArrowLeft class="w-4 h-4" />
          </button>
          <span v-else class="relative flex items-center justify-center w-9 h-9 rounded-full bg-[#16274a] text-amber-400 shrink-0">
            <Bot class="w-5 h-5" />
            <span class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-emerald-400 border-2 border-[#0b1426]"></span>
          </span>
          <div class="min-w-0 flex-1">
            <h2 class="font-black text-sm tracking-wide leading-tight">{{ view === 'history' ? 'Riwayat Percakapan' : 'SADEWA AI' }}</h2>
            <p class="text-[11px] text-slate-400 truncate">
              <template v-if="view === 'history'">{{ history.length }} tersimpan di akun Anda</template>
              <template v-else>{{ scopeLabel || 'Memuat data…' }}<template v-if="meta?.tenant"> · {{ meta.tenant }}</template></template>
            </p>
          </div>

          <div class="relative" data-sdw-menu>
            <button type="button" @click.stop="menuOpen = !menuOpen" class="p-2 rounded-lg hover:bg-white/10 cursor-pointer" title="Menu" aria-haspopup="menu" :aria-expanded="menuOpen">
              <MoreHorizontal class="w-4 h-4" />
            </button>
            <div v-if="menuOpen" role="menu" class="absolute right-0 top-full mt-1 w-56 rounded-xl bg-white dark:bg-[#0b1426] text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 shadow-xl py-1 z-10 text-xs font-semibold">
              <button type="button" role="menuitem" @click="newChat" class="w-full flex items-center gap-2.5 px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer"><Plus class="w-4 h-4 text-amber-500" /> Percakapan baru</button>
              <button type="button" role="menuitem" @click="openHistory" class="w-full flex items-center gap-2.5 px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer"><History class="w-4 h-4 text-amber-500" /> Riwayat</button>
              <button type="button" role="menuitem" @click="autoSpeak = !autoSpeak" class="w-full flex items-center gap-2.5 px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer">
                <component :is="autoSpeak ? Volume2 : VolumeX" class="w-4 h-4" :class="autoSpeak ? 'text-emerald-500' : 'text-slate-400'" />
                <span class="flex-1 text-left">Bacakan jawaban</span>
                <span class="text-[10px] font-black" :class="autoSpeak ? 'text-emerald-500' : 'text-slate-400'">{{ autoSpeak ? 'AKTIF' : 'MATI' }}</span>
              </button>
            </div>
          </div>
          <button type="button" @click="close" class="p-2 -mr-1 rounded-lg hover:bg-white/10 cursor-pointer" title="Tutup (Esc)"><X class="w-4 h-4" /></button>
        </header>

        <!-- Riwayat -->
        <div v-if="view === 'history'" class="flex-1 overflow-y-auto custom-scrollbar p-3 space-y-2">
          <div v-if="historyLoading" class="text-center text-xs text-slate-400 py-10">Memuat…</div>
          <div v-else-if="!history.length" class="text-center py-14 px-6">
            <History class="w-8 h-8 mx-auto text-slate-300 dark:text-slate-600 mb-2" />
            <p class="text-sm font-bold">Belum ada riwayat</p>
            <p class="text-xs text-slate-500 mt-1">Percakapan Anda tersimpan otomatis di akun ini dan dapat dibuka dari perangkat mana pun.</p>
          </div>
          <template v-else>
            <div
              v-for="h in history"
              :key="h.id"
              class="group flex items-start gap-2 rounded-xl border border-slate-200 dark:border-slate-700/70 bg-slate-50 dark:bg-slate-900/50 hover:border-amber-400/60 transition"
            >
              <button type="button" @click="resume(h)" class="flex-1 min-w-0 text-left p-3 cursor-pointer">
                <div class="flex items-center justify-between gap-2">
                  <p class="text-xs font-bold truncate">{{ h.title }}</p>
                  <span class="text-[10px] text-slate-400 shrink-0">{{ fmtDate(h.updated_at) }}</span>
                </div>
                <p v-if="h.snippet" class="text-[11px] text-slate-500 dark:text-slate-400 line-clamp-2 mt-0.5">{{ h.snippet }}</p>
              </button>
              <button type="button" @click="removeConversation(h)" class="p-2 mt-2 mr-1 rounded-lg text-slate-400 hover:text-rose-500 hover:bg-rose-500/10 cursor-pointer" title="Hapus"><Trash2 class="w-3.5 h-3.5" /></button>
            </div>
            <button type="button" @click="clearAll" class="w-full text-center text-[11px] font-bold py-2 rounded-lg cursor-pointer" :class="confirmClear ? 'bg-rose-500 text-white' : 'text-slate-500 hover:text-rose-500'">
              {{ confirmClear ? 'Klik lagi untuk menghapus semua riwayat' : 'Hapus semua riwayat' }}
            </button>
          </template>
        </div>

        <!-- Percakapan -->
        <template v-else>
          <!-- Lingkup data -->
          <div v-if="scopeOptions.length" class="shrink-0 flex items-center gap-1.5 px-3 py-2 border-b border-slate-200 dark:border-slate-800 overflow-x-auto" role="tablist" aria-label="Lingkup data">
            <button
              v-for="o in scopeOptions"
              :key="o.key"
              type="button"
              role="tab"
              :aria-selected="scope === o.key"
              :disabled="thinking"
              @click="scope = o.key"
              class="px-3 py-1 rounded-full text-[11px] font-bold whitespace-nowrap border transition cursor-pointer disabled:opacity-50"
              :class="scope === o.key ? 'bg-amber-400 text-slate-950 border-amber-300' : 'bg-transparent text-slate-600 dark:text-slate-300 border-slate-300 dark:border-slate-700 hover:border-amber-400'"
            >{{ o.label }}</button>
          </div>

          <div ref="bodyEl" class="flex-1 overflow-y-auto custom-scrollbar px-4 py-4 space-y-4">
            <!-- Layar kosong -->
            <div v-if="!messages.length" class="pt-2">
              <p class="text-base font-black">Halo<template v-if="firstName">, {{ firstName }}</template>.</p>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                <template v-if="scopes.length">Saya menjawab hanya dari data yang tercatat di sistem{{ meta?.tenant ? ' untuk ' + meta.tenant : '' }}, lengkap dengan sumbernya.</template>
                <template v-else-if="meta">Peran Anda belum memiliki akses ke sumber data SADEWA.</template>
              </p>
              <div v-if="statChips.length" class="flex flex-wrap gap-1.5 mt-3">
                <span v-for="c in statChips" :key="c" class="px-2.5 py-1 rounded-full text-[10.5px] font-semibold bg-slate-100 dark:bg-slate-800/80 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700">{{ c }}</span>
              </div>
              <div v-if="scopes.length" class="mt-4 grid grid-cols-1 gap-2">
                <button
                  v-for="s in suggestions"
                  :key="s.t"
                  type="button"
                  @click="send(s.q)"
                  :disabled="thinking"
                  class="group flex items-center gap-3 text-left px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700/70 bg-slate-50 dark:bg-slate-900/50 hover:border-amber-400/70 transition cursor-pointer disabled:opacity-50"
                >
                  <component :is="s.icon" class="w-4 h-4 text-amber-500 shrink-0" />
                  <span class="flex-1 text-xs font-bold">{{ s.t }}</span>
                  <ChevronRight class="w-4 h-4 text-slate-300 group-hover:text-amber-500" />
                </button>
              </div>
            </div>

            <!-- Pesan -->
            <template v-for="(m, i) in messages" :key="i">
              <div v-if="m.role === 'user'" class="flex justify-end">
                <div class="max-w-[85%] rounded-2xl rounded-br-md bg-amber-400 text-slate-950 px-3.5 py-2 text-xs font-semibold leading-relaxed whitespace-pre-wrap">{{ m.text }}</div>
              </div>

              <div v-else-if="m.error" class="rounded-xl border border-rose-300 dark:border-rose-500/40 bg-rose-50 dark:bg-rose-500/10 px-3.5 py-3">
                <p class="text-xs text-rose-700 dark:text-rose-300 leading-relaxed">{{ m.text }}</p>
                <button type="button" @click="retry" :disabled="thinking" class="mt-2 inline-flex items-center gap-1.5 text-[11px] font-bold text-rose-700 dark:text-rose-300 hover:underline cursor-pointer"><RotateCcw class="w-3.5 h-3.5" /> Coba lagi</button>
              </div>

              <div v-else class="flex gap-2.5">
                <span class="w-7 h-7 shrink-0 rounded-full bg-[#0b1426] text-amber-400 flex items-center justify-center"><Bot class="w-4 h-4" /></span>
                <div class="min-w-0 flex-1">
                  <div class="text-[12px] text-slate-700 dark:text-slate-200 leading-relaxed sadewa-markdown-body" v-html="renderMarkdown(m.text)"></div>

                  <div v-if="m.sources?.length" class="mt-2.5">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Sumber</p>
                    <ul class="space-y-1">
                      <li v-for="s in m.sources" :key="s.code">
                        <a v-if="s.url && s.external" :href="s.url" target="_blank" rel="noopener noreferrer" class="sdw-src">
                          <sup class="sdw-cite">{{ s.code }}</sup><span class="truncate">{{ s.label }} · {{ s.title }}</span><ExternalLink class="w-3 h-3 shrink-0 opacity-60" />
                        </a>
                        <Link v-else-if="s.url" :href="s.url" class="sdw-src">
                          <sup class="sdw-cite">{{ s.code }}</sup><span class="truncate">{{ s.label }} · {{ s.title }}</span>
                        </Link>
                        <span v-else class="sdw-src cursor-default"><sup class="sdw-cite">{{ s.code }}</sup><span class="truncate">{{ s.label }} · {{ s.title }}</span></span>
                      </li>
                    </ul>
                  </div>

                  <div class="mt-2 flex items-center gap-1 text-slate-400">
                    <button type="button" @click="copyMessage(m, i)" class="sdw-act" title="Salin jawaban">
                      <component :is="copiedIdx === i ? Check : Copy" class="w-3.5 h-3.5" :class="copiedIdx === i ? 'text-emerald-500' : ''" /><span>{{ copiedIdx === i ? 'Tersalin' : 'Salin' }}</span>
                    </button>
                    <button type="button" @click="speak(m.text, i)" class="sdw-act" :title="speakingIdx === i ? 'Hentikan' : 'Dengarkan'">
                      <component :is="speakingIdx === i ? Square : Volume2" class="w-3.5 h-3.5" :class="speakingIdx === i ? 'text-amber-500' : ''" /><span>{{ speakingIdx === i ? 'Berhenti' : 'Dengar' }}</span>
                    </button>
                    <button v-if="i === messages.length - 1 && !thinking" type="button" @click="makeWhatsapp" class="sdw-act" title="Ubah menjadi naskah WhatsApp">
                      <Share2 class="w-3.5 h-3.5" /><span>Naskah WA</span>
                    </button>
                    <span class="ml-auto text-[10px]">{{ fmtTime(m.time) }}</span>
                  </div>
                </div>
              </div>
            </template>

            <div v-if="thinking" class="flex gap-2.5 items-center">
              <span class="w-7 h-7 shrink-0 rounded-full bg-[#0b1426] text-amber-400 flex items-center justify-center"><Bot class="w-4 h-4" /></span>
              <div class="flex items-center gap-2 text-[11px] text-slate-500 dark:text-slate-400">
                <span class="flex gap-1"><i class="sdw-dot"></i><i class="sdw-dot" style="animation-delay:.15s"></i><i class="sdw-dot" style="animation-delay:.3s"></i></span>
                <span>Menganalisis {{ scopeLabel.toLowerCase() }}…</span>
              </div>
            </div>
          </div>

          <!-- Input -->
          <footer class="shrink-0 border-t border-slate-200 dark:border-slate-800 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
            <div v-if="messages.length && !thinking && scopes.length" class="flex gap-1.5 overflow-x-auto pb-2 -mt-0.5">
              <button v-for="s in suggestions.slice(0, 3)" :key="s.t" type="button" @click="send(s.q)" class="px-2.5 py-1 rounded-full text-[10.5px] font-semibold whitespace-nowrap border border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:border-amber-400 cursor-pointer">{{ s.t }}</button>
            </div>
            <div v-if="subject" class="flex items-center gap-2 mb-2 px-2.5 py-1.5 rounded-lg bg-amber-400/10 border border-amber-400/30 text-[11px]">
              <span class="font-bold text-amber-600 dark:text-amber-300 shrink-0">Membahas</span>
              <span class="truncate flex-1">{{ subject }}</span>
              <button type="button" @click="subject = ''" class="text-slate-400 hover:text-slate-700 dark:hover:text-white cursor-pointer" title="Lepas"><X class="w-3.5 h-3.5" /></button>
            </div>
            <form @submit.prevent="send()" class="flex items-end gap-2">
              <textarea
                ref="inputEl"
                v-model="input"
                rows="1"
                maxlength="1000"
                :disabled="!scopes.length || limitReached"
                :placeholder="limitReached ? 'Batas pertanyaan AI hari ini tercapai' : scopes.length ? 'Tanyakan sesuatu tentang data terkini…' : 'Tidak ada akses data'"
                @keydown.enter.exact.prevent="send()"
                class="flex-1 resize-none max-h-28 rounded-xl px-3.5 py-2.5 text-xs bg-slate-100 dark:bg-slate-900 text-slate-800 dark:text-slate-100 placeholder-slate-400 border border-slate-200 dark:border-slate-700 focus:outline-none focus:border-amber-400"
              ></textarea>
              <button v-if="thinking" type="button" @click="stopThinking" class="w-10 h-10 shrink-0 rounded-xl bg-slate-700 hover:bg-slate-600 text-white flex items-center justify-center cursor-pointer" title="Hentikan"><Square class="w-4 h-4" /></button>
              <button v-else type="submit" :disabled="!input.trim() || !scopes.length || limitReached" class="w-10 h-10 shrink-0 rounded-xl bg-amber-400 hover:bg-amber-300 disabled:opacity-40 text-slate-950 flex items-center justify-center cursor-pointer" title="Kirim (Enter)"><Send class="w-4 h-4" /></button>
            </form>
            <p v-if="limitReached" class="text-[10.5px] font-bold text-rose-500 mt-1.5">Batas pertanyaan AI harian Anda tercapai. Coba lagi besok atau minta administrator menaikkan batas.</p>
            <p v-else-if="usage?.blocked" class="text-[10.5px] font-bold text-amber-500 mt-1.5">Jatah AI dari Google sedang habis; permintaan ditahan beberapa menit.</p>
            <p v-else class="text-[10px] text-slate-400 mt-1.5">{{ usageText ? usageText + ' · ' : '' }}Jawaban disusun dari data sistem; periksa sumbernya.</p>
          </footer>
        </template>
      </section>
    </transition>
  </Teleport>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.25); border-radius: 8px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(245, 158, 11, 0.5); }

.sdw-cite {
  display: inline-block;
  font-size: 9px;
  font-weight: 800;
  line-height: 1;
  padding: 2px 4px;
  margin: 0 1px;
  border-radius: 4px;
  color: #92400e;
  background: rgba(245, 158, 11, 0.18);
  vertical-align: baseline;
}
:root.dark .sdw-cite { color: #fcd34d; }

.sdw-src {
  display: flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
  font-size: 11px;
  padding: 5px 8px;
  border-radius: 8px;
  color: #475569;
  background: rgba(148, 163, 184, 0.12);
  text-decoration: none;
}
.sdw-src:hover { background: rgba(245, 158, 11, 0.15); }
:root.dark .sdw-src { color: #cbd5e1; }

.sdw-act {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 7px;
  border-radius: 6px;
  font-size: 10.5px;
  font-weight: 700;
  cursor: pointer;
}
.sdw-act:hover { background: rgba(148, 163, 184, 0.15); color: #f59e0b; }

.sdw-dot {
  width: 5px; height: 5px; border-radius: 9999px; background: #f59e0b;
  animation: sdw-bounce 0.9s infinite ease-in-out;
}
@keyframes sdw-bounce { 0%, 80%, 100% { opacity: .3; transform: translateY(0); } 40% { opacity: 1; transform: translateY(-3px); } }

.sadewa-markdown-body :deep(h1), .sadewa-markdown-body :deep(h2), .sadewa-markdown-body :deep(h3), .sadewa-markdown-body :deep(h4) {
  font-weight: 800; font-size: 12px; line-height: 1.45; margin: 0.7rem 0 0.3rem; color: #b45309;
}
:root.dark .sadewa-markdown-body :deep(h1), :root.dark .sadewa-markdown-body :deep(h2), :root.dark .sadewa-markdown-body :deep(h3), :root.dark .sadewa-markdown-body :deep(h4) { color: #fbbf24; }
.sadewa-markdown-body :deep(p) { margin-bottom: 0.45rem; line-height: 1.6; }
.sadewa-markdown-body :deep(p:last-child) { margin-bottom: 0; }
.sadewa-markdown-body :deep(ul), .sadewa-markdown-body :deep(ol) { padding-left: 1.25rem; margin: 0.25rem 0 0.45rem; }
.sadewa-markdown-body :deep(ul) { list-style-type: disc; }
.sadewa-markdown-body :deep(ol) { list-style-type: decimal; }
.sadewa-markdown-body :deep(li) { margin-bottom: 0.25rem; line-height: 1.5; }
.sadewa-markdown-body :deep(strong) { font-weight: 800; color: #0f172a; }
:root.dark .sadewa-markdown-body :deep(strong) { color: #f8fafc; }
.sadewa-markdown-body :deep(a) { color: #b45309; text-decoration: underline; }
:root.dark .sadewa-markdown-body :deep(a) { color: #fbbf24; }
.sadewa-markdown-body :deep(hr) { border: 0; border-top: 1px solid rgba(148, 163, 184, 0.25); margin: 0.65rem 0; }
.sadewa-markdown-body :deep(.sdw-cite) {
  display: inline-block; font-size: 9px; font-weight: 800; line-height: 1; padding: 2px 4px; margin: 0 1px; border-radius: 4px;
  color: #92400e; background: rgba(245, 158, 11, 0.18); vertical-align: baseline;
}
:root.dark .sadewa-markdown-body :deep(.sdw-cite) { color: #fcd34d; }
</style>
