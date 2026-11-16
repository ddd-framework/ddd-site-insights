<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Sites;

use DDD\Domain\Base\Entities\ObjectSet;

/**
 * The page groups of a site.
 *
 * @property InsightsPageGroup[] $elements
 * @method InsightsPageGroup[] getElements()
 * @method InsightsPageGroup|null first()
 * @method InsightsPageGroup|null getByUniqueKey(string $uniqueKey)
 */
class InsightsPageGroups extends ObjectSet
{
}
