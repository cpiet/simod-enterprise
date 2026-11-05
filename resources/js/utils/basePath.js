/**
 * Dukungan aplikasi di subfolder (mis. https://domain/simod-v2/). Nilai berasal dari server
 * (`request()->getBasePath()`, kosong bila aplikasi berada di akar domain), sehingga kode lain
 * tetap memakai alamat absolut seperti `/issues` dan awalan subfolder ditambahkan di satu tempat.
 */
export const BASE = (typeof window !== 'undefined' && window.__SIMOD_BASE__ ? String(window.__SIMOD_BASE__) : '').replace(/\/+$/, '');

const hasBase = (path) => path === BASE || path.startsWith(BASE + '/');

/** Tambahkan awalan subfolder pada path internal (diawali "/", bukan "//"); URL lain dibiarkan. */
export const withBase = (url) => {
  if (!BASE || typeof url !== 'string' || !url.startsWith('/') || url.startsWith('//') || hasBase(url)) return url;
  return BASE + url;
};

/** Buang awalan subfolder dari path (untuk membandingkan dengan alamat menu yang tersimpan tanpa awalan). */
export const stripBase = (path) => {
  if (!BASE || typeof path !== 'string' || !hasBase(path)) return path;
  return path.slice(BASE.length) || '/';
};
