import app from 'flarum/forum/app';
import BaseTransport from './BaseTransport';

/**
 * Always-available fallback: poll GET /api/onair/live on an interval. Backs off
 * while the tab is hidden (Page Visibility API) to avoid pointless requests.
 */
export default class PollingTransport extends BaseTransport {
  start() {
    const secs = parseInt(app.forum && app.forum.attribute('onairPollInterval'), 10) || 30;
    this.refresh();
    this.timer = setInterval(() => {
      if (typeof document !== 'undefined' && document.hidden) return;
      this.refresh();
    }, secs * 1000);
  }

  stop() {
    clearInterval(this.timer);
  }

  get name() {
    return 'polling';
  }
}
