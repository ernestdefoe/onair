import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Avatar from 'flarum/common/components/Avatar';
import Link from 'flarum/common/components/Link';
import Icon from 'flarum/common/components/Icon';
import Button from 'flarum/common/components/Button';
import extractText from 'flarum/common/utils/extractText';
import StreamViewer from './StreamViewer';

/** Full-page viewer at /onair/:id. */
export default class StreamPage extends Page {
  oninit(vnode) {
    super.oninit(vnode);
    this.stream = null;
    this.loading = true;
    this.ending = false;
    this.deleting = false;

    const id = m.route.param('id');
    app.store
      .find('onair-streams', id, { include: 'user' })
      .then((stream) => {
        this.stream = stream;
        this.loading = false;
        m.redraw();
      })
      .catch(() => {
        this.loading = false;
        m.redraw();
      });
  }

  view() {
    return m('.OnAir-streamPage', m('.container', this.content()));
  }

  content() {
    if (this.loading) return m(LoadingIndicator);
    if (!this.stream) return m('.OnAir-streamPage-empty', app.translator.trans('onair.forum.stream.not_found'));

    const stream = this.stream;
    const user = stream.user();
    const canEdit = !!stream.canEdit();
    const ended = stream.status() !== 'live';

    return [
      m(StreamViewer, { stream }),
      m('.OnAir-streamPage-meta', [
        user ? m(Avatar, { user }) : null,
        m('.OnAir-streamPage-info', [
          m('h2.OnAir-streamPage-title', stream.title() || app.translator.trans('onair.forum.stream.untitled')),
          user
            ? m('.OnAir-streamPage-by', [
                app.translator.trans('onair.forum.stream.by'),
                ' ',
                m(Link, { href: app.route('user', { username: user.slug() }) }, user.displayName()),
              ])
            : null,
        ]),
        m('.OnAir-streamPage-views', [Icon.component({ name: 'fa-solid fa-eye' }), ' ', String(stream.viewerCount() || 0)]),
        canEdit && !ended
          ? m(
              Button,
              { className: 'Button OnAir-endButton', icon: 'fa-solid fa-stop', loading: this.ending, onclick: () => this.end() },
              app.translator.trans('onair.forum.stream.end')
            )
          : null,
        canEdit
          ? m(
              Button,
              { className: 'Button OnAir-deleteButton', icon: 'fa-solid fa-trash', loading: this.deleting, onclick: () => this.remove() },
              app.translator.trans('onair.forum.stream.delete')
            )
          : null,
      ]),
      ended ? m('.OnAir-streamPage-endedNote', app.translator.trans('onair.forum.stream.has_ended')) : null,
    ];
  }

  remove() {
    if (!confirm(extractText(app.translator.trans('onair.forum.stream.delete_confirm')))) return;
    this.deleting = true;
    this.stream
      .delete()
      .then(() => {
        if (app.onair && app.onair.presence) app.onair.presence.refresh();
        m.route.set(app.route('ernestdefoe-onair.index'));
      })
      .catch(() => {
        app.alerts.show({ type: 'error' }, app.translator.trans('onair.forum.stream.delete_failed'));
      })
      .then(() => {
        this.deleting = false;
        m.redraw();
      });
  }

  end() {
    this.ending = true;
    this.stream
      .save({ status: 'ended' })
      .then(() => {
        if (app.onair && app.onair.presence) app.onair.presence.refresh();
        m.route.set(app.route('ernestdefoe-onair.index'));
      })
      .catch(() => {
        // Surface the failure instead of swallowing it — the stream is still
        // live and the member needs to know the "end" didn't take.
        app.alerts.show({ type: 'error' }, app.translator.trans('onair.forum.stream.end_failed'));
      })
      .then(() => {
        this.ending = false;
        m.redraw();
      });
  }
}
