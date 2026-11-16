<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Google;

use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;
use DDD\Domain\SiteInsights\Repo\Argus\Google\ArgusGoogleSearchAnalyticsReport;
use DDD\Domain\SiteInsights\Utils\SearchDays;
use DDD\Infrastructure\Base\DateTime\Date;

/**
 * One Search Analytics question to Google and Google's answer, in Google's own shape. Carries the question and the
 * answer only: never a token or a key (an Argus cache entry is this object serialised with hidden properties).
 */
#[LazyLoadRepo(LazyLoadRepo::ARGUS, ArgusGoogleSearchAnalyticsReport::class)]
class GoogleSearchAnalyticsReport extends GoogleProviderAnswer
{
    public const string RESOURCE = 'searchAnalytics';

    /** @var string[] Google dimensions in the module's canonical order (the order of `keys` in every row) */
    public const array CANONICAL_DIMENSION_ORDER = ['date', 'query', 'page', 'country', 'device', 'searchAppearance'];

    /** @var string Search Console property: `https://example.test/` or `sc-domain:example.test` */
    public string $siteUrl;

    /** @var Date First day, inclusive, as Google counts days (Pacific Time) */
    public Date $startDate;

    /** @var Date Last day, inclusive */
    public Date $endDate;

    /** @var string[] Requested dimensions; asked and stored in CANONICAL_DIMENSION_ORDER; empty = totals */
    public array $dimensions = [];

    /** @var string Google `type`: web, image, video, news, discover, googleNews */
    public string $searchType;

    /** @var string Google `dataState`: final or all */
    public string $dataState;

    /** @var string Google `aggregationType`: auto, byPage, byProperty */
    public string $aggregationType;

    /** @var GoogleSearchAnalyticsFilters Filters of the one `and` group, sorted by dimension, operator, expression */
    public GoogleSearchAnalyticsFilters $filters;

    /** @var int Google `rowLimit`, 1–25,000 */
    public int $rowLimit;

    /** @var int Google `startRow`, 0-based */
    public int $startRow = 0;

    /** @var GoogleSearchAnalyticsRows|null Google's `rows`; empty set when Google sent none */
    public ?GoogleSearchAnalyticsRows $rows;

    /** @var string|null Google's `responseAggregationType` (byPage, byProperty, …); unset when Google sent none */
    public ?string $responseAggregationType;

    /** @var Date|null Google's `metadata.first_incomplete_date` (dataState all, grouped by date, incomplete days only) */
    public ?Date $firstIncompleteDate;

    /** @var string|null Google's `metadata.first_incomplete_hour` (hourly_all only; not used in version 1) */
    public ?string $firstIncompleteHour;

    /** @return string[] the requested dimensions in CANONICAL_DIMENSION_ORDER */
    public function getCanonicalDimensions(): array
    {
        return array_values(array_filter(
            self::CANONICAL_DIMENSION_ORDER,
            fn (string $dimension): bool => in_array($dimension, $this->dimensions, true)
        ));
    }

    /** The filters in filter order (an empty set without filters) */
    public function getSortedFilters(): GoogleSearchAnalyticsFilters
    {
        return isset($this->filters) ? $this->filters->buildSorted() : new GoogleSearchAnalyticsFilters();
    }

    protected function buildQuestionParts(): array
    {
        return [
            'property' => $this->siteUrl,
            'startDate' => SearchDays::fromDate($this->startDate),
            'endDate' => SearchDays::fromDate($this->endDate),
            'dimensions' => $this->getCanonicalDimensions(),
            'type' => $this->searchType,
            'dataState' => $this->dataState,
            'aggregationType' => $this->aggregationType,
            'filters' => array_map(
                static fn (GoogleSearchAnalyticsFilter $googleSearchAnalyticsFilter): array => [$googleSearchAnalyticsFilter->dimension, $googleSearchAnalyticsFilter->operator, $googleSearchAnalyticsFilter->expression],
                array_values($this->getSortedFilters()->getElements())
            ),
            'rowLimit' => $this->rowLimit,
            'startRow' => $this->startRow,
        ];
    }

    /**
     * @return array Google's request body for this question (also the body of a recorded run: holds no secret)
     */
    public function buildRequestBody(): array
    {
        $requestBody = [
            'startDate' => SearchDays::fromDate($this->startDate),
            'endDate' => SearchDays::fromDate($this->endDate),
        ];
        $canonicalDimensions = $this->getCanonicalDimensions();
        if ($canonicalDimensions) {
            $requestBody['dimensions'] = $canonicalDimensions;
        }
        $requestBody['type'] = $this->searchType;
        $requestBody['dataState'] = $this->dataState;
        $requestBody['aggregationType'] = $this->aggregationType;
        $requestBody['rowLimit'] = $this->rowLimit;
        $requestBody['startRow'] = $this->startRow;
        $sortedFilters = array_values($this->getSortedFilters()->getElements());
        if ($sortedFilters) {
            $requestBody['dimensionFilterGroups'] = [[
                'groupType' => 'and',
                'filters' => array_map(
                    static fn (GoogleSearchAnalyticsFilter $googleSearchAnalyticsFilter): array => [
                        'dimension' => $googleSearchAnalyticsFilter->dimension,
                        'operator' => $googleSearchAnalyticsFilter->operator,
                        'expression' => $googleSearchAnalyticsFilter->expression,
                    ],
                    $sortedFilters
                ),
            ]];
        }
        return $requestBody;
    }
}
