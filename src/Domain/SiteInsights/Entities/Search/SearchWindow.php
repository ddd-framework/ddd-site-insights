<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Search;

use DDD\Domain\Base\Entities\ValueObject;
use DDD\Infrastructure\Base\DateTime\Date;

/**
 * The window an answer covers: inclusive days as the provider counts them (`dayTimeZone`), the data state, and up to
 * which day the provider's data is final.
 */
class SearchWindow extends ValueObject
{
    public const string DATA_THROUGH_DATE_SOURCE_PROBE = 'probe';
    public const string DATA_THROUGH_DATE_SOURCE_FALLBACK = 'fallback';

    /** @var Date First day, inclusive */
    public Date $from;

    /** @var Date Last day, inclusive */
    public Date $to;

    /** @var int Days of the window, both ends included */
    public int $days;

    /** @var string|null The preset the window was built from (7d, 28d, 90d, 6m, 12m, 16m); unset for explicit days */
    public ?string $preset;

    /** @var bool Whether `from` was raised to the earliest day the provider keeps */
    public bool $windowClamped = false;

    /** @var string The provider's day zone, e.g. America/Los_Angeles */
    public string $dayTimeZone;

    /** @var string final (settled days) or all (fresh days that may still change) */
    public string $dataState;

    /** @var string Search type, e.g. web */
    public string $searchType;

    /** @var Date|null The last day with final data */
    public ?Date $dataThroughDate;

    /** @var string|null probe (read from the provider) or fallback (today − 3 in the provider's zone) */
    public ?string $dataThroughDateSource;

    /** @var Date|null The first incomplete day (dataState all) */
    public ?Date $firstIncompleteDate;

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic((string)spl_object_id($this));
    }
}
