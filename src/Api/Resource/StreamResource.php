<?php

namespace Ernestdefoe\OnAir\Api\Resource;

use Carbon\Carbon;
use Ernestdefoe\OnAir\Model\Stream;
use Ernestdefoe\OnAir\Provider\ProviderManager;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Discussion\Discussion;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Tobyz\JsonApiServer\Context as BaseContext;

class StreamResource extends AbstractDatabaseResource
{
    public function __construct(
        protected ProviderManager $providers,
        protected SettingsRepositoryInterface $settings,
        protected TranslatorInterface $translator,
    ) {}

    public function type(): string
    {
        return 'onair-streams';
    }

    public function model(): string
    {
        return Stream::class;
    }

    public function scope(Builder $query, BaseContext $context): void
    {
        // The public index is "who is live right now", newest first.
        $query->where('status', Stream::STATUS_LIVE)->orderByDesc('started_at');
    }

    /**
     * Direct lookup that bypasses the live-only scope, so Show returns ended
     * streams too. Required because flarum/realtime's payload Generator does an
     * internal GET /onair-streams/{id} when broadcasting StreamEnded — if that
     * 404'd (scoped out), the "went offline" push would never be generated.
     * Streams are publicly viewable in Lite, so no per-record gate here.
     */
    public function find(string $id, BaseContext $context): ?object
    {
        return Stream::find($id);
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()->paginate(),
            Endpoint\Show::make(),
            Endpoint\Create::make()
                ->authenticated()
                ->can('onair.broadcast'),
            Endpoint\Update::make()
                ->authenticated()
                ->can('edit'),
            Endpoint\Delete::make()
                ->authenticated()
                ->can('edit'),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('provider')
                ->get(fn (Stream $s) => $s->provider),

            // Writable so the owner can PATCH status=ended to go offline (a soft
            // end — the row persists for history + the realtime offline payload).
            // Only end transitions are honoured; never re-open via the API.
            Schema\Str::make('status')
                ->writable()
                ->set(function (Stream $s, $value) {
                    if (in_array($value, [Stream::STATUS_ENDED, Stream::STATUS_OFFLINE], true)) {
                        $s->status = $value;
                        $s->ended_at = \Carbon\Carbon::now();
                    }
                })
                ->get(fn (Stream $s) => $s->status),

            Schema\Str::make('title')
                ->writable()
                ->nullable()
                ->maxLength(120)
                ->set(fn (Stream $s, $value) => $s->title = $value ? trim($value) : null),

            // Write-only input: the URL the member pastes. Resolved in creating(),
            // so the setter is a no-op (prevents Eloquent persisting a bogus
            // camelCase `channelUrl` column).
            Schema\Str::make('channelUrl')
                ->writable()
                ->set(fn (Stream $s, $value) => null)
                ->get(fn (Stream $s) => $s->channel_url),

            Schema\Str::make('embedUrl')
                ->get(fn (Stream $s) => $s->embed_url),

            Schema\Str::make('externalId')
                ->get(fn (Stream $s) => $s->external_id),

            Schema\Integer::make('viewerCount')
                ->get(fn (Stream $s) => (int) $s->viewer_count),

            Schema\Integer::make('discussionId')
                ->writable()
                ->set(function (Stream $s, $value, BaseContext $context) {
                    // Only link a discussion that exists AND is visible to the
                    // actor — otherwise store null rather than a dangling /
                    // unauthorized reference.
                    if (! $value) {
                        $s->discussion_id = null;
                        return;
                    }
                    $id = (int) $value;
                    $visible = Discussion::query()
                        ->whereVisibleTo($context->getActor())
                        ->whereKey($id)
                        ->exists();
                    $s->discussion_id = $visible ? $id : null;
                })
                ->get(fn (Stream $s) => $s->discussion_id),

            Schema\DateTime::make('startedAt')
                ->get(fn (Stream $s) => $s->started_at),

            Schema\Relationship\ToOne::make('user')
                ->type('users')
                ->includable()
                ->get(fn (Stream $s) => $s->user),
        ];
    }

    public function creating(object $model, BaseContext $context): ?object
    {
        /** @var Context $context */
        /** @var Stream $model */
        $actor = $context->getActor();

        $body = $context->request->getParsedBody();
        $url = trim($body['data']['attributes']['channelUrl'] ?? '');

        if ($url === '') {
            throw new ValidationException(['channelUrl' => $this->translator->trans('onair.lib.errors.url_required')]);
        }

        // Resolve provider + embed details (throws if unrecognised).
        $resolved = $this->providers->normalize($url);

        // One active stream per user (Lite). End any existing live stream first.
        Stream::where('user_id', $actor->id)
            ->where('status', Stream::STATUS_LIVE)
            ->update(['status' => Stream::STATUS_ENDED, 'ended_at' => Carbon::now()]);

        $model->user_id     = $actor->id;
        $model->provider    = $resolved['provider'];
        $model->external_id = $resolved['external_id'];
        $model->embed_url   = $resolved['embed_url'];
        $model->channel_url = $resolved['channel_url'];
        $model->status      = Stream::STATUS_LIVE;
        $model->started_at  = Carbon::now();

        return null;
    }
}
