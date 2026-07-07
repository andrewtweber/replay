# Replay

[![Tests](https://github.com/andrewtweber/replay/actions/workflows/tests.yml/badge.svg)](https://github.com/andrewtweber/replay/actions/workflows/tests.yml)

Track changes to Laravel Eloquent models as **replayable steps** — then replay them to reproduce any state.

Instead of only auditing what changed, Replay records each create, update, delete, and many-to-many change as a step that can be re-executed. Deleted a model? Replay its history to bring it back exactly as it was. Want the state as of last Tuesday? Replay up to that step.

## Installation

```bash
composer require andrewtweber/replay
```

Run the migrations (they load automatically, or publish them to customize):

```bash
php artisan migrate

# optional
php artisan vendor:publish --tag=replay-migrations
php artisan vendor:publish --tag=replay-config
```

## Tracking changes

Add the `Replayable` trait to any model:

```php
use Illuminate\Database\Eloquent\Model;
use Replay\Replayable;

class Post extends Model
{
    use Replayable;

    public function tags()
    {
        return $this->belongsToMany(Tag::class)->withPivot('note');
    }
}
```

That's it. Steps are recorded automatically via Laravel's model events:

| You do | Recorded step | `new_values` |
|---|---|---|
| `Post::create([...])` | `created` | all attributes (including the key) |
| `$post->update([...])` | `updated` | only the changed attributes |
| `$post->delete()` | `deleted` | `deleted_at` + `updated_at` when soft deleting |
| `$post->restore()` | `restored` | `deleted_at => null` + `updated_at` |
| `$post->forceDelete()` | `force_deleted` | — |

### Many-to-many relationships

Because `Replayable` swaps in replay-aware relation classes, pivot changes on `belongsToMany` and `morphToMany` relations are recorded automatically too — no extra setup:

```php
$post->tags()->attach($tag, ['note' => 'first']); // "attached"  step on $post
$post->tags()->detach();                          // "detached" step (records the actual ids removed)
$post->tags()->updateExistingPivot($tag->id, [...]); // "pivot_updated" step
$post->tags()->sync([...]);                       // recorded as its attach/detach steps
```

`sync()` and `toggle()` are captured automatically because Laravel implements them in terms of `attach`/`detach`/`updateExistingPivot`.

### Inspecting history

```php
$post->replays;                 // MorphMany of Replay\Replay steps
$step->event;                   // "created", "updated", "attached", ...
$step->relation;                // "tags" for pivot steps, null otherwise
$step->new_values;              // what changed (all a replay needs)
$step->old_values;              // previous values, stored for convenience/diffs
$step->user;                    // who did it (when authenticated)
```

Replaying only ever needs `new_values` — any earlier state is reachable by replaying from the start. `old_values` are stored purely for display/diffing and can be turned off with `replay.store_old_values = false`.

### Manual recording

Record custom steps yourself:

```php
$post->recordReplay('published', ['channel' => 'rss']);
```

Custom events need a handler to be replayable:

```php
use Replay\Replayer;

Replayer::extend('published', function ($model, $step, $replayer) {
    $model->update(['published_at' => $step->new_values['at']]);
});
```

### Pausing recording

```php
Replayer::withoutRecording(function () {
    // nothing in here is recorded
    $post->update(['title' => 'untracked']);
});
```

### Excluding attributes

Globally via the `replay.exclude` config, or per model:

```php
class Post extends Model
{
    use Replayable;

    protected array $replayExclude = ['view_count'];
}
```

## Replaying

`Replayer` re-executes the recorded steps against the database, reproducing the exact sequence of creates, updates, deletes and pivot changes:

```php
use Replay\Replayer;

// Rebuild a deleted model from scratch, in its final state:
$post = Replayer::for(Post::class, $id)->replay();

// Reproduce the state at any point in time:
$post = Replayer::for(Post::class, $id)->upTo($step)->replay();

// Replay over a model that still exists (deletes it and its recorded
// pivot rows first):
$post = Replayer::for(Post::class, $id)->overwriting()->upTo($step)->replay();

// Or from an instance:
$post->replayer()->upTo($step)->replay();
```

### With or without events

Replaying is **quiet by default** — no model events fire (`saveQuietly`, `deleteQuietly`, ...). Opt in to events if you want observers to react:

```php
Replayer::for(Post::class, $id)->withEvents()->replay();
```

Either way, **recording is always paused during a replay**, so replaying never records new steps.

### Fidelity

Steps store raw database values and are applied with `setRawAttributes`, so timestamps, casts, and the primary key survive a replay byte-for-byte. Soft-delete replays restore the original `deleted_at` timestamp.

One known drift: pivot rows created with `withTimestamps()` get fresh `created_at`/`updated_at` values on replay, since Laravel stamps them at attach time.

## Configuration

```php
return [
    'table' => 'replays',          // storage table
    'store_old_values' => true,    // keep old values for diffs (not needed to replay)
    'exclude' => [],               // attributes never recorded, on any model
    'track_user' => true,          // store the authenticated user on each step
];
```

> **Note:** the migration uses numeric `morphs()` columns. If your models use UUID/ULID keys, publish the migration and switch to `uuidMorphs()`/`ulidMorphs()`.

## Testing

```bash
composer test
```

## License

MIT
