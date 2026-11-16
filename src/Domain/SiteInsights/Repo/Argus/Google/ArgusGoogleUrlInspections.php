<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Repo\Argus\Google;

use DDD\Domain\Base\Repo\Argus\Attributes\ArgusLoad;
use DDD\Domain\Base\Repo\Argus\Traits\ArgusTrait;
use DDD\Domain\Base\Repo\Argus\Utils\ArgusCache;
use DDD\Domain\SiteInsights\Entities\Google\GoogleUrlInspections;

/**
 * The batch root of one call round of URL inspections: no endpoint of its own, never cached. Its timeout applies to
 * every call of the batch; the source service sets it per load from `budgets.batchTimeoutSeconds`.
 *
 * @property ArgusGoogleUrlInspection[] $elements
 * @method ArgusGoogleUrlInspection[] getElements()
 */
#[ArgusLoad(cacheLevel: ArgusCache::CACHELEVEL_NONE)]
class ArgusGoogleUrlInspections extends GoogleUrlInspections
{
    use ArgusTrait;

    /** Root timeout of every call of the batch; overridden per load from budgets.batchTimeoutSeconds */
    public const int DEFAULT_ARGUS_TIMEOUT = 25;
}
