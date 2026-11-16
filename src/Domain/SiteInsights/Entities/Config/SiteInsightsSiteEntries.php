<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Config;

use DDD\Domain\Base\Entities\ObjectSet;

/**
 * The configured site entries, in config order.
 *
 * @property SiteInsightsSiteEntry[] $elements
 * @method SiteInsightsSiteEntry[] getElements()
 * @method SiteInsightsSiteEntry|null first()
 */
class SiteInsightsSiteEntries extends ObjectSet
{
}
