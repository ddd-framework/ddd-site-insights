<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Opportunities;

use DDD\Domain\Base\Entities\ValueObject;

/** One position bucket of weakClickRateForPosition for one device, or for all devices */
class SearchPositionBucket extends ValueObject
{
    /** The bucket over every device */
    public const string DEVICE_ALL = 'all';

    /** Fewer rows than minRowsPerBucket */
    public const string NO_BASELINE_TOO_FEW_ROWS = 'tooFewRows';

    /** The click rate is below minBaselineClickRate */
    public const string NO_BASELINE_BELOW_MIN_BASELINE_CLICK_RATE = 'belowMinBaselineClickRate';

    /** @var float Lowest position (inclusive, on the rounded-down position) */
    public float $positionFrom;

    /** @var float Upper bound (exclusive) */
    public float $positionTo;

    /** @var string desktop, mobile, tablet or all */
    public string $device;

    /** @var int Rows of the bucket with at least the minimum impressions */
    public int $rowCount = 0;

    /** @var float|null Σ clicks ÷ Σ impressions of those rows; unset without rows */
    public ?float $clickRate;

    /**
     * @var bool Whether the bucket can judge rows: at least minRowsPerBucket rows and a click rate of at least
     *      minBaselineClickRate (each row is then judged against the bucket without its own row)
     */
    public bool $hasBaseline = false;

    /** @var string|null Why the bucket has no baseline: tooFewRows or belowMinBaselineClickRate */
    public ?string $noBaselineReason;

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic($this->device . '_' . $this->positionFrom);
    }
}
