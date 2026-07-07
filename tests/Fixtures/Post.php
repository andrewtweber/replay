<?php

declare(strict_types=1);

namespace Replay\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Replay\Contracts\RecordsReplays;
use Replay\Replayable;

class Post extends Model implements RecordsReplays
{
    use Replayable;

    protected $guarded = [];

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withPivot('note');
    }
}
