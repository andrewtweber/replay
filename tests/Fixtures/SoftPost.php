<?php

declare(strict_types=1);

namespace Replay\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Replay\Contracts\RecordsReplays;
use Replay\Replayable;

class SoftPost extends Model implements RecordsReplays
{
    use Replayable;
    use SoftDeletes;

    protected $table = 'posts';

    protected $guarded = [];
}
