<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Google;

use DDD\Domain\Base\Entities\ObjectSet;

/**
 * Google's `rows` of one Search Analytics answer.
 *
 * @property GoogleSearchAnalyticsRow[] $elements
 * @method GoogleSearchAnalyticsRow[] getElements()
 * @method GoogleSearchAnalyticsRow|null first()
 */
class GoogleSearchAnalyticsRows extends ObjectSet
{
}
