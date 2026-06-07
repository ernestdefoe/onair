<?php

namespace Ernestdefoe\OnAir\Access;

use Ernestdefoe\OnAir\Model\Stream;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

class StreamPolicy extends AbstractPolicy
{
    /**
     * A member may edit/end their own stream. (Admins are allowed globally by
     * core's gate, and forum moderators with the manage permission below.)
     */
    public function edit(User $actor, Stream $stream)
    {
        if ($actor->id === (int) $stream->user_id) {
            return $this->allow();
        }

        if ($actor->hasPermission('onair.manage')) {
            return $this->allow();
        }
    }
}
