<?php

namespace Replay\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Replay\Replayable;

class UuidPost extends Model
{
    use HasUuids;
    use Replayable;

    protected $table = 'uuid_posts';

    protected $guarded = [];
}
