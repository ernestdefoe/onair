import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Link from 'flarum/common/components/Link';
import Avatar from 'flarum/common/components/Avatar';
import Icon from 'flarum/common/components/Icon';
import Button from 'flarum/common/components/Button';
import GoLiveModal from './GoLiveModal';

/** /onair — directory of everyone currently live. */
export default class LiveDirectoryPage extends Page {
  oninit(vnode) {
    super.oninit(vnode);
    this.loading = true;
    this.streams = [];
    this.load();
  }

  load() {
    app.store
      .find('onair-streams', { include: 'user' })
      .then((res) => {
        this.streams = res || [];
        this.loading = false;
        m.redraw();
      })
      .catch(() => {
        this.loading = false;
        m.redraw();
      });
  }

  view() {
    const canBroadcast = app.session.user && app.data.onairCanBroadcast;

    return m('.OnAir-directory', m('.container', [
      m('.OnAir-directory-head', [
        m('h2.OnAir-directory-title', [
          Icon.component({ name: 'fa-solid fa-tower-broadcast' }),
          ' ',
          app.translator.trans('onair.forum.directory.title'),
        ]),
        canBroadcast
          ? m(
              Button,
              { className: 'Button Button--primary', icon: 'fa-solid fa-tower-broadcast', onclick: () => app.modal.show(GoLiveModal) },
              app.translator.trans('onair.forum.go_live.button')
            )
          : null,
      ]),
      this.content(),
    ]));
  }

  content() {
    if (this.loading) return m(LoadingIndicator);

    if (!this.streams.length) {
      return m('.OnAir-directory-empty', [
        Icon.component({ name: 'fa-regular fa-circle-dot' }),
        m('p', app.translator.trans('onair.forum.directory.empty')),
      ]);
    }

    return m(
      '.OnAir-directory-grid',
      this.streams.map((stream) => {
        const user = stream.user && stream.user();
        return m(Link, { className: 'OnAir-card', href: app.route('ernestdefoe-onair.stream', { id: stream.id() }) }, [
          m('.OnAir-card-thumb', [
            m('span.OnAir-livePill', [m('span.OnAir-livePill-dot'), app.translator.trans('onair.lib.live')]),
            m('span.OnAir-card-views', [Icon.component({ name: 'fa-solid fa-eye' }), ' ', String(stream.viewerCount() || 0)]),
          ]),
          m('.OnAir-card-body', [
            user ? m(Avatar, { user }) : null,
            m('.OnAir-card-meta', [
              m('.OnAir-card-title', stream.title() || app.translator.trans('onair.forum.stream.untitled')),
              user ? m('.OnAir-card-by', user.displayName()) : null,
            ]),
          ]),
        ]);
      })
    );
  }
}
