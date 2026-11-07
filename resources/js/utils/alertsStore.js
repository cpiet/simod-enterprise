import { reactive } from 'vue';

// Status peringatan social listening untuk lonceng header, bertahan selama sesi SPA (tingkat modul).
// Layout dipasang ulang di setiap navigasi Inertia; tanpa cache ini setiap pindah halaman memicu 1 permintaan
// dan cepat mengenai batas laju (429). Diperbarui paling sering tiap TTL_MS, atau paksa saat lonceng dibuka.
const TTL_MS = 60000;
export const alertsState = reactive({ unread: 0, items: [], loadedAt: 0 });
let inflight = null;

export async function loadAlerts(force = false) {
  if (!window.axios) return;
  if (!force && Date.now() - alertsState.loadedAt < TTL_MS) return;
  if (inflight) return inflight;
  inflight = window.axios.get('/social-intelligence/alerts')
    .then(({ data }) => {
      alertsState.unread = data.unread ?? 0;
      alertsState.items = data.items ?? [];
      alertsState.loadedAt = Date.now();
    })
    .catch(() => { alertsState.loadedAt = Date.now(); /* jangan ulangi terus saat galat */ })
    .finally(() => { inflight = null; });
  return inflight;
}

export async function markAlertsSeen() {
  try {
    await window.axios.post('/social-intelligence/alerts/seen');
    alertsState.unread = 0;
    alertsState.items = alertsState.items.map((a) => ({ ...a, unread: false }));
  } catch { /* abaikan */ }
}
