<?php

declare(strict_types=1);

namespace Replay\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Replay\Contracts\RecordsReplays;
use Replay\Replayable;

class UuidPost extends Model implements RecordsReplays
{
    use HasUuids;
    use Replayable;

    protected $table = 'uuid_posts';

    protected $guarded = [];
}
