<?php

declare(strict_types=1);

namespace Replay\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @template TRelatedModel of Model
 * @template TDeclaringModel of Model
 *
 * @extends BelongsToMany<TRelatedModel, TDeclaringModel>
 */
class ReplayableBelongsToMany extends BelongsToMany
{
    /** @use RecordsPivotReplays<TRelatedModel, TDeclaringModel> */
    use RecordsPivotReplays;
}
