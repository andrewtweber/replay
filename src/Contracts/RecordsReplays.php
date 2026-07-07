<?php

declare(strict_types=1);

namespace Replay\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Replay\Replay;
use Replay\Replayer;

/**
 * Implemented by models that record their changes as replayable steps.
 *
 * The {@see \Replay\Replayable} trait provides the implementation; a model
 * must also implement this interface so related pivot changes can be recorded
 * against it.
 *
 * @phpstan-require-extends Model
 */
interface RecordsReplays
{
    /**
     * All recorded steps for this model.
     *
     * @return MorphMany<Replay, $this>
     */
    public function replays(): MorphMany; // @phpstan-ignore generics.notSubtype (implementers are Eloquent models via @phpstan-require-extends, so $this satisfies MorphMany's declaring-model bound)

    /**
     * Record a step for this model.
     *
     * @param  array<string, mixed>  $newValues
     * @param  array<string, mixed>  $oldValues
     */
    public function recordReplay(string $event, array $newValues = [], ?string $relation = null, array $oldValues = []): ?Replay;

    /**
     * A Replayer instance for this model, ready to re-execute its history.
     */
    public function replayer(): Replayer;

    /**
     * Strip attributes that should not be recorded.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function replayableAttributes(array $attributes): array;
}
