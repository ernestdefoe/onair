import PollingTransport from './PollingTransport';
import RealtimeTransport from './RealtimeTransport';

/**
 * Pick the best presence transport: realtime push when flarum/realtime is
 * available, polling otherwise. Both expose the same interface, so the rest of
 * the UI never branches on which is active.
 */
export default function setupPresence() {
  const transport = RealtimeTransport.available() ? new RealtimeTransport() : new PollingTransport();
  transport.start();
  return transport;
}
