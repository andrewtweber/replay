<?php

namespace Replay;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Replay\Relations\ReplayableBelongsToMany;
use Replay\Relations\ReplayableMorphToMany;

/**
 * Records every change to the model as a replayable step.
 */
trait Replayable
{
    public static function bootReplayable(): void
    {
        static::created(function (Model $model) {
            $model->recordReplay('created', $model->replayableAttributes($model->getAttributes()));
        });

        static::updated(function (Model $model) {
            $changes = $model->replayableAttributes($model->getChanges());

            if ($changes === []) {
                return;
            }

            $old = [];
            foreach (array_keys($changes) as $key) {
                $old[$key] = $model->getRawOriginal($key);
            }

            // restore() saves the model with deleted_at reset, so it surfaces
            // here as a plain update. Record it under its real name; the
            // changes (deleted_at + updated_at) replay it exactly.
            $event = 'updated';

            if (static::usesSoftDeletes()) {
                $column = $model->getDeletedAtColumn();

                if (array_key_exists($column, $changes)
                    && $changes[$column] === null
                    && ($old[$column] ?? null) !== null) {
                    $event = 'restored';
                }
            }

            $model->recordReplay($event, $changes, null, $old);
        });

        static::deleted(function (Model $model) {
            if (static::usesSoftDeletes() && $model->isForceDeleting()) {
                $model->recordReplay('force_deleted');

                return;
            }

            $changes = [];

            if (static::usesSoftDeletes()) {
                // Soft deleting also touches updated_at; record both so a
                // replay reproduces the row byte-for-byte.
                $column = $model->getDeletedAtColumn();
                $changes[$column] = $model->getAttributes()[$column] ?? null;

                if ($model->usesTimestamps() && ($updatedAt = $model->getUpdatedAtColumn())) {
                    $changes[$updatedAt] = $model->getAttributes()[$updatedAt] ?? null;
                }
            }

            $model->recordReplay('deleted', $changes);
        });
    }

    public static function usesSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive(static::class));
    }

    /**
     * All recorded steps for this model.
     */
    public function replays(): MorphMany
    {
        return $this->morphMany(Replay::class, 'model');
    }

    /**
     * Record a step manually. Attribute events ('created', 'updated', ...)
     * are recorded automatically; use this for custom events, which can be
     * replayed by registering a handler with Replayer::extend().
     */
    public function recordReplay(string $event, array $newValues = [], ?string $relation = null, array $oldValues = []): ?Replay
    {
        if (! Replayer::isRecording()) {
            return null;
        }

        $attributes = [
            'event' => $event,
            'relation' => $relation,
            'new_values' => $newValues,
            'old_values' => config('replay.store_old_values', true) && $oldValues !== [] ? $oldValues : null,
        ];

        if (config('replay.track_user', true) && ($user = auth()->user())) {
            $attributes['user_type'] = $user->getMorphClass();
            $attributes['user_id'] = $user->getKey();
        }

        return $this->replays()->create($attributes);
    }

    /**
     * A Replayer instance for this model, ready to re-execute its history.
     */
    public function replayer(): Replayer
    {
        return Replayer::for(static::class, $this->getKey());
    }

    /**
     * Strip attributes that should not be recorded.
     */
    public function replayableAttributes(array $attributes): array
    {
        $exclude = array_merge(
            config('replay.exclude', []),
            $this->replayExclude ?? [],
        );

        return array_diff_key($attributes, array_flip($exclude));
    }

    protected function newBelongsToMany(Builder $query, Model $parent, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName = null)
    {
        return new ReplayableBelongsToMany($query, $parent, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName);
    }

    protected function newMorphToMany(Builder $query, Model $parent, $name, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName = null, $inverse = false)
    {
        return new ReplayableMorphToMany($query, $parent, $name, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName, $inverse);
    }
}
