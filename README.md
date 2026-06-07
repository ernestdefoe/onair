# OnAir — Live Streaming for Flarum 2

[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Bring live video into your community. Members go live from **YouTube** or **Twitch**,
and a **LIVE NOW** badge follows their avatar everywhere it appears — posts, the
discussion list, mentions, the header, the sidebar.

> This is the free **Lite** edition. The paid **OnAir Pro** add-on adds a built-in
> RTMP server (self-hosted *or* managed ingest), HLS playback, multistream, VOD,
> live chat overlay, go-live notifications, and scheduled streams. See `ROADMAP.md`.

A no-backend concept render of the whole product lives in [`preview/index.html`](preview/index.html).

## Features (Lite)

- 🔴 **LIVE badge on every avatar** — one `Avatar` override lights up a streamer everywhere.
- ▶️ **YouTube + Twitch embed viewer** — paste a channel/video URL and go live.
- 📡 **Presence with graceful fallback** — uses `flarum/realtime` for instant badge
  updates when installed, and transparently falls back to lightweight polling
  (`GET /api/onair/live`) when it isn't.
- 🟥 **Live Now sidebar widget** — see who's streaming at a glance.
- 🔐 **Permissions** — `onair.broadcast` (go live) and `onair.manage` (end others' streams).

## Install

```bash
composer require ernestdefoe/onair
```

Then enable **OnAir** in the admin panel. Optionally install `flarum/realtime` for
instant (push) LIVE badges — OnAir detects it automatically.

## Development

```bash
cd js
npm install
npm run build      # or: npm run dev  (watch)
```

## Architecture notes

- **LIVE badge:** overrides the shared `Avatar` component (main-bundle, safe to
  extend at init) and reads a serializer-supplied `user.isLive()`.
- **Presence transport:** `app.onair.presence` picks `RealtimeTransport` when
  `flarum/realtime` is detected, else `PollingTransport`; both expose the same API.
- **Providers:** `app.onair.providers` (JS) + the `StreamProvider` interface (PHP) are
  the extension points OnAir Pro plugs its RTMP/HLS provider into.

## License

MIT © Ernestdefoe
