export default {
  key: 'youtube',
  label: 'YouTube',
  icon: 'fa-brands fa-youtube',
  match: (url) => /(youtube\.com|youtu\.be)/i.test(url),

  // The server already resolved an /embed/ URL. Just ensure it autoplays.
  embedSrc(stream) {
    const base = stream.embedUrl && stream.embedUrl();
    if (!base) return null;
    return base.includes('autoplay') ? base : base + (base.includes('?') ? '&' : '?') + 'autoplay=1';
  },
};
