<?php

namespace Ernestdefoe\OnAir\Tests\integration\console;

use Carbon\Carbon;
use Flarum\Testing\integration\ConsoleTestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

/** `onair:reap` ends streams left live past the maximum, so a closed tab cannot stay LIVE for ever. */
class ReapStaleStreamsTest extends ConsoleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-onair');
        $this->setting('onair.max_stream_hours', '3');

        $this->prepareDatabase([
            User::class => [['id' => 2, 'username' => 'normal', 'email' => 'normal@machine.local', 'is_email_confirmed' => 1]],
            'onair_streams' => [
                ['id' => 1, 'user_id' => 2, 'provider' => 'twitch', 'status' => 'live', 'viewer_count' => 0, 'started_at' => Carbon::now()->subHours(4)],
                ['id' => 2, 'user_id' => 2, 'provider' => 'twitch', 'status' => 'live', 'viewer_count' => 0, 'started_at' => Carbon::now()->subHours(2)],
            ],
        ]);
    }

    #[Test]
    public function streams_past_the_maximum_are_ended_and_younger_ones_are_left()
    {
        $output = $this->runCommand(['command' => 'onair:reap']);

        $this->assertStringContainsString('ended 1 stale stream(s) live past 3h', $output);
        $this->assertSame(['1' => 'ended', '2' => 'live'], $this->database()->table('onair_streams')->orderBy('id')->pluck('status', 'id')->mapWithKeys(fn ($s, $id) => [(string) $id => $s])->all());
        $this->assertNotNull($this->database()->table('onair_streams')->where('id', 1)->value('ended_at'));
    }
}
