export default {
  key: 'twitch',
  label: 'Twitch',
  icon: 'fa-brands fa-twitch',
  match: (url) => /twitch\.tv/i.test(url),

  // Twitch's embed REQUIRES a `parent=<host>` matching the page hostname, which
  // only the browser knows — so we append it here rather than server-side.
  embedSrc(stream) {
    const base = stream.embedUrl && stream.embedUrl();
    if (!base) return null;
    const sep = base.includes('?') ? '&' : '?';
    return `${base}${sep}parent=${window.location.hostname}&autoplay=true`;
  },
};
