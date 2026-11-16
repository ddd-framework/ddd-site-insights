<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Budgets;

use DDD\Domain\Base\Entities\ObjectSet;

/**
 * The counters of one quota.
 *
 * @property SiteInsightsBudgetCounter[] $elements
 * @method SiteInsightsBudgetCounter[] getElements()
 * @method SiteInsightsBudgetCounter|null first()
 */
class SiteInsightsBudgetCounters extends ObjectSet
{
}
