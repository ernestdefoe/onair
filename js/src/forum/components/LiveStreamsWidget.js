import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Link from 'flarum/common/components/Link';
import Icon from 'flarum/common/components/Icon';

/**
 * "Live streams" widget for Bespoke (registered via window.BespokeWidgetQueue
 * in forum.js — only ever rendered on forums that also run Bespoke).
 *
 * Lists everyone live right now with an inline PREVIEW of each stream:
 *  - YouTube / Twitch → their embed iframe, muted + autoplaying
 *  - rtmp (OnAir+ self-hosted) → OnAir+'s hls.js player via the module
 *    registry, so Lite never bundles hls.js itself
 *  - anything else → avatar cover with a play badge
 *
 * Data comes from the presence snapshot (app.onair.liveStreams) that the
 * active transport already maintains — realtime push or polling — so the
 * widget adds zero extra requests and updates the moment someone goes live.
 * A transparent overlay link covers each preview: previews are ambient,
 * clicking anywhere opens the full stream page.
 */
export default class LiveStreamsWidget extends Component {
  view(vnode) {
    const s = vnode.attrs.settings || {};
    const max = Math.max(1, Number(s.count) || 4);
    const showPreview = s.preview !== false;
    const t = (k, p) => app.translator.trans('onair.forum.widget.' + k, p);

    const streams = ((app.onair && app.onair.liveStreams) || []).slice(0, max);

    return m('.Bespoke-w.OnAir-bw', [
      s.title
        ? m('h4', [s.title, streams.length ? m('span.OnAir-bw-count', streams.length) : null])
        : null,
      !streams.length
        ? m('p.Bespoke-w-empty', t('empty'))
        : m(
            '.OnAir-bw-list',
            streams.map((st) => this.card(st, showPreview))
          ),
    ]);
  }

  card(st, showPreview) {
    const href = app.route('ernestdefoe-onair.stream', { id: st.id });

    return m('.OnAir-bw-card', { key: st.id }, [
      showPreview
        ? m('.OnAir-bw-preview', [
            this.preview(st) || this.cover(st),
            // Transparent click-through to the stream page; keeps iframe/video
            // chrome unreachable, which is what we want for a preview.
            m(Link, { className: 'OnAir-bw-open', href, 'aria-label': st.displayName || st.username }),
            m('span.OnAir-livePill', [m('span.OnAir-livePill-dot'), app.translator.trans('onair.lib.live_now')]),
          ])
        : null,
      m(Link, { className: 'OnAir-bw-row', href }, [
        st.avatarUrl
          ? m('img.OnAir-bw-avatar', { src: st.avatarUrl, alt: '' })
          : m('span.OnAir-bw-avatar.OnAir-bw-avatar--ph', (st.displayName || st.username || '?').charAt(0).toUpperCase()),
        m('.OnAir-bw-meta', [
          m('.OnAir-bw-who', st.displayName || st.username),
          st.title ? m('.OnAir-bw-what', st.title) : null,
        ]),
        m('.OnAir-bw-views', [Icon.component({ name: 'fa-solid fa-eye' }), ' ', String(st.viewerCount || 0)]),
      ]),
    ]);
  }

  /** Provider-appropriate muted inline preview, or null for the cover fallback. */
  preview(st) {
    const url = st.embedUrl;
    if (!url) return null;
    const sep = url.includes('?') ? '&' : '?';

    if (st.provider === 'youtube') {
      return this.frame(`${url}${sep}autoplay=1&mute=1&controls=0&playsinline=1`);
    }

    if (st.provider === 'twitch') {
      // Twitch requires parent=<hostname>, which only the browser knows.
      return this.frame(`${url}${sep}parent=${window.location.hostname}&autoplay=true&muted=true`);
    }

    if (st.provider === 'rtmp') {
      // OnAir+'s hls.js player, looked up at render time so Lite works with or
      // without Plus installed (and regardless of bundle order).
      const mod =
        typeof flarum !== 'undefined' && flarum.reg && typeof flarum.reg.get === 'function'
          ? flarum.reg.get('ernestdefoe-onair-plus', 'forum/components/HlsPlayer')
          : null;
      const HlsPlayer = mod && (mod.default || mod);
      if (HlsPlayer) return m('.OnAir-bw-hls', m(HlsPlayer, { src: url }));
    }

    return null;
  }

  frame(src) {
    return m('iframe.OnAir-bw-frame', {
      src,
      allow: 'autoplay; encrypted-media; picture-in-picture',
      frameborder: '0',
      scrolling: 'no',
      tabindex: -1,
      loading: 'lazy',
    });
  }

  cover(st) {
    return m('.OnAir-bw-coverPh', [
      st.avatarUrl ? m('img', { src: st.avatarUrl, alt: '' }) : null,
      m('span.OnAir-bw-play', Icon.component({ name: 'fa-solid fa-play' })),
    ]);
  }
}
