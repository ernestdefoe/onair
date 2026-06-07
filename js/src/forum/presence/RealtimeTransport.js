import app from 'flarum/forum/app';
import BaseTransport from './BaseTransport';

/**
 * Used when flarum/realtime is installed. The actual push subscription lives in
 * extendRealtime.js (the official RealtimeExtend binding), which calls
 * presence.refresh() when an `onairPresence` event arrives. This transport just
 * takes the initial snapshot and keeps a slow safety poll in case a websocket
 * event is ever missed (reconnects, dropped frames).
 */
export default class RealtimeTransport extends BaseTransport {
  static available() {
    return (
      typeof flarum !== 'undefined' &&
      flarum.extensions &&
      !!flarum.extensions['flarum-realtime']
    );
  }

  start() {
    this.refresh(); // initial snapshot
    // Safety net only — push events do the real-time work.
    this.timer = setInterval(() => {
      if (typeof document !== 'undefined' && document.hidden) return;
      this.refresh();
    }, 120000);
  }

  stop() {
    clearInterval(this.timer);
  }

  get name() {
    return 'realtime';
  }
}
