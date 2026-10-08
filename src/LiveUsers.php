<?php

namespace Ernestdefoe\OnAir;

use Ernestdefoe\OnAir\Model\Stream;
use Flarum\Api\Context;
use WeakMap;

/**
 * Who is live right now, asked once per API request.
 *
 * `isLive` is on every serialized user: post authors, discussion starters,
 * the member list. Reading `$user->liveStream` in the getter ran one query per
 * user on the page. Live streams are few, so one query for all of their user
 * ids answers every user on the page at once.
 *
 * Keyed by the request object, so a long-running process (a queue worker)
 * never reuses an answer from an earlier request.
 */
final class LiveUsers
{
    /** @var WeakMap<object, array<int, true>>|null */
    private static ?WeakMap $byRequest = null;

    public static function has(Context $context, int $userId): bool
    {
        self::$byRequest ??= new WeakMap();
        $request = $context->request;

        if (! isset(self::$byRequest[$request])) {
            self::$byRequest[$request] = array_fill_keys(
                Stream::query()
                    ->where('status', Stream::STATUS_LIVE)
                    ->distinct()
                    ->pluck('user_id')
                    ->map(fn ($id) => (int) $id)
                    ->all(),
                true
            );
        }

        return isset(self::$byRequest[$request][$userId]);
    }
}
