<?php

declare(strict_types=1);

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
    | Morph Key Type
    |--------------------------------------------------------------------------
    |
    | The column type used for the model_id and user_id morph keys. Use "int"
    | when your models have auto-incrementing (bigint) keys, "uuid" when they
    | use UUID/ULID keys, or "string" when a single replays table needs to
    | hold a mix of both. This is read by the package migration, so set it
    | before migrating (or roll back and re-run after changing it).
    |
    | Supported: "int", "uuid", "string"
    |
    */

    'morph_key_type' => 'int',

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
