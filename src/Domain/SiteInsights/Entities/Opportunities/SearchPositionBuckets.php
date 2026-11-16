<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Opportunities;

use DDD\Domain\Base\Entities\ObjectSet;

/**
 * The position buckets of weakClickRateForPosition per device and for all devices, with their row counts (why a
 * result is empty with `thinBaseline`).
 *
 * @property SearchPositionBucket[] $elements
 * @method SearchPositionBucket[] getElements()
 * @method SearchPositionBucket|null first()
 */
class SearchPositionBuckets extends ObjectSet
{
    /**
     * @param string $device
     * @param float $positionFrom
     * @return SearchPositionBucket|null
     */
    public function findBucket(string $device, float $positionFrom): ?SearchPositionBucket
    {
        return $this->getByUniqueKey(SearchPositionBucket::uniqueKeyStatic($device . '_' . $positionFrom));
    }
}
