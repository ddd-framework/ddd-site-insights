<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Search;

use DDD\Domain\Base\Entities\ObjectSet;

/**
 * The parts of an export in order.
 *
 * @property SearchExportDay[] $elements
 * @method SearchExportDay[] getElements()
 * @method SearchExportDay|null first()
 */
class SearchExportDays extends ObjectSet
{
}
