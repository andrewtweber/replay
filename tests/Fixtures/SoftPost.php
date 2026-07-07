<?php

namespace Replay\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Replay\Replayable;

class SoftPost extends Model
{
    use Replayable;
    use SoftDeletes;

    protected $table = 'posts';

    protected $guarded = [];
}
