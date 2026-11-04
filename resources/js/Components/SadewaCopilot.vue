<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import axios from 'axios';
import { router, usePage } from '@inertiajs/vue3';
import { marked } from 'marked';
import {
  Bot,
  Volume2,
  VolumeX,
  X,
  Send,
  Shield,
  Trash2,
  Minimize2,
  Maximize2,
  Sliders,
  Square,
  Sparkles,
  FolderClock,
  Plus,
  MessageSquare,
  CornerDownLeft,
  ArrowRight,
} from 'lucide-vue-next';

// Configure marked parser for clean HTML rendering
marked.setOptions({
  gfm: true,
  breaks: true,
});

const renderMarkdown = (content) => {
  if (!content) return '';
  return marked.parse(content);
};

const page = usePage();

// Persistent Storage Keys
const STORAGE_KEY = 'simod_sadewa_chat_history_v2';
const SESSIONS_KEY = 'simod_sadewa_sessions_v1';
const ACTIVE_SESSION_KEY = 'simod_sadewa_active_session_id_v1';
const VOICE_PRESET_KEY = 'simod_sadewa_voice_preset_v2';
const MUTE_KEY = 'simod_sadewa_voice_muted_v2';

const isSadewaOpen = ref(false);
const isMinimized = ref(false);
const showArchiveView = ref(false);
const isSadewaSpeaking = ref(false);
const isSpeechMuted = ref(false);
const showVoiceSettings = ref(false);
const voicePitch = ref(0.78); // Bariton wibawa perwira default
const voiceRate = ref(0.98);
const selectedVoicePreset = ref('baritone'); // 'baritone' | 'command' | 'tactical'
const sadewaInput = ref('');
const isSadewaThinking = ref(false);

const DEFAULT_GREETING = {
  role: 'assistant',
  text: 'Siap Jenderal / Komandan. Berdasarkan pemantauan radar media siber terkini oleh **SADEWA** (*Sistem Analisis Digital & Evaluasi Wacana Aktual*), seluruh arus informasi dan dinamika opini publik dari 33 portal media telah terhimpun secara real-time. Ada isu spesifik atau pemutakhiran situasi yang ingin didalami?',
  time: 'Realtime',
};

const currentSessionId = ref(null);
const chatSessions = ref([]);
const sadewaMessages = ref([DEFAULT_GREETING]);

const sadewaChips = [
  { label: '🚨 Isu Negatif Terkini', query: 'Tampilkan daftar dan ringkasan isu-isu bersentimen negatif yang menonjol hari ini beserta potensi kerawanannya.' },
  { label: '📊 Ringkasan Situasi 24 Jam', query: 'Berikan ringkasan eksekutif situasi dinamika pemberitaan media online dan sentimen publik dalam 24 jam terakhir.' },
  { label: '🎯 Rekomendasi Respon Humas', query: 'Apa saja rekomendasi taktis komunikasi kehumasan yang perlu segera dilakukan oleh jajaran untuk mengendalikan opini publik?' },
  { label: '⚡ Sentimen & Sikap Warganet', query: 'Bagaimana komposisi polarisasi sentimen opini publik hari ini dan apa narasi dominan yang berkembang di media massa?' },
  { label: '🛡️ Isu Atensi Pimpinan', query: 'Apakah ada isu yang memerlukan atensi khusus pimpinan (Top Concern) hari ini dan bagaimana rekomendasi penanganannya?' },
];

const availableVoices = ref([]);

const loadVoices = () => {
  if (typeof window !== 'undefined' && 'speechSynthesis' in window) {
    const list = window.speechSynthesis.getVoices();
    if (list && list.length > 0) {
      availableVoices.value = list;
    }
  }
};

// Sessions Archive Management
const generateSessionId = () => 'sess_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6);

const loadSessions = () => {
  try {
    const raw = localStorage.getItem(SESSIONS_KEY);
    if (raw) {
      chatSessions.value = JSON.parse(raw);
    }
  } catch (e) {
    chatSessions.value = [];
  }
};

const persistSessions = () => {
  try {
    localStorage.setItem(SESSIONS_KEY, JSON.stringify(chatSessions.value));
  } catch (e) {}
};

const syncActiveSessionToArchive = () => {
  const hasUserMsg = sadewaMessages.value.some((m) => m.role === 'user');
  if (!hasUserMsg) return;

  if (!currentSessionId.value) {
    currentSessionId.value = generateSessionId();
    try {
      localStorage.setItem(ACTIVE_SESSION_KEY, currentSessionId.value);
    } catch (e) {}
  }

  const firstUserMsg = sadewaMessages.value.find((m) => m.role === 'user');
  let title = firstUserMsg ? firstUserMsg.text : 'Percakapan Intelijen SADEWA';
  if (title.length > 60) title = title.substring(0, 57) + '...';

  const lastAssistantMsg = [...sadewaMessages.value].reverse().find((m) => m.role === 'assistant');
  let snippet = lastAssistantMsg ? lastAssistantMsg.text.replace(/[#*`_~]/g, '').trim() : '';
  if (snippet.length > 85) snippet = snippet.substring(0, 82) + '...';

  const now = new Date();
  const timeLabel =
    now.toLocaleDateString('id-ID', { day: '2-digit', month: 'short' }) +
    ', ' +
    now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) +
    ' WITA';

  const sessionObj = {
    id: currentSessionId.value,
    title,
    snippet,
    messages: JSON.parse(JSON.stringify(sadewaMessages.value)),
    updatedAt: now.toISOString(),
    timeLabel,
  };

  const existingIdx = chatSessions.value.findIndex((s) => s.id === currentSessionId.value);
  if (existingIdx >= 0) {
    chatSessions.value[existingIdx] = sessionObj;
  } else {
    chatSessions.value.unshift(sessionObj);
  }

  if (chatSessions.value.length > 30) {
    chatSessions.value = chatSessions.value.slice(0, 30);
  }

  persistSessions();
};

const resumeSession = (session) => {
  stopSpeech();
  // Auto-archive active thread if it has unsaved questions and isn't the same
  if (currentSessionId.value !== session.id) {
    syncActiveSessionToArchive();
  }

  currentSessionId.value = session.id;
  sadewaMessages.value = JSON.parse(JSON.stringify(session.messages));
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(sadewaMessages.value));
    localStorage.setItem(ACTIVE_SESSION_KEY, session.id);
  } catch (e) {}

  showArchiveView.value = false;
  setTimeout(() => {
    const el = document.getElementById('sadewa-chat-body-global');
    if (el) el.scrollTop = el.scrollHeight;
  }, 50);
};

