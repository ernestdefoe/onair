import app from 'flarum/forum/app';
import youtube from './youtube';
import twitch from './twitch';

/**
 * Frontend provider registry. Pro adds its RTMP/HLS provider to
 * `app.onair.providers` the same way.
 */
export default function registerProviders() {
  app.onair.providers[youtube.key] = youtube;
  app.onair.providers[twitch.key] = twitch;
}

/** Resolve the registered provider for a Stream model. */
export function providerFor(stream) {
  const key = stream && stream.provider && stream.provider();
  return (key && app.onair.providers[key]) || null;
}
