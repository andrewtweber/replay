<?php

declare(strict_types=1);

namespace Replay\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * @template TRelatedModel of Model
 * @template TDeclaringModel of Model
 *
 * @extends MorphToMany<TRelatedModel, TDeclaringModel>
 */
class ReplayableMorphToMany extends MorphToMany
{
    /** @use RecordsPivotReplays<TRelatedModel, TDeclaringModel> */
    use RecordsPivotReplays;
}