const startNewSession = () => {
  stopSpeech();
  syncActiveSessionToArchive();
  currentSessionId.value = null;
  sadewaMessages.value = [DEFAULT_GREETING];
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(sadewaMessages.value));
    localStorage.removeItem(ACTIVE_SESSION_KEY);
  } catch (e) {}
  showArchiveView.value = false;
};

const deleteSession = (id) => {
  chatSessions.value = chatSessions.value.filter((s) => s.id !== id);
  persistSessions();
  if (currentSessionId.value === id) {
    currentSessionId.value = null;
  }
};

const clearAllArchives = () => {
  chatSessions.value = [];
  persistSessions();
};

// Auto-Archive & Reset on Navigation Hook
const handleNavigation = () => {
  stopSpeech();
  // Auto-archive active session so user can resume it later
  syncActiveSessionToArchive();
  // Reset active screen to empty greeting so user on the new page gets a fresh slate
  currentSessionId.value = null;
  sadewaMessages.value = [DEFAULT_GREETING];
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(sadewaMessages.value));
    localStorage.removeItem(ACTIVE_SESSION_KEY);
  } catch (e) {}
  showArchiveView.value = false;
};

// Voice Presets
const applyVoicePreset = (preset) => {
  selectedVoicePreset.value = preset;
  try {
    localStorage.setItem(VOICE_PRESET_KEY, preset);
  } catch (e) {}

  if (preset === 'baritone') {
    voicePitch.value = 0.78; // Suara Pria Bariton Wibawa
    voiceRate.value = 0.98;
  } else if (preset === 'command') {
    voicePitch.value = 0.85; // Suara Pria Komandan Tegas
    voiceRate.value = 1.02;
  } else if (preset === 'tactical') {
    voicePitch.value = 0.92; // Suara Pria Analis Taktis
    voiceRate.value = 1.05;
  }
};

// Web Speech API Voice Picker prioritizing Indonesian Male
const getIndonesianMaleVoice = () => {
  if (typeof window === 'undefined' || !('speechSynthesis' in window)) return null;
  const voices = availableVoices.value.length > 0 ? availableVoices.value : window.speechSynthesis.getVoices();
  if (!voices.length) return null;

  // 1. Explicit Indonesian/Malay Male voice (Ardi, Andika, Male, Pria, etc.)
  const idMale = voices.find(
    (v) =>
      (v.lang.startsWith('id') || v.lang.startsWith('in') || v.lang.startsWith('ms') || v.lang.includes('ID')) &&
      (v.name.toLowerCase().includes('ardi') ||
        v.name.toLowerCase().includes('andika') ||
        v.name.toLowerCase().includes('male') ||
        v.name.toLowerCase().includes('pria') ||
        v.name.toLowerCase().includes('lelaki'))
  );
  if (idMale) return idMale;

  // 2. Any Indonesian / Malay voice (Google Bahasa Indonesia, Gadis, etc.)
  // Modulated pitch transforms it into authoritative masculine baritone
  const idAny = voices.find(
    (v) =>
      v.lang.startsWith('id') ||
      v.lang.startsWith('in') ||
      v.lang.startsWith('ms') ||
      v.lang.toLowerCase().includes('indonesia') ||
      v.lang.toLowerCase().includes('melayu')
  );
  if (idAny) return idAny;

  // 3. Fallback Male system voice
  const anyMale = voices.find(
    (v) =>
      v.name.toLowerCase().includes('male') ||
      v.name.toLowerCase().includes('david') ||
      v.name.toLowerCase().includes('mark')
  );
  if (anyMale) return anyMale;

  return voices[0] || null;
};

const activeVoiceInfo = computed(() => {
  const voice = getIndonesianMaleVoice();
  if (!voice) return 'Default Sistem (Modulasi Bariton Pria)';
  const isIndo = voice.lang.startsWith('id') || voice.lang.startsWith('in') || voice.lang.toLowerCase().includes('indonesia');
  if (isIndo) {
    return `${voice.name} (${voice.name.toLowerCase().includes('ardi') || voice.name.toLowerCase().includes('male') ? 'Pria Natural' : 'Termodulasi Bariton Pria'})`;
  }
  return `${voice.name} (Modulasi Bariton Pria)`;
});

