<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import {
  Shield,
  Building2,
  AlertTriangle,
  Flame,
  Radio,
  Newspaper,
  CheckCircle2,
  ExternalLink,
  Bookmark,
  TrendingUp,
  MapPin,
  Sparkles,
  Info,
  Clock,
  Calendar,
  Plus,
  PlusCircle,
  X,
  Send,
  Zap,
  Award,
  FileText,
  Search,
  RotateCw,
  Copy,
  Clipboard,
  SlidersHorizontal,
  Globe,
  Trash2,
  Check,
  ChevronDown,
  ChevronUp,
  FileJson,
  Eye,
  MessageSquare,
  MessageCircle,
  Download,
  Maximize2,
  Minimize2,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  earlyWarnings: Array,
  mediaFeeds: Array,
  sampleArticles: Array,
  portalsList: Array,
  categoriesList: Array,
  report: Object,
  aiPromptTemplate: String,
  issues: Array,
  pressReleases: Array,
  metrics: Object,
});

// Active Navigation Tab (01-07)
const activeTab = ref('01');

const tabs = [
  { id: '01', name: '01. Berita Online (Live RSS)', badge: 'LIVE', icon: Radio },
  { id: '02', name: '02. Executive Briefing Pimpinan', badge: 'ANALISIS', icon: Newspaper },
  { id: '03', name: '03. Sentimen Berita', badge: null, icon: Zap },
  { id: '04', name: '04. Statistik Satwil', badge: null, icon: MapPin },
  { id: '05', name: '05. Early Warning (EWS)', badge: 'EWS', icon: Shield },
  { id: '06', name: '06. Narasi & Rekomendasi', badge: null, icon: Award },
  { id: '07', name: '07. Naskah WA Pimpinan', badge: 'WA RESMI', icon: MessageSquare },
];

// Bookmark state stored in localStorage per device
const bookmarkedIds = ref(JSON.parse(localStorage.getItem('simod_ent_bookmarks') || '[]'));
const isBookmarked = (id) => bookmarkedIds.value.includes(id);

const toggleBookmark = (id) => {
  if (bookmarkedIds.value.includes(id)) {
    bookmarkedIds.value = bookmarkedIds.value.filter((item) => item !== id);
    showToastNotification('Berita dihapus dari Bookmark Tersimpan');
  } else {
    bookmarkedIds.value.push(id);
    showToastNotification('Berita berhasil disimpan ke Bookmark! (★ Tersimpan)');
  }
  localStorage.setItem('simod_ent_bookmarks', JSON.stringify(bookmarkedIds.value));
};

// Pagination State
const currentPage = ref(1);
const perPage = ref(50);
const handleSearch = () => {
  currentPage.value = 1;
};

// Image fallback tracker
const brokenImages = ref(new Set());
const handleImageError = (e, article) => {
  brokenImages.value.add(article.id);
  e.target.style.display = 'none';
  if (e.target.nextElementSibling) {
    e.target.nextElementSibling.classList.remove('hidden');
  }
};

// Filter States
const selectedPortal = ref('Semua Portal');
const selectedCategory = ref('all');
const selectedSentiment = ref('all');
const searchQuery = ref('');
const toastMessage = ref('');

// Modal state for viewing/copying prompt manually
const showPromptModal = ref(false);
const promptModalTitle = ref('');
const promptModalContent = ref('');

const openPromptPreviewModal = (title, content) => {
  promptModalTitle.value = title;
  promptModalContent.value = content;
  showPromptModal.value = true;
};

// Robust Toast
const showToastNotification = (msg) => {
  toastMessage.value = msg;
  setTimeout(() => {
    toastMessage.value = '';
  }, 4000);
};

// Universal copy with fallback for non-secure HTTP contexts (.test domain)
const execCommandCopy = (text, label = 'Teks') => {
  try {
    const el = document.createElement('textarea');
    el.value = text;
    el.setAttribute('readonly', '');
    el.style.position = 'fixed';
    el.style.top = '0';
    el.style.left = '-9999px';
    el.style.opacity = '0';
    document.body.appendChild(el);
    el.focus();
    el.select();
    el.setSelectionRange(0, 99999);

    const successful = document.execCommand('copy');
    document.body.removeChild(el);

    if (successful) {
      showToastNotification(`${label} berhasil disalin ke clipboard!`);
      return true;
    }
  } catch (err) {
    console.warn('execCommand copy failed:', err);
  }
  return false;
};

const copyText = (text, label = 'Teks') => {
  if (!text) {
    showToastNotification('Teks tidak tersedia untuk disalin.');
    return;
  }

  // 1. Try modern clipboard API if secure context
  if (navigator?.clipboard?.writeText && window.isSecureContext) {
    navigator.clipboard.writeText(text)
      .then(() => {
        showToastNotification(`${label} berhasil disalin ke clipboard!`);
      })
      .catch(() => {
        const ok = execCommandCopy(text, label);
        if (!ok) {
          openPromptPreviewModal(label, text);
        }
      });
    return;
  }

  // 2. Fallback for HTTP (non-secure context, e.g. .test domain)
  const ok = execCommandCopy(text, label);
  if (!ok) {
    openPromptPreviewModal(label, text);
  }
};

// Salin Prompt AI Handler
const copyAiPrompt = () => {
  const prompt = props.aiPromptTemplate || 'Formula prompt intelijen media Polri...';
  copyText(prompt, 'Formula Prompt AI Presisi');
};

// =========================================================================
// WHATSAPP REPORT DISPATCH KE PIMPINAN (KAPOLDA & PJU)
// =========================================================================
const isWaCopied = ref(false);
const isWaMaximized = ref(false);
const waBoxRef = ref(null);
const waBoxTab2Ref = ref(null);

const scrollWaToTop = () => {
  if (waBoxRef.value) waBoxRef.value.scrollTo({ top: 0, behavior: 'smooth' });
  if (waBoxTab2Ref.value) waBoxTab2Ref.value.scrollTo({ top: 0, behavior: 'smooth' });
};

const scrollWaToBottom = () => {
  if (waBoxRef.value) waBoxRef.value.scrollTo({ top: waBoxRef.value.scrollHeight, behavior: 'smooth' });
  if (waBoxTab2Ref.value) waBoxTab2Ref.value.scrollTo({ top: waBoxTab2Ref.value.scrollHeight, behavior: 'smooth' });
};

const copyWaReport = () => {
  copyText(whatsappReportText.value, 'Format Naskah WhatsApp Pimpinan');
  isWaCopied.value = true;
  setTimeout(() => { isWaCopied.value = false; }, 3500);
};

const openWhatsAppDirect = () => {
  const url = `https://api.whatsapp.com/send?text=${encodeURIComponent(whatsappReportText.value)}`;
  window.open(url, '_blank');
};

