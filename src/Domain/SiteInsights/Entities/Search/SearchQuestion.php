<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Search;

use DDD\Domain\Base\Entities\ValueObject;
use DDD\Domain\SiteInsights\Utils\SearchDays;
use DDD\Infrastructure\Base\DateTime\Date;

/**
 * A neutral question to a search data source: window, dimensions, filters, type, data state, aggregation and the
 * rows asked. Two questions with the same identity are one question (a batch asks it once).
 */
class SearchQuestion extends ValueObject
{
    public const string AGGREGATION_AUTO = 'auto';
    public const string AGGREGATION_BY_PAGE = 'byPage';
    public const string AGGREGATION_BY_PROPERTY = 'byProperty';

    /** @var Date First day, inclusive (provider days) */
    public Date $startDate;

    /** @var Date Last day, inclusive */
    public Date $endDate;

    /** @var string[] Dimensions (date, query, page, country, device, searchAppearance); empty = totals */
    public array $dimensions = [];

    /** @var SearchQuestionFilters Filters, all must match */
    public SearchQuestionFilters $filters;

    /** @var string Search type, e.g. web */
    public string $searchType = SearchWindowRequest::SEARCH_TYPE_WEB;

    /** @var string final or all */
    public string $dataState = SearchWindowRequest::DATA_STATE_FINAL;

    /** @var string auto, byPage or byProperty */
    public string $aggregationType = self::AGGREGATION_AUTO;

    /** @var int Rows asked */
    public int $rowLimit = 1000;

    /** @var int First row, 0-based */
    public int $startRow = 0;

    /** @var Date|null The last final day when known: a final window ending on or before it is cached longer (not part of the identity) */
    public ?Date $dataThroughDate;

    /** @var bool Whether this is the availability probe (cached `cache.probeTtlSeconds`; not part of the identity) */
    public bool $isAvailabilityProbe = false;

    /** @return string[] the dimensions sorted for the identity */
    public function getSortedDimensions(): array
    {
        $sortedDimensions = array_values(array_unique($this->dimensions));
        sort($sortedDimensions);
        return $sortedDimensions;
    }

    /** The filters in filter order (an empty set without filters) */
    public function getSortedFilters(): SearchQuestionFilters
    {
        return isset($this->filters) ? $this->filters->buildSorted() : new SearchQuestionFilters();
    }

    /** @return string the normalised JSON of the question */
    public function buildNormalizedJson(): string
    {
        return (string)json_encode([
            'startDate' => SearchDays::fromDate($this->startDate),
            'endDate' => SearchDays::fromDate($this->endDate),
            'dimensions' => $this->getSortedDimensions(),
            'filters' => array_map(
                static fn (SearchQuestionFilter $searchQuestionFilter): array => [$searchQuestionFilter->dimension, $searchQuestionFilter->operator, $searchQuestionFilter->expression],
                array_values($this->getSortedFilters()->getElements())
            ),
            'type' => $this->searchType,
            'dataState' => $this->dataState,
            'aggregationType' => $this->aggregationType,
            'rowLimit' => $this->rowLimit,
            'startRow' => $this->startRow,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic(hash('sha256', $this->buildNormalizedJson()));
    }
}
