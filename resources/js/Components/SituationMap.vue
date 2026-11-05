<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const props = defineProps({ national: { type: Array, default: () => [] }, regional: { type: Array, default: () => [] }, selectedCode: String });
const scope = ref(props.selectedCode === 'ALL' ? 'national' : 'regional');
const mapElement = ref(null);
const selected = ref(null);
const mapError = ref(false);
const pins = computed(() => scope.value === 'national' ? props.national : props.regional);
const located = computed(() => pins.value.filter(p => p.latitude !== null && p.latitude !== undefined && p.longitude !== null && p.longitude !== undefined && Number.isFinite(Number(p.latitude)) && Number.isFinite(Number(p.longitude)) && Math.abs(Number(p.latitude)) <= 90 && Math.abs(Number(p.longitude)) <= 180 && !(Number(p.latitude) === 0 && Number(p.longitude) === 0)));
const missing = computed(() => pins.value.length - located.value.length);
const colors = { ATENSI: '#e11d48', WASPADA: '#d97706', KONDUSIF: '#059669', 'BELUM ADA DATA': '#64748b' };
let map, markers;
function fitMap() {
  if (!map) return;
  if (scope.value === 'national') map.fitBounds([[-11.5, 94], [6.5, 141.5]], { padding: [14, 14] });
  else if (located.value.length === 1) map.setView([Number(located.value[0].latitude), Number(located.value[0].longitude)], 9);
  else if (located.value.length > 1) map.fitBounds(L.latLngBounds(located.value.map(p => [Number(p.latitude), Number(p.longitude)])), { padding: [35, 35], maxZoom: 9 });
  else map.fitBounds([[-11.5, 94], [6.5, 141.5]], { padding: [14, 14] });
}
function renderMarkers() {
  if (!map) return;
  markers.clearLayers();
  for (const p of located.value) {
    const marker = L.circleMarker([Number(p.latitude), Number(p.longitude)], { radius: 8, color: '#fff', weight: 2, fillColor: colors[p.status] || '#64748b', fillOpacity: 1 });
    marker.bindTooltip(document.createTextNode(p.short_name || p.name));
    marker.on('click', () => { selected.value = p; });
    marker.addTo(markers);
  }
}
onMounted(() => {
  map = L.map(mapElement.value, { scrollWheelZoom: false }).setView([-2, 118], 5);
  const tileUrl = import.meta.env.VITE_MAP_TILE_URL || 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
  const attribution = import.meta.env.VITE_MAP_ATTRIBUTION || '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';
  const tiles = L.tileLayer(tileUrl, { maxZoom: 19, attribution });
  tiles.on('tileerror', () => { mapError.value = true; });
  tiles.on('load', () => { mapError.value = false; });
  tiles.addTo(map);
  markers = L.layerGroup().addTo(map);
  renderMarkers();
  nextTick(() => { map.invalidateSize(); fitMap(); });
});
watch(scope, () => { selected.value = null; renderMarkers(); nextTick(fitMap); });
watch(() => [props.national, props.regional], () => { renderMarkers(); nextTick(fitMap); });
onUnmounted(() => { if (map) map.remove(); });
</script>

