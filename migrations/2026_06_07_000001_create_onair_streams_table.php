<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('onair_streams', function (Blueprint $table) {
    $table->bigIncrements('id');

    // core users.id is INT UNSIGNED — match it for the FK.
    $table->unsignedInteger('user_id')->index();

    $table->string('provider', 30);                 // 'youtube' | 'twitch' | (pro) 'rtmp'
    $table->string('status', 20)->default('live');  // 'live' | 'offline' | 'ended'
    $table->string('title')->nullable();

    $table->string('external_id')->nullable();       // provider video/channel id
    $table->string('channel_url')->nullable();       // human-facing URL the member pasted
    $table->text('embed_url')->nullable();           // resolved iframe src

    $table->unsignedInteger('viewer_count')->default(0);

    // Optional link to a host discussion (viewer card embeds there).
    $table->unsignedInteger('discussion_id')->nullable()->index();

    $table->timestamp('started_at')->nullable();
    $table->timestamp('ended_at')->nullable();
    $table->timestamps();

    $table->foreign('user_id')
        ->references('id')->on('users')
        ->onDelete('cascade');

    // Fast "who is live right now" lookups for the polling transport.
    $table->index(['status', 'started_at']);
});
