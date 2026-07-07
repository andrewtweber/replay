<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    |
    | The table that replay steps are stored in.
    |
    */

    'table' => 'replays',

    /*
    |--------------------------------------------------------------------------
    | Store Old Values
    |--------------------------------------------------------------------------
    |
    | Replaying only ever needs the new values, since any prior state can be
    | reproduced by replaying from the beginning. Storing the old values as
    | well is purely for convenience (showing diffs, quick inspection).
    |
    */

    'store_old_values' => true,

    /*
    |--------------------------------------------------------------------------
    | Globally Excluded Attributes
    |--------------------------------------------------------------------------
    |
    | Attributes that should never be recorded, on any model. Individual
    | models can add their own via the $replayExclude property.
    |
    */

    'exclude' => [],

    /*
    |--------------------------------------------------------------------------
    | Track User
    |--------------------------------------------------------------------------
    |
    | When enabled, the currently authenticated user is stored on each
    | recorded step.
    |
    */

    'track_user' => true,

];
