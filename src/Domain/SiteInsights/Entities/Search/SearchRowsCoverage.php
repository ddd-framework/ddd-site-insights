<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Search;

use DDD\Domain\Base\Entities\ValueObject;
use DDD\Infrastructure\Base\DateTime\DateTime;

/** What a list of rows covers: how it was fetched, how far, and what the provider leaves out */
class SearchRowsCoverage extends ValueObject
{
    public const string SORT_SCOPE_PROVIDER = 'provider';
    public const string SORT_SCOPE_FETCHED_ROWS = 'fetchedRows';

    /** @var int Rows the module fetched for this list */
    public int $fetchedRows = 0;

    /** @var int Provider calls made (cache hits not counted) */
    public int $providerCalls = 0;

    /** @var bool Whether every part was served from the cache */
    public bool $fromCache = false;

    /** @var DateTime|null When the provider answered (the oldest part) */
    public ?DateTime $fetchedAt;

    /** @var int Rows asked per call */
    public int $rowLimitPerCall = 0;

    /**
     * @var bool The question has every row there can be: totals (no dimension) or a series asked for every day of its
     *      window. `limitReached` and `providerRowsExhausted` are false then: nothing to qualify about the rows
     */
    public bool $isCompleteByConstruction = false;

    /** @var bool The module stopped (limit, ceiling or budget) while the last page was full */
    public bool $limitReached = false;

    /** @var bool The last page returned fewer rows than asked (not "all rows": the provider keeps top rows only) */
    public bool $providerRowsExhausted = false;

    /** @var string provider (the provider sorted) or fetchedRows (the module sorted the rows it fetched) */
    public string $sortScope = self::SORT_SCOPE_PROVIDER;

    /** @var int Rows dropped across pages because their identity was already seen */
    public int $duplicateRowsDropped = 0;

    /** @var string Aggregation asked: auto, byPage or byProperty */
    public string $requestedAggregation;

    /** @var string Aggregation answered: byPage or byProperty */
    public string $responseAggregation;

    /** @var bool Whether the provider named its aggregation */
    public bool $responseAggregationKnown = false;

    /** @var bool Grouped or filtered by query: rare queries are missing, sums are below the totals */
    public bool $anonymizedQueriesOmitted = false;

    /** @var bool Query × page: the provider drops rows in joint reports */
    public bool $joinedDimensionsMayLoseRows = false;

    /** @var int Counterpart calls made for a comparison */
    public int $counterpartChunks = 0;

    /** @var int Rows whose counterpart was not asked (limits) */
    public int $counterpartsNotRequested = 0;

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic((string)spl_object_id($this));
    }
}
