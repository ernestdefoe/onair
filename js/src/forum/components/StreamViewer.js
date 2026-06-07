import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import { providerFor } from '../providers';

/**
 * Embeds a live stream. attrs: { stream: Stream }.
 * Pro swaps in an hls.js player for its RTMP provider via the same registry.
 */
export default class StreamViewer extends Component {
  view() {
    const stream = this.attrs.stream;
    if (!stream) return null;

    const provider = providerFor(stream);
    const src = provider && provider.embedSrc(stream);

    if (!src) {
      return m('.OnAir-viewer.OnAir-viewer--empty', app.translator.trans('onair.forum.viewer.unavailable'));
    }

    return m('.OnAir-viewer', [
      m('iframe.OnAir-viewer-frame', {
        src,
        allow: 'autoplay; fullscreen; encrypted-media; picture-in-picture',
        allowfullscreen: true,
        frameborder: '0',
        scrolling: 'no',
      }),
    ]);
  }
}
