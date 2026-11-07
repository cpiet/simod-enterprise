<script setup>
/**
 * Peta Situasi Command Center.
 * - mode "national": 38 provinsi, pemberitaan 7 hari tenant Divhumas (App\Services\SituationMapService).
 * - mode "regional": titik Polres (status dari laporan isu; berita 7 hari per Polres sebagai pendamping).
 * Klik titik → panel samping: alasan status, 5 berita terbaru (tautan ke sumber), dan pintasan ke Radar Media Online.
 */
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const props = defineProps({ map: { type: Object, default: () => ({ mode: 'national', regions: [] }) } });

const mapElement = ref(null);
const selected = ref(null);
const mapError = ref(false);
const isNational = computed(() => props.map?.mode !== 'regional');
const regions = computed(() => props.map?.regions || []);
const colors = { ATENSI: '#e11d48', WASPADA: '#d97706', KONDUSIF: '#059669', 'BELUM ADA DATA': '#64748b' };
const statusLabel = (s) => (s === 'BELUM ADA DATA' && !isNational.value ? 'BELUM ADA LAPORAN' : s);
const located = computed(() => regions.value.filter((p) => {
  const lat = Number(p.latitude);
  const lng = Number(p.longitude);
  return p.latitude != null && p.longitude != null && Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180 && !(lat === 0 && lng === 0);
}));
const missing = computed(() => regions.value.length - located.value.length);
const newsCount = (p) => (isNational.value ? p.articles_count : p.media_7d) ?? 0;
const rank = { ATENSI: 0, WASPADA: 1, KONDUSIF: 2, 'BELUM ADA DATA': 3 };
// Wilayah yang paling perlu dilihat: status terberat lalu jumlah berita/laporan aktif.
const spotlight = computed(() => [...regions.value]
  .filter((p) => newsCount(p) > 0 || (p.issues_count ?? 0) > 0)
  .sort((a, b) => rank[a.status] - rank[b.status] || (b.issues_count ?? 0) - (a.issues_count ?? 0) || newsCount(b) - newsCount(a))
  .slice(0, 6));
const counts = computed(() => regions.value.reduce((acc, p) => ({ ...acc, [p.status]: (acc[p.status] || 0) + 1 }), {}));
const sentimentDot = (s) => (s === 'negatif' ? 'bg-rose-500' : s === 'positif' ? 'bg-emerald-500' : 'bg-slate-500');
const radarHref = (p) => (p?.search_q ? `/media-intelligence?q=${encodeURIComponent(p.search_q)}` : '/media-intelligence');

let lmap;
let markers;
const fitMap = () => {
  if (!lmap) return;
  if (isNational.value || !located.value.length) {
    lmap.fitBounds([[-11.5, 94], [6.5, 141.5]], { padding: [10, 10] });
  } else if (located.value.length === 1) {
    lmap.setView([Number(located.value[0].latitude), Number(located.value[0].longitude)], 9);
  } else {
    lmap.fitBounds(L.latLngBounds(located.value.map((p) => [Number(p.latitude), Number(p.longitude)])), { padding: [35, 35], maxZoom: 9 });
  }
};
const select = (p, pan = false) => {
  selected.value = p;
  if (pan && lmap && p.latitude != null) lmap.setView([Number(p.latitude), Number(p.longitude)], isNational.value ? 6 : 9);
};
const renderMarkers = () => {
  if (!lmap) return;
  markers.clearLayers();
  for (const p of located.value) {
    const n = newsCount(p);
    const radius = Math.min(18, 6 + Math.sqrt(n) * 1.6);
    const isSel = selected.value?.id === p.id;
    const marker = L.circleMarker([Number(p.latitude), Number(p.longitude)], {
      radius, color: isSel ? '#fbbf24' : '#ffffff', weight: isSel ? 3 : 1.5, fillColor: colors[p.status] || '#64748b', fillOpacity: 0.9,
    });
    marker.bindTooltip(document.createTextNode(`${p.short_name || p.name} • ${statusLabel(p.status)} • ${n} berita${isNational.value ? '' : ` • ${p.issues_count ?? 0} laporan aktif`}`));
    marker.on('click', () => select(p));
    marker.addTo(markers);
  }
};

