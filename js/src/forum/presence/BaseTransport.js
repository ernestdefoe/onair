import app from 'flarum/forum/app';

/**
 * Shared presence logic. A transport's job is to keep `app.onair.liveStreams`
 * and `app.onair.liveUserIds` current; the LIVE badge and Live Now widget read
 * from there. Subclasses decide HOW updates arrive (push vs poll).
 */
export default class BaseTransport {
  apply(streams) {
    const onair = app.onair;
    onair.liveStreams = streams || [];
    onair.liveUserIds = new Set((streams || []).map((s) => String(s.userId)));
    m.redraw();
  }

  refresh() {
    // Defensive: never throw if called before the forum payload is ready.
    if (!app.forum) return Promise.resolve();

    return app
      .request({ method: 'GET', url: app.forum.attribute('apiUrl') + '/onair/live' })
      .then((res) => this.apply((res && res.data) || []))
      .catch(() => {
        // app.request has already shown the error alert (401, 500, network)
        // and logged it; this only stops an unhandled rejection. Keep the
        // last known list rather than blanking every LIVE badge.
      });
  }

  start() {}
  stop() {}
  get name() {
    return 'base';
  }
}
