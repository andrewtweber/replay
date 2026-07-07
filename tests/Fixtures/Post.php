<?php

namespace Replay\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Replay\Replayable;

class Post extends Model
{
    use Replayable;

    protected $guarded = [];

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withPivot('note');
    }
}
