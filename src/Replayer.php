<?php

namespace Replay;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Replay\Exceptions\ReplayException;

/**
 * Re-executes a model's recorded steps against the database, reproducing
 * the exact sequence of creates, updates, deletes and pivot changes.
 */
class Replayer
{
    protected static bool $recording = true;

    /** @var array<string, callable> */
    protected static array $handlers = [];

    protected bool $withEvents = false;

    protected bool $overwrite = false;

    protected ?int $upTo = null;

    protected ?Model $model = null;

    final public function __construct(
        protected string $modelClass,
        protected mixed $key,
    ) {
    }

    public static function for(string $modelClass, mixed $key): static
    {
        return new static($modelClass, $key);
    }

    /*
    |--------------------------------------------------------------------------
    | Recording
    |--------------------------------------------------------------------------
    */

    public static function isRecording(): bool
    {
        return static::$recording;
    }

    /**
     * Run the callback without recording any replay steps.
     */
    public static function withoutRecording(callable $callback): mixed
    {
        $previous = static::$recording;
        static::$recording = false;

        try {
            return $callback();
        } finally {
            static::$recording = $previous;
        }
    }

    /**
     * Register a handler to replay a custom event recorded via recordReplay().
     * The callback receives (Model $model, Replay $step, Replayer $replayer).
     */
    public static function extend(string $event, callable $handler): void
    {
        static::$handlers[$event] = $handler;
    }

    /*
    |--------------------------------------------------------------------------
    | Options
    |--------------------------------------------------------------------------
    */

    /**
     * Fire model events while replaying. Recording stays paused either way,
     * so replaying never records new steps.
     */
    public function withEvents(bool $withEvents = true): static
    {
        $this->withEvents = $withEvents;

        return $this;
    }

    public function withoutEvents(): static
    {
        return $this->withEvents(false);
    }

    /**
     * Only replay steps up to and including the given step, reproducing the
     * state at that point in time.
     */
    public function upTo(Replay|int $step): static
    {
        $this->upTo = $step instanceof Replay ? $step->getKey() : $step;

        return $this;
    }

    /**
     * Delete the existing row (and its recorded pivot rows) before replaying,
     * so history can be replayed over a model that still exists.
     */
    public function overwriting(bool $overwrite = true): static
    {
        $this->overwrite = $overwrite;

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Replaying
    |--------------------------------------------------------------------------
    */

    /**
     * Re-execute the recorded steps and return the model in its final state.
     */
    public function replay(): Model
    {
        $steps = $this->steps();

        if ($steps->isEmpty()) {
            throw new ReplayException(
                "No replay steps found for [{$this->modelClass}] with key [{$this->key}]."
            );
        }

        return static::withoutRecording(function () use ($steps) {
            $this->model = null;

            if ($this->overwrite) {
                $this->deleteExisting();
            } elseif ($steps->first()->event === 'created' && $this->findExisting()) {
                throw new ReplayException(
                    "[{$this->modelClass}] with key [{$this->key}] already exists. "
                    .'Use overwriting() to delete it before replaying.'
                );
            }

            foreach ($steps as $step) {
                $this->apply($step);
            }

            return $this->model;
        });
    }

    /**
     * The steps that would be replayed, oldest first.
     */
    public function steps(): Collection
    {
        return Replay::query()
            ->where('model_type', (new $this->modelClass)->getMorphClass())
            ->where('model_id', $this->key)
            ->when($this->upTo, fn ($query) => $query->where('id', '<=', $this->upTo))
            ->orderBy('id')
            ->get();
    }

    protected function apply(Replay $step): void
    {
        if ($step->relation !== null) {
            $this->applyPivot($step);

            return;
        }

        match ($step->event) {
            'created' => $this->applyCreated($step),
            'updated' => $this->applyUpdated($step),
            'deleted' => $this->applyDeleted($step),
            'force_deleted' => $this->applyForceDeleted(),
            // A restore is recorded with its full changes (deleted_at reset
            // plus updated_at), so it replays exactly like an update.
            'restored' => $this->applyUpdated($step),
            default => $this->applyCustom($step),
        };
    }

    protected function applyCreated(Replay $step): void
    {
        $model = new $this->modelClass;
        $model->setRawAttributes($step->new_values ?? []);

        $this->withEvents ? $model->save() : $model->saveQuietly();

        $this->model = $model;
    }

    protected function applyUpdated(Replay $step): void
    {
        $model = $this->resolveModel();

        $model->setRawAttributes(array_merge($model->getAttributes(), $step->new_values ?? []));

        $this->withEvents ? $model->save() : $model->saveQuietly();
    }

    protected function applyDeleted(Replay $step): void
    {
        $model = $this->resolveModel();

        $this->withEvents ? $model->delete() : $model->deleteQuietly();

        // Soft deletes stamp deleted_at with the current time; restore the
        // recorded timestamp so the replayed state matches the original.
        if ($model->exists && ($step->new_values ?? []) !== []) {
            $model->newQueryWithoutScopes()
                ->whereKey($model->getKey())
                ->toBase()
                ->update($step->new_values);

            $model->setRawAttributes(array_merge($model->getAttributes(), $step->new_values), true);
        }
    }

    protected function applyForceDeleted(): void
    {
        $model = $this->resolveModel();

        $this->withEvents
            ? $model->forceDelete()
            : $model::withoutEvents(fn () => $model->forceDelete());
    }

    protected function applyPivot(Replay $step): void
    {
        $model = $this->resolveModel();
        $relation = $model->{$step->relation}();
        $values = $step->new_values ?? [];

        match ($step->event) {
            'attached' => $relation->attach($values),
            'detached' => $relation->detach($values),
            'pivot_updated' => collect($values)->each(
                fn ($attributes, $id) => $relation->updateExistingPivot($id, $attributes)
            ),
            default => $this->applyCustom($step),
        };
    }

    protected function applyCustom(Replay $step): void
    {
        $handler = static::$handlers[$step->event] ?? null;

        if ($handler === null) {
            throw new ReplayException(
                "No handler registered to replay event [{$step->event}]. Register one with Replayer::extend()."
            );
        }

        $handler($this->resolveModel(), $step, $this);
    }

    protected function resolveModel(): Model
    {
        if ($this->model !== null) {
            return $this->model;
        }

        $model = $this->findExisting();

        if ($model === null) {
            throw new ReplayException(
                "[{$this->modelClass}] with key [{$this->key}] does not exist and its history "
                .'has no created step to replay it from.'
            );
        }

        return $this->model = $model;
    }

    protected function findExisting(): ?Model
    {
        // Without global scopes the soft-delete scope is bypassed too, so
        // trashed rows are found as well.
        return (new $this->modelClass)->newQueryWithoutScopes()->whereKey($this->key)->first();
    }

    protected function deleteExisting(): void
    {
        $existing = $this->findExisting();

        if ($existing === null) {
            return;
        }

        // Clear every relation that appears anywhere in the history — not
        // just in the steps being replayed — so pivot rows from later steps
        // don't survive an upTo() replay.
        Replay::query()
            ->where('model_type', $existing->getMorphClass())
            ->where('model_id', $this->key)
            ->whereNotNull('relation')
            ->distinct()
            ->pluck('relation')
            ->each(fn (string $relation) => $existing->{$relation}()->detach());

        $existing->newQueryWithoutScopes()->whereKey($this->key)->toBase()->delete();
    }
}
