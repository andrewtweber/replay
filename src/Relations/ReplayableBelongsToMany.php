<?php

namespace Replay\Relations;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ReplayableBelongsToMany extends BelongsToMany
{
    use RecordsPivotReplays;
}