const stopSpeech = () => {
  if (typeof window !== 'undefined' && 'speechSynthesis' in window) {
    window.speechSynthesis.cancel();
  }
  isSadewaSpeaking.value = false;
};

const speakText = (text) => {
  if (isSpeechMuted.value) return;
  if (typeof window === 'undefined' || !('speechSynthesis' in window)) return;

  stopSpeech();

  const clean = text
    .replace(/[#*`_~]/g, '')
    .replace(/\[.*?\]/g, '')
    .replace(/\(.*?\)/g, '')
    .replace(/•/g, ' ')
    .trim();

  if (!clean) return;

  const utterance = new SpeechSynthesisUtterance(clean);
  utterance.lang = 'id-ID';
  utterance.rate = voiceRate.value;
  utterance.pitch = voicePitch.value; // Deep masculine officer baritone

  const voice = getIndonesianMaleVoice();
  if (voice) utterance.voice = voice;

  utterance.onstart = () => {
    isSadewaSpeaking.value = true;
  };
  utterance.onend = () => {
    isSadewaSpeaking.value = false;
  };
  utterance.onerror = () => {
    isSadewaSpeaking.value = false;
  };

  window.speechSynthesis.speak(utterance);
};

const testVoice = () => {
  speakText('Siap Jenderal. Karakter suara pria bariton SADEWA siap menyajikan analisis intelijen media siber.');
};

let removeBeforeHook = null;
let removeNavigateHook = null;

onMounted(() => {
  loadVoices();
  if (typeof window !== 'undefined' && 'speechSynthesis' in window) {
    window.speechSynthesis.onvoiceschanged = loadVoices;
  }

  loadSessions();

  try {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved) {
      const parsed = JSON.parse(saved);
      if (Array.isArray(parsed) && parsed.length > 0) {
        sadewaMessages.value = parsed;
      }
    }

    const savedSessionId = localStorage.getItem(ACTIVE_SESSION_KEY);
    if (savedSessionId) {
      currentSessionId.value = savedSessionId;
    }

    const savedMute = localStorage.getItem(MUTE_KEY);
    if (savedMute !== null) {
      isSpeechMuted.value = savedMute === 'true';
    }

    const savedPreset = localStorage.getItem(VOICE_PRESET_KEY);
    if (savedPreset) {
      applyVoicePreset(savedPreset);
    }
  } catch (e) {}

  window.addEventListener('open-sadewa-copilot', handleGlobalOpen);

  // Auto-archive active session and stop audio when navigating between menus in Inertia
  removeBeforeHook = router.on('before', handleNavigation);
  removeNavigateHook = router.on('navigate', stopSpeech);
});

onBeforeUnmount(() => {
  stopSpeech();
  syncActiveSessionToArchive();
  window.removeEventListener('open-sadewa-copilot', handleGlobalOpen);
  if (removeBeforeHook) removeBeforeHook();
  if (removeNavigateHook) removeNavigateHook();
});

const handleGlobalOpen = (e) => {
  isSadewaOpen.value = true;
  isMinimized.value = false;
  showArchiveView.value = false;
  if (e.detail?.query) {
    sendSadewaMessage(e.detail.query);
  }
};

// Save chat history on update
watch(
  sadewaMessages,
  (newVal) => {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(newVal));
    } catch (e) {}
  },
  { deep: true }
);

watch(isSpeechMuted, (val) => {
  try {
    localStorage.setItem(MUTE_KEY, val.toString());
  } catch (e) {}
});

const sendSadewaMessage = async (customText = null) => {
  const query = (customText || sadewaInput.value).trim();
  if (!query || isSadewaThinking.value) return;

  showArchiveView.value = false;

  sadewaMessages.value.push({
    role: 'user',
    text: query,
    time: new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }),
  });
  sadewaInput.value = '';
  isSadewaThinking.value = true;

  // Immediately sync session so it exists in archive
  syncActiveSessionToArchive();

  setTimeout(() => {
    const el = document.getElementById('sadewa-chat-body-global');
    if (el) el.scrollTop = el.scrollHeight;
  }, 50);

  try {
    const historyPayload = sadewaMessages.value.map((m) => ({
      role: m.role,
      content: m.text,
    }));

    const res = await axios.post('/media-intelligence/chat', {
      message: query,
      history: historyPayload,
      polda_id: page.props.selectedPolda?.id || page.props.auth?.user?.polda_id,
    });

    if (res.data?.success && res.data?.reply) {
      const replyObj = {
        role: 'assistant',
        text: res.data.reply,
        time: new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }),
      };
      sadewaMessages.value.push(replyObj);

      // Persist full answer to archive
      syncActiveSessionToArchive();

      setTimeout(() => {
        const el = document.getElementById('sadewa-chat-body-global');
        if (el) el.scrollTop = el.scrollHeight;
      }, 50);

      if (!isSpeechMuted.value) {
        speakText(res.data.reply);
      }
    } else {
      sadewaMessages.value.push({
        role: 'assistant',
        text: 'Mohon maaf, terjadi kendala saat memproses jawaban radar intelijen. Silakan coba kembali.',
        time: 'Error',
      });
    }
  } catch (err) {
    sadewaMessages.value.push({
      role: 'assistant',
      text: err.response?.data?.message || 'Gagal terhubung ke mesin analisis SADEWA AI. Pastikan API Key Gemini telah aktif.',
      time: 'Error',
    });
  } finally {
    isSadewaThinking.value = false;
    setTimeout(() => {
      const el = document.getElementById('sadewa-chat-body-global');
      if (el) el.scrollTop = el.scrollHeight;
    }, 50);
  }
};
</script>

