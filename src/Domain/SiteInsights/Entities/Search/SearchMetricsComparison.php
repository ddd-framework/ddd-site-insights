<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Search;

use DDD\Domain\Base\Entities\ValueObject;

/**
 * One row or aggregate compared with its counterpart in the comparison window. With a partial comparison (shorter
 * than the window) the click and impression deltas are per-day averages (`perDay`).
 */
class SearchMetricsComparison extends ValueObject
{
    public const string COUNTERPART_OBSERVED = 'observed';
    /** The provider reported no row for the key in the comparison window: zero, below its threshold or anonymised */
    public const string COUNTERPART_NOT_OBSERVED = 'notObserved';
    public const string COUNTERPART_NOT_REQUESTED = 'notRequested';
    public const string COUNTERPART_FAILED = 'failed';

    /** @var SearchMetrics Metrics of the window */
    public SearchMetrics $current;

    /** @var SearchMetrics|null Metrics of the comparison window; unset unless observed */
    public ?SearchMetrics $previous;

    /** @var string observed, notObserved, notRequested or failed */
    public string $counterpartState;

    /** @var int|null Clicks current − previous (equal window lengths) */
    public ?int $clicksDelta;

    /** @var int|null Impressions current − previous (equal window lengths) */
    public ?int $impressionsDelta;

    /** @var float|null Per-day clicks current − previous (partial comparison) */
    public ?float $clicksPerDayDelta;

    /** @var float|null Per-day impressions current − previous (partial comparison) */
    public ?float $impressionsPerDayDelta;

    /** @var float|null Click rate current − previous */
    public ?float $ctrDelta;

    /** @var float|null Position current − previous (negative = better) */
    public ?float $positionDelta;

    /** @var bool Whether the click and impression deltas are per-day averages */
    public bool $perDay = false;

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic((string)spl_object_id($this));
    }
}
