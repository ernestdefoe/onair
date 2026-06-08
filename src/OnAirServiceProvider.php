<?php

namespace Ernestdefoe\OnAir;

use Ernestdefoe\OnAir\Event\StreamEnded;
use Ernestdefoe\OnAir\Event\StreamStarted;
use Ernestdefoe\OnAir\Model\Stream;
use Ernestdefoe\OnAir\Provider\ProviderManager;
use Ernestdefoe\OnAir\Provider\TwitchProvider;
use Ernestdefoe\OnAir\Provider\YouTubeProvider;
use Flarum\Foundation\AbstractServiceProvider;
use Flarum\Locale\TranslatorInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;

class OnAirServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        // The provider registry. Pro resolves this same singleton and ->add()s
        // its RTMP/HLS provider, so both editions share one resolution path.
        $this->container->singleton(ProviderManager::class, function (Container $container) {
            return new ProviderManager(
                [new YouTubeProvider(), new TwitchProvider()],
                $container->make(TranslatorInterface::class)
            );
        });
    }

    public function boot(Dispatcher $events): void
    {
        // Translate model lifecycle into domain events the realtime listener
        // (and Pro) can subscribe to. The dispatcher is injected rather than
        // reached for via the event() global helper.
        Stream::created(function (Stream $stream) use ($events) {
            if ($stream->isLive()) {
                $events->dispatch(new StreamStarted($stream));
            }
        });

        Stream::updated(function (Stream $stream) use ($events) {
            if ($stream->wasChanged('status') && $stream->status !== Stream::STATUS_LIVE) {
                $events->dispatch(new StreamEnded($stream));
            }
        });

        // Ending a stream via the Delete endpoint hard-deletes the row, so the
        // updated() hook above won't see it — fire the offline event here too so
        // realtime/Pro listeners always learn a live stream went offline.
        Stream::deleted(function (Stream $stream) use ($events) {
            if ($stream->status === Stream::STATUS_LIVE) {
                $events->dispatch(new StreamEnded($stream));
            }
        });
    }
}
