<?php

namespace Ernestdefoe\OnAir\Provider;

class YouTubeProvider implements StreamProvider
{
    public function key(): string
    {
        return 'youtube';
    }

    public function label(): string
    {
        return 'YouTube';
    }

    public function matches(string $url): bool
    {
        return (bool) preg_match('~(youtube\.com|youtu\.be)~i', $url);
    }

    public function normalize(string $url): array
    {
        $url = trim($url);
        $videoId = null;
        $channel = null;

        // youtu.be/<id>
        if (preg_match('~youtu\.be/([A-Za-z0-9_-]{6,})~', $url, $m)) {
            $videoId = $m[1];
        } elseif (preg_match('~[?&]v=([A-Za-z0-9_-]{6,})~', $url, $m)) {
            // youtube.com/watch?v=<id>
            $videoId = $m[1];
        } elseif (preg_match('~youtube\.com/live/([A-Za-z0-9_-]{6,})~', $url, $m)) {
            // youtube.com/live/<id>
            $videoId = $m[1];
        } elseif (preg_match('~youtube\.com/(@[\w.-]+|channel/[\w-]+|c/[\w-]+|user/[\w-]+)~', $url, $m)) {
            // A channel URL — embed its live stream via the channel handle.
            $channel = $m[1];
        }

        $embed = $videoId
            ? "https://www.youtube.com/embed/{$videoId}?autoplay=1"
            : ($channel ? "https://www.youtube.com/embed/live_stream?channel={$channel}&autoplay=1" : null);

        return [
            'external_id' => $videoId ?? $channel,
            'embed_url'   => $embed,
            'channel_url' => $url,
        ];
    }
}
