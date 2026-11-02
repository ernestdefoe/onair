<?php

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

// A stream linked to a discussion that is later deleted loses the link instead
// of keeping a stale id.
return [
    'up' => function (Builder $schema) {
        // Clear ids that already point at deleted discussions, or the key can't be added.
        $schema->getConnection()->table('onair_streams')
            ->whereNotNull('discussion_id')
            ->whereNotExists(function (QueryBuilder $query) {
                $query->selectRaw(1)
                    ->from('discussions')
                    ->whereColumn('discussions.id', 'onair_streams.discussion_id');
            })
            ->update(['discussion_id' => null]);

        $schema->table('onair_streams', function (Blueprint $table) {
            $table->foreign('discussion_id')
                ->references('id')->on('discussions')
                ->nullOnDelete();
        });
    },

    'down' => function (Builder $schema) {
        $schema->table('onair_streams', function (Blueprint $table) {
            $table->dropForeign(['discussion_id']);
        });
    },
];
