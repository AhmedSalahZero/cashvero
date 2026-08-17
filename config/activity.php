<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    | Master switch for the per-record audit trail. Turning it off stops
    | all writes; reading existing history still works.
    */
    'enabled' => env('ACTIVITY_LOG_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Entries shown in a record's timeline
    |--------------------------------------------------------------------------
    | A long-lived record can accumulate hundreds of entries. The modal
    | loads the most recent N; older ones stay in the table and remain
    | queryable.
    */
    'timeline_limit' => env('ACTIVITY_TIMELINE_LIMIT', 100),
];
