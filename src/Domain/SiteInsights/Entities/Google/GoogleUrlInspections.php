<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Google;

use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;
use DDD\Domain\Base\Entities\ObjectSet;
use DDD\Domain\SiteInsights\Repo\Argus\Google\ArgusGoogleUrlInspections;

/**
 * URL inspections asked together (one call round).
 *
 * @property GoogleUrlInspection[] $elements
 * @method GoogleUrlInspection[] getElements()
 * @method GoogleUrlInspection|null first()
 */
#[LazyLoadRepo(LazyLoadRepo::ARGUS, ArgusGoogleUrlInspections::class)]
class GoogleUrlInspections extends ObjectSet
{
}
