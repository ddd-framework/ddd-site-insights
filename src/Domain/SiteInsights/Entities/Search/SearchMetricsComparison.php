<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Search;

use DDD\Domain\Base\Entities\ValueObject;
use DDD\Infrastructure\Base\DateTime\Date;

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

    /** @var Date|null Series only: the day of the comparison window this day is compared with (aligned by offset) */
    public ?Date $previousDate;

    /** @var SearchPartError|null Why the counterpart could not be read (counterpartState failed) */
    public ?SearchPartError $error;

    /**
     * Compares metrics of the window with the counterpart the provider reported. Without an observed counterpart the
     * state is notObserved and no delta is set (never shown as zero). With equal window lengths the click and
     * impression deltas are absolute; otherwise (a partial comparison) they are per-day averages. Click rate and
     * position deltas come from the aggregates themselves.
     *
     * @param SearchMetrics $currentMetrics
     * @param SearchMetrics|null $previousMetrics null or without data: not observed
     * @param int $currentDays days of the window
     * @param int $previousDays days of the comparison window
     * @param bool $comparisonIsPartial
     * @return static
     */
    public static function createFromMetrics(SearchMetrics $currentMetrics, ?SearchMetrics $previousMetrics, int $currentDays, int $previousDays, bool $comparisonIsPartial): static
    {
        $searchMetricsComparison = new static();
        $searchMetricsComparison->current = $currentMetrics;
        if ($previousMetrics === null || !$previousMetrics->hasData) {
            $searchMetricsComparison->counterpartState = self::COUNTERPART_NOT_OBSERVED;
            return $searchMetricsComparison;
        }
        $searchMetricsComparison->counterpartState = self::COUNTERPART_OBSERVED;
        $searchMetricsComparison->previous = $previousMetrics;
        if (!$comparisonIsPartial && $currentDays === $previousDays) {
            $searchMetricsComparison->clicksDelta = $currentMetrics->clicks - $previousMetrics->clicks;
            $searchMetricsComparison->impressionsDelta = $currentMetrics->impressions - $previousMetrics->impressions;
        } else {
            $searchMetricsComparison->perDay = true;
            $searchMetricsComparison->clicksPerDayDelta = $currentMetrics->clicks / max(1, $currentDays) - $previousMetrics->clicks / max(1, $previousDays);
            $searchMetricsComparison->impressionsPerDayDelta = $currentMetrics->impressions / max(1, $currentDays) - $previousMetrics->impressions / max(1, $previousDays);
        }
        $searchMetricsComparison->ctrDelta = $currentMetrics->ctr - $previousMetrics->ctr;
        if (isset($currentMetrics->position, $previousMetrics->position)) {
            $searchMetricsComparison->positionDelta = $currentMetrics->position - $previousMetrics->position;
        }
        return $searchMetricsComparison;
    }

    /**
     * @param SearchMetrics $currentMetrics
     * @param string $counterpartState notRequested or failed
     * @param SearchPartError|null $searchPartError the cause of a failed counterpart
     * @return static metrics of the window without a counterpart and without deltas
     */
    public static function createWithoutCounterpart(SearchMetrics $currentMetrics, string $counterpartState, ?SearchPartError $searchPartError = null): static
    {
        $searchMetricsComparison = new static();
        $searchMetricsComparison->current = $currentMetrics;
        $searchMetricsComparison->counterpartState = $counterpartState;
        if ($searchPartError !== null) {
            $searchMetricsComparison->error = $searchPartError;
        }
        return $searchMetricsComparison;
    }

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic((string)spl_object_id($this));
    }
}
