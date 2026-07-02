import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import Model from 'flarum/common/Model';
import User from 'flarum/common/models/User';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import SessionDropdown from 'flarum/forum/components/SessionDropdown';
import Button from 'flarum/common/components/Button';
import LinkButton from 'flarum/common/components/LinkButton';

import Stream from './common/models/Stream';
import addLiveBadge from './forum/addLiveBadge';
import registerProviders from './forum/providers';
import setupPresence from './forum/presence';
import LiveNowWidget from './forum/components/LiveNowWidget';
import LiveStreamsWidget from './forum/components/LiveStreamsWidget';
import GoLiveModal from './forum/components/GoLiveModal';
import StreamPage from './forum/components/StreamPage';
import LiveDirectoryPage from './forum/components/LiveDirectoryPage';
import extendRealtime from './forum/extendRealtime';

app.initializers.add('ernestdefoe-onair', () => {
  // Register the Stream model so app.store.createRecord('onair-streams') works.
  app.store.models['onair-streams'] = Stream;

  // Extend the core User model with the OnAir state.
  User.prototype.isLive = Model.attribute('isLive');
  User.prototype.liveStream = function () {
    return Model.hasOne('liveStream').call(this);
  };

  // Shared runtime namespace.
  app.onair = {
    liveStreams: [],
    liveUserIds: new Set(),
    providers: {},
    presence: null,
    isLive(user) {
      if (!user) return false;
      const id = user.id && String(user.id());
      return (
        (id && this.liveUserIds.has(id)) ||
        (typeof user.isLive === 'function' && !!user.isLive())
      );
    },
  };

  registerProviders();
  addLiveBadge();

  // Subscribe to realtime presence pushes when flarum/realtime is enabled.
  // (Guard before touching the module — the ext: import is undefined otherwise.)
  if ('flarum-realtime' in flarum.extensions) {
    extendRealtime();
  }

  app.routes['ernestdefoe-onair.index'] = { path: '/onair', component: LiveDirectoryPage };
  app.routes['ernestdefoe-onair.stream'] = { path: '/onair/:id', component: StreamPage };

  // "Go Live" entry in the session (avatar) dropdown — gated by permission.
  extend(SessionDropdown.prototype, 'items', function (items) {
    if (!app.session.user || !app.data.onairCanBroadcast) return;
    items.add(
      'onair-go-live',
      m(
        Button,
        {
          icon: 'fa-solid fa-tower-broadcast',
          onclick: () => app.modal.show(GoLiveModal),
        },
        app.translator.trans('onair.forum.go_live.button')
      ),
      10
    );
  });

  // "Live" nav link + Live Now widget in the index sidebar.
  extend(IndexSidebar.prototype, 'navItems', function (items) {
    items.add(
      'onair-live',
      m(
        LinkButton,
        { href: app.route('ernestdefoe-onair.index'), icon: 'fa-solid fa-tower-broadcast' },
        app.translator.trans('onair.forum.nav.live')
      ),
      80
    );
    items.add('onair-live-now', m(LiveNowWidget), 4);
  });

  // Bespoke integration: a "Live streams" widget with inline muted previews
  // (YouTube/Twitch iframes; OnAir+'s hls.js player for rtmp). Queue-based
  // registration — Bespoke drains it whenever it renders, so load order never
  // matters and this line is inert when Bespoke isn't installed. Labels are
  // full translation keys resolved from OUR locale by Bespoke's inspector.
  (window.BespokeWidgetQueue = window.BespokeWidgetQueue || []).push({
    type: 'onair-live',
    label: 'onair.forum.widget.name',
    icon: '📺',
    zones: ['above-list', 'sidebar', 'below-list', 'footer'],
    schema: [
      { key: 'title', type: 'text', label: 'onair.forum.widget.title_label', default: 'Live streams' },
      { key: 'count', type: 'number', label: 'onair.forum.widget.count_label', default: 4 },
      { key: 'preview', type: 'toggle', label: 'onair.forum.widget.preview_label', default: true },
    ],
    component: LiveStreamsWidget,
  });

  // Start presence AFTER boot. `app.forum` is only populated once boot
  // finishes, so starting synchronously inside the initializer throws
  // ("undefined is not an object (evaluating 'app.forum.attribute')").
  const startPresence = () => {
    if (!app.forum) {
      setTimeout(startPresence, 0);
      return;
    }
    app.onair.presence = setupPresence();
  };
  setTimeout(startPresence, 0);
});
