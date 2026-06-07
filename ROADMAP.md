# OnAir — Live Streaming for Flarum 2

> Bring live video into the forum. Members go live from YouTube or Twitch (free),
> or stream straight into the community via a built‑in RTMP server (Pro). A
> **LIVE NOW** badge follows a streamer's avatar everywhere it appears.

Concept render: [`preview/index.html`](preview/index.html) — open in any browser.

---

## 1. Product shape — two packages

| Package | License | Packagist id | Depends on |
|---|---|---|---|
| **OnAir** (Lite) | Free / MIT | `ernestdefoe/onair` | core only |
| **OnAir+** | Proprietary | `ernestdefoe/onair-pro` | `ernestdefoe/onair` |

Pro is an **add‑on**, not a fork — same pattern as `bespoke` + `theme-toggle` and the
`page-builder` premium model. Lite ships everything below in *Core* + *Lite*; Pro
registers extra providers, the RTMP pipeline, and the advanced UI by extending Lite's
own extension points. This keeps the free repo fully open‑source and clean.

### Why an add‑on beats a license gate
- The free repo carries **zero** proprietary code (safe to publish/MIT).
- Pro can be sold/distributed separately and updated on its own cadence.
- Lite exposes a small, documented extender surface (`StreamProvider` interface,
  JS `app.onair.providers` registry) that Pro plugs into — same way third parties could.

---

## 2. The cross‑cutting feature: the LIVE badge (both editions)

The single most visible feature — a **LIVE NOW** indicator beside a streamer's avatar
**everywhere the avatar renders** (posts, discussion list, mentions, user card, header,
sidebar, online list).

**Flarum 2 implementation**
- `Avatar` is now a **component**, not the removed `avatar()` helper. It is registered
  on the **main bundle** (`flarum.reg.add`), so it is safe to extend at initializer time.
