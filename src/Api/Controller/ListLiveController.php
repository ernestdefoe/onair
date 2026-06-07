<?php

namespace Ernestdefoe\OnAir\Api\Controller;

use Ernestdefoe\OnAir\Model\Stream;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Lightweight presence endpoint for the PollingTransport fallback.
 *
 * GET /api/onair/live  →  { data: [ { id, userId, username, displayName,
 *                                     avatarUrl, provider, title, viewerCount } ] }
 *
 * Deliberately a flat, cache-friendly payload (no JSON:API envelope) so the
 * frontend can diff "who is live" cheaply on a short interval. When
 * flarum/realtime is present the RealtimeTransport is used instead and this is
 * only hit on first load / reconnect.
 */
class ListLiveController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $streams = Stream::query()
            ->live()
            ->with('user')
            ->orderByDesc('started_at')
            ->limit(100)
            ->get();

        $data = $streams->map(function (Stream $s) {
            $user = $s->user;

            return [
                'id'          => (int) $s->id,
                'userId'      => (int) $s->user_id,
                'username'    => $user?->username,
                'displayName' => $user?->display_name,
                'avatarUrl'   => $user?->avatar_url,
                'provider'    => $s->provider,
                'title'       => $s->title,
                'viewerCount' => (int) $s->viewer_count,
                'startedAt'   => optional($s->started_at)->toIso8601String(),
            ];
        })->filter(fn ($row) => $row['username'] !== null)->values();

        return new JsonResponse(['data' => $data]);
    }
}
