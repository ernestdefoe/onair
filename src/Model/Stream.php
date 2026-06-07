<?php

namespace Ernestdefoe\OnAir\Model;

use Flarum\Database\AbstractModel;
use Flarum\Discussion\Discussion;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string $status
 * @property string|null $title
 * @property string|null $external_id
 * @property string|null $channel_url
 * @property string|null $embed_url
 * @property int $viewer_count
 * @property int|null $discussion_id
 * @property \Carbon\Carbon|null $started_at
 * @property \Carbon\Carbon|null $ended_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class Stream extends AbstractModel
{
    public const STATUS_LIVE = 'live';
    public const STATUS_OFFLINE = 'offline';
    public const STATUS_ENDED = 'ended';

    protected $table = 'onair_streams';

    public $timestamps = true;

    protected $casts = [
        'viewer_count' => 'integer',
        'started_at'   => 'datetime',
        'ended_at'     => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function discussion()
    {
        return $this->belongsTo(Discussion::class, 'discussion_id');
    }

    public function isLive(): bool
    {
        return $this->status === self::STATUS_LIVE;
    }

    /** Scope: only currently-live streams. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_LIVE);
    }
}