<template>
  <section class="lg:col-span-2 self-start rounded-3xl bg-[#0b1426] border border-slate-800 p-5 md:p-6 shadow-xl space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h2 class="text-base font-black text-white">Peta Situasi &amp; Sentimen</h2>
        <p class="text-xs text-slate-400 mt-1">Lokasi sentra dan Polres • Data SIMOD</p>
      </div>
      <div class="flex gap-2 flex-wrap">
        <button type="button" class="px-3 py-2 rounded-lg text-xs font-bold" :class="scope === 'national' ? 'bg-amber-400 text-slate-950' : 'bg-slate-800 text-slate-200'" @click="scope = 'national'">Nasional</button>
        <button type="button" class="px-3 py-2 rounded-lg text-xs font-bold" :class="scope === 'regional' ? 'bg-amber-400 text-slate-950' : 'bg-slate-800 text-slate-200'" @click="scope = 'regional'">Satwil</button>
        <button type="button" class="px-3 py-2 rounded-lg text-xs font-bold bg-slate-800 text-slate-200" @click="fitMap">Atur ulang peta</button>
      </div>
    </div>
    <p class="text-xs text-slate-400">{{ located.length }} lokasi ditampilkan<span v-if="missing"> • {{ missing }} lokasi belum memiliki koordinat</span>. Titik menunjukkan pusat wilayah, bukan lokasi kejadian.</p>
    <div class="relative rounded-2xl overflow-hidden border border-slate-700">
      <div ref="mapElement" class="simod-map w-full h-[430px] md:h-[520px]" aria-label="Peta interaktif wilayah SIMOD"></div>
    </div>
    <p v-if="mapError" role="status" class="text-xs text-rose-400">Sebagian peta belum dapat dimuat. Periksa koneksi internet atau layanan peta; daftar wilayah tetap tersedia di bawah.</p>
    <div class="flex flex-wrap gap-x-4 gap-y-2 text-xs text-slate-300">
      <span v-for="(color, status) in colors" :key="status" class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: color }"></span>{{ status }}</span>
    </div>
    <p class="text-xs text-slate-400">Status merupakan indikator dari data yang tersedia, bukan penetapan resmi kondisi keamanan. Wilayah berdasarkan kata kunci adalah perkiraan.</p>
    <div v-if="selected" class="rounded-xl bg-slate-900 border border-slate-700 p-4 space-y-2" aria-live="polite">
      <div class="flex flex-wrap justify-between gap-2"><strong class="text-white text-sm">{{ selected.name }}</strong><span class="text-xs font-bold text-slate-300">{{ selected.status }}</span></div>
      <p class="text-xs text-slate-300">{{ selected.region || selected.tag_desc }} <span v-if="selected.basis">• {{ selected.basis }}</span></p>
      <p v-if="scope === 'national'" class="text-xs text-slate-300">{{ selected.articles_count }} berita • {{ selected.issues_count }} isu aktif • Sentimen positif: {{ selected.score ?? 'Belum ada data' }}</p>
      <p v-else class="text-xs text-slate-300">{{ selected.issues_count }} isu aktif • {{ selected.total_issues }} laporan • Laporan bersentimen positif: {{ selected.score ?? 'Belum ada laporan' }}</p>
      <p class="text-xs text-slate-300">{{ selected.latest_headline || selected.summary || 'Belum ada berita.' }}</p>
      <p v-if="selected.latest_source" class="text-xs text-slate-400">{{ selected.latest_source }}<span v-if="selected.pub_date"> • {{ selected.pub_date }}</span></p>
      <Link v-if="scope === 'regional'" :href="`/issues?polres=${encodeURIComponent(selected.code)}`" class="inline-block text-xs text-amber-400 font-bold">Lihat laporan Polres →</Link>
      <Link v-else href="/media-intelligence" class="inline-block text-xs text-amber-400 font-bold">Buka Radar Media Online →</Link>
    </div>
    <details class="text-xs text-slate-300">
      <summary class="cursor-pointer font-bold">Daftar wilayah ({{ pins.length }})</summary>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-3">
        <button v-for="p in pins" :key="p.id" type="button" class="text-left rounded-lg bg-slate-900 border border-slate-700 p-2.5 text-slate-200" @click="selected = p; if (p.latitude != null && p.longitude != null && map) map.setView([Number(p.latitude), Number(p.longitude)], 8)"><strong>{{ p.short_name || p.name }}</strong> · {{ p.status }}</button>
      </div>
    </details>
  </section>
</template>

<style>
.simod-map { background: #e2e8f0; z-index: 0; }
.simod-map .leaflet-control-attribution { font-size: 10px; }
.simod-map .leaflet-tooltip { color: #0f172a; }
</style>