<template>
  <!-- Floating Action Launcher Button (Theme: Midnight Command Navy & Amber Gold) -->
  <div class="fixed bottom-6 right-6 z-40 no-print flex items-center gap-2">
    <button
      type="button"
      @click="isSadewaOpen = !isSadewaOpen; if (isSadewaOpen) isMinimized = false"
      class="group flex items-center gap-3 px-4 py-3 rounded-full bg-gradient-to-r from-[#070d1d] via-[#0b1426] to-[#0f1d38] hover:from-[#0b1426] hover:to-[#16274a] text-white font-black text-xs shadow-2xl shadow-amber-500/10 border border-amber-500/50 hover:border-amber-400 hover:scale-105 active:scale-95 transition-all cursor-pointer select-none"
      title="Buka Tanya Jawab SADEWA AI seputar berita & intelijen media"
    >
      <div class="relative flex items-center justify-center w-7 h-7 rounded-full bg-gradient-to-tr from-[#0b1426] to-[#1a2d52] border border-amber-400/40 text-amber-400">
        <Bot class="w-4 h-4" />
        <span class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
        <span class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-emerald-400"></span>
      </div>
      <div class="text-left">
        <div class="flex items-center gap-1.5">
          <span class="tracking-wider font-black text-amber-300">SADEWA AI</span>
          <span class="px-1.5 py-0.2 rounded text-[9px] font-black bg-amber-400/20 text-amber-300 border border-amber-400/30 uppercase">
            Copilot
          </span>
        </div>
        <p class="text-[10px] text-slate-300 font-normal">Tanya Situasi Berita</p>
      </div>

      <!-- Live Speaking Equalizer Indicator on Launcher -->
      <div v-if="isSadewaSpeaking" class="flex items-center gap-0.5 h-3 pl-1 border-l border-slate-700">
        <span class="w-0.5 h-full bg-amber-400 animate-pulse"></span>
        <span class="w-0.5 h-2 bg-amber-400 animate-pulse delay-75"></span>
        <span class="w-0.5 h-3 bg-amber-400 animate-pulse delay-150"></span>
      </div>
    </button>
  </div>

  <!-- SADEWA AI Floating Chat Window (Desktop: 460px wide x 630px tall, Mobile: Responsive) -->
  <Teleport to="body">
    <transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="opacity-0 translate-y-8 scale-95"
      enter-to-class="opacity-100 translate-y-0 scale-100"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="opacity-100 translate-y-0 scale-100"
      leave-to-class="opacity-0 translate-y-8 scale-95"
    >
      <div
        v-if="isSadewaOpen"
        class="fixed bottom-20 right-4 sm:right-6 z-50 w-[calc(100vw-32px)] sm:w-[460px] bg-white dark:bg-[#070d1d] text-slate-800 dark:text-slate-100 border border-slate-300 dark:border-amber-500/30 rounded-3xl shadow-2xl overflow-hidden flex flex-col no-print ring-1 ring-black/10 dark:ring-amber-500/10 backdrop-blur-xl transition-all"
        :class="isMinimized ? 'h-[64px]' : 'h-[630px] max-h-[85vh]'"
      >
        <!-- Header (Executive Midnight Navy & Gold Accents - SIMOD Theme) -->
        <div class="px-4 py-3 bg-gradient-to-r from-[#070d1d] via-[#0b1426] to-[#0f1d38] border-b border-amber-500/30 flex items-center justify-between text-white shrink-0">
          <div class="flex items-center gap-3">
            <div class="relative w-9 h-9 rounded-full bg-gradient-to-tr from-[#0b1426] to-[#1a2d52] border border-amber-400/40 flex items-center justify-center text-amber-400 shadow-inner">
              <Bot class="w-5 h-5" />
              <span class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-emerald-400 border-2 border-[#0b1426]"></span>
            </div>
            <div>
              <div class="flex items-center gap-2">
                <h3 class="font-black text-sm text-white tracking-wide">SADEWA AI</h3>
                <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-amber-400/10 text-amber-300 border border-amber-400/30">
                  Command Center Copilot
                </span>
              </div>
              <p class="text-[10px] text-slate-300 line-clamp-1">
                Sistem Analisis Digital & Evaluasi Wacana Aktual
              </p>
            </div>
          </div>

          <!-- Utility Buttons (Arsip, Sesi Baru, Suara, Mute, Minimize, Close) -->
          <div class="flex items-center gap-1 text-slate-300">
            <!-- Archive View Toggle Button -->
            <button
              type="button"
              @click="showArchiveView = !showArchiveView; showVoiceSettings = false"
              class="px-2 py-1 rounded-lg text-[10.5px] font-bold border transition flex items-center gap-1 cursor-pointer"
              :class="showArchiveView ? 'bg-amber-400 text-slate-950 border-amber-300 font-black shadow-sm' : 'bg-slate-900/80 border-slate-700/80 text-amber-300 hover:bg-slate-800'"
              title="Buka Arsip Percakapan Sebelumnya"
            >
              <FolderClock class="w-3.5 h-3.5" />
              <span class="hidden sm:inline">Arsip</span>
              <span v-if="chatSessions.length > 0" class="px-1.5 py-0.2 rounded-full text-[9px]" :class="showArchiveView ? 'bg-slate-950 text-amber-300' : 'bg-amber-400/20 text-amber-300'">
                {{ chatSessions.length }}
              </span>
            </button>

            <!-- New Chat / Archive Current Session Button -->
            <button
              type="button"
              @click="startNewSession"
              class="p-1.5 rounded-lg hover:bg-white/10 text-slate-300 hover:text-amber-300 transition cursor-pointer"
              title="Mulai Sesi Baru (+ Arsipkan Chat Saat Ini)"
            >
              <Plus class="w-4 h-4" />
            </button>

            <!-- Voice Settings Popover Trigger -->
            <button
              type="button"
              @click="showVoiceSettings = !showVoiceSettings; showArchiveView = false"
              class="p-1.5 rounded-lg hover:bg-white/10 transition cursor-pointer"
              :class="showVoiceSettings ? 'bg-white/20 text-white' : ''"
              title="Pengaturan Karakter Suara Pria SADEWA"
            >
              <Sliders class="w-3.5 h-3.5 text-slate-300 hover:text-white" />
            </button>

            <!-- Mute/Unmute Audio Toggle -->
            <button
              type="button"
              @click="isSpeechMuted = !isSpeechMuted; if (isSpeechMuted) stopSpeech()"
              class="p-1.5 rounded-lg hover:bg-white/10 transition cursor-pointer"
              :title="isSpeechMuted ? 'Suara AI Dimatikan (Klik untuk aktifkan)' : 'Suara AI Aktif (Klik untuk matikan)'"
            >
              <VolumeX v-if="isSpeechMuted" class="w-4 h-4 text-rose-300" />
              <Volume2 v-else class="w-4 h-4 text-emerald-300" />
            </button>

            <!-- Minimize / Expand Toggle -->
            <button
              type="button"
              @click="isMinimized = !isMinimized"
              class="p-1.5 rounded-lg hover:bg-white/10 transition cursor-pointer"
              :title="isMinimized ? 'Perluas jendela' : 'Minimalkan jendela'"
            >
              <Maximize2 v-if="isMinimized" class="w-3.5 h-3.5 text-slate-300 hover:text-white" />
              <Minimize2 v-else class="w-3.5 h-3.5 text-slate-300 hover:text-white" />
            </button>

            <!-- Close Window Button -->
            <button
              type="button"
              @click="isSadewaOpen = false; stopSpeech()"
              class="p-1.5 rounded-lg hover:bg-white/10 transition cursor-pointer"
              title="Tutup SADEWA AI"
            >
              <X class="w-4 h-4 text-slate-300 hover:text-white" />
            </button>
          </div>
        </div>

        <!-- Voice Settings Sub-Panel (Dropdown Modal) -->
        <div
          v-if="showVoiceSettings && !isMinimized"
          class="p-3 bg-[#0b1426] text-white border-b border-slate-700/80 space-y-2.5 text-xs animate-in slide-in-from-top-2 duration-150"
        >
          <div class="flex items-center justify-between">
            <span class="font-bold text-slate-200 flex items-center gap-1.5 text-[11px]">
              <Volume2 class="w-3.5 h-3.5 text-amber-400" />
              <span>Karakter Suara Pria SADEWA (Bahasa Indonesia):</span>
            </span>
            <button
              @click="showVoiceSettings = false"
              class="text-slate-400 hover:text-white text-[10px]"
            >
              ✕ Tutup
            </button>
          </div>

          <!-- Presets -->
          <div class="grid grid-cols-3 gap-2">
            <button
              type="button"
              @click="applyVoicePreset('baritone')"
              class="px-2.5 py-1.5 rounded-xl border text-[11px] font-bold transition flex flex-col items-center gap-0.5 cursor-pointer"
              :class="selectedVoicePreset === 'baritone' ? 'bg-amber-400 text-slate-950 border-amber-300 font-black shadow-sm' : 'bg-slate-900 border-slate-700 text-slate-300 hover:border-slate-500'"
            >
              <span>Perwira Bariton</span>
              <span class="text-[9px] opacity-75">Wibawa Dalam (0.78)</span>
            </button>

            <button
              type="button"
              @click="applyVoicePreset('command')"
              class="px-2.5 py-1.5 rounded-xl border text-[11px] font-bold transition flex flex-col items-center gap-0.5 cursor-pointer"
              :class="selectedVoicePreset === 'command' ? 'bg-amber-400 text-slate-950 border-amber-300 font-black shadow-sm' : 'bg-slate-900 border-slate-700 text-slate-300 hover:border-slate-500'"
            >
              <span>Komandan</span>
              <span class="text-[9px] opacity-75">Tegas & Mantap (0.85)</span>
            </button>

            <button
              type="button"
              @click="applyVoicePreset('tactical')"
              class="px-2.5 py-1.5 rounded-xl border text-[11px] font-bold transition flex flex-col items-center gap-0.5 cursor-pointer"
              :class="selectedVoicePreset === 'tactical' ? 'bg-amber-400 text-slate-950 border-amber-300 font-black shadow-sm' : 'bg-slate-900 border-slate-700 text-slate-300 hover:border-slate-500'"
            >
              <span>Analis Taktis</span>
              <span class="text-[9px] opacity-75">Runtut Cepat (0.92)</span>
            </button>
          </div>

          <!-- Detected Speech Synthesis Engine Info -->
          <div class="px-2.5 py-1.5 rounded-xl bg-slate-900/90 border border-slate-800/90 flex items-center justify-between text-[10px]">
            <span class="text-slate-400 flex items-center gap-1">
              <Sparkles class="w-3 h-3 text-amber-400" />
              <span>Engine Suara:</span>
            </span>
            <span class="font-bold text-amber-300 truncate max-w-[240px]" :title="activeVoiceInfo">
              {{ activeVoiceInfo }}
            </span>
          </div>

          <div class="flex items-center justify-between pt-1 border-t border-slate-800 text-[10.5px]">
            <span class="text-slate-400">Modulasi Nada: <strong class="text-amber-400">{{ voicePitch }}x</strong> (Bariton Pria)</span>
            <button
              type="button"
              @click="testVoice"
              class="px-3 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold transition flex items-center gap-1.5 cursor-pointer"
            >
              <Volume2 class="w-3 h-3" />
              <span>▶ Uji Suara SADEWA</span>
            </button>
          </div>
        </div>

        <!-- Active Voice Playback Banner (Animated Equalizer & Instant Stop) -->
        <div
          v-if="isSadewaSpeaking && !isMinimized"
          class="px-4 py-2 bg-gradient-to-r from-[#070d1d] via-[#0f1d38] to-[#070d1d] border-b border-amber-500/30 flex items-center justify-between text-xs text-amber-200 shrink-0"
        >
          <div class="flex items-center gap-2.5">
            <!-- Animated Soundwave Equalizer -->
            <div class="flex items-center gap-0.5 h-3.5">
              <span class="w-1 bg-amber-400 rounded-full h-full animate-[pulse_0.4s_ease-in-out_infinite]"></span>
              <span class="w-1 bg-amber-300 rounded-full h-2 animate-[pulse_0.6s_ease-in-out_infinite]"></span>
              <span class="w-1 bg-amber-400 rounded-full h-3.5 animate-[pulse_0.3s_ease-in-out_infinite]"></span>
              <span class="w-1 bg-amber-300 rounded-full h-1.5 animate-[pulse_0.5s_ease-in-out_infinite]"></span>
            </div>
            <span class="font-bold text-[11px] text-white">SADEWA sedang membacakan laporan...</span>
          </div>
          <button
            type="button"
            @click="stopSpeech"
            class="px-2.5 py-1 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-[10px] flex items-center gap-1 transition shadow-sm cursor-pointer"
          >
            <Square class="w-2.5 h-2.5 fill-slate-950" />
            <span>Hentikan Suara</span>
          </button>
        </div>

        <!-- VIEW A: ARSIP CHAT SESSIONS (Ketika showArchiveView Aktif) -->
        <template v-if="!isMinimized && showArchiveView">
          <div class="p-3 bg-slate-100 dark:bg-[#0b1426] border-b border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs shrink-0">
            <div class="flex items-center gap-2">
              <FolderClock class="w-4 h-4 text-amber-400" />
              <span class="font-bold text-slate-900 dark:text-white text-xs">Riwayat Sesi Percakapan</span>
              <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-400/20 text-amber-300 border border-amber-400/30">
                {{ chatSessions.length }} Tersimpan
              </span>
            </div>
            <button
              type="button"
              @click="showArchiveView = false"
              class="text-[11px] font-bold text-amber-500 hover:text-amber-400 flex items-center gap-1 cursor-pointer"
            >
              <span>✕ Kembali ke Chat</span>
            </button>
          </div>

          <!-- Empty Archive State -->
          <div
            v-if="chatSessions.length === 0"
            class="flex-1 p-8 flex flex-col items-center justify-center text-center text-slate-400 space-y-3 bg-slate-50/70 dark:bg-[#070d18]"
          >
            <div class="w-12 h-12 rounded-2xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 flex items-center justify-center text-slate-400">
              <FolderClock class="w-6 h-6 text-amber-400" />
            </div>
            <div class="space-y-1">
              <h4 class="font-bold text-slate-800 dark:text-slate-200 text-xs">Belum Ada Sesi Diarsipkan</h4>
              <p class="text-[11px] text-slate-500 dark:text-slate-400 max-w-xs leading-relaxed">
                Setiap percakapan akan otomatis diarsipkan saat Anda berpindah menu atau menekan tombol <strong>+ Sesi Baru</strong>.
              </p>
            </div>
            <button
              type="button"
              @click="showArchiveView = false"
              class="px-3.5 py-1.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-bold text-xs transition shadow-sm cursor-pointer"
            >
              Mulai Tanya SADEWA
            </button>
          </div>

          <!-- Archive List -->
          <div
            v-else
            class="flex-1 p-3 overflow-y-auto space-y-2.5 custom-scrollbar bg-slate-50/70 dark:bg-[#070d18]"
          >
            <div
              v-for="sess in chatSessions"
              :key="sess.id"
              class="p-3.5 rounded-2xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800 hover:border-amber-500/50 hover:shadow-lg transition space-y-2 group"
            >
              <!-- Card Header -->
              <div class="flex items-start justify-between gap-2">
                <h4
                  @click="resumeSession(sess)"
                  class="font-bold text-xs text-slate-900 dark:text-slate-100 group-hover:text-amber-400 transition cursor-pointer line-clamp-2"
                  :title="sess.title"
                >
                  {{ sess.title }}
                </h4>
                <span class="text-[10px] text-slate-400 font-mono whitespace-nowrap shrink-0">{{ sess.timeLabel }}</span>
              </div>

              <!-- Card Snippet -->
              <p
                v-if="sess.snippet"
                @click="resumeSession(sess)"
                class="text-[11px] text-slate-600 dark:text-slate-300 line-clamp-2 leading-relaxed cursor-pointer"
              >
                {{ sess.snippet }}
              </p>

              <!-- Card Footer Action -->
              <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800/80 text-[11px]">
                <span class="text-[10px] text-slate-400 flex items-center gap-1 font-mono">
                  <MessageSquare class="w-3 h-3 text-amber-400" />
                  <span>{{ sess.messages.length }} Pesan</span>
                </span>

                <div class="flex items-center gap-2">
                  <button
                    type="button"
                    @click.stop="deleteSession(sess.id)"
                    class="p-1 rounded-lg hover:bg-rose-500/20 text-slate-400 hover:text-rose-400 transition cursor-pointer"
                    title="Hapus sesi arsip ini"
                  >
                    <Trash2 class="w-3.5 h-3.5" />
                  </button>

                  <button
                    type="button"
                    @click="resumeSession(sess)"
                    class="px-2.5 py-1 rounded-lg bg-amber-500/15 hover:bg-amber-400 text-amber-300 hover:text-slate-950 font-bold text-[10.5px] border border-amber-500/30 transition flex items-center gap-1 cursor-pointer"
                    title="Lanjutkan percakapan ini"
                  >
                    <span>▶ Lanjutkan</span>
                  </button>
                </div>
              </div>
            </div>

            <!-- Clear All Option -->
            <div v-if="chatSessions.length > 1" class="text-center pt-2 pb-1">
              <button
                type="button"
                @click="clearAllArchives"
                class="text-[10.5px] text-rose-400 hover:text-rose-300 underline cursor-pointer"
              >
                Hapus Semua Arsip ({{ chatSessions.length }})
              </button>
            </div>
          </div>
        </template>

        <!-- VIEW B: ACTIVE CHAT CONVERSATION -->
        <template v-else-if="!isMinimized">
          <!-- Quick Prompts Horizontal Pills -->
          <div class="px-3 py-2 bg-slate-100/90 dark:bg-[#070d18] border-b border-slate-200 dark:border-slate-800/80 flex items-center gap-2 overflow-x-auto custom-scrollbar shrink-0">
            <button
              v-for="(chip, cIdx) in sadewaChips"
              :key="'chip-' + cIdx"
              type="button"
              @click="sendSadewaMessage(chip.query)"
              :disabled="isSadewaThinking"
              class="px-3 py-1 rounded-full text-[11px] font-bold bg-white dark:bg-[#0b1426] hover:bg-amber-50 dark:hover:bg-amber-500/10 text-slate-700 dark:text-slate-300 hover:text-amber-500 dark:hover:text-amber-300 border border-slate-300 dark:border-slate-800 hover:border-amber-500/60 whitespace-nowrap transition cursor-pointer shrink-0 disabled:opacity-50 shadow-xs"
            >
              {{ chip.label }}
            </button>
          </div>

          <!-- Conversation Scrollable Body -->
          <div
            id="sadewa-chat-body-global"
            class="flex-1 p-4 overflow-y-auto space-y-4 custom-scrollbar bg-slate-50/70 dark:bg-gradient-to-b dark:from-[#070d1d] dark:via-[#091122] dark:to-[#070d18]"
          >
            <div
              v-for="(msg, mIdx) in sadewaMessages"
              :key="'msg-' + mIdx"
              class="flex flex-col"
              :class="msg.role === 'user' ? 'items-end' : 'items-start'"
            >
              <!-- User Bubble (Executive Command Center Navy Card with Gold Accent) -->
              <div
                v-if="msg.role === 'user'"
                class="max-w-[85%] rounded-2xl rounded-tr-xs bg-[#132244] border border-amber-500/30 text-slate-100 px-4 py-2.5 text-xs shadow-md leading-relaxed"
              >
                {{ msg.text }}
                <div class="text-[9px] text-amber-300/80 text-right mt-1 font-mono font-medium">{{ msg.time }}</div>
              </div>

              <!-- Assistant Bubble (Command Slate Card with Emerald Live Badge) -->
              <div
                v-else
                class="flex items-start gap-2.5 max-w-[92%]"
              >
                <!-- Left Mini Avatar -->
                <div class="w-7 h-7 rounded-full bg-gradient-to-tr from-[#0b1426] to-[#1a2d52] border border-amber-400/40 text-amber-400 flex items-center justify-center shrink-0 mt-0.5 shadow-sm">
                  <Bot class="w-4 h-4" />
                </div>

                <!-- Message Card -->
                <div class="flex-1 rounded-2xl rounded-tl-xs bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800/80 text-slate-800 dark:text-slate-100 p-3.5 text-xs shadow-md space-y-2 leading-relaxed">
                  <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800/80 pb-1.5 gap-2">
                    <div class="flex items-center gap-2">
                      <span class="font-extrabold text-slate-900 dark:text-white text-[11px]">SADEWA AI</span>
                      <span class="px-1.5 py-0.2 rounded text-[9px] font-black uppercase bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
                        • RADAR LIVE
                      </span>
                    </div>

                    <!-- Text-to-Speech Play Button -->
                    <button
                      type="button"
                      @click="speakText(msg.text)"
                      class="p-1 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 hover:text-amber-400 transition cursor-pointer"
                      title="Dengarkan pembacaan suara pria SADEWA"
                    >
                      <Volume2 class="w-3.5 h-3.5" :class="{ 'text-amber-400 animate-pulse': isSadewaSpeaking }" />
                    </button>
                  </div>

                  <!-- Message Content (Markdown/Formatted via marked) -->
                  <div
                    class="text-[11.5px] text-slate-700 dark:text-slate-200 leading-relaxed sadewa-markdown-body"
                    v-html="renderMarkdown(msg.text)"
                  ></div>

                  <div class="text-[9px] text-slate-400 font-mono text-right pt-1 flex items-center justify-between border-t border-slate-100 dark:border-slate-800/60">
                    <span class="text-slate-400 dark:text-slate-500 font-sans font-medium">Terverifikasi 33 Media</span>
                    <span>{{ msg.time }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Thinking / Loading Indicator -->
            <div v-if="isSadewaThinking" class="flex items-start gap-2.5">
              <div class="w-7 h-7 rounded-full bg-gradient-to-tr from-[#0b1426] to-[#1a2d52] border border-amber-400/40 text-amber-400 flex items-center justify-center shrink-0 mt-0.5">
                <Bot class="w-4 h-4" />
              </div>
              <div class="px-4 py-3 rounded-2xl rounded-tl-xs bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-800/80 text-slate-800 dark:text-slate-200 text-xs shadow-md flex items-center gap-2.5">
                <span class="relative flex h-2 w-2">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                </span>
                <span class="text-[11px] font-semibold text-amber-600 dark:text-amber-400 animate-pulse">SADEWA sedang menganalisis radar berita...</span>
              </div>
            </div>
          </div>

          <!-- Chat Input Bar (Sleek Command Field & Amber Gold Send Arrow) -->
          <div class="p-3 bg-white dark:bg-[#070d1d] border-t border-slate-200 dark:border-slate-800 shrink-0 space-y-1.5 shadow-lg">
            <form @submit.prevent="sendSadewaMessage()" class="flex items-center gap-2">
              <div class="relative flex-1">
                <input
                  v-model="sadewaInput"
                  type="text"
                  placeholder="Tanya SADEWA seputar dinamika berita & isu Polri..."
                  :disabled="isSadewaThinking"
                  class="w-full px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-[#0b1426] border border-slate-300 dark:border-slate-700/80 text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 disabled:opacity-50 transition"
                />
              </div>
              <button
                type="submit"
                :disabled="!sadewaInput.trim() || isSadewaThinking"
                class="w-9 h-9 rounded-xl bg-gradient-to-r from-amber-500 via-amber-400 to-amber-500 hover:from-amber-400 hover:to-amber-300 text-slate-950 font-bold flex items-center justify-center shrink-0 transition shadow-lg shadow-amber-500/20 active:scale-95 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                title="Kirim pertanyaan"
              >
                <Send class="w-4 h-4 text-slate-950" />
              </button>
            </form>

            <!-- Footer Attribution -->
            <div class="flex items-center justify-between text-[9px] text-slate-500 dark:text-slate-400 px-1">
              <span class="flex items-center gap-1.5 font-medium">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                <span>SADEWA (Sistem Analisis Digital & Evaluasi Wacana Aktual)</span>
              </span>
              <span class="flex items-center gap-1 font-semibold text-slate-600 dark:text-slate-400">
                <Shield class="w-2.5 h-2.5 text-amber-500" />
                <span>Divhumas Polri Engine</span>
              </span>
            </div>
          </div>
        </template>
      </div>
    </transition>
  </Teleport>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  height: 4px;
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(148, 163, 184, 0.2);
  border-radius: 8px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgba(245, 158, 11, 0.5);
}

/* SADEWA AI Natural Markdown Body Styling (Theme: SIMOD Gold & Slate) */
.sadewa-markdown-body :deep(h1),
.sadewa-markdown-body :deep(h2),
.sadewa-markdown-body :deep(h3),
.sadewa-markdown-body :deep(h4) {
  font-weight: 800;
  font-size: 12px;
  line-height: 1.45;
  margin-top: 0.75rem;
  margin-bottom: 0.35rem;
  color: #b45309;
}
:root.dark .sadewa-markdown-body :deep(h1),
:root.dark .sadewa-markdown-body :deep(h2),
:root.dark .sadewa-markdown-body :deep(h3),
:root.dark .sadewa-markdown-body :deep(h4) {
  color: #fbbf24;
}
.sadewa-markdown-body :deep(p) {
  margin-bottom: 0.45rem;
  line-height: 1.6;
}
.sadewa-markdown-body :deep(p:last-child) {
  margin-bottom: 0;
}
.sadewa-markdown-body :deep(ul),
.sadewa-markdown-body :deep(ol) {
  padding-left: 1.25rem;
  margin-top: 0.25rem;
  margin-bottom: 0.45rem;
}
.sadewa-markdown-body :deep(ul) {
  list-style-type: disc;
}
.sadewa-markdown-body :deep(ol) {
  list-style-type: decimal;
}
.sadewa-markdown-body :deep(li) {
  margin-bottom: 0.25rem;
  line-height: 1.5;
}
.sadewa-markdown-body :deep(strong) {
  font-weight: 800;
  color: #0f172a;
}
:root.dark .sadewa-markdown-body :deep(strong) {
  color: #f8fafc;
}
.sadewa-markdown-body :deep(hr) {
  border: 0;
  border-top: 1px solid rgba(148, 163, 184, 0.25);
  margin: 0.65rem 0;
}
</style>
