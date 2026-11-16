<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Opportunities;

use DDD\Domain\Base\Entities\ValueObject;

/** One position bucket of weakClickRateForPosition for one device, or for all devices */
class SearchPositionBucket extends ValueObject
{
    /** The bucket over every device */
    public const string DEVICE_ALL = 'all';

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

    /** @var bool Whether the bucket has at least minRowsPerBucket rows (a baseline of its own) */
    public bool $hasBaseline = false;

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic($this->device . '_' . $this->positionFrom);
    }
}
