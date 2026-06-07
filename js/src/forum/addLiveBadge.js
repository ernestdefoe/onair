import app from 'flarum/forum/app';
import { override } from 'flarum/common/extend';
import Avatar from 'flarum/common/components/Avatar';

/**
 * THE cross-cutting feature: a LIVE badge beside a streamer's avatar everywhere
 * it renders. We override the shared `Avatar` component once (it is registered
 * on the main bundle via reg.add, so it is safe to extend at init time) instead
 * of editing every call site.
 *
 * We wrap the original avatar vnode rather than mutating its children, because
 * uploaded avatars render as <img> (which can't hold children). The wrapper
 * inherits the original vnode's key so we never mix keyed/unkeyed siblings in
 * lists (which would throw Mithril's "fragments must either all have keys" error).
 */
export default function addLiveBadge() {
  override(Avatar.prototype, 'view', function (original, vnode) {
    const out = original(vnode);
    const user = this.attrs && this.attrs.user;

    if (!out || !user || !app.onair || !app.onair.isLive(user)) {
      return out;
    }

    return m(
      'span.OnAir-avatarWrap',
      { key: out.key },
      out,
      m('span.OnAir-liveBadge', { title: app.translator.trans('onair.lib.live') }, app.translator.trans('onair.lib.live'))
    );
  });
}
