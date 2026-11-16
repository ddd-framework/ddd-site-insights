<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Config;

use DDD\Domain\Base\Entities\ObjectSet;

/**
 * The page groups of one configured site, in config order.
 *
 * @property SiteInsightsPageGroupEntry[] $elements
 * @method SiteInsightsPageGroupEntry[] getElements()
 * @method SiteInsightsPageGroupEntry|null first()
 */
class SiteInsightsPageGroupEntries extends ObjectSet
{
}
