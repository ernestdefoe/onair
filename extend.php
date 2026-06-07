<?php

use Ernestdefoe\OnAir\Api\Controller\ListLiveController;
use Ernestdefoe\OnAir\Api\Resource\StreamResource;
use Ernestdefoe\OnAir\Access\StreamPolicy;
use Ernestdefoe\OnAir\Console\HourlySchedule;
use Ernestdefoe\OnAir\Console\ReapStaleStreamsCommand;
use Ernestdefoe\OnAir\Event\StreamEnded;
use Ernestdefoe\OnAir\Event\StreamStarted;
use Ernestdefoe\OnAir\Model\Stream;
use Ernestdefoe\OnAir\OnAirServiceProvider;
use Flarum\Api\Resource\UserResource;
use Flarum\Api\Schema;
use Flarum\Extend;
use Flarum\Frontend\Document;
use Flarum\Http\RequestUtil;
use Flarum\Realtime\Extend\Realtime as RealtimeExtend;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/less/forum.less')
        ->route('/onair', 'ernestdefoe-onair.index')
        ->route('/onair/{id}', 'ernestdefoe-onair.stream')
        ->content(function (Document $document, ServerRequestInterface $request) {
            $actor = RequestUtil::getActor($request);
            $document->payload['onairCanBroadcast'] = $actor->hasPermission('onair.broadcast');
        }),

    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js')
        ->css(__DIR__ . '/less/admin.less'),

    new Extend\Locales(__DIR__ . '/locale'),

    (new Extend\Routes('api'))
        ->get('/onair/live', 'onair.live', ListLiveController::class),

    (new Extend\ApiResource(StreamResource::class)),

    // Add the LIVE state to every user payload. The closure passed to ->fields()
    // is invoked with ZERO arguments (ContainerUtil only injects for string
    // 'Class@method' callbacks), so never type-hint params here.
    (new Extend\ApiResource(UserResource::class))
        ->fields(fn () => [
            Schema\Boolean::make('isLive')
                ->get(fn (User $user) => (bool) $user->liveStream),

            Schema\Relationship\ToOne::make('liveStream')
                ->type('onair-streams')
                ->includable()
                ->get(fn (User $user) => $user->liveStream),
        ]),

    // A "currently live" hasOne on the core User model.
    (new Extend\Model(User::class))
        ->relationship('liveStream', function (User $user) {
            return $user->hasOne(Stream::class, 'user_id')
                ->where('status', Stream::STATUS_LIVE)
                ->latest('started_at');
        }),

    (new Extend\Policy())
        ->modelPolicy(Stream::class, StreamPolicy::class),

    // Realtime presence push (only when flarum/realtime is enabled). Broadcasts
    // an `onairPresence` event on going live / offline; the frontend binding
    // (js/src/forum/extendRealtime.js) just calls presence.refresh() in response,
    // so /api/onair/live stays the single source of truth. Without realtime this
    // whole block is skipped and the PollingTransport handles presence.
    (new Extend\Conditional())
        ->whenExtensionEnabled('flarum-realtime', fn () => [
            (new RealtimeExtend())
                ->broadcastModelEvent(
                    [StreamStarted::class, StreamEnded::class],
                    fn ($event) => $event->stream,
                    null,
                    'onairPresence'
                )
                ->registerModelEndpoint(Stream::class, 'onair-streams'),
        ]),

    (new Extend\Console())
        ->command(ReapStaleStreamsCommand::class)
        ->schedule(ReapStaleStreamsCommand::class, HourlySchedule::class),

    (new Extend\Settings())
        ->default('onair.poll_interval', 30)
        ->default('onair.default_provider', 'twitch')
        ->default('onair.max_stream_hours', 12)
        ->serializeToForum('onairPollInterval', 'onair.poll_interval', 'intval')
        ->serializeToForum('onairDefaultProvider', 'onair.default_provider'),

    (new Extend\ServiceProvider())
        ->register(OnAirServiceProvider::class),
];
