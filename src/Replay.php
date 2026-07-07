<?php

namespace Replay;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A single recorded step in a model's history.
 *
 * @property int $id
 * @property string $model_type
 * @property int|string $model_id
 * @property string $event
 * @property string|null $relation
 * @property array|null $old_values
 * @property array|null $new_values
 * @property string|null $user_type
 * @property int|string|null $user_id
 */
class Replay extends Model
{
    protected $guarded = [];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function getTable(): string
    {
        return config('replay.table', 'replays');
    }

    /**
     * The model this step belongs to.
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The user who performed this step, if tracked.
     */
    public function user(): MorphTo
    {
        return $this->morphTo();
    }
}
