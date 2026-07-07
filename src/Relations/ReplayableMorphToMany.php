<?php

namespace Replay\Relations;

use Illuminate\Database\Eloquent\Relations\MorphToMany;

class ReplayableMorphToMany extends MorphToMany
{
    use RecordsPivotReplays;
}