const downloadWaTxt = () => {
  try {
    const blob = new Blob([whatsappReportText.value], { type: 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `LAPORAN_WA_KAPOLDA_${props.selectedCode || 'SULBAR'}_${new Date().toISOString().slice(0, 10)}.txt`;
    link.click();
    URL.revokeObjectURL(url);
    showToastNotification('✓ Naskah WA berhasil diunduh sebagai file .txt!');
  } catch (err) {
    console.error('Download error:', err);
  }
};

const whatsappReportText = computed(() => {
  const r = props.report || {};
  const poldaName = props.selectedPolda?.name || 'Polda Sulawesi Barat';
  const poldaShort = props.selectedCode === 'SULBAR' ? 'Polda Sulbar' : poldaName;
  const kapoldaTitle = props.selectedPolda?.kapolda_name
    ? `Bapak Kapolda ${poldaName} (${props.selectedPolda.kapolda_name})`
    : `Bapak Kapolda ${poldaName}`;
  const kabidHumasTitle = props.selectedPolda?.kabid_humas_name
    ? `Kabid Humas ${poldaName} (${props.selectedPolda.kabid_humas_name})`
    : `Kabid Humas ${poldaName}`;

  const todayStr = r.report_date
    ? new Date(r.report_date).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
    : new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

  const reportTime = r.report_time || '19.03 WITA';

  let s = `*LAPORAN INTELIJEN MEDIA & SENTIMEN OPINI PUBLIK*\n`;
  s += `*BIDANG HUMAS ${poldaName.toUpperCase()}*\n\n`;
  s += `Kepada Yth. : ${kapoldaTitle}\n`;
  s += `Dari        : ${kabidHumasTitle}\n`;
  s += `Tanggal     : ${todayStr} (Pukul ${reportTime})\n`;
  s += `Perihal     : Analisis Pemberitaan, Sentimen Opini Publik, dan Rekomendasi Tindak Lanjut\n\n`;
  s += `*Assalamu’alaikum Wr. Wb.*\n`;
  s += `*Selamat Pagi Jenderal,*\n\n`;
  s += `Mohon izin Jenderal, kami sampaikan laporan hasil analisis pemberitaan dan sentimen opini publik terkait Polri secara nasional serta ${poldaShort} berdasarkan bahan pemantauan media siber dan opini publik yang tersedia.\n\n`;

  // SECTION I: SITUASI DAN ANALISIS KABID HUMAS
  s += `━━━━━━━━━━━━━━━━━━━━━━━━━━━\n`;
  s += `*I. SITUASI DAN ANALISIS KABID HUMAS*\n`;
  s += `━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n`;
  s += `*1. Sentimen Pemberitaan*\n\n`;
  s += `🇮🇩 *Nasional*\n`;
  s += `• Positif: ${r.sentiment_national?.positif ?? 60}%\n`;
  s += `• Netral : ${r.sentiment_national?.netral ?? 30}%\n`;
  s += `• Negatif: ${r.sentiment_national?.negatif ?? 10}%\n\n`;

  s += `📍 *${poldaShort}*\n`;
  s += `• Positif: ${r.sentiment_sulbar?.positif ?? 55}%\n`;
  s += `• Netral : ${r.sentiment_sulbar?.netral ?? 30}%\n`;
  s += `• Negatif: ${r.sentiment_sulbar?.negatif ?? 15}%\n\n`;

  s += `*Analisis Perkembangan:*\n`;
  if (r.snapshot_sulbar_desc) {
    s += `${r.snapshot_sulbar_desc}\n\n`;
  } else if (r.executive_brief) {
    s += `${r.executive_brief}\n\n`;
  } else {
    s += `Berdasarkan pemantauan media siber dan 29 portal berita resmi terverifikasi, situasi kamtibmas di wilayah hukum ${poldaShort} terpantau relatif aman dan kondusif dengan dominasi sentimen positif atas akselerasi patroli presisi dan pelayanan prima kepolisian.\n\n`;
  }

  // SECTION I.B: ISU STRATEGIS NASIONAL POLRI
  const nationalIssues = r.national_issues && r.national_issues.length > 0 ? r.national_issues : [];
  if (nationalIssues.length > 0) {
    s += `🇮🇩 *ISU STRATEGIS NASIONAL POLRI*\n`;
    s += `Status Isu Nasional: *${r.snapshot_national_status || 'KUNING - WASPADA'}*\n\n`;
    nationalIssues.forEach((iss, nIdx) => {
      s += `*A.${nIdx + 1}. ${iss.title}*\n`;
      s += `• Fakta: ${iss.fact}\n`;
      if (iss.source) s += `• Sumber: ${iss.source}\n`;
      if (iss.sentiment) s += `• Sentimen: ${iss.sentiment} | Virality: ${iss.virality || 'Sedang'}\n`;
      if (iss.url) s += `• Tautan: ${iss.url}\n`;
      s += `\n`;
    });
  }

  // SECTION I.C: ISU KEWILAYAHAN DAERAH & POLRES
  s += `📍 *ISU KEWILAYAHAN ${poldaShort.toUpperCase()} & POLRES JAJARAN*\n\n`;
  const sulbarIssues = r.sulbar_issues && r.sulbar_issues.length > 0
    ? r.sulbar_issues.slice(0, 4)
    : [
        {
          title: 'Pengamanan Intensif Tahapan Pilkada Serentak di Mamuju',
          fact: 'Satgas Operasi Mantap Praja Polresta Mamuju memperketat pengamanan 24 jam di Kantor KPU Sulbar dan Bawaslu, menjaga stabilitas pesta demokrasi.',
          source: 'Humas Polri & Radar Sulbar',
          sentiment: 'Positif',
        },
        {
          title: 'Klarifikasi Tim Siber atas Hoaks Isu Begal di Tarailu',
          fact: 'Subdit Siber Ditreskrimsus dan Humas Mateng mengonfirmasi video beredar merupakan insiden tahun 2021 di luar provinsi dan jalur trans dipatroli rutin aman.',
          source: 'Subdit Siber & WhatsApp',
          sentiment: 'Positif',
        },
        {
          title: 'Penertiban Jukir Liar di Kawasan Pantai Manakarra',
          fact: 'Merespons keluhan video warga di media sosial, Polresta Mamuju bersama Dishub menertibkan jukir liar untuk menjamin kenyamanan ruang publik.',
          source: 'TikTok & Media Siber',
          sentiment: 'Netral',
        },
      ];

  let issueNum = 2;
  sulbarIssues.forEach((iss) => {
    s += `*${issueNum}. Isu: ${iss.title}*\n`;
    if (iss.source) {
      s += `Pemberitaan ${iss.source} menyoroti: ${iss.fact}\n\n`;
    } else {
      s += `${iss.fact}\n\n`;
    }
    s += `*Analisis dan masukan:*\n`;
    const sent = (iss.sentiment || '').toLowerCase();
    const risk = (iss.risk || '').toLowerCase();
    if (sent.includes('positif')) {
      s += `Pemberitaan ini menjadi momentum positif bagi institusi dalam menunjukkan komitmen perlindungan dan pelayanan masyarakat. Disarankan agar dokumentasi humanis ini terus diamplifikasi melalui platform media sosial jajaran Satwil.\n\n`;
    } else if (sent.includes('negatif') || risk.includes('merah') || risk.includes('kuning')) {
      s += `Untuk meredam spekulasi yang belum terverifikasi, kami memandang perlu dilakukan konfirmasi segera kepada satuan kewilayahan terkait serta penyampaian klarifikasi resmi satu pintu agar opini publik tidak berkembang liar.\n\n`;
    } else {
      s += `Pelaksanaan kegiatan ini memperkuat kepercayaan masyarakat. Disarankan koordinasi berkala dengan pihak terkait serta edukasi kepada masyarakat melalui kanal komunikasi resmi.\n\n`;
    }
    if (iss.url) {
      s += `Tautan Sumber: ${iss.url}\n\n`;
    }
    issueNum++;
  });

  // SECTION II: SARAN LANGKAH TINDAK LANJUT
  s += `━━━━━━━━━━━━━━━━━━━━━━━━━━━\n`;
  s += `*II. SARAN LANGKAH TINDAK LANJUT*\n`;
  s += `━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n`;
  if (r.recommendations && r.recommendations.length > 0) {
    r.recommendations.forEach((rec, idx) => {
      s += `*${idx + 1}. Terkait ${rec.issue}*\n`;
      s += `${rec.action}\n`;
      if (rec.pic) {
        s += `Satuan Pelaksana (PIC): ${rec.pic}\n\n`;
      } else {
        s += `\n`;
      }
    });
  } else {
    s += `*1. Terkait Pengamanan Tahapan Pilkada Serentak*\n`;
    s += `Memperkuat koordinasi Sat Samapta, Intelkam, dan jajaran Polres dalam pengawalan logistik dan stabilitas kamtibmas.\n`;
    s += `Satuan Pelaksana (PIC): Polresta Mamuju & Polres Jajaran\n\n`;

    s += `*2. Terkait Penanggulangan Isu Hoaks & Provokasi*\n`;
    s += `Subdit Siber dan Tim Humas aktif 24 jam melakukan fact-checking dan menerbitkan stempel HOAKS resmi pada setiap disinformasi viral.\n`;
    s += `Satuan Pelaksana (PIC): Subdit Siber Ditreskrimsus & Bidhumas\n\n`;
  }

  s += `*3. Terkait Monitoring Media dan Opini Publik*\n`;
  s += `Bidang Humas terus mengoptimalkan pemantauan 29 portal media online resmi, Google News, dan media sosial. Setiap isu sensitif dilaporkan secara berjenjang beserta tautan rujukan dan waktu publikasi WITA.\n\n`;

  s += `*4. Terkait Pelayanan Pengaduan Masyarakat (110)*\n`;
  s += `Sosialisasi layanan Contact Center Polri 110 terus digencarkan agar masyarakat dapat menyampaikan informasi gangguan kamtibmas secara cepat dan gratis.\n\n`;

  // SECTION III: SARAN KOORDINASI DAN EVALUASI
  s += `━━━━━━━━━━━━━━━━━━━━━━━━━━━\n`;
  s += `*III. SARAN KOORDINASI DAN EVALUASI*\n`;
  s += `━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n`;
  s += `Untuk mendukung tindak lanjut yang terukur, kami menyarankan pola koordinasi berjenjang:\n\n`;

  const resp0_2h = r.priority_responses?.response_0_2h?.action || 'Validasi fakta lapangan dan koordinasi cepat dengan satuan fungsi terkait (0-2 jam).';
  const resp2_6h = r.priority_responses?.response_2_6h?.action || 'Konsolidasi data, penyusunan FAQ kedinasan, dan rilis pers satu pintu (2-6 jam).';
  const resp24h  = r.priority_responses?.response_24h?.action  || 'Rangkum perkembangan penanganan isu dan evaluasi respons netizen dalam 24 jam.';

  s += `• *Tahap Awal (0–2 jam):* ${resp0_2h}\n\n`;
  s += `• *Tahap Konsolidasi (2–6 jam):* ${resp2_6h}\n\n`;
  s += `• *Tahap Evaluasi (24 jam):* ${resp24h}\n\n`;
  s += `Alur waktu koordinasi tersebut dapat disesuaikan dengan eskalasi urgensi dan petunjuk pimpinan.\n\n`;

  // SECTION IV: KESIMPULAN DAN PERTIMBANGAN
  s += `━━━━━━━━━━━━━━━━━━━━━━━━━━━\n`;
  s += `*IV. KESIMPULAN DAN PERTIMBANGAN KEPADA BAPAK KAPOLDA*\n`;
  s += `━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n`;

  let sitStatus = r.snapshot_sulbar_status || 'KUNING - WASPADA';
  s += `Jenderal, berdasarkan data pemantauan terpadu, situasi komunikasi publik ${poldaShort} saat ini dinilai berada pada kategori *${sitStatus}*.\n\n`;

  if (r.top_concern) {
    s += `*Atensi Prioritas:* ${r.top_concern}\n\n`;
  }

  s += `Sebagai bahan pertimbangan tindak lanjut, kami menyarankan:\n`;
  s += `1. Konsolidasi data lintas fungsi diperkuat agar setiap rilis klarifikasi berbasis fakta terverifikasi.\n`;
  s += `2. Komunikasi publik konsisten mengedepankan transparansi, pendekatan humanis, dan manfaat nyata bagi warga.\n`;
  s += `3. Monitoring media siber berkelanjutan guna deteksi dini potensi anomali opini publik.\n\n`;

  s += `Demikian kami laporkan sebagai bahan pertimbangan Bapak Kapolda. Atas arahan dan petunjuk lebih lanjut, kami siap laksanakan.\n\n`;
  s += `Hormat kami,\n`;
  s += `*BIDANG HUMAS ${poldaName.toUpperCase()}*\n\n`;
  s += `_Tembusan:_\n`;
  s += `_1. Wakapolda ${poldaName}_\n`;
  s += `_2. Irwasda ${poldaName}_\n\n`;
  s += `*Wassalamu’alaikum Wr. Wb.*`;

  return s;
});

// Tempel Ringkasan AI Modal State
const showPasteAiModal = ref(false);
const pasteAiInput = ref('');
const isSubmittingAi = ref(false);

const submitPastedAi = () => {
  if (!pasteAiInput.value.trim()) return;
  isSubmittingAi.value = true;

  router.post('/media-intelligence/ai-briefing?polda=' + props.selectedCode, {
    raw_input: pasteAiInput.value,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      isSubmittingAi.value = false;
      showPasteAiModal.value = false;
      pasteAiInput.value = '';
      toastMessage.value = 'Laporan Intelijen AI berhasil diurai & diperbarui di semua tab!';
      setTimeout(() => {
        toastMessage.value = '';
      }, 4000);
    },
    onError: () => {
      isSubmittingAi.value = false;
      toastMessage.value = 'Gagal mengurai teks. Pastikan format teks/JSON sesuai template.';
      setTimeout(() => {
        toastMessage.value = '';
      }, 4000);
    }
  });
};

// Kelola Sumber RSS Modal State
const showRssManagerModal = ref(false);
const newFeedForm = ref({
  name: '',
  url: '',
  category: 'Regional Sulselbar',
});
const testResult = ref(null);
const isTestingUrl = ref(false);

const testFeedUrl = async (url) => {
  if (!url) return;
  isTestingUrl.value = true;
  testResult.value = null;

  try {
    const res = await fetch('/media-intelligence/rss-sources/test', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify({ url }),
    });
    const data = await res.json();
    testResult.value = data;
  } catch (e) {
    testResult.value = { valid: false, message: 'Koneksi gagal atau CORS error: ' + e.message };
  } finally {
    isTestingUrl.value = false;
  }
};

const submitNewRssSource = () => {
  const pId = props.selectedPolda?.id || props.allPoldas?.[0]?.id || 1;
  router.post('/media-intelligence/rss-sources?polda=' + props.selectedCode, {
    polda_id: pId,
    name: newFeedForm.value.name,
    url: newFeedForm.value.url,
    category: newFeedForm.value.category,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      newFeedForm.value = { name: '', url: '', category: 'Regional Sulselbar' };
      testResult.value = null;
      toastMessage.value = 'Sumber RSS baru berhasil ditambahkan!';
      setTimeout(() => {
        toastMessage.value = '';
      }, 3500);
    }
  });
};

