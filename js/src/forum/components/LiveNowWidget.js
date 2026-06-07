import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Link from 'flarum/common/components/Link';
import Icon from 'flarum/common/components/Icon';

/**
 * Sidebar widget listing who is live right now. Reads the presence snapshot
 * maintained by the active transport (app.onair.liveStreams).
 */
export default class LiveNowWidget extends Component {
  view() {
    const streams = (app.onair && app.onair.liveStreams) || [];
    if (!streams.length) return null;

    return m('.OnAir-liveNow', [
      m('.OnAir-liveNow-header', [
        m('span.OnAir-livePill', [m('span.OnAir-livePill-dot'), app.translator.trans('onair.lib.live_now')]),
        m('span.OnAir-liveNow-count', streams.length),
      ]),
      m(
        'ul.OnAir-liveNow-list',
        streams.slice(0, 8).map((s) =>
          m(
            'li',
            m(
              Link,
              { className: 'OnAir-liveNow-row', href: app.route('ernestdefoe-onair.stream', { id: s.id }) },
              [
                s.avatarUrl
                  ? m('img.OnAir-liveNow-avatar', { src: s.avatarUrl, alt: '' })
                  : m('span.OnAir-liveNow-avatar.OnAir-liveNow-avatar--ph', (s.displayName || s.username || '?').charAt(0).toUpperCase()),
                m('.OnAir-liveNow-meta', [
                  m('.OnAir-liveNow-who', s.displayName || s.username),
                  s.title ? m('.OnAir-liveNow-what', s.title) : null,
                ]),
                m('.OnAir-liveNow-views', [Icon.component({ name: 'fa-solid fa-eye' }), ' ', String(s.viewerCount || 0)]),
              ]
            )
          )
        )
      ),
    ]);
  }
}
