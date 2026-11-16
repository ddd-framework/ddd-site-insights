<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Opportunities;

use DDD\Domain\Base\Entities\ValueObject;
use DDD\Domain\SiteInsights\Entities\Search\SearchMetrics;
use DDD\Domain\SiteInsights\Entities\Search\SearchMetricsComparison;
use DDD\Domain\SiteInsights\Entities\Search\SearchPerformanceRows;

/**
 * One candidate a rule found, with the rows it is based on. A candidate, not a diagnosis: its kind result's `note` says
 * what it does not prove. The thresholds and the coverage of the source are on the kind result.
 */
class SearchOpportunity extends ValueObject
{
    public const string KIND_STRIKING_DISTANCE_QUERY = 'strikingDistanceQuery';
    public const string KIND_WEAK_CLICK_RATE_FOR_POSITION = 'weakClickRateForPosition';
    public const string KIND_SEVERAL_PAGES_FOR_QUERY = 'severalPagesForQuery';
    public const string KIND_RISING_QUERY = 'risingQuery';
    public const string KIND_DECLINING_QUERY = 'decliningQuery';
    public const string KIND_RISING_PAGE = 'risingPage';
    public const string KIND_DECLINING_PAGE = 'decliningPage';

    /** @var string[] Every kind, in the order they are evaluated */
    public const array KINDS = [
        self::KIND_STRIKING_DISTANCE_QUERY,
        self::KIND_WEAK_CLICK_RATE_FOR_POSITION,
        self::KIND_SEVERAL_PAGES_FOR_QUERY,
        self::KIND_RISING_QUERY,
        self::KIND_DECLINING_QUERY,
        self::KIND_RISING_PAGE,
        self::KIND_DECLINING_PAGE,
    ];

    public const string SUBJECT_QUERY = 'query';
    public const string SUBJECT_PAGE = 'page';
    public const string SUBJECT_QUERY_PAGE = 'queryPage';

    /** @var string The rule that found it */
    public string $kind;

    /** @var string query, page or queryPage */
    public string $subjectType;

    /** @var string|null The query */
    public ?string $query;

    /** @var string|null The page */
    public ?string $page;

    /** @var string|null The device (weakClickRateForPosition) */
    public ?string $device;

    /** @var SearchMetrics The subject's metrics in the window */
    public SearchMetrics $metrics;

    /** @var SearchMetricsComparison|null The comparison with the previous period (rising and declining kinds) */
    public ?SearchMetricsComparison $comparison;

    /** @var SearchClickRateBaseline|null The baseline the click rate was judged against (weakClickRateForPosition) */
    public ?SearchClickRateBaseline $baseline;

    /** @var float|null impressions × (baseline − click rate): the clicks the row misses against its baseline; the order of weakClickRateForPosition */
    public ?float $missingClicks;

    /** @var SearchPerformanceRows The rows the result is based on (window and coverage: see the kind result) */
    public SearchPerformanceRows $evidenceRows;

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic((string)spl_object_id($this));
    }
}