const toggleFeed = (feedId) => {
  router.post(`/media-intelligence/rss-sources/${feedId}/toggle?polda=${props.selectedCode}`, {}, {
    preserveScroll: true,
  });
};

const deleteFeed = (feedId) => {
  if (confirm('Yakin ingin menghapus sumber RSS ini?')) {
    router.delete(`/media-intelligence/rss-sources/${feedId}?polda=${props.selectedCode}`, {
      preserveScroll: true,
    });
  }
};

// Live Refresh RSS Feeds
const isRefreshingRss = ref(false);
const refreshRssFeeds = () => {
  isRefreshingRss.value = true;
  router.get('/media-intelligence', { polda: props.selectedCode, refresh: 1 }, {
    preserveScroll: true,
    onFinish: () => {
      isRefreshingRss.value = false;
      toastMessage.value = 'Sinkronisasi berita RSS selesai!';
      setTimeout(() => {
        toastMessage.value = '';
      }, 3500);
    },
  });
};

// Filtered Articles Computed
const filteredArticles = computed(() => {
  let list = props.sampleArticles || [];

  // Filter Portal
  if (selectedPortal.value !== 'Semua Portal') {
    list = list.filter((a) => a.source_name.toLowerCase() === selectedPortal.value.toLowerCase());
  }

  // Filter Kategori
  if (selectedCategory.value === 'bookmark') {
    list = list.filter((a) => bookmarkedIds.value.includes(a.id));
  } else if (selectedCategory.value !== 'all') {
    list = list.filter((a) => a.category.toLowerCase() === selectedCategory.value.toLowerCase());
  }

  // Filter Sentimen
  if (selectedSentiment.value !== 'all') {
    list = list.filter((a) => (a.sentiment || 'netral').toLowerCase() === selectedSentiment.value.toLowerCase());
  }

  // Filter Search
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter((a) => (a.title && a.title.toLowerCase().includes(q)) || (a.summary && a.summary.toLowerCase().includes(q)));
  }

  return list;
});

const totalPages = computed(() => Math.max(1, Math.ceil(filteredArticles.value.length / perPage.value)));
const paginatedArticles = computed(() => {
  const start = (currentPage.value - 1) * perPage.value;
  return filteredArticles.value.slice(start, start + perPage.value);
});

// Salin Berita sebagai Bahan AI
const copyArticleForAi = (article) => {
  const content = `Judul: ${article.title}\nSumber: ${article.source_name}\nWaktu: ${article.pub_date || article.time_ago}\nCuplikan: ${article.summary}\nTautan: ${article.link}`;
  copyText(content, `Bahan AI [${article.source_name}]`);
};

// Create Issue Modal State
const showIssueModal = ref(false);
const activeArticleForIssue = ref(null);
const issueForm = ref({
  priority: 'KUNING',
  pic: 'Bidhumas Polda',
  notes: '',
});

const openIssueModal = (article) => {
  activeArticleForIssue.value = article;
  issueForm.value.notes = `Eskalasi temuan media: ${article.title}`;
  showIssueModal.value = true;
};

