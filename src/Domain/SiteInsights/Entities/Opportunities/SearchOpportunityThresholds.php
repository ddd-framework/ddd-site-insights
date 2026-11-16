<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Opportunities;

use DDD\Domain\Base\Entities\ValueObject;

/**
 * The effective thresholds of one kind: only the rule's own are set; clicks and impressions already scaled to the
 * window (`windowDays`).
 */
class SearchOpportunityThresholds extends ValueObject
{
    /** @var int Days of the window the thresholds are scaled to */
    public int $windowDays;

    /** @var float|null Lowest position (strikingDistanceQuery) */
    public ?float $positionFrom;

    /** @var float|null Highest position (strikingDistanceQuery) */
    public ?float $positionTo;

    /** @var int|null Fewest impressions of a row, scaled */
    public ?int $minImpressions;

    /** @var bool|null Whether brand queries are left out */
    public ?bool $excludeBrand;

    /** @var int|null Fewest rows per position bucket (weakClickRateForPosition) */
    public ?int $minRowsPerBucket;

    /** @var float|null Lowest baseline click rate that judges a row (weakClickRateForPosition) */
    public ?float $minBaselineClickRate;

    /** @var float|null Share of the bucket's click rate below which a row is a hit (weakClickRateForPosition) */
    public ?float $ratio;

    /** @var bool|null Whether a thin device bucket takes the baseline of all devices (weakClickRateForPosition) */
    public ?bool $fallbackToAllDevices;

    /** @var int|null Top queries asked (severalPagesForQuery) */
    public ?int $topQueries;

    /** @var int|null Fewest impressions per page, scaled (severalPagesForQuery) */
    public ?int $minImpressionsPerPage;

    /** @var float|null Smallest share of the query's impressions per page (severalPagesForQuery) */
    public ?float $minShare;

    /** @var int|null Fewest pages (severalPagesForQuery) */
    public ?int $minPages;

    /** @var int|null Top keys per period (rising and declining) */
    public ?int $candidatesPerPeriod;

    /** @var int|null Fewest clicks in either period, scaled (rising and declining) */
    public ?int $minClicksEitherPeriod;

    /** @var int|null Smallest change of clicks, scaled (rising and declining) */
    public ?int $minAbsoluteDelta;

    /** @var float|null Smallest relative change (rising and declining) */
    public ?float $minRelativeDelta;

    /** @var int|null Most results */
    public ?int $maxResults;

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic((string)spl_object_id($this));
    }
}
