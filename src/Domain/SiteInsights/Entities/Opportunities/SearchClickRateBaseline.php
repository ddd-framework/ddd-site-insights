<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Opportunities;

use DDD\Domain\Base\Entities\ValueObject;

/** The click rate of a position bucket a row of weakClickRateForPosition was judged against */
class SearchClickRateBaseline extends ValueObject
{
    /** The baseline of the row's own device */
    public const string SCOPE_DEVICE = 'device';

    /** The device bucket had too few rows: the baseline of all devices of the position bucket */
    public const string SCOPE_ALL_DEVICES = 'allDevices';

    /** @var float Lowest position of the bucket (inclusive, on the rounded-down position) */
    public float $positionFrom;

    /** @var float Upper bound of the bucket (exclusive) */
    public float $positionTo;

    /** @var string device or allDevices */
    public string $baselineScope;

    /** @var float Σ clicks ÷ Σ impressions of the bucket's rows */
    public float $clickRate;

    /** @var int Rows the baseline is computed from */
    public int $rowCount;

    /** @var float The row's click rate must be below ratio × clickRate */
    public float $ratio;

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic((string)spl_object_id($this));
    }
}
