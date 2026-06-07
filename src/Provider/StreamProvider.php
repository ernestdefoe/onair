<?php

namespace Ernestdefoe\OnAir\Provider;

/**
 * A source a member can go live from. Lite ships YouTube + Twitch.
 *
 * OnAir Pro registers additional providers (e.g. the built-in RTMP/HLS pipeline,
 * whether self-hosted or via a managed ingest API) through the same interface —
 * bind another implementation in a ServiceProvider and tag it 'onair.providers'.
 */
interface StreamProvider
{
    /** Stable machine key, e.g. "youtube", "twitch", "rtmp". */
    public function key(): string;

    /** Human label for UI / admin. */
    public function label(): string;

    /** True if this provider recognises the given channel/video URL. */
    public function matches(string $url): bool;

    /**
     * Resolve a pasted URL into stored stream fields.
     *
     * @return array{external_id: ?string, embed_url: ?string, channel_url: string}
     */
    public function normalize(string $url): array;
}