onMounted(() => {
  lmap = L.map(mapElement.value, { scrollWheelZoom: false, zoomSnap: 0.5 }).setView([-2, 118], 5);
  const tileUrl = import.meta.env.VITE_MAP_TILE_URL || 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
  const attribution = import.meta.env.VITE_MAP_ATTRIBUTION || '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';
  const tiles = L.tileLayer(tileUrl, { maxZoom: 19, attribution });
  tiles.on('tileerror', () => { mapError.value = true; });
  tiles.on('load', () => { mapError.value = false; });
  tiles.addTo(lmap);
  markers = L.layerGroup().addTo(lmap);
  renderMarkers();
  nextTick(() => { lmap.invalidateSize(); fitMap(); });
});
watch(selected, renderMarkers);
watch(() => props.map, () => { selected.value = null; renderMarkers(); nextTick(fitMap); });
onUnmounted(() => { if (lmap) lmap.remove(); });
</script>

<template>
  <section class="lg:col-span-3 rounded-3xl bg-[#0b1426] border border-slate-800 p-5 md:p-6 shadow-xl space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h2 class="text-base font-black text-white">Peta Situasi &amp; Pemberitaan</h2>
        <p class="text-xs text-slate-400 mt-1">
          <template v-if="isNational">Pemberitaan {{ map.days ?? 7 }} hari terakhir per provinsi • {{ map.total_articles ?? 0 }} berita dianalisis<span v-if="map.unmatched"> ({{ map.unmatched }} tidak menyebut wilayah)</span></template>
          <template v-else>Status Polres dari laporan isu • berita {{ map.days ?? 7 }} hari per wilayah Polres</template>
        </p>
      </div>
      <button type="button" class="px-3 py-2 rounded-lg text-xs font-bold bg-slate-800 text-slate-200 hover:bg-slate-700 cursor-pointer" @click="selected = null; fitMap()">Atur ulang peta</button>
    </div>

    <div class="flex flex-wrap gap-2 text-[11px]">
      <span v-for="(color, status) in colors" :key="status" class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg bg-slate-900 border border-slate-800 text-slate-300" :title="map.definitions?.[status]">
        <span class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: color }"></span>
        <b>{{ statusLabel(status) }}</b> <span class="text-slate-500">{{ counts[status] || 0 }}</span>
      </span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <div class="lg:col-span-2 space-y-2">
        <div class="relative rounded-2xl overflow-hidden border border-slate-700">
          <div ref="mapElement" class="simod-map w-full h-[380px] md:h-[480px]" aria-label="Peta interaktif situasi wilayah"></div>
        </div>
        <p v-if="mapError" role="status" class="text-xs text-rose-400">Sebagian peta belum dapat dimuat. Periksa koneksi internet; daftar wilayah di samping tetap dapat dipakai.</p>
        <p class="text-[11px] text-slate-500">Ukuran titik = jumlah berita; warna = status. Titik adalah {{ isNational ? 'ibu kota provinsi' : 'lokasi Markas Polres' }}, bukan lokasi kejadian.<span v-if="missing"> {{ missing }} wilayah belum memiliki koordinat.</span></p>
      </div>

      <!-- Panel samping -->
      <aside class="rounded-2xl bg-slate-900/70 border border-slate-800 p-4 space-y-3 text-xs lg:max-h-[520px] lg:overflow-y-auto" aria-live="polite">
        <template v-if="selected">
          <div class="flex items-start justify-between gap-2">
            <div>
              <p class="text-sm font-black text-white leading-tight">{{ selected.name }}</p>
              <span class="inline-flex items-center gap-1.5 mt-1 text-[10px] font-black uppercase" :style="{ color: colors[selected.status] }">
                <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: colors[selected.status] }"></span>{{ statusLabel(selected.status) }}
              </span>
            </div>
            <button type="button" class="text-slate-400 hover:text-white text-[11px] font-bold cursor-pointer" @click="selected = null">✕ Tutup</button>
          </div>
          <p class="text-slate-300 leading-relaxed">
            <template v-if="isNational">{{ selected.reason }}</template>
            <template v-else>{{ selected.issues_count ?? 0 }} laporan aktif<span v-if="selected.priority_count"> ({{ selected.priority_count }} prioritas)</span> dari {{ selected.total_issues ?? 0 }} laporan. {{ selected.summary }}</template>
          </p>
          <div v-if="newsCount(selected)" class="space-y-1">
            <div class="flex h-2 rounded-full overflow-hidden bg-slate-800">
              <div class="bg-emerald-500" :style="{ width: ((selected.positif ?? 0) / newsCount(selected) * 100) + '%' }"></div>
              <div class="bg-slate-500" :style="{ width: (isNational ? (selected.netral ?? 0) : Math.max(0, newsCount(selected) - (selected.media_negative_7d ?? 0))) / newsCount(selected) * 100 + '%' }"></div>
              <div class="bg-rose-500" :style="{ width: ((isNational ? selected.negatif : selected.media_negative_7d) ?? 0) / newsCount(selected) * 100 + '%' }"></div>
            </div>
            <p class="text-[11px] text-slate-400">{{ newsCount(selected) }} berita {{ map.days ?? 7 }} hari • <span class="text-rose-300 font-bold">{{ (isNational ? selected.negatif : selected.media_negative_7d) ?? 0 }} negatif</span><span v-if="isNational"> • <span class="text-emerald-300 font-bold">{{ selected.positif }} positif</span></span></p>
          </div>

          <div>
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1.5">Berita terbaru</p>
            <ul v-if="selected.articles?.length" class="space-y-2">
              <li v-for="a in selected.articles" :key="a.url" class="flex gap-2">
                <span class="mt-1.5 w-2 h-2 rounded-full shrink-0" :class="sentimentDot(a.sentiment)" :title="`Sentimen: ${a.sentiment}`"></span>
                <span class="min-w-0">
                  <a :href="a.url" target="_blank" rel="noopener noreferrer" class="font-bold text-slate-100 hover:text-amber-400 leading-snug">{{ a.title }}</a>
                  <span class="block text-[10px] text-slate-500">{{ a.source }} • {{ a.time }}</span>
                </span>
              </li>
            </ul>
            <p v-else class="text-slate-500">Belum ada berita yang menyebut wilayah ini dalam {{ map.days ?? 7 }} hari.</p>
          </div>

          <div class="flex flex-wrap gap-2 pt-1">
            <Link :href="radarHref(selected)" class="px-3 py-1.5 rounded-lg bg-amber-400 text-slate-950 font-black hover:bg-amber-300">Lihat semua di Radar Media Online →</Link>
            <Link v-if="!isNational && selected.code" :href="`/issues?polres=${encodeURIComponent(selected.code)}`" class="px-3 py-1.5 rounded-lg bg-slate-800 text-slate-200 font-bold hover:bg-slate-700">Laporan Polres</Link>
          </div>
          <p v-if="isNational" class="text-[10px] text-slate-500">Radar mencari nama provinsi; peta juga menghitung nama kota utama, sehingga jumlahnya bisa berbeda.</p>
        </template>

        <template v-else>
          <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Wilayah paling disorot</p>
          <p v-if="!spotlight.length" class="text-slate-400">Belum ada {{ isNational ? 'berita yang menyebut wilayah' : 'laporan atau berita wilayah' }} dalam {{ map.days ?? 7 }} hari terakhir.</p>
          <button v-for="p in spotlight" :key="p.id" type="button" class="w-full text-left rounded-xl bg-slate-950/60 border border-slate-800 hover:border-amber-400/60 p-3 space-y-1 cursor-pointer" @click="select(p, true)">
            <span class="flex items-center justify-between gap-2">
              <b class="text-white">{{ p.short_name || p.name }}</b>
              <span class="text-[10px] font-black uppercase" :style="{ color: colors[p.status] }">{{ statusLabel(p.status) }}</span>
            </span>
            <span class="block text-[11px] text-slate-400">
              {{ newsCount(p) }} berita<template v-if="isNational"> • {{ p.negatif }} negatif</template><template v-else> • {{ p.issues_count ?? 0 }} laporan aktif</template>
            </span>
            <span v-if="p.articles?.[0]" class="block text-[11px] text-slate-300 line-clamp-2">{{ p.articles[0].title }}</span>
          </button>
          <details class="pt-1">
            <summary class="cursor-pointer font-bold text-slate-300">Definisi status</summary>
            <ul class="mt-2 space-y-1 text-[11px] text-slate-400">
              <li v-for="(def, status) in map.definitions || {}" :key="status"><b :style="{ color: colors[status] }">{{ statusLabel(status) }}</b>: {{ def }}</li>
            </ul>
          </details>
          <details>
            <summary class="cursor-pointer font-bold text-slate-300">Semua wilayah ({{ regions.length }})</summary>
            <div class="grid grid-cols-1 gap-1 mt-2">
              <button v-for="p in regions" :key="p.id" type="button" class="text-left rounded-lg bg-slate-950/60 border border-slate-800 px-2.5 py-1.5 text-slate-200 hover:border-slate-600 cursor-pointer" @click="select(p, true)">
                <span class="inline-block w-2 h-2 rounded-full mr-1.5" :style="{ backgroundColor: colors[p.status] }"></span>{{ p.short_name || p.name }} <span class="text-slate-500">· {{ newsCount(p) }} berita</span>
              </button>
            </div>
          </details>
        </template>
      </aside>
    </div>
  </section>
</template>

<style>
.simod-map { background: #e2e8f0; z-index: 0; }
.simod-map .leaflet-control-attribution { font-size: 10px; }
.simod-map .leaflet-tooltip { color: #0f172a; font-weight: 600; }
</style>
