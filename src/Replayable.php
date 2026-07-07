<?php

declare(strict_types=1);

namespace Replay;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Replay\Contracts\RecordsReplays;
use Replay\Relations\ReplayableBelongsToMany;
use Replay\Relations\ReplayableMorphToMany;

/**
 * Records every change to the model as a replayable step.
 *
 * Models using this trait should also implement
 * {@see \Replay\Contracts\RecordsReplays} so their pivot changes are recorded.
 */
trait Replayable
{
    public static function bootReplayable(): void
    {
        static::created(function (Model&RecordsReplays $model): void {
            $model->recordReplay('created', $model->replayableAttributes($model->getAttributes()));
        });

        static::updated(function (Model&RecordsReplays $model): void {
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

            // getDeletedAtColumn() comes from the SoftDeletes trait, which is
            // only present when usesSoftDeletes() is true.
            if (static::usesSoftDeletes() && method_exists($model, 'getDeletedAtColumn')) {
                $column = $model->getDeletedAtColumn();

                if (array_key_exists($column, $changes)
                    && $changes[$column] === null
                    && ($old[$column] ?? null) !== null) {
                    $event = 'restored';
                }
            }

            $model->recordReplay($event, $changes, null, $old);
        });

        static::deleted(function (Model&RecordsReplays $model): void {
            if (static::usesSoftDeletes()
                && method_exists($model, 'isForceDeleting')
                && $model->isForceDeleting()) {
                $model->recordReplay('force_deleted');

                return;
            }

            $changes = [];

            if (static::usesSoftDeletes() && method_exists($model, 'getDeletedAtColumn')) {
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
     *
     * @return MorphMany<Replay, $this>
     */
    public function replays(): MorphMany
    {
        return $this->morphMany(Replay::class, 'model');
    }

    /**
     * Record a step manually. Attribute events ('created', 'updated', ...)
     * are recorded automatically; use this for custom events, which can be
     * replayed by registering a handler with Replayer::extend().
     *
     * @param  array<string, mixed>  $newValues
     * @param  array<string, mixed>  $oldValues
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
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function replayableAttributes(array $attributes): array
    {
        $exclude = array_merge(
            config('replay.exclude', []),
            $this->replayExclude ?? [],
        );

        return array_diff_key($attributes, array_flip($exclude));
    }

    /**
     * @template TRelatedModel of Model
     * @template TDeclaringModel of Model
     *
     * @param  Builder<TRelatedModel>  $query
     * @param  TDeclaringModel  $parent
     * @param  string|class-string<Model>  $table
     * @param  string  $foreignPivotKey
     * @param  string  $relatedPivotKey
     * @param  string  $parentKey
     * @param  string  $relatedKey
     * @param  string|null  $relationName
     * @return ReplayableBelongsToMany<TRelatedModel, TDeclaringModel>
     */
    protected function newBelongsToMany(Builder $query, Model $parent, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName = null)
    {
        return new ReplayableBelongsToMany($query, $parent, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName);
    }

    /**
     * @template TRelatedModel of Model
     * @template TDeclaringModel of Model
     *
     * @param  Builder<TRelatedModel>  $query
     * @param  TDeclaringModel  $parent
     * @param  string  $name
     * @param  string  $table
     * @param  string  $foreignPivotKey
     * @param  string  $relatedPivotKey
     * @param  string  $parentKey
     * @param  string  $relatedKey
     * @param  string|null  $relationName
     * @param  bool  $inverse
     * @return ReplayableMorphToMany<TRelatedModel, TDeclaringModel>
     */
    protected function newMorphToMany(Builder $query, Model $parent, $name, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName = null, $inverse = false)
    {
        return new ReplayableMorphToMany($query, $parent, $name, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName, $inverse);
    }
}