- Override `Avatar`'s view to wrap output in a `.OnAir-avatarWrap` and append a
  `.OnAir-liveBadge` when `attrs.user?.isLive?.()` is true. One override → badge appears
  in *every* call site for free (that's the whole point of editing the shared component).
- Add `isLive` (bool) + `liveStreamId` to the **User** API attributes via a serializer/
  `ApiResource` field on the backend; expose `User.prototype.isLive()` /
  `User.prototype.liveStream()` on the frontend model. Relation accessors must use
  `Model.hasOne('liveStream').call(this)` form (per the hasOne gotcha), not `(this)`.
- Badge has a subtle pulsing ring + "LIVE" wordmark; clicking it opens the stream.

---

## 3. Presence transport — realtime *with graceful fallback*

Not every install has `flarum/realtime` (Redis/WebSockets). OnAir detects capability and
picks the best transport automatically; the rest of the app talks to one interface.

```
app.onair.presence  →  PresenceTransport
                         ├─ RealtimeTransport   (preferred, if flarum/realtime present)
                         │    subscribes to the `onair.presence` channel; live/offline
                         │    events flip badges instantly with no polling.
                         └─ PollingTransport     (fallback, always available)
                              GET /api/onair/live every N s (default 30s, backoff when
                              tab hidden via the Page Visibility API); diff → emit events.
```

- **Capability check:** look for the realtime extension at boot
  (`flarum.extensions['flarum-realtime']` / a pushed socket handle). If present → Realtime;
  else → Polling. Same public API (`onLive`, `onOffline`, `currentlyLive()`), so UI code
  never branches.
- Backend publishes presence changes to the realtime channel **and** keeps the
  `GET /api/onair/live` endpoint authoritative, so both transports agree.
- Pro's RTMP server webhooks (`on_publish` / `on_publish_done`) flip state server‑side,
  which then fans out through whichever transport is active.

---

## 4. Feature matrix

### Core (in Lite, reused by Pro)
- `streams` table + `onair-streams` API resource; `Stream` model (owner, provider,
  status, title, external id/url, started_at, viewer_count).
- LIVE badge on `Avatar` (§2) + per‑user `isLive` attribute.
- **Live Now** sidebar widget + index filter ("Live now (n)").
- Stream **viewer page** / embeddable viewer card in a discussion.
- Presence transport with realtime↔polling fallback (§3).
- Admin: who‑can‑go‑live permission, max concurrent streams, default provider.

### Lite (free)
- **Providers: YouTube + Twitch** (embed only).
  - Member pastes a channel/video URL or connects a channel handle → OnAir resolves the
    embed (YouTube IFrame API; Twitch Embed — note the required `parent=` domain param).
  - Live status from the provider's **public** signal + a manual "I'm live" toggle.
- One active stream per user; basic title.
- In‑forum player with provider chrome.

### Pro (paid add‑on)
- **In‑forum RTMP ingest → HLS** playback with `hls.js`. PHP can't *be* the RTMP server
  (no persistent socket per‑request), but the server **is bundled/installable**. Two
  selectable modes in admin — badge, presence, viewer page and HLS player are identical
  either way; only the ingest source differs:
  - **Self‑hosted** — ship/auto‑download a single self‑contained server (MediaMTX / SRS /
    Node Media Server) + an `onair:rtmp-serve` console command, run under a supervisor
    (systemd / pm2 / **Docker**). Mirrors how `flarum/realtime` ships `realtime:serve`.
    Full control, no per‑stream cost; needs one persistent process + port 1935 open
    (fine on VPS/Docker, usually blocked on shared hosting). `on_publish`/
    `on_publish_done` webhooks flip live state (see §3). Fits the existing Redis‑in‑Docker
    dev setup as one more container.
  - **Managed ingest** — integrate a streaming API (Cloudflare Stream Live / Mux /
    api.video): admin pastes a key, OnAir requests a per‑user ingest URL + key and embeds
    the returned HLS. No local daemon or open ports → works on **any** host incl. shared
    hosting. Trade‑off: paid third party + egress cost.
- Per‑user **stream keys** + a creator dashboard (key, ingest URL, health, bitrate).
- **Multistream / restream** to YouTube + Twitch simultaneously.
- **VOD & clips** — auto‑record HLS, generate clips.
- **Live chat overlay** bound to the host discussion; live viewer counts.
- **Follower / go‑live notifications** (core `NotificationBlueprint`); "Notify me when X
  goes live."
- **Scheduled streams** + integration with `ernestdefoe/calendar`.
- Stretch: monetization hooks (sub‑gated streams), low‑latency LL‑HLS.

---

## 5. Flarum 2 best‑practice checklist (applies to both)
- `composer.json`: `"type": "flarum-extension"`, `^2.0` core, PHP 8.3+, author email
  `ernestdefoe@gmail.com`.
- Frontend imports use `flarum/...`; **never** `@flarum/core/...`. Pro→Lite imports use
  the `ext:` prefix (`import Stream from 'ext:ernestdefoe/onair/common/models/Stream'`)
  and `useExtensions: ['ernestdefoe/onair']` in webpack.
- `flarum-webpack-config@^3`; entry `js/forum.js` → `export * from './src/forum'`.
- Admin settings registered from **JS** via the common `Admin` extender (`.setting()` takes
  a function) — `Extend\Admin` has no `->setting()`.
- `ApiResource(...)->fields(Closure)` is called with **zero args** → resolve services with
  `resolve(...)` inside, never type‑hint the closure.
- Migrations: closures receive a schema `Builder`; FKs to `users.id` use
  `unsignedInteger`. Blueprint (if any search/filter) needs `getFromUser()`.
- Icons are **FA7** (`fa-solid fa-...`). Use the `Icon` **component**, not the removed
  `icon()` helper.
- Locale keys: `onair.forum.*`, `onair.admin.*`, shared labels under `onair.lib.*`.
- Ship `.github/workflows/publish-to-flarum.yml` + `draft-release.yml`; release via semver
  **tags** (push alone doesn't publish). Set `FLARUM_DISCUSSION_ID` before first release.
- Develop/test against the **default theme** on `flarum.test`.

---

## 6. Phased plan

- **Phase 0 — Concept (this).** Name, package split, presence design, roadmap + render. ✅
- **Phase 1 — Lite MVP.** `streams` table + API, `Stream` model, YouTube/Twitch embed
  viewer, LIVE badge on `Avatar`, manual go‑live toggle, Live Now widget. Polling transport.
- **Phase 2 — Lite polish.** Realtime transport + auto fallback, provider status polling,
  permissions + admin settings, locale, CI/publish workflows, README, first tagged release.
- **Phase 3 — Pro foundation.** Companion RTMP server (Node Media Server) + setup docs,
  stream keys, webhook → live‑state endpoint, HLS player (`hls.js`), creator dashboard.
- **Phase 4 — Pro advanced.** Multistream/restream, VOD + clips, live chat overlay, viewer
  counts, follower/go‑live notifications, scheduled streams + calendar tie‑in.
- **Phase 5 — Scale/monetization.** Sub‑gated streams, LL‑HLS, analytics, multi‑node RTMP.

---

## 7. Open questions / later
- Resolve YouTube/Twitch *live* status without per‑user OAuth (public endpoints vs API
  keys + quota) — may need an admin‑set API key for reliable auto‑detection in Lite.
- RTMP server packaging: ship a Docker compose alongside Pro, matching the existing
  Redis‑in‑Docker dev setup.
- Storage/egress for VOD (local disk vs S3) — Pro admin setting.
