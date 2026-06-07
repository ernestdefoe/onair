<?php

namespace Ernestdefoe\OnAir\Console;

use Carbon\Carbon;
use Ernestdefoe\OnAir\Model\Stream;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Console\Command;

/**
 * Ends streams that have been "live" longer than the configured maximum. This
 * is the pragmatic Lite answer to "the streamer closed their tab / stopped
 * broadcasting" without per-user YouTube/Twitch OAuth: a stream can't stay live
 * forever. Pro replaces this with authoritative RTMP on_publish_done webhooks.
 *
 * Runs hourly via the scheduler (see extend.php) and can be invoked manually:
 *   php flarum onair:reap
 */
class ReapStaleStreamsCommand extends Command
{
    protected $signature = 'onair:reap';
    protected $description = 'End OnAir streams that have been live past the configured maximum duration.';

    public function handle(SettingsRepositoryInterface $settings): int
    {
        $hours = (int) ($settings->get('onair.max_stream_hours') ?: 12);
        $cutoff = Carbon::now()->subHours(max(1, $hours));

        $stale = Stream::query()
            ->where('status', Stream::STATUS_LIVE)
            ->where('started_at', '<', $cutoff)
            ->get();

        // Per-model save (not a bulk update) so Stream::updated fires and the
        // StreamEnded event / realtime offline push happen for each one.
        $stale->each(function (Stream $stream) {
            $stream->status = Stream::STATUS_ENDED;
            $stream->ended_at = Carbon::now();
            $stream->save();
        });

        $this->info("OnAir: ended {$stale->count()} stale stream(s) live past {$hours}h.");

        return Command::SUCCESS;
    }
}
