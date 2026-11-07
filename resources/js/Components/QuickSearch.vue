<script setup>
/**
 * Pencarian Cepat (Ctrl+K / ⌘K): berita, laporan isu, tugas, unggahan & akun medsos (server, terkunci tenant & izin),
 * ditambah halaman/menu yang sudah diizinkan (dari navigasi). Navigasi keyboard: ↑ ↓ Enter, Esc untuk menutup.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Search, X, ExternalLink, CornerDownLeft } from 'lucide-vue-next';

const page = usePage();
const open = ref(false);
const q = ref('');
const groups = ref([]);
const loading = ref(false);
const active = ref(0);
const input = ref(null);
let timer = null;
let seq = 0;

const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform || '');

// Halaman dari navigasi yang sudah difilter izin oleh server.
const pages = computed(() => {
  const term = q.value.trim().toLowerCase();
  if (term.length < 1) return [];
  const nav = page.props.navigation || {};
  const out = new Map();
  const add = (it, meta) => {
    if (it?.href && it.href !== '#' && !out.has(it.href) && it.name?.toLowerCase().includes(term)) out.set(it.href, { title: it.name, meta, url: it.href, external: false });
  };
  for (const group of nav.sidebarGroups || []) {
    for (const item of group.items || []) {
      for (const it of [item, ...(item.children || [])]) add(it, group.name || 'Menu');
    }
  }
  for (const tab of nav.securityTabs || []) add(tab, 'Keamanan & Pemeliharaan');
  return [...out.values()].slice(0, 5);
});
const allGroups = computed(() => [...(pages.value.length ? [{ key: 'pages', label: 'Halaman', items: pages.value }] : []), ...groups.value]);
const flat = computed(() => allGroups.value.flatMap((g) => g.items.map((it) => ({ ...it, group: g.key }))));

const show = async () => {
  open.value = true;
  await nextTick();
  input.value?.focus();
};
const hide = () => {
  open.value = false;
  q.value = '';
  groups.value = [];
};
const go = (item) => {
  if (!item) return;
  if (item.external) {
    window.open(item.url, '_blank', 'noopener,noreferrer');
  } else {
    router.visit(item.url);
  }
  hide();
};

watch(q, (value) => {
  active.value = 0;
  clearTimeout(timer);
  const term = value.trim();
  if (term.length < 2) {
    groups.value = [];
    loading.value = false;
    return;
  }
  loading.value = true;
  const mine = ++seq;
  timer = setTimeout(async () => {
    try {
      const { data } = await window.axios.get('/quick-search', { params: { q: term } });
      if (mine === seq) groups.value = data.groups || [];
    } catch {
      if (mine === seq) groups.value = [];
    } finally {
      if (mine === seq) loading.value = false;
    }
  }, 250);
});

const onKey = (e) => {
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
    e.preventDefault();
    open.value ? hide() : show();
    return;
  }
  if (!open.value) return;
  if (e.key === 'Escape') hide();
  else if (e.key === 'ArrowDown') { e.preventDefault(); active.value = Math.min(flat.value.length - 1, active.value + 1); }
  else if (e.key === 'ArrowUp') { e.preventDefault(); active.value = Math.max(0, active.value - 1); }
  else if (e.key === 'Enter') { e.preventDefault(); go(flat.value[active.value]); }
};
const indexOf = (item) => flat.value.findIndex((x) => x.url === item.url && x.title === item.title);

onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
defineExpose({ show });
</script>

<template>
  <button
    type="button"
    @click="show"
    class="hidden md:flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-900/80 hover:bg-slate-800 border border-slate-800 text-slate-400 hover:text-white text-xs transition cursor-pointer"
    title="Pencarian cepat"
  >
    <Search class="w-3.5 h-3.5" />
    <span>Cari…</span>
    <kbd class="ml-2 px-1.5 py-0.5 rounded bg-slate-800 border border-slate-700 text-[10px] font-mono">{{ isMac ? '⌘' : 'Ctrl' }} K</kbd>
  </button>
  <button type="button" @click="show" class="md:hidden p-2 rounded-xl bg-slate-900/80 hover:bg-slate-800 border border-slate-800 text-slate-300 cursor-pointer" aria-label="Pencarian cepat">
    <Search class="w-4 h-4" />
  </button>

  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-[90] bg-slate-950/60 backdrop-blur-sm flex items-start justify-center p-3 sm:pt-[12vh]" @mousedown.self="hide">
      <div role="dialog" aria-label="Pencarian cepat" class="w-full max-w-2xl rounded-2xl bg-white dark:bg-[#0b1426] border border-slate-200 dark:border-slate-700 shadow-2xl overflow-hidden">
        <div class="flex items-center gap-2 px-4 border-b border-slate-200 dark:border-slate-800">
          <Search class="w-4 h-4 text-slate-400 shrink-0" />
          <input
            ref="input"
            v-model="q"
            type="text"
            enterkeyhint="search"
            autocomplete="off"
            placeholder="Cari berita, laporan isu, tugas, akun, unggahan, atau halaman…"
            aria-label="Kata kunci pencarian"
            class="flex-1 py-3.5 bg-transparent text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none"
          />
          <span v-if="loading" class="text-[11px] text-slate-400">Mencari…</span>
          <button type="button" @click="hide" class="p-1 rounded text-slate-400 hover:text-slate-700 dark:hover:text-white cursor-pointer" aria-label="Tutup"><X class="w-4 h-4" /></button>
        </div>

        <div class="max-h-[60vh] overflow-y-auto p-2">
          <p v-if="q.trim().length < 2 && !pages.length" class="px-3 py-6 text-center text-xs text-slate-500 dark:text-slate-400">
            Ketik minimal 2 huruf. Hasil mengikuti wilayah aktif dan hak akses Anda.
          </p>
          <p v-else-if="!loading && q.trim().length >= 2 && !allGroups.length" class="px-3 py-6 text-center text-xs text-slate-500 dark:text-slate-400">
            Tidak ada hasil untuk "{{ q.trim() }}".
          </p>
          <div v-for="g in allGroups" :key="g.key" class="mb-2">
            <div class="flex items-center justify-between px-3 py-1.5">
              <span class="text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ g.label }}</span>
              <a v-if="g.more" :href="g.more" @click.prevent="router.visit(g.more); hide()" class="text-[10px] font-bold text-amber-600 dark:text-amber-400 hover:underline">Lihat semua</a>
            </div>
            <button
              v-for="item in g.items"
              :key="g.key + item.url + item.title"
              type="button"
              @click="go(item)"
              @mousemove="active = indexOf(item)"
              class="w-full text-left px-3 py-2 rounded-xl flex items-start gap-3 cursor-pointer"
              :class="indexOf(item) === active ? 'bg-amber-50 dark:bg-amber-400/10' : 'hover:bg-slate-50 dark:hover:bg-slate-900'"
            >
              <span class="min-w-0 flex-1">
                <span class="block text-sm font-semibold text-slate-900 dark:text-slate-100 line-clamp-1">{{ item.title }}</span>
                <span class="block text-[11px] text-slate-500 dark:text-slate-400 truncate">{{ item.meta }}</span>
              </span>
              <ExternalLink v-if="item.external" class="w-3.5 h-3.5 mt-1 text-slate-400 shrink-0" />
              <CornerDownLeft v-else-if="indexOf(item) === active" class="w-3.5 h-3.5 mt-1 text-slate-400 shrink-0" />
            </button>
          </div>
        </div>

        <div class="hidden sm:flex items-center gap-4 px-4 py-2 border-t border-slate-200 dark:border-slate-800 text-[10px] text-slate-500 dark:text-slate-400">
          <span>↑ ↓ pilih</span><span>Enter buka</span><span>Esc tutup</span>
        </div>
      </div>
    </div>
  </Teleport>
</template>
