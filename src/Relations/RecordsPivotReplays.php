<?php

declare(strict_types=1);

namespace Replay\Relations;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as BaseCollection;
use Replay\Contracts\RecordsReplays;
use Replay\Replayer;

/**
 * Records attach / detach / pivot update operations as replay steps on the
 * parent model. sync() and toggle() are captured automatically because
 * Laravel implements them in terms of these three operations.
 *
 * @template TRelatedModel of Model
 * @template TDeclaringModel of Model
 */
trait RecordsPivotReplays
{
    public function attach($id, array $attributes = [], $touch = true)
    {
        $records = $this->normalizeAttachRecords($id, $attributes);

        parent::attach($id, $attributes, $touch);

        if ($records !== []) {
            $this->recordPivotReplay('attached', $records);
        }
    }

    public function detach($ids = null, $touch = true)
    {
        // When detaching everything, resolve the current ids first so the
        // step records exactly which relations were removed.
        $keys = is_null($ids) ? $this->allRelatedIds()->all() : $this->parseIds($ids);

        $result = parent::detach($ids, $touch);

        if ($keys !== []) {
            $this->recordPivotReplay('detached', array_values($keys));
        }

        return $result;
    }

    public function updateExistingPivot($id, array $attributes, $touch = true)
    {
        $result = parent::updateExistingPivot($id, $attributes, $touch);

        $keys = $this->parseIds($id);

        if ($keys !== []) {
            $this->recordPivotReplay('pivot_updated', [reset($keys) => $attributes]);
        }

        return $result;
    }

    protected function recordPivotReplay(string $event, array $values): void
    {
        if (! Replayer::isRecording()) {
            return;
        }

        $parent = $this->getParent();

        if ($parent instanceof RecordsReplays) {
            // getRelationName() is documented @return string, but the underlying
            // property is nullable; keep the table name as a fallback.
            $parent->recordReplay($event, $values, $this->getRelationName() ?? $this->getTable()); // @phpstan-ignore nullCoalesce.expr
        }
    }

    /**
     * Normalize the many shapes attach() accepts into [id => pivot attributes].
     */
    protected function normalizeAttachRecords($id, array $attributes = []): array
    {
        if ($id instanceof Model) {
            return [$id->{$this->relatedKey} => $attributes];
        }

        if ($id instanceof EloquentCollection) {
            $id = $id->pluck($this->relatedKey)->all();
        }

        if ($id instanceof BaseCollection) {
            $id = $id->all();
        }

        $records = [];

        foreach ((array) $id as $key => $value) {
            if (is_array($value)) {
                $records[$key] = array_merge($attributes, $value);
            } else {
                $records[$value] = $attributes;
            }
        }

        return $records;
    }
}
