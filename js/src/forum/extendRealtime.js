import app from 'flarum/forum/app';
// Resolved at runtime via flarum.reg (the `ext:` prefix externalises it). Only
// imported behind a `'flarum-realtime' in flarum.extensions` guard at the call
// site, so this is harmless when realtime isn't installed.
import RealtimeExtend from 'ext:flarum/realtime/forum/extenders/Realtime';

/**
 * Bind the `onairPresence` event (pushed by the PHP broadcastModelEvent) on
 * both the public and per-user channels. The payload is ignored — we just
 * re-fetch /api/onair/live, the authoritative presence source. Bound on both
 * channels because discussion-less streams are delivered per connected user.
 */
export default function extendRealtime() {
  new RealtimeExtend()
    .onBothChannelsEvent('onairPresence', () => {
      if (app.onair && app.onair.presence) app.onair.presence.refresh();
    })
    .extend(app, { name: 'ernestdefoe-onair', exports: {} });
}
