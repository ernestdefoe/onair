<?php

use Flarum\Database\Migration;
use Flarum\Group\Group;

// By default, registered members may go live. Admins refine this in the
// Permissions grid (onair.broadcast / onair.manage).
return Migration::addPermissions([
    'onair.broadcast' => Group::MEMBER_ID,
]);
