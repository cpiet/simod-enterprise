import { Marked } from 'marked';

// Parser markdown terisolasi dan aman untuk teks AI/data: HTML mentah dilepas, gambar dibuang,
// hanya tautan http(s) yang aktif (dibuka di tab baru). Dipakai SADEWA & Ringkasan Komentar.
export const escapeHtml = (t) => String(t).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

export const safeMarked = new Marked({
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

export const renderSafeMarkdown = (text) => (text ? safeMarked.parse(String(text)) : '');
