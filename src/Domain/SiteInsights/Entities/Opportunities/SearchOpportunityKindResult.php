<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Opportunities;

use DDD\Domain\Base\Entities\ValueObject;
use DDD\Domain\SiteInsights\Entities\Search\SearchPartError;
use DDD\Domain\SiteInsights\Entities\Search\SearchPerformanceRows;
use DDD\Domain\SiteInsights\Entities\Search\SearchRowsCoverage;

/** How one kind was evaluated: its results, why it has none, its thresholds and the coverage of its source */
class SearchOpportunityKindResult extends ValueObject
{
    public const string EMPTY_NO_ROWS = 'noRows';
    public const string EMPTY_BELOW_THRESHOLDS = 'belowThresholds';
    public const string EMPTY_WINDOW_TOO_SHORT = 'windowTooShort';
    public const string EMPTY_NO_COMPARISON = 'noComparison';
    public const string EMPTY_THIN_BASELINE = 'thinBaseline';
    public const string EMPTY_SOURCE_FAILED = 'sourceFailed';

    /** @var string The kind */
    public string $kind;

    /** @var int Results of the kind in `elements` of the set */
    public int $resultCount = 0;

    /** @var string|null Why the kind has no result: noRows, belowThresholds, windowTooShort, noComparison, thinBaseline, sourceFailed */
    public ?string $emptyReason;

    /** @var SearchPartError|null Why the kind (or a part of its source) failed; budgetRefused when the operation's call budget did not reach it */
    public ?SearchPartError $error;

    /** @var bool Results come from part of the source only (some calls failed, see `error`) */
    public bool $isPartial = false;

    /** @var SearchOpportunityThresholds The thresholds applied */
    public SearchOpportunityThresholds $thresholds;

    /** @var SearchRowsCoverage|null What the source rows covered (the window's list the rule read) */
    public ?SearchRowsCoverage $sourceCoverage;

    /** @var int Provider calls this kind added (sources shared with an earlier kind are counted there) */
    public int $providerCalls = 0;

    /** @var string What a result of this kind does not prove */
    public string $note;

    /** @var SearchPositionBuckets|null Position buckets with their row counts (weakClickRateForPosition) */
    public ?SearchPositionBuckets $positionBuckets;

    /** @var SearchPerformanceRows|null Keys observed in one period only, never ranked as new or lost (rising and declining) */
    public ?SearchPerformanceRows $observedInOnePeriodOnly;

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic($this->kind ?? '');
    }
}
