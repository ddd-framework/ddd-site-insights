<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Google;

use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;
use DDD\Domain\Base\Entities\ObjectSet;
use DDD\Domain\SiteInsights\Repo\Argus\Google\ArgusGoogleSearchAnalyticsReports;

/**
 * Search Analytics questions asked together (one call round).
 *
 * @property GoogleSearchAnalyticsReport[] $elements
 * @method GoogleSearchAnalyticsReport[] getElements()
 * @method GoogleSearchAnalyticsReport|null first()
 */
#[LazyLoadRepo(LazyLoadRepo::ARGUS, ArgusGoogleSearchAnalyticsReports::class)]
class GoogleSearchAnalyticsReports extends ObjectSet
{
}
