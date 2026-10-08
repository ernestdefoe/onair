<?php

namespace Ernestdefoe\OnAir\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

/**
 * Going live, ending a stream, and who is shown as live.
 * Stream 1 is user 3's live stream; stream 2 is user 3's ended one.
 */
class StreamsTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-onair');

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'streamer', 'email' => 'streamer@machine.local', 'is_email_confirmed' => 1],
            ],
            'group_user' => [['user_id' => 3, 'group_id' => 3]],
            Discussion::class => [
                ['id' => 1, 'title' => 'Watch party', 'created_at' => Carbon::now(), 'user_id' => 2, 'comment_count' => 0],
                ['id' => 2, 'title' => 'Staff', 'created_at' => Carbon::now(), 'user_id' => 1, 'comment_count' => 0, 'hidden_at' => Carbon::now()],
            ],
            'onair_streams' => [
                ['id' => 1, 'user_id' => 3, 'provider' => 'twitch', 'status' => 'live', 'external_id' => 'streamer', 'channel_url' => 'https://twitch.tv/streamer', 'embed_url' => 'https://player.twitch.tv/?channel=streamer', 'viewer_count' => 0, 'started_at' => Carbon::now()->subMinutes(5)],
                ['id' => 2, 'user_id' => 3, 'provider' => 'twitch', 'status' => 'ended', 'external_id' => 'streamer', 'channel_url' => 'https://twitch.tv/streamer', 'embed_url' => 'https://player.twitch.tv/?channel=streamer', 'viewer_count' => 0, 'started_at' => Carbon::now()->subDay(), 'ended_at' => Carbon::now()->subDay()],
            ],
        ]);
    }

    private function api(string $method, string $path, ?int $actor = null, ?array $attributes = null, ?string $id = null): array
    {
        $options = $actor ? ['authenticatedAs' => $actor] : [];
        if ($attributes !== null) {
            $options['json'] = ['data' => array_filter(['type' => 'onair-streams', 'id' => $id, 'attributes' => $attributes])];
        }

        $response = $this->send($this->request($method, $path, $options));

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    #[Test]
    public function a_member_goes_live_from_a_pasted_url()
    {
        $cases = [
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ' => ['youtube', 'dQw4w9WgXcQ', 'https://www.youtube.com/embed/dQw4w9WgXcQ?autoplay=1'],
            'https://youtu.be/dQw4w9WgXcQ' => ['youtube', 'dQw4w9WgXcQ', 'https://www.youtube.com/embed/dQw4w9WgXcQ?autoplay=1'],
            'https://www.youtube.com/@SomeChannel' => ['youtube', '@SomeChannel', 'https://www.youtube.com/embed/live_stream?channel=@SomeChannel&autoplay=1'],
            'https://www.twitch.tv/videos/123456' => ['twitch', 'v123456', 'https://player.twitch.tv/?video=123456'],
            'https://twitch.tv/some_streamer' => ['twitch', 'some_streamer', 'https://player.twitch.tv/?channel=some_streamer'],
        ];

        foreach ($cases as $url => [$provider, $externalId, $embed]) {
            [$status, $body] = $this->api('POST', '/api/onair-streams', 2, ['channelUrl' => $url, 'title' => '  Live now  ']);

            $this->assertSame(201, $status, $url.' '.json_encode($body));
            $attributes = $body['data']['attributes'];
            $this->assertSame([$provider, $externalId, $embed, 'live', 'Live now', true], [$attributes['provider'], $attributes['externalId'], $attributes['embedUrl'], $attributes['status'], $attributes['title'], $attributes['canEdit']], $url);
        }

        $this->assertSame(1, $this->database()->table('onair_streams')->where('user_id', 2)->where('status', 'live')->count(), 'Going live again ends the stream before');
    }

    #[Test]
    public function an_empty_or_unrecognised_url_is_refused()
    {
        [$status, $body] = $this->api('POST', '/api/onair-streams', 2, ['channelUrl' => '']);
        $this->assertSame(422, $status);
        $this->assertSame('/data/attributes/channelUrl', $body['errors'][0]['source']['pointer'], 'Refused as missing, before any provider is tried');

        [$status] = $this->api('POST', '/api/onair-streams', 2, ['channelUrl' => 'https://vimeo.com/123']);
        $this->assertSame(422, $status);

        $this->assertSame(0, $this->database()->table('onair_streams')->where('user_id', 2)->count());
    }

    #[Test]
    public function going_live_needs_the_broadcast_permission()
    {
        $this->app();
        $this->database()->table('group_permission')->where('permission', 'onair.broadcast')->delete();

        [$status] = $this->api('POST', '/api/onair-streams', 2, ['channelUrl' => 'https://twitch.tv/x_y_z']);

        $this->assertSame(403, $status);
    }

    #[Test]
    public function a_stream_only_links_a_discussion_its_streamer_can_see()
    {
        [, $body] = $this->api('POST', '/api/onair-streams', 2, ['channelUrl' => 'https://twitch.tv/abc', 'discussionId' => 1]);
        $this->assertSame(1, $body['data']['attributes']['discussionId']);

        [, $body] = $this->api('POST', '/api/onair-streams', 2, ['channelUrl' => 'https://twitch.tv/abc', 'discussionId' => 2]);
        $this->assertNull($body['data']['attributes']['discussionId']);
    }

    #[Test]
    public function only_the_streamer_or_a_manager_can_end_a_stream_and_it_cannot_be_reopened()
    {
        [$status] = $this->api('PATCH', '/api/onair-streams/1', 2, ['status' => 'ended'], '1');
        $this->assertSame(403, $status);

        [$status] = $this->api('DELETE', '/api/onair-streams/1', 2);
        $this->assertSame(403, $status);

        [$status, $body] = $this->api('PATCH', '/api/onair-streams/1', 3, ['status' => 'ended'], '1');
        $this->assertSame(200, $status);
        $this->assertSame('ended', $body['data']['attributes']['status']);

        [, $body] = $this->api('PATCH', '/api/onair-streams/1', 3, ['status' => 'live'], '1');
        $this->assertSame('ended', $body['data']['attributes']['status'], 'An ended stream stays ended');
    }

    #[Test]
    public function a_member_with_the_manage_permission_can_end_anyones_stream()
    {
        $this->prepareDatabase(['group_permission' => [['group_id' => 3, 'permission' => 'onair.manage']]]);

        [$status] = $this->api('DELETE', '/api/onair-streams/1', 2);

        $this->assertSame(204, $status);
        $this->assertNull($this->database()->table('onair_streams')->find(1));
    }

    #[Test]
    public function the_list_shows_who_is_live_and_an_ended_stream_can_still_be_fetched()
    {
        [$status, $body] = $this->api('GET', '/api/onair-streams');
        $this->assertSame(200, $status);
        $this->assertSame(['1'], array_column($body['data'], 'id'));

        [$status, $body] = $this->api('GET', '/api/onair-streams/2');
        $this->assertSame(200, $status);
        $this->assertSame('ended', $body['data']['attributes']['status']);
    }

    #[Test]
    public function the_presence_endpoint_is_a_flat_list_of_live_streamers()
    {
        [$status, $body] = $this->api('GET', '/api/onair/live');

        $this->assertSame(200, $status);
        $this->assertCount(1, $body['data']);
        $this->assertSame(['id' => 1, 'userId' => 3, 'username' => 'streamer', 'provider' => 'twitch'], array_intersect_key($body['data'][0], array_flip(['id', 'userId', 'username', 'provider'])));
    }

    #[Test]
    public function every_user_payload_says_whether_they_are_live_without_a_query_each()
    {
        $this->app();
        $users = [];
        $streams = [];
        for ($id = 10; $id < 30; $id++) {
            $users[] = ['id' => $id, 'username' => "u$id", 'email' => "u$id@machine.local", 'is_email_confirmed' => 1, 'password' => 'x', 'joined_at' => Carbon::now()];
            if ($id % 4 === 0) {
                $streams[] = ['user_id' => $id, 'provider' => 'twitch', 'status' => 'live', 'viewer_count' => 0, 'started_at' => Carbon::now()];
            } elseif ($id % 4 === 1) {
                // Streamed once, offline now.
                $streams[] = ['user_id' => $id, 'provider' => 'twitch', 'status' => 'ended', 'viewer_count' => 0, 'started_at' => Carbon::now()->subDay()];
            }
        }
        $this->database()->table('users')->insert($users);
        $this->database()->table('onair_streams')->insert($streams);

        $response = $this->send($this->request('GET', '/api/users', ['authenticatedAs' => 1])->withQueryParams(['page' => ['limit' => 50]]));
        $this->assertSame(200, $response->getStatusCode());

        $live = [];
        foreach (json_decode((string) $response->getBody(), true)['data'] as $user) {
            if ($user['attributes']['isLive']) {
                $live[] = (int) $user['id'];
            }
        }
        sort($live);

        $this->assertSame([3, 12, 16, 20, 24, 28], $live);
    }

    #[Test]
    public function the_forum_payload_carries_the_poll_interval_as_a_number()
    {
        $this->setting('onair.poll_interval', '45');

        $attributes = json_decode((string) $this->send($this->request('GET', '/api'))->getBody(), true)['data']['attributes'];

        $this->assertSame(45, $attributes['onairPollInterval']);
        $this->assertSame('twitch', $attributes['onairDefaultProvider']);
    }
}
