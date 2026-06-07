import app from 'flarum/forum/app';
import FormModal from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';
import Icon from 'flarum/common/components/Icon';

/**
 * Lite "Go Live" form: paste a YouTube/Twitch URL + title, create an
 * onair-streams record. Extends FormModal (NOT Modal) so the submit button
 * actually wires to onsubmit.
 */
export default class GoLiveModal extends FormModal {
  oninit(vnode) {
    super.oninit(vnode);
    this.provider = app.forum.attribute('onairDefaultProvider') || 'twitch';
    this.url = '';
    this.streamTitle = '';
    this.loading = false;
  }

  className() {
    return 'OnAir-goLiveModal Modal--small';
  }

  title() {
    return app.translator.trans('onair.forum.go_live.title');
  }

  content() {
    const providers = Object.values((app.onair && app.onair.providers) || {});

    return m('.Modal-body', [
      m('.Form-group', [
        m('label', app.translator.trans('onair.forum.go_live.provider_label')),
        m(
          '.OnAir-providerSeg',
          providers.map((p) =>
            m(
              Button,
              {
                type: 'button',
                className: 'Button OnAir-providerSeg-btn' + (this.provider === p.key ? ' is-active' : ''),
                icon: p.icon,
                onclick: () => (this.provider = p.key),
              },
              p.label
            )
          )
        ),
      ]),

      m('.Form-group', [
        m('label', app.translator.trans('onair.forum.go_live.url_label')),
        m('input.FormControl', {
          value: this.url,
          placeholder: 'https://twitch.tv/your_channel',
          oninput: (e) => (this.url = e.target.value),
        }),
      ]),

      m('.Form-group', [
        m('label', app.translator.trans('onair.forum.go_live.title_label')),
        m('input.FormControl', {
          value: this.streamTitle,
          maxlength: 120,
          oninput: (e) => (this.streamTitle = e.target.value),
        }),
      ]),

      m('.Form-group', [
        m(
          Button,
          { type: 'submit', className: 'Button Button--primary OnAir-goLiveModal-submit', loading: this.loading, disabled: !this.url.trim() },
          [Icon.component({ name: 'fa-solid fa-tower-broadcast' }), ' ', app.translator.trans('onair.forum.go_live.submit')]
        ),
      ]),
    ]);
  }

  onsubmit(e) {
    e.preventDefault();
    this.loading = true;

    app.store
      .createRecord('onair-streams')
      .save({ channelUrl: this.url.trim(), title: this.streamTitle.trim() || null, provider: this.provider })
      .then(() => {
        app.alerts.show({ type: 'success' }, app.translator.trans('onair.forum.go_live.success'));
        if (app.onair && app.onair.presence) app.onair.presence.refresh();
        this.hide();
      })
      .catch((err) => {
        this.loading = false;
        m.redraw();
        throw err; // let Flarum surface validation errors on the fields
      });
  }
}