const submitIssue = () => {
  const pId = props.selectedPolda?.id || props.allPoldas?.[0]?.id || 1;
  const priorityMap = {
    'HIJAU': 'low',
    'KUNING': 'high',
    'MERAH': 'critical'
  };

  router.post('/issues', {
    polda_id: pId,
    title: activeArticleForIssue.value?.title || 'Isu Temuan Media',
    summary: activeArticleForIssue.value?.summary || issueForm.value.notes,
    category: activeArticleForIssue.value?.category || 'Harkamtibmas',
    priority: priorityMap[issueForm.value.priority] || 'medium',
    sentiment: activeArticleForIssue.value?.sentiment || 'netral',
    source_media: activeArticleForIssue.value?.source_name || 'Media Online',
    source_url: activeArticleForIssue.value?.link || '',
    assigned_unit: issueForm.value.pic || 'Bidhumas Polda',
  }, {
    preserveScroll: true,
    onSuccess: () => {
      showIssueModal.value = false;
      toastMessage.value = `Berhasil membuat Isu Baru dari [${activeArticleForIssue.value?.title.substring(0, 30)}...]! Telah tersimpan di Database & Manajemen Isu.`;
      setTimeout(() => {
        toastMessage.value = '';
      }, 4500);
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
    <!-- Floating Toast Notification -->
    <transition
      enter-active-class="transform ease-out duration-300 transition"
      enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
      enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="toastMessage"
        class="fixed bottom-6 right-6 z-50 max-w-md bg-emerald-950 border border-emerald-500 text-emerald-100 p-4 rounded-2xl shadow-2xl flex items-center gap-3 backdrop-blur-xl"
      >
        <CheckCircle2 class="w-6 h-6 text-emerald-400 shrink-0" />
        <p class="text-xs font-bold leading-relaxed">{{ toastMessage }}</p>
      </div>
    </transition>

    <!-- Top Command Header with Actions (Salin Prompt AI, Tempel Ringkasan AI) -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 p-6 rounded-3xl bg-gradient-to-r from-[#0d182e] via-[#0b1426] to-[#070d1d] border border-slate-800 shadow-2xl relative overflow-hidden">
      <div class="absolute top-0 right-0 -mt-12 -mr-12 w-64 h-64 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>

      <div class="space-y-2 relative z-10">
        <div class="flex items-center gap-2 flex-wrap">
          <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-rose-500/20 text-rose-400 border border-rose-500/40">
            Klasifikasi: Rahasia Terbatas / Command Center
          </span>
          <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-500/20 text-amber-300 border border-amber-500/40">
            📰 Monitoring Media Online & Agregasi Pers
          </span>
          <span class="flex items-center gap-1 text-[11px] font-bold text-emerald-400">
            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
            Live Radar 29 RSS Aktif
          </span>
        </div>
        <h1 class="text-2xl md:text-3xl font-extrabold text-white tracking-tight">
          Monitoring Media Online &bull; {{ selectedCode === 'ALL' ? 'KONSOLIDASI NASIONAL MABES POLRI' : selectedPolda?.name }}
        </h1>
        <p class="text-xs md:text-sm text-slate-300 max-w-2xl leading-relaxed">
          Pusat Agregasi Berita Pers & Media Siber Real-Time, Analisis Isu Strategis Nasional Polri, Executive Briefing Harian Pimpinan, dan Naskah WhatsApp Terstandarisasi.
        </p>
      </div>

      <!-- Action Buttons: Salin Prompt AI, Tempel Ringkasan AI, Kelola Sumber RSS -->
      <div class="flex flex-wrap items-center gap-2 relative z-10">
        <!-- Button 1: Salin Prompt AI -->
        <button
          @click="copyAiPrompt"
          class="flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-amber-400/40 hover:border-amber-400 text-amber-300 hover:text-amber-200 text-xs font-bold transition shadow-lg shadow-amber-400/10"
          title="Salin formula prompt ChatGPT resmi Senior Media Analyst"
        >
          <Sparkles class="w-4 h-4 text-amber-400" />
          <span>Salin Prompt AI</span>
        </button>

        <!-- Button 1b: Lihat Prompt AI (Preview Modal) -->
        <button
          @click="openPromptPreviewModal('Formula Prompt AI Presisi', props.aiPromptTemplate || 'Formula prompt intelijen media Polri...')"
          class="flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 hover:border-slate-600 text-slate-300 hover:text-white text-xs font-bold transition"
          title="Lihat teks formula prompt ChatGPT secara lengkap"
        >
          <Eye class="w-4 h-4 text-slate-400" />
          <span>Lihat Prompt</span>
        </button>

        <!-- Button 2: Tempel Ringkasan AI -->
        <button
          @click="showPasteAiModal = true"
          class="flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-400 hover:from-amber-400 hover:to-amber-300 text-slate-950 text-xs font-extrabold transition shadow-lg shadow-amber-500/20"
          title="Tempel teks/JSON hasil analisis dari ChatGPT"
        >
          <Clipboard class="w-4 h-4" />
          <span>Tempel Ringkasan AI</span>
        </button>

        <!-- Button 4: Format WA Pimpinan (Tab 07) -->
        <button
          @click="activeTab = '07'"
          class="flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 text-white text-xs font-black transition shadow-lg shadow-emerald-600/25 border border-emerald-400/30"
          title="Buka format naskah WhatsApp resmi siap kirim ke Bapak Kapolda & PJU"
        >
          <MessageCircle class="w-4 h-4" />
          <span>📱 Format WA Pimpinan</span>
        </button>
      </div>
    </div>

    <!-- 4 KPI Metrics Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
          <span>Portal RSS Terdaftar</span>
          <Radio class="w-4 h-4 text-amber-400" />
        </div>
        <p class="text-2xl md:text-3xl font-black text-white">{{ mediaFeeds?.length || 29 }} Portal</p>
        <p class="text-[11px] text-slate-400">{{ mediaFeeds?.length || 29 }} Portal Sulbar & Nasional</p>
      </div>

      <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
          <span>Total Berita Tersimpan</span>
          <Newspaper class="w-4 h-4 text-sky-400" />
        </div>
        <p class="text-2xl md:text-3xl font-black text-white">{{ sampleArticles?.length || 150 }}</p>
        <p class="text-[11px] text-slate-400">Pantauan 24 Jam Terakhir</p>
      </div>

      <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
          <span>EWS Siaga Aktif</span>
          <Flame class="w-4 h-4 text-rose-400" />
        </div>
        <div class="flex items-baseline gap-2">
          <p class="text-2xl md:text-3xl font-black text-amber-400">{{ metrics.active_ews }}</p>
          <span class="text-xs font-bold text-rose-400">Prioritas Tinggi</span>
        </div>
        <p class="text-[11px] text-slate-400">Mitigasi Risiko Reputasi</p>
      </div>

      <div class="p-5 rounded-2xl bg-[#0b1426] border border-slate-800 shadow-lg space-y-1">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold">
          <span>Indeks Sentimen Publik</span>
          <TrendingUp class="w-4 h-4 text-emerald-400" />
        </div>
        <p class="text-2xl md:text-3xl font-black text-emerald-400">
          {{ report?.sentiment_sulbar?.positif || 84.2 }}%
        </p>
        <p class="text-[11px] text-slate-400">Kondusifitas Wilayah Tinggi</p>
      </div>
    </div>

    <!-- Navigation Tabs Bar (01 - 06) -->
    <div class="p-2 rounded-2xl bg-[#08101e] border border-slate-800 shadow-xl">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
        <button
          v-for="t in tabs"
          :key="t.id"
          @click="activeTab = t.id"
          type="button"
          class="flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl text-[11px] md:text-xs font-extrabold text-center leading-tight transition-all duration-200 cursor-pointer"
          :class="[
            activeTab === t.id
              ? 'bg-amber-500/20 text-amber-400 border-2 border-amber-500 shadow-[0_0_15px_rgba(245,158,11,0.25)] ring-1 ring-amber-400/50'
              : 'bg-slate-900/90 text-slate-200 hover:text-white hover:bg-slate-800 border border-slate-700/80 hover:border-slate-500'
          ]"
        >
          <component
            :is="t.icon"
            class="w-4 h-4"
            :class="[
              activeTab === t.id
                ? 'text-amber-400'
                : t.id === '05'
                  ? 'text-rose-400'
                  : 'text-slate-400'
            ]"
          />
          <span>{{ t.name }}</span>
          <span
            v-if="t.badge"
            class="px-2 py-0.5 rounded-full text-[10px] font-black tracking-wider uppercase ml-0.5"
            :class="[
              t.id === '01'
                ? 'bg-rose-500 text-white animate-pulse'
                : 'bg-amber-400 text-slate-950'
            ]"
          >
            {{ t.badge }}
          </span>
        </button>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 01: LIVE RSS MEDIA PATROL (FULL PARITAS SEPERTI SIMOD)                 -->
    <!-- ========================================================================= -->
    <div v-show="activeTab === '01'" class="space-y-5">
      <!-- Patroli Siaran Media Massa Real-Time Header -->
      <div class="rounded-3xl bg-gradient-to-r from-[#0d182e] via-[#0b1426] to-[#070d1d] border border-slate-800 p-6 shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="space-y-1">
          <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-rose-500 animate-ping"></span>
            <h2 class="text-lg md:text-xl font-black text-white tracking-tight flex items-center gap-2">
              Radar & Agregasi Berita Online Real-Time
            </h2>
          </div>
          <p class="text-xs text-slate-300 max-w-2xl leading-relaxed">
            Sindikasi RSS multi-portal memantau siaran pers dan portal media online nasional & daerah secara otomatis. Dilengkapi filter portal, sentimen, pencarian instan, bookmark artikel, dan konversi ke Laporan Isu Humas.
          </p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <button
            @click="refreshRssFeeds"
            :disabled="isRefreshingRss"
            class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-xs font-bold text-white transition shadow-sm disabled:opacity-50"
          >
            <RotateCw class="w-4 h-4 text-amber-400" :class="{ 'animate-spin': isRefreshingRss }" />
            <span>{{ isRefreshingRss ? 'Menarik Berita...' : 'Sinkron Berita' }}</span>
          </button>
          <button
            @click="showRssManagerModal = true"
            class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border-2 border-amber-400/90 text-xs font-black text-amber-300 hover:text-amber-200 transition shadow-lg shadow-amber-400/10"
          >
            <SlidersHorizontal class="w-4 h-4 text-amber-400" />
            <span>Kelola Sumber RSS</span>
          </button>
        </div>
      </div>

      <!-- FILTER TOOLBAR (PERSIS TAMPILAN GAMBAR 1) -->
      <div class="p-4 rounded-2xl bg-[#0b1426] border border-slate-800/90 shadow-xl flex flex-wrap items-center gap-3">
        <!-- Portal Dropdown -->
        <div class="flex items-center gap-2">
          <span class="text-xs font-bold text-slate-300">Portal:</span>
          <select
            v-model="selectedPortal"
            @change="currentPage = 1"
            class="px-3 py-2 rounded-xl text-xs font-bold bg-[#070d19] border border-slate-700 text-white focus:outline-none focus:border-amber-400 cursor-pointer"
          >
            <option value="Semua Portal">Semua Portal ({{ portalsList?.length > 1 ? portalsList.length - 1 : portalsList?.length || 29 }})</option>
            <option
              v-for="p in portalsList?.filter(p => p.name !== 'Semua Portal')"
              :key="p.name"
              :value="p.name"
            >
              {{ p.name }} ({{ p.count }})
            </option>
          </select>
        </div>

        <!-- Kategori Dropdown -->
        <div class="flex items-center gap-2">
          <span class="text-xs font-bold text-slate-300">Kategori:</span>
          <select
            v-model="selectedCategory"
            @change="currentPage = 1"
            class="px-3 py-2 rounded-xl text-xs font-bold bg-[#070d19] border border-slate-700 text-white focus:outline-none focus:border-amber-400 cursor-pointer"
          >
            <option value="all">Semua Kategori</option>
            <option value="bookmark">⭐ Tersimpan / Bookmark ({{ bookmarkedIds.length }})</option>
            <option value="Regional Sulselbar">Regional Sulselbar</option>
            <option value="Nasional & Umum">Nasional & Umum</option>
            <option value="Hukum & Kriminal">Hukum & Kriminal</option>
            <option value="Google News RSS">Google News RSS</option>
          </select>
        </div>

        <!-- Search Input -->
        <div class="relative flex-1 min-w-[240px]">
          <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
          <input
            v-model="searchQuery"
            @keyup.enter="handleSearch"
            type="text"
            placeholder="Cari judul berita atau topik (tekan Enter)..."
            class="w-full pl-9 pr-8 py-2 rounded-xl bg-[#070d19] border border-slate-700 text-xs text-white placeholder-slate-400 focus:outline-none focus:border-amber-400"
          />
          <button
            v-if="searchQuery"
            @click="searchQuery = ''; currentPage = 1"
            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white"
          >
            <X class="w-3 h-3" />
          </button>
        </div>

        <!-- Cari Button -->
        <button
          @click="handleSearch"
          class="px-5 py-2 rounded-xl bg-[#0d182e] hover:bg-slate-800 text-amber-400 border border-amber-400/50 hover:border-amber-400 text-xs font-black transition shadow-sm"
        >
          Cari
        </button>

        <!-- Count Indicator -->
        <div class="text-xs text-slate-400 shrink-0 ml-auto whitespace-nowrap flex items-center gap-1">
          <span>Menampilkan</span>
          <strong class="text-amber-400 font-extrabold">{{ filteredArticles.length }}</strong>
          <span>siaran berita</span>
        </div>
      </div>

      <!-- KARTU-KARTU BERITA (PERSIS GAMBAR 1 & 2) -->
      <div v-if="filteredArticles.length > 0" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
          <div
            v-for="article in paginatedArticles"
            :key="article.id"
            class="rounded-3xl bg-[#0b1426] border border-slate-800/90 hover:border-amber-400/50 transition-all duration-300 flex flex-col justify-between overflow-hidden shadow-xl group hover:shadow-2xl hover:shadow-amber-400/5"
          >
            <!-- Top Cover Image Area (h-48) -->
            <div class="h-48 w-full bg-slate-950 relative overflow-hidden shrink-0 border-b border-slate-800/80">
              <!-- Top Left: Source Name Badge & Top Right: Time Ago -->
              <div class="absolute top-3 left-3 right-3 flex items-center justify-between gap-2 z-10 pointer-events-none">
                <!-- Source Name -->
                <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-black/75 text-amber-400 border border-amber-400/40 shadow-sm backdrop-blur-md truncate max-w-[170px]">
                  {{ article.source_name }}
                </span>

                <!-- Time Ago -->
                <span class="px-2.5 py-1 rounded-full text-[10px] font-semibold bg-black/75 text-slate-200 border border-white/10 backdrop-blur-md flex items-center gap-1.5 shadow-sm">
                  <Clock class="w-3 h-3 text-amber-400" />
                  <span>{{ article.time_ago }}</span>
                </span>
              </div>

              <!-- Real Cover Image -->
              <img
                v-if="article.image_url && !brokenImages.has(article.id)"
                :src="article.image_url"
                :alt="article.title"
                loading="lazy"
                referrerpolicy="no-referrer"
                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                @error="(e) => handleImageError(e, article)"
              />

              <!-- Fallback Placeholder Persis SIMOD ("Gambar tidak disediakan oleh website") -->
              <div
                v-else
                class="w-full h-full flex flex-col items-center justify-center gap-2 bg-gradient-to-br from-slate-900 via-[#0d1830] to-slate-950 text-slate-600 p-4 text-center select-none"
              >
                <Newspaper class="w-12 h-12 text-slate-700 stroke-[1.5]" />
                <span class="text-[10px] text-slate-500 font-medium">Gambar tidak disediakan oleh website</span>
              </div>

              <!-- Dark Gradient Overlay -->
              <div class="absolute inset-0 bg-gradient-to-t from-[#0b1426] via-transparent to-black/40 pointer-events-none"></div>
            </div>

            <!-- Card Body Content -->
            <div class="p-5 flex-1 flex flex-col justify-between space-y-3">
              <div class="space-y-2">
                <!-- Category Badge -->
                <div class="flex items-center gap-2 pt-1">
                  <span class="px-2.5 py-0.5 rounded-md text-[9px] font-extrabold uppercase bg-slate-900/90 text-slate-300 border border-slate-700/80 tracking-wider">
                    {{ article.category_badge || article.category || 'REGIONAL SULSELBAR' }}
                  </span>
                </div>

                <!-- Date & Time (📅 28 Oct 2026, 23.22 WITA) -->
                <div class="text-[10px] text-slate-400 flex items-center gap-1.5">
                  <Calendar class="w-3.5 h-3.5 text-slate-500 shrink-0" />
                  <span class="truncate">{{ article.pub_date }}</span>
                </div>

                <!-- Title -->
                <a
                  :href="article.link"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="block font-black text-sm md:text-[15px] text-white group-hover:text-amber-400 transition leading-snug line-clamp-2"
                  :title="article.title"
                >
                  {{ article.title }}
                </a>

                <!-- Summary Excerpt -->
                <p class="text-xs text-slate-300 line-clamp-3 leading-relaxed">
                  {{ article.summary }}
                </p>
              </div>

              <!-- Card Footer Actions (PERSIS GAMBAR 2) -->
              <div class="pt-3 border-t border-slate-800/80 flex items-center justify-between gap-2">
                <!-- Left: Buka Button -->
                <a
                  :href="article.link"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="px-3 py-1.5 rounded-full text-xs font-semibold text-slate-300 bg-slate-900 hover:bg-slate-800 hover:text-white border border-slate-700 flex items-center gap-1.5 transition shadow-sm"
                  title="Buka artikel asli di tab baru"
                >
                  <ExternalLink class="w-3.5 h-3.5 text-blue-400" />
                  <span>Buka</span>
                </a>

                <!-- Right: Simpan / Bookmark & Buat Isu -->
                <div class="flex items-center gap-2">
                  <!-- Simpan / Bookmark -->
                  <button
                    @click.stop="toggleBookmark(article.id)"
                    :class="[
                      'px-3 py-1.5 rounded-full text-xs font-bold transition flex items-center gap-1.5 shadow-sm border',
                      isBookmarked(article.id)
                        ? 'bg-amber-400 text-slate-950 font-black border-amber-300 shadow-md'
                        : 'bg-slate-900 hover:bg-slate-800 text-slate-300 hover:text-amber-300 border-slate-700'
                    ]"
                    :title="isBookmarked(article.id) ? 'Hapus dari Bookmark Tersimpan' : 'Simpan / Bookmark Berita Ini'"
                  >
                    <Check v-if="isBookmarked(article.id)" class="w-3.5 h-3.5 text-slate-950" />
                    <Copy v-else class="w-3.5 h-3.5 text-amber-400" />
                    <span>{{ isBookmarked(article.id) ? '★ Tersimpan' : '🔖 Simpan' }}</span>
                  </button>

                  <!-- Buat Isu -->
                  <button
                    @click.stop="openIssueModal(article)"
                    class="px-3.5 py-1.5 rounded-full text-xs font-bold text-white bg-slate-900 hover:bg-slate-800 border-2 border-amber-400/90 hover:border-amber-300 flex items-center gap-1 transition shadow-lg shadow-amber-400/10"
                    title="Jadikan laporan isu publik baru di SIMOD HUMAS"
                  >
                    <Plus class="w-3.5 h-3.5 text-white" />
                    <span>Buat Isu</span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Pagination Bar -->
        <div v-if="totalPages > 1" class="flex flex-wrap items-center justify-between gap-3 p-4 rounded-2xl bg-[#0b1426] border border-slate-800">
          <button
            :disabled="currentPage === 1"
            @click="currentPage--"
            class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-900 text-slate-300 border border-slate-700 hover:text-white disabled:opacity-40 disabled:cursor-not-allowed transition"
          >
            &larr; Sebelumnya
          </button>

          <div class="flex items-center gap-1 text-xs">
            <span class="text-slate-400">Halaman</span>
            <span class="px-2.5 py-1 rounded-lg bg-amber-400 text-slate-950 font-black">{{ currentPage }}</span>
            <span class="text-slate-400">dari {{ totalPages }}</span>
          </div>

          <button
            :disabled="currentPage === totalPages"
            @click="currentPage++"
            class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-900 text-slate-300 border border-slate-700 hover:text-white disabled:opacity-40 disabled:cursor-not-allowed transition"
          >
            Selanjutnya &rarr;
          </button>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="rounded-3xl bg-[#0b1426] border border-slate-800 p-12 text-center space-y-3">
        <Newspaper class="w-12 h-12 mx-auto text-slate-600" />
        <h3 class="text-base font-bold text-white">Tidak ada artikel yang sesuai dengan filter</h3>
        <p class="text-xs text-slate-400 max-w-sm mx-auto">
          Coba ganti pilihan portal, ubah kategori, atau klik tombol "Sinkron Berita" untuk menarik berita terkini dari seluruh portal.
        </p>
        <button
          @click="selectedPortal = 'Semua Portal'; selectedCategory = 'all'; selectedSentiment = 'all'; searchQuery = ''"
          class="px-4 py-2 rounded-xl bg-amber-400 text-slate-950 font-black text-xs"
        >
          Reset Semua Filter
        </button>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 02: BERITA & BRIEFING (EXECUTIVE SNAPSHOT & FORMAT WA RESMI)           -->
    <!-- ========================================================================= -->
    <div v-show="activeTab === '02'" class="space-y-6">
      <!-- Executive Briefing 200 Words -->
      <div class="rounded-3xl bg-gradient-to-br from-[#0c162e] to-[#080f20] border border-amber-400/30 p-6 shadow-2xl space-y-3">
        <div class="flex items-center justify-between border-b border-slate-800/80 pb-3 flex-wrap gap-2">
          <div class="flex items-center gap-2">
            <Sparkles class="w-4 h-4 text-amber-400" />
            <h2 class="text-sm font-black text-white uppercase tracking-wider">
              Executive Briefing Harian Pimpinan
            </h2>
          </div>
          <div class="flex items-center gap-3">
            <button
              @click="copyWaReport"
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-400 border border-emerald-500/40 text-[11px] font-bold transition shadow-sm"
              title="Salin naskah lengkap siap kirim ke WhatsApp pimpinan"
            >
              <Check v-if="isWaCopied" class="w-3.5 h-3.5 text-emerald-400" />
              <MessageSquare v-else class="w-3.5 h-3.5 text-emerald-400" />
              <span>{{ isWaCopied ? 'Tersalin!' : 'Salin Format WA Pimpinan' }}</span>
            </button>
            <span class="text-xs font-mono text-amber-400">{{ report?.report_time || '19.03 WITA' }}</span>
          </div>
        </div>
        <p class="text-xs md:text-sm text-slate-200 leading-relaxed">
          {{ report?.executive_brief || 'Ringkasan briefing pimpinan belum diisi. Gunakan tombol "Tempel Ringkasan AI" untuk memuat hasil ChatGPT.' }}
        </p>
      </div>

      <!-- Top Concern -->
      <div class="p-5 rounded-2xl bg-rose-500/10 border border-rose-500/40 text-rose-300 text-xs flex items-center gap-3">
        <AlertTriangle class="w-5 h-5 shrink-0 text-rose-400" />
        <div>
          <strong class="font-black uppercase tracking-wider block">Atensi Utama Pimpinan (Top Concern):</strong>
          <span>{{ report?.top_concern || 'Monitoring dan mitigasi disinformasi ruang digital di wilayah pesisir.' }}</span>
        </div>
      </div>

      <!-- Grid 2 Col: Isu Nasional vs Isu Kewilayahan -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Isu Nasional -->
        <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
          <div class="flex items-center justify-between border-b border-slate-800 pb-3 flex-wrap gap-2">
            <div class="flex items-center gap-2">
              <span class="text-base">🇮🇩</span>
              <h3 class="text-sm font-black text-white uppercase tracking-wider">Isu Strategis Nasional Polri</h3>
            </div>
            <span
              class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider border"
              :class="[
                (report?.snapshot_national_status || '').includes('MERAH')
                  ? 'bg-rose-500/20 text-rose-300 border-rose-500/40'
                  : (report?.snapshot_national_status || '').includes('HIJAU')
                    ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40'
                    : 'bg-amber-400/20 text-amber-300 border-amber-400/40'
              ]"
            >
              {{ report?.snapshot_national_status || 'KUNING - WASPADA' }}
            </span>
          </div>
          <div class="space-y-3">
            <div
              v-for="(iss, idx) in (report?.national_issues || [])"
              :key="idx"
              class="p-4 rounded-2xl bg-slate-900/90 border border-slate-800 space-y-2.5 text-xs hover:border-slate-700 transition"
            >
              <div class="flex items-start justify-between gap-3">
                <span class="font-bold text-white text-xs md:text-sm leading-snug">{{ iss.title }}</span>
                <span
                  class="px-2 py-0.5 rounded text-[9px] font-black uppercase shrink-0"
                  :class="[
                    (iss.sentiment || '').toLowerCase() === 'positif'
                      ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40'
                      : (iss.sentiment || '').toLowerCase() === 'negatif'
                        ? 'bg-rose-500/20 text-rose-300 border border-rose-500/40'
                        : 'bg-slate-700/60 text-slate-300 border border-slate-600'
                  ]"
                >
                  {{ iss.sentiment || 'Positif' }}
                </span>
              </div>
              <p class="text-slate-300 leading-relaxed">{{ iss.fact }}</p>
              <div class="text-[10px] text-slate-400 flex items-center justify-between pt-1 border-t border-slate-800/60 flex-wrap gap-2">
                <span class="flex items-center gap-1 font-mono text-slate-400">
                  <Radio class="w-3 h-3 text-amber-400" />
                  {{ iss.source }}
                </span>
                <div class="flex items-center gap-2">
                  <span class="text-slate-400">Virality: <strong class="text-amber-300">{{ iss.virality || 'Sedang' }}</strong></span>
                  <a
                    v-if="iss.url"
                    :href="iss.url"
                    target="_blank"
                    class="text-sky-400 hover:underline flex items-center gap-0.5"
                  >
                    <span>Tautan</span>
                    <ExternalLink class="w-3 h-3" />
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Isu Kewilayahan Polda & Polres -->
        <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
          <div class="flex items-center justify-between border-b border-slate-800 pb-3 flex-wrap gap-2">
            <div class="flex items-center gap-2">
              <MapPin class="w-4 h-4 text-rose-400" />
              <h3 class="text-sm font-black text-white uppercase tracking-wider">
                Isu Kewilayahan {{ selectedCode === 'ALL' ? 'Mabes & Seluruh Polda' : (selectedPolda?.name || 'Polda & Jajaran Polres') }}
              </h3>
            </div>
            <span
              class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider border"
              :class="[
                (report?.snapshot_sulbar_status || '').includes('MERAH')
                  ? 'bg-rose-500/20 text-rose-300 border-rose-500/40'
                  : (report?.snapshot_sulbar_status || '').includes('HIJAU')
                    ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40'
                    : 'bg-amber-400/20 text-amber-300 border-amber-400/40'
              ]"
            >
              {{ report?.snapshot_sulbar_status || 'KUNING - WASPADA' }}
            </span>
          </div>
          <div class="space-y-3">
            <div
              v-for="(iss, idx) in (report?.sulbar_issues || [])"
              :key="idx"
              class="p-4 rounded-2xl bg-slate-900/90 border border-slate-800 space-y-2.5 text-xs hover:border-slate-700 transition"
            >
              <div class="flex items-start justify-between gap-3">
                <span class="font-bold text-white text-xs md:text-sm leading-snug">{{ iss.title }}</span>
                <span
                  class="px-2 py-0.5 rounded text-[9px] font-black uppercase shrink-0"
                  :class="[
                    (iss.sentiment || '').toLowerCase() === 'positif'
                      ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40'
                      : (iss.sentiment || '').toLowerCase() === 'negatif'
                        ? 'bg-rose-500/20 text-rose-300 border border-rose-500/40'
                        : 'bg-slate-700/60 text-slate-300 border border-slate-600'
                  ]"
                >
                  {{ iss.sentiment || 'Positif' }}
                </span>
              </div>
              <p class="text-slate-300 leading-relaxed">{{ iss.fact }}</p>
              <div class="text-[10px] text-slate-400 flex items-center justify-between pt-1 border-t border-slate-800/60 flex-wrap gap-2">
                <span class="flex items-center gap-1 font-mono text-slate-400">
                  <Radio class="w-3 h-3 text-amber-400" />
                  {{ iss.source }}
                </span>
                <div class="flex items-center gap-2">
                  <span class="text-slate-400">Virality: <strong class="text-amber-300">{{ iss.virality || 'Sedang' }}</strong></span>
                  <a
                    v-if="iss.url"
                    :href="iss.url"
                    target="_blank"
                    class="text-sky-400 hover:underline flex items-center gap-0.5"
                  >
                    <span>Tautan</span>
                    <ExternalLink class="w-3 h-3" />
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- WhatsApp Report Dispatch Card (Integrated in Tab 02) -->
      <div class="rounded-3xl bg-[#0b1426] border border-emerald-500/30 overflow-hidden shadow-2xl space-y-0">
        <!-- Header -->
        <div class="p-6 bg-gradient-to-r from-emerald-950/40 via-[#0b1426] to-[#080f20] border-b border-emerald-500/20 flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div class="space-y-1">
            <div class="flex items-center gap-2">
              <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
                LAPORAN RESMI WHATSAPP
              </span>
              <span class="text-xs text-slate-400 font-mono">{{ report?.report_time || '19.03 WITA' }}</span>
            </div>
            <h3 class="text-base font-extrabold text-white flex items-center gap-2">
              <MessageSquare class="w-5 h-5 text-emerald-400" />
              <span>Format Naskah WhatsApp Siap Kirim ke Pimpinan</span>
            </h3>
            <p class="text-xs text-slate-400">
              Sintesis analisis intelijen media & sentimen opini publik terstandarisasi untuk Bapak Kapolda & Pejabat Utama
            </p>
          </div>

          <!-- Quick Action Buttons -->
          <div class="flex items-center flex-wrap gap-2 shrink-0">
            <!-- Scroll Top -->
            <button
              @click="scrollWaToTop"
              class="p-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-300 hover:text-white transition"
              title="Gulir ke Atas"
            >
              <ChevronUp class="w-4 h-4" />
            </button>
            <!-- Scroll Bottom -->
            <button
              @click="scrollWaToBottom"
              class="p-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-300 hover:text-white transition"
              title="Gulir ke Bawah"
            >
              <ChevronDown class="w-4 h-4" />
            </button>
            <!-- Unduh .txt -->
            <button
              @click="downloadWaTxt"
              class="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-300 hover:text-white text-xs font-bold transition"
              title="Unduh naskah sebagai file .txt"
            >
              <Download class="w-3.5 h-3.5 text-slate-400" />
              <span>Unduh .txt</span>
            </button>
            <!-- Kirim WA Langsung -->
            <button
              @click="openWhatsAppDirect"
              class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-600/30 hover:bg-emerald-600/50 border border-emerald-500/50 text-emerald-200 text-xs font-bold transition"
              title="Buka WhatsApp Web dengan naskah siap kirim"
            >
              <Send class="w-3.5 h-3.5 text-emerald-400" />
              <span>Kirim ke WA</span>
            </button>
            <!-- Salin Format WA -->
            <button
              @click="copyWaReport"
              class="flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-400 hover:from-emerald-400 hover:to-teal-300 text-slate-950 text-xs font-black transition shadow-lg shadow-emerald-500/20"
            >
              <Check v-if="isWaCopied" class="w-4 h-4" />
              <Copy v-else class="w-4 h-4" />
              <span>{{ isWaCopied ? 'Berhasil Disalin!' : 'Salin Naskah WA' }}</span>
            </button>
          </div>
        </div>

        <!-- Terminal Preformatted Box -->
        <div class="relative bg-[#060b14] p-5">
          <div
            ref="waBoxTab2Ref"
            class="max-h-[380px] overflow-y-auto font-mono text-xs leading-relaxed text-emerald-300/90 whitespace-pre-wrap select-all p-4 rounded-2xl bg-black/50 border border-emerald-950/80 shadow-inner custom-scrollbar"
          >
            {{ whatsappReportText }}
          </div>
        </div>

        <!-- Footer Bar -->
        <div class="px-6 py-3 bg-[#080f1e] border-t border-slate-800 flex items-center justify-between text-[11px] text-slate-400">
          <div class="flex items-center gap-4">
            <span class="flex items-center gap-1.5 text-emerald-400 font-semibold">
              <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
              Tersinkronisasi Realtime WITA
            </span>
            <span>Karakter: {{ whatsappReportText.length }}</span>
            <span>Target: Bapak Kapolda & PJU</span>
          </div>
          <button
            @click="activeTab = '07'"
            class="text-amber-400 hover:underline font-bold flex items-center gap-1"
          >
            <span>Buka Tampilan Penuh di Tab 07</span>
            <span>&rarr;</span>
          </button>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 03: EXECUTIVE SNAPSHOT                                                -->
    <!-- ========================================================================= -->
    <div v-show="activeTab === '03'" class="space-y-6">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Sentiment Nasional -->
        <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
          <h3 class="text-sm font-black text-white uppercase tracking-wider">Sentimen Publik Nasional</h3>
          <div class="flex items-center justify-around py-4">
            <div class="text-center">
              <span class="text-3xl font-black text-emerald-400">{{ report?.sentiment_national?.positif || 60 }}%</span>
              <p class="text-xs text-slate-400">Positif</p>
            </div>
            <div class="text-center">
              <span class="text-3xl font-black text-amber-400">{{ report?.sentiment_national?.netral || 30 }}%</span>
              <p class="text-xs text-slate-400">Netral</p>
            </div>
            <div class="text-center">
              <span class="text-3xl font-black text-rose-400">{{ report?.sentiment_national?.negatif || 10 }}%</span>
              <p class="text-xs text-slate-400">Negatif</p>
            </div>
          </div>
          <p class="text-xs text-slate-300 leading-relaxed">{{ report?.snapshot_national_desc }}</p>
        </div>

        <!-- Sentiment Sulbar -->
        <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
          <h3 class="text-sm font-black text-white uppercase tracking-wider">Sentimen Publik Sulawesi Barat</h3>
          <div class="flex items-center justify-around py-4">
            <div class="text-center">
              <span class="text-3xl font-black text-emerald-400">{{ report?.sentiment_sulbar?.positif || 55 }}%</span>
              <p class="text-xs text-slate-400">Positif</p>
            </div>
            <div class="text-center">
              <span class="text-3xl font-black text-amber-400">{{ report?.sentiment_sulbar?.netral || 30 }}%</span>
              <p class="text-xs text-slate-400">Netral</p>
            </div>
            <div class="text-center">
              <span class="text-3xl font-black text-rose-400">{{ report?.sentiment_sulbar?.negatif || 15 }}%</span>
              <p class="text-xs text-slate-400">Negatif</p>
            </div>
          </div>
          <p class="text-xs text-slate-300 leading-relaxed">{{ report?.snapshot_sulbar_desc }}</p>
        </div>
      </div>

      <!-- Priority Action Protocol (0-2h, 2-6h, 24h) -->
      <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
        <h3 class="text-sm font-black text-white uppercase tracking-wider">Matriks Respons Taktis Bertahap</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800 space-y-2">
            <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-rose-500/20 text-rose-300">0 - 2 Jam: Validasi</span>
            <p class="text-xs font-bold text-white">{{ report?.priority_responses?.response_0_2h?.title }}</p>
            <p class="text-xs text-slate-300">{{ report?.priority_responses?.response_0_2h?.action }}</p>
          </div>
          <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800 space-y-2">
            <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-amber-500/20 text-amber-300">2 - 6 Jam: Klarifikasi</span>
            <p class="text-xs font-bold text-white">{{ report?.priority_responses?.response_2_6h?.title }}</p>
            <p class="text-xs text-slate-300">{{ report?.priority_responses?.response_2_6h?.action }}</p>
          </div>
          <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800 space-y-2">
            <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-emerald-500/20 text-emerald-300">24 Jam: Amplifikasi</span>
            <p class="text-xs font-bold text-white">{{ report?.priority_responses?.response_24h?.title }}</p>
            <p class="text-xs text-slate-300">{{ report?.priority_responses?.response_24h?.action }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 04: STATISTIK SATWIL (6 POLRES SULBAR)                                 -->
    <!-- ========================================================================= -->
    <div v-show="activeTab === '04'" class="space-y-4">
      <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl">
        <h3 class="text-sm font-black text-white uppercase tracking-wider mb-4">Pemetaan Kondusifitas 6 Polres Jajaran Sulbar</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="polres in (selectedPolda?.polres || [
              { name: 'Polresta Mamuju', code: 'MAMUJU' },
              { name: 'Polres Majene', code: 'MAJENE' },
              { name: 'Polres Polewali Mandar', code: 'POLMAN' },
              { name: 'Polres Mamasa', code: 'MAMASA' },
              { name: 'Polres Pasangkayu', code: 'PASANGKAYU' },
              { name: 'Polres Mamuju Tengah', code: 'MATENG' },
            ])"
            :key="polres.name"
            class="p-5 rounded-2xl bg-slate-900 border border-slate-800 space-y-2"
          >
            <div class="flex items-center justify-between">
              <span class="font-bold text-white">{{ polres.name }}</span>
              <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-500/20 text-emerald-300">Kondusif</span>
            </div>
            <p class="text-xs text-slate-300">Patroli presisi dan pemantauan ruang publik terpantau lancar tanpa gejolak menonjol.</p>
            <div class="pt-2 border-t border-slate-800 flex items-center justify-between text-[11px] text-slate-400">
              <span>Indeks Sentimen: 85% Positif</span>
              <span class="text-amber-400 font-bold">Siaga Normal</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 05: EARLY WARNING (EWS)                                               -->
    <!-- ========================================================================= -->
    <div v-show="activeTab === '05'" class="space-y-4">
      <div
        v-for="(ew, idx) in (report?.early_warnings || [])"
        :key="idx"
        class="rounded-3xl bg-[#0b1426] border border-amber-400/40 p-6 shadow-xl space-y-3"
      >
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <span class="px-2.5 py-0.5 rounded text-[10px] font-black uppercase bg-amber-400 text-slate-950">
              LEVEL {{ ew.level }}
            </span>
            <h3 class="text-sm font-black text-white">{{ ew.title }}</h3>
          </div>
          <span class="text-xs font-mono text-slate-400">PIC: {{ ew.pic }}</span>
        </div>
        <p class="text-xs text-slate-300 leading-relaxed">{{ ew.fact }}</p>
        <div class="p-3 rounded-xl bg-slate-900 text-xs text-rose-300">
          <strong>Potensi Risiko:</strong> {{ ew.risk }}
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 pt-2 text-xs">
          <div class="p-2.5 rounded-lg bg-slate-900/60 border border-slate-800">
            <strong class="text-amber-400 block text-[10px]">0-2 Jam:</strong>
            <span class="text-slate-300">{{ ew.response_0_2h }}</span>
          </div>
          <div class="p-2.5 rounded-lg bg-slate-900/60 border border-slate-800">
            <strong class="text-amber-400 block text-[10px]">2-6 Jam:</strong>
            <span class="text-slate-300">{{ ew.response_2_6h }}</span>
          </div>
          <div class="p-2.5 rounded-lg bg-slate-900/60 border border-slate-800">
            <strong class="text-amber-400 block text-[10px]">6-24 Jam:</strong>
            <span class="text-slate-300">{{ ew.response_6_24h }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 06: NARASI & REKOMENDASI                                              -->
    <!-- ========================================================================= -->
    <div v-show="activeTab === '06'" class="space-y-6">
      <!-- Strategic Content Ideas -->
      <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
        <h3 class="text-sm font-black text-white uppercase tracking-wider">Ide Konten Kreatif & Viralisasi Humas</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div
            v-for="(c, idx) in (report?.strategic_contents || [])"
            :key="idx"
            class="p-4 rounded-2xl bg-slate-900 border border-slate-800 space-y-2 text-xs"
          >
            <div class="flex items-center justify-between">
              <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase bg-amber-400 text-slate-950">{{ c.label }}</span>
              <span class="text-slate-400 font-mono text-[10px]">{{ c.platform }}</span>
            </div>
            <p class="font-bold text-white text-sm">{{ c.title }}</p>
            <p class="text-slate-300">Format: {{ c.format }} &bull; Target: {{ c.target }}</p>
          </div>
        </div>
      </div>

      <!-- Action Recommendations -->
      <div class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4">
        <h3 class="text-sm font-black text-white uppercase tracking-wider">Rekomendasi Komunikasi Publik</h3>
        <div class="space-y-3">
          <div
            v-for="(rec, idx) in (report?.recommendations || [])"
            :key="idx"
            class="p-4 rounded-2xl bg-slate-900 border border-slate-800 space-y-1 text-xs"
          >
            <div class="flex items-center justify-between">
              <span class="font-bold text-white">{{ rec.issue }}</span>
              <span class="text-amber-400 font-bold text-[10px]">{{ rec.target }}</span>
            </div>
            <p class="text-slate-300">{{ rec.action }}</p>
            <p class="text-[10px] text-slate-400">PIC: {{ rec.pic }} &bull; Indikator: {{ rec.indicator }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 07: FORMAT NASKAH WHATSAPP PIMPINAN (KAPOLDA & PJU)                   -->
    <!-- ========================================================================= -->
    <div v-show="activeTab === '07'" class="space-y-6">
      <!-- Executive Notification Banner -->
      <div class="rounded-3xl bg-gradient-to-r from-emerald-950/60 via-[#0b1426] to-[#080f20] border border-emerald-500/30 p-6 shadow-2xl space-y-3">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div class="space-y-1">
            <div class="flex items-center gap-2">
              <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
                PRODUK INTELIJEN HUMAS RESMI
              </span>
              <span class="text-xs text-emerald-400 font-mono">{{ report?.report_time || '19.03 WITA' }}</span>
            </div>
            <h2 class="text-lg font-black text-white flex items-center gap-2">
              <MessageSquare class="w-5 h-5 text-emerald-400" />
              <span>Format Naskah WhatsApp Siap Kirim ke Pimpinan</span>
            </h2>
            <p class="text-xs text-slate-300 max-w-3xl leading-relaxed">
              Disusun secara terstandarisasi untuk penyampaian cepat kepada <strong>{{ selectedPolda?.kapolda_name ? `Bapak Kapolda (${selectedPolda.kapolda_name})` : 'Bapak Kapolda' }}</strong>, Wakapolda, dan Irwasda. Format telah dioptimalkan dengan tipografi WhatsApp (bold, italic, divider, and bullets) agar nyaman dibaca pada perangkat mobile pimpinan.
            </p>
          </div>

          <!-- Top Quick Actions -->
          <div class="flex items-center gap-2 shrink-0">
            <button
              @click="openWhatsAppDirect"
              class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600/30 hover:bg-emerald-600/50 border border-emerald-500/50 text-emerald-200 text-xs font-bold transition shadow-sm"
              title="Buka WhatsApp Web dan kirimkan teks laporan langsung"
            >
              <Send class="w-4 h-4 text-emerald-400" />
              <span>Buka di WhatsApp Web</span>
            </button>
            <button
              @click="copyWaReport"
              class="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-400 hover:from-emerald-400 hover:to-teal-300 text-slate-950 text-xs font-black transition shadow-lg shadow-emerald-500/25"
            >
              <Check v-if="isWaCopied" class="w-4 h-4" />
              <Copy v-else class="w-4 h-4" />
              <span>{{ isWaCopied ? '✓ Berhasil Disalin!' : 'Salin Seluruh Naskah' }}</span>
            </button>
          </div>
        </div>

        <!-- 3-Step Flow Guide -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 pt-3 border-t border-slate-800/80 text-xs">
          <div class="p-3 rounded-2xl bg-slate-900/80 border border-slate-800 space-y-1">
            <span class="text-[10px] font-black uppercase text-amber-400">Langkah 1 &bull; Verifikasi Fakta</span>
            <p class="text-slate-300 text-[11px]">Pastikan ringkasan briefing, sentimen, dan isu prioritas telah disinkronkan dari pemantauan 24 jam.</p>
          </div>
          <div class="p-3 rounded-2xl bg-slate-900/80 border border-slate-800 space-y-1">
            <span class="text-[10px] font-black uppercase text-emerald-400">Langkah 2 &bull; Salin Naskah</span>
            <p class="text-slate-300 text-[11px]">Klik tombol "Salin Seluruh Naskah" atau unduh salinan naskah .txt sebagai arsip fisik dinas.</p>
          </div>
          <div class="p-3 rounded-2xl bg-slate-900/80 border border-slate-800 space-y-1">
            <span class="text-[10px] font-black uppercase text-sky-400">Langkah 3 &bull; Diseminasi Berjenjang</span>
            <p class="text-slate-300 text-[11px]">Tempelkan langsung ke chat WhatsApp dinas Bapak Kapolda dengan tembusan Wakapolda & Irwasda.</p>
          </div>
        </div>
      </div>

      <!-- Main WhatsApp Terminal Box -->
      <div class="rounded-3xl bg-[#0b1426] border border-emerald-500/40 shadow-2xl overflow-hidden">
        <!-- Terminal Header Toolbar -->
        <div class="p-4 md:p-5 bg-gradient-to-r from-emerald-950/50 via-[#0c162e] to-[#080f20] border-b border-emerald-500/30 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div class="flex items-center gap-3">
            <div class="flex items-center gap-1.5">
              <span class="w-3 h-3 rounded-full bg-emerald-500 inline-block shadow-sm shadow-emerald-500/50"></span>
              <span class="w-3 h-3 rounded-full bg-amber-400 inline-block"></span>
              <span class="w-3 h-3 rounded-full bg-slate-600 inline-block"></span>
            </div>
            <span class="text-xs font-mono font-bold text-slate-300 uppercase tracking-wider">
              WA_DISPATCH_OFFICIAL_BRIEFING.TXT
            </span>
          </div>

          <!-- Controls -->
          <div class="flex items-center flex-wrap gap-2">
            <!-- Scroll to Top -->
            <button
              @click="scrollWaToTop"
              class="p-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-300 hover:text-white transition"
              title="Gulir ke Awal Teks"
            >
              <ChevronUp class="w-4 h-4" />
            </button>
            <!-- Scroll to Bottom -->
            <button
              @click="scrollWaToBottom"
              class="p-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-300 hover:text-white transition"
              title="Gulir ke Akhir Teks"
            >
              <ChevronDown class="w-4 h-4" />
            </button>
            <!-- Maximize Toggle -->
            <button
              @click="isWaMaximized = !isWaMaximized"
              class="p-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-300 hover:text-white transition"
              :title="isWaMaximized ? 'Perkecil Kotak' : 'Perluas Kotak Teks'"
            >
              <Minimize2 v-if="isWaMaximized" class="w-4 h-4 text-amber-400" />
              <Maximize2 v-else class="w-4 h-4 text-slate-300" />
            </button>
            <!-- Download TXT -->
            <button
              @click="downloadWaTxt"
              class="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-300 hover:text-white text-xs font-bold transition"
              title="Unduh naskah mentah sebagai file .txt"
            >
              <Download class="w-3.5 h-3.5 text-slate-400" />
              <span>Unduh .txt</span>
            </button>
            <!-- Copy Main -->
            <button
              @click="copyWaReport"
              class="flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 text-xs font-black transition shadow-md shadow-emerald-500/20"
            >
              <Check v-if="isWaCopied" class="w-4 h-4" />
              <Copy v-else class="w-4 h-4" />
              <span>{{ isWaCopied ? 'Tersalin ke Clipboard!' : 'Salin Naskah' }}</span>
            </button>
          </div>
        </div>

        <!-- Terminal Body -->
        <div class="relative bg-[#060b14] p-5">
          <!-- Floating Copy Button in top right -->
          <button
            @click="copyWaReport"
            class="absolute top-8 right-8 z-10 flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-950/80 hover:bg-emerald-900 border border-emerald-500/50 text-emerald-300 text-[11px] font-bold backdrop-blur-md transition shadow-md"
            title="Klik untuk salin cepat"
          >
            <Check v-if="isWaCopied" class="w-3.5 h-3.5 text-emerald-400" />
            <Copy v-else class="w-3.5 h-3.5 text-emerald-400" />
            <span>{{ isWaCopied ? 'Tersalin!' : 'Salin Teks' }}</span>
          </button>

          <div
            ref="waBoxRef"
            :class="[
              'overflow-y-auto font-mono text-xs leading-relaxed text-emerald-300/95 whitespace-pre-wrap select-all p-6 rounded-2xl bg-black/70 border border-emerald-950/90 shadow-2xl custom-scrollbar transition-all duration-300',
              isWaMaximized ? 'max-h-[850px]' : 'max-h-[560px]'
            ]"
          >
            {{ whatsappReportText }}
          </div>
        </div>

        <!-- Footer Meta -->
        <div class="px-6 py-4 bg-[#080f1e] border-t border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-slate-400">
          <div class="flex items-center flex-wrap gap-4 text-[11px]">
            <span class="flex items-center gap-1.5 text-emerald-400 font-bold">
              <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
              Siap Kirim WhatsApp (UTF-8)
            </span>
            <span>Jumlah Karakter: <strong class="text-white">{{ whatsappReportText.length }}</strong></span>
            <span>Estimasi Baca: <strong class="text-white">~3 Menit</strong></span>
            <span>Tembusan: <strong class="text-slate-300">Wakapolda & Irwasda</strong></span>
          </div>

          <div class="flex items-center gap-2">
            <button
              @click="openWhatsAppDirect"
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-600/30 hover:bg-emerald-600/50 border border-emerald-500/40 text-emerald-300 text-xs font-bold transition"
            >
              <Send class="w-3.5 h-3.5 text-emerald-400" />
              <span>Buka WhatsApp Web</span>
            </button>
            <button
              @click="copyWaReport"
              class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black transition"
            >
              <Copy class="w-3.5 h-3.5" />
              <span>Salin Sekarang</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 1: TEMPEL RINGKASAN AI (CHATGPT / CLAUDE / GEMINI)                  -->
    <!-- ========================================================================= -->
    <Teleport to="body">
    <div
      v-if="showPasteAiModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
    >
      <div class="w-full max-w-2xl rounded-3xl bg-[#0b1426] border border-slate-700 shadow-2xl p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <Clipboard class="w-5 h-5 text-amber-400" />
            <h3 class="text-base font-extrabold text-white">Tempel Ringkasan Intelijen AI</h3>
          </div>
          <button @click="showPasteAiModal = false" class="text-slate-400 hover:text-white">
            <X class="w-5 h-5" />
          </button>
        </div>

        <p class="text-xs text-slate-300 leading-relaxed">
          Tempelkan seluruh teks atau blok JSON yang Anda dapatkan dari <strong>ChatGPT / Claude / Gemini</strong>. Sistem akan membersihkan markdown, mengurai sentimen, dan menyegarkan tampilan dashboard secara otomatis.
        </p>

        <textarea
          v-model="pasteAiInput"
          rows="12"
          placeholder="Tempelkan teks / JSON di sini (contoh: ```json { 'title': '...', 'sentiment_sulbar': { 'positif': 60 } } ```)..."
          class="w-full p-4 rounded-2xl bg-slate-900 border border-slate-700 text-xs font-mono text-amber-300 placeholder-slate-500 focus:outline-none focus:border-amber-400 leading-relaxed"
        ></textarea>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
          <button
            @click="showPasteAiModal = false"
            class="px-4 py-2 rounded-xl bg-slate-900 border border-slate-700 text-xs font-bold text-slate-300 hover:text-white"
          >
            Batal
          </button>
          <button
            @click="submitPastedAi"
            :disabled="isSubmittingAi || !pasteAiInput.trim()"
            class="flex items-center gap-2 px-5 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black transition disabled:opacity-50"
          >
            <Check class="w-4 h-4" />
            <span>{{ isSubmittingAi ? 'Memproses Laporan...' : 'Terapkan & Muat Laporan' }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 2: KELOLA SUMBER RSS (DAFTAR FEED, TAMBAH, & UJI XML)               -->
    <!-- ========================================================================= -->
    <div
      v-if="showRssManagerModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
    >
      <div class="w-full max-w-4xl rounded-3xl bg-[#0b1426] border border-slate-700 shadow-2xl p-6 space-y-5 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <SlidersHorizontal class="w-5 h-5 text-amber-400" />
            <div>
              <h3 class="text-base font-extrabold text-white">Manajemen Sumber Feed RSS Media</h3>
              <p class="text-xs text-slate-400">Konfigurasi URL portal berita daring untuk wilayah {{ selectedPolda?.name }}</p>
            </div>
          </div>
          <button @click="showRssManagerModal = false" class="text-slate-400 hover:text-white">
            <X class="w-5 h-5" />
          </button>
        </div>

        <!-- Form Tambah Sumber Baru -->
        <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800 space-y-3">
          <h4 class="text-xs font-black text-amber-400 uppercase tracking-wider">Tambah Portal Media Baru</h4>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
            <div>
              <label class="block font-bold text-slate-300 mb-1">Nama Media</label>
              <input
                v-model="newFeedForm.name"
                type="text"
                placeholder="Contoh: Tribratanews Sulbar"
                class="w-full px-3 py-2 rounded-xl bg-[#0b1426] border border-slate-700 text-white"
              />
            </div>
            <div>
              <label class="block font-bold text-slate-300 mb-1">Kategori</label>
              <select
                v-model="newFeedForm.category"
                class="w-full px-3 py-2 rounded-xl bg-[#0b1426] border border-slate-700 text-white"
              >
                <option>Regional Sulselbar</option>
                <option>Nasional & Umum</option>
                <option>Hukum & Kriminal</option>
                <option>Google News RSS</option>
              </select>
            </div>
            <div>
              <label class="block font-bold text-slate-300 mb-1">URL Feed RSS (XML / Atom)</label>
              <input
                v-model="newFeedForm.url"
                type="url"
                placeholder="https://domain.com/feed/"
                class="w-full px-3 py-2 rounded-xl bg-[#0b1426] border border-slate-700 text-white font-mono"
              />
            </div>
          </div>

          <!-- Test Result Banner -->
          <div
            v-if="testResult"
            class="p-2.5 rounded-xl text-xs flex items-center justify-between"
            :class="testResult.valid ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' : 'bg-rose-500/20 text-rose-300 border border-rose-500/40'"
          >
            <span>{{ testResult.message }}</span>
            <span class="font-mono font-bold">{{ testResult.latency_ms }}ms</span>
          </div>

          <div class="flex items-center justify-end gap-2 pt-1">
            <button
              @click="testFeedUrl(newFeedForm.url)"
              :disabled="isTestingUrl || !newFeedForm.url"
              class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-bold text-slate-200 disabled:opacity-50"
            >
              {{ isTestingUrl ? 'Menguji...' : 'Uji Validitas URL' }}
            </button>
            <button
              @click="submitNewRssSource"
              :disabled="!newFeedForm.name || !newFeedForm.url"
              class="px-4 py-1.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-black text-xs disabled:opacity-50"
            >
              Simpan Sumber
            </button>
          </div>
        </div>

        <!-- Daftar Sumber RSS Terdaftar -->
        <div class="rounded-2xl border border-slate-800 overflow-hidden">
          <div class="px-4 py-2.5 bg-slate-900/80 border-b border-slate-800 text-[11px] font-black uppercase text-slate-400 flex items-center justify-between">
            <span>Daftar Portal Aktif ({{ mediaFeeds?.length || 0 }} Portal)</span>
            <span>Aksi Manajemen</span>
          </div>
          <div class="divide-y divide-slate-800 text-xs max-h-64 overflow-y-auto">
            <div
              v-for="feed in mediaFeeds"
              :key="feed.id"
              class="px-4 py-3 flex items-center justify-between gap-3 hover:bg-slate-900/40 transition"
            >
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                  <span class="font-bold text-white">{{ feed.name }}</span>
                  <span class="px-2 py-0.2 rounded text-[9px] font-black uppercase bg-slate-800 text-slate-300 border border-slate-700">
                    {{ feed.category }}
                  </span>
                  <span
                    class="px-2 py-0.2 rounded text-[9px] font-bold"
                    :class="feed.is_active ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-800 text-slate-500'"
                  >
                    {{ feed.is_active ? 'AKTIF' : 'NONAKTIF' }}
                  </span>
                </div>
                <p class="text-[10px] text-slate-400 font-mono truncate mt-0.5">{{ feed.url }}</p>
              </div>

              <div class="flex items-center gap-2">
                <button
                  @click="testFeedUrl(feed.url)"
                  class="px-2 py-1 rounded bg-slate-900 hover:bg-slate-800 border border-slate-700 text-[10px] font-bold text-slate-300"
                >
                  Uji
                </button>
                <button
                  @click="toggleFeed(feed.id)"
                  class="px-2 py-1 rounded text-[10px] font-bold"
                  :class="feed.is_active ? 'bg-amber-400/20 text-amber-300 border border-amber-400/40' : 'bg-emerald-500/20 text-emerald-300'"
                >
                  {{ feed.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                </button>
                <button
                  @click="deleteFeed(feed.id)"
                  class="p-1 rounded text-rose-400 hover:bg-rose-500/20"
                  title="Hapus Sumber"
                >
                  <Trash2 class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 3: BUAT LAPORAN ISU DARI TEMUAN BERITA                               -->
    <!-- ========================================================================= -->
    <div
      v-if="showIssueModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
    >
      <div class="w-full max-w-lg rounded-3xl bg-[#0b1426] border border-slate-700 shadow-2xl p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <Flame class="w-4 h-4 text-amber-400" />
            <h3 class="text-base font-extrabold text-white">Eskalasi Berita Menjadi Isu Kamtibmas</h3>
          </div>
          <button @click="showIssueModal = false" class="text-slate-400 hover:text-white">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="p-3 rounded-2xl bg-slate-900 border border-slate-800 space-y-1 text-xs">
          <p class="font-bold text-white">{{ activeArticleForIssue?.title }}</p>
          <p class="text-[10px] text-amber-400">{{ activeArticleForIssue?.source_name }} &bull; {{ activeArticleForIssue?.pub_date }}</p>
        </div>

        <div class="space-y-3 text-xs">
          <div>
            <label class="block font-bold text-slate-300 mb-1">Tingkat Prioritas Isu</label>
            <div class="grid grid-cols-3 gap-2">
              <button
                v-for="prio in ['HIJAU', 'KUNING', 'MERAH']"
                :key="prio"
                type="button"
                @click="issueForm.priority = prio"
                class="py-2 rounded-xl text-xs font-black uppercase transition"
                :class="issueForm.priority === prio
                  ? prio === 'MERAH' ? 'bg-rose-500 text-white' : prio === 'KUNING' ? 'bg-amber-400 text-slate-950' : 'bg-emerald-500 text-slate-950'
                  : 'bg-slate-900 border border-slate-700 text-slate-400'"
              >
                {{ prio }}
              </button>
            </div>
          </div>

          <div>
            <label class="block font-bold text-slate-300 mb-1">Satker / PIC Tindak Lanjut</label>
            <input
              v-model="issueForm.pic"
              type="text"
              class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400"
            />
          </div>

          <div>
            <label class="block font-bold text-slate-300 mb-1">Catatan Eskalasi / Arahan Pimpinan</label>
            <textarea
              v-model="issueForm.notes"
              rows="3"
              class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-amber-400"
            ></textarea>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-800">
          <button
            @click="showIssueModal = false"
            class="px-4 py-2 rounded-xl bg-slate-900 border border-slate-700 text-xs font-bold text-slate-300 hover:text-white"
          >
            Batal
          </button>
          <button
            @click="submitIssue"
            class="px-5 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black transition"
          >
            Simpan ke Manajemen Isu
          </button>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 4: LIHAT & SALIN FORMULA PROMPT AI (PREVIEW MODAL)                  -->
    <!-- ========================================================================= -->
    <div
      v-if="showPromptModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
    >
      <div class="w-full max-w-3xl rounded-3xl bg-[#0b1426] border border-amber-400/40 shadow-2xl p-6 space-y-4 max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <Sparkles class="w-5 h-5 text-amber-400" />
            <div>
              <h3 class="text-base font-extrabold text-white">{{ promptModalTitle || 'Formula Prompt AI Presisi' }}</h3>
              <p class="text-xs text-slate-400">Siap digunakan di ChatGPT (GPT-4o), Claude 3.5 Sonnet, atau Gemini Pro</p>
            </div>
          </div>
          <button @click="showPromptModal = false" class="text-slate-400 hover:text-white">
            <X class="w-5 h-5" />
          </button>
        </div>

        <p class="text-xs text-slate-300 leading-relaxed">
          Salin seluruh formula prompt di bawah ini, lalu tempelkan ke kolom chat AI. Formula ini telah disesuaikan secara otomatis dengan wilayah satuan <strong>{{ selectedPolda?.name || 'Polda Sulbar' }}</strong> dan jajaran Polres terkait.
        </p>

        <div class="relative flex-1 overflow-hidden rounded-2xl border border-slate-700 bg-slate-950">
          <textarea
            readonly
            :value="promptModalContent"
            rows="14"
            class="w-full h-80 p-4 text-xs font-mono text-amber-300 bg-slate-950 border-none focus:outline-none resize-none leading-relaxed"
            @focus="$event.target.select()"
          ></textarea>
        </div>

        <div class="flex items-center justify-between pt-3 border-t border-slate-800">
          <span class="text-[11px] text-slate-400">Tips: Klik kolom teks untuk memblok/memilih semua secara manual</span>
          <div class="flex items-center gap-2">
            <button
              @click="showPromptModal = false"
              class="px-4 py-2 rounded-xl bg-slate-900 border border-slate-700 text-xs font-bold text-slate-300 hover:text-white"
            >
              Tutup
            </button>
            <button
              @click="execCommandCopy(promptModalContent, promptModalTitle || 'Formula Prompt AI'); showPromptModal = false;"
              class="flex items-center gap-2 px-5 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black transition shadow-lg shadow-amber-400/20"
            >
              <Copy class="w-4 h-4" />
              <span>Salin Teks ke Clipboard</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
  </EnterpriseLayout>
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
</style>
