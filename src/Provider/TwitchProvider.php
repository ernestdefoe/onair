<?php

namespace Ernestdefoe\OnAir\Provider;

class TwitchProvider implements StreamProvider
{
    public function key(): string
    {
        return 'twitch';
    }

    public function label(): string
    {
        return 'Twitch';
    }

    public function matches(string $url): bool
    {
        return (bool) preg_match('~twitch\.tv~i', $url);
    }

    /**
     * NOTE: Twitch's embed iframe requires a `parent=<host>` query param that
     * must match the page's hostname. PHP can't reliably know the public host
     * at create time, so we store the channel/video id + base player URL and the
     * frontend provider (js/src/forum/providers/twitch.js) appends the correct
     * `parent=` from window.location.hostname when it renders the iframe.
     */
    public function normalize(string $url): array
    {
        $url = trim($url);
        $externalId = null;
        $embed = null;

        if (preg_match('~twitch\.tv/videos/(\d+)~', $url, $m)) {
            $externalId = 'v' . $m[1];
            $embed = 'https://player.twitch.tv/?video=' . $m[1];
        } elseif (preg_match('~twitch\.tv/([A-Za-z0-9_]{3,30})~', $url, $m)) {
            $externalId = $m[1];
            $embed = 'https://player.twitch.tv/?channel=' . $m[1];
        }

        return [
            'external_id' => $externalId,
            'embed_url'   => $embed,
            'channel_url' => $url,
        ];
    }
}
