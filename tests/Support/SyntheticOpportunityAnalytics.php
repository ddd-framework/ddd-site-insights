<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Support;

use GuzzleHttp\Psr7\Response;

/**
 * Invented Search Analytics answers for the opportunity rules: each test sets the rows of a dimension list per period
 * (keys, clicks, impressions, position); the answer applies Google's filters (equals, contains, includingRegex),
 * startRow and rowLimit, and keeps the order given (provider order). The probe answers with its fixture. No real data.
 */
class SyntheticOpportunityAnalytics
{
    public const string PERIOD_WINDOW = 'window';
    public const string PERIOD_COMPARISON = 'comparison';

    /** @var array[] rows by period and dimension list (`query`, `query|device`, …): [keys, clicks, impressions, position] */
    public array $rowsByPeriodAndDimensions = [];

    /** @var int[] status codes to answer with, by dimension list */
    public array $failingStatusByDimensions = [];

    /** @var array[] the request bodies of the data calls, in order */
    public array $dataRequestBodies = [];

    /**
     * @param string $period window or comparison
     * @param string[] $dimensions
     * @param array[] $rows [keys, clicks, impressions, position]
     * @return void
     */
    public function setRows(string $period, array $dimensions, array $rows): void
    {
        $this->rowsByPeriodAndDimensions[$period][implode('|', $dimensions)] = $rows;
    }

    public function answer(array $requestBody): Response
    {
        if (SyntheticSearchAnalytics::isProbe($requestBody)) {
            return GoogleFixtures::response('probe-ten-days');
        }
        $this->dataRequestBodies[] = $requestBody;
        $dimensionsKey = implode('|', $requestBody['dimensions'] ?? []);
        if (isset($this->failingStatusByDimensions[$dimensionsKey])) {
            return SyntheticSearchAnalytics::respond([])->withStatus($this->failingStatusByDimensions[$dimensionsKey]);
        }
        $period = SyntheticSearchAnalytics::isComparisonWindow($requestBody) ? self::PERIOD_COMPARISON : self::PERIOD_WINDOW;
        $answerRows = [];
        foreach ($this->rowsByPeriodAndDimensions[$period][$dimensionsKey] ?? [] as [$keys, $clicks, $impressions, $position]) {
            if (!$this->matchesFilters($requestBody['dimensions'] ?? [], $keys, $requestBody)) {
                continue;
            }
            $answerRows[] = ['keys' => $keys, 'clicks' => $clicks, 'impressions' => $impressions, 'ctr' => $impressions > 0 ? $clicks / $impressions : 0, 'position' => $position];
        }
        $answerRows = array_slice($answerRows, (int)($requestBody['startRow'] ?? 0), (int)($requestBody['rowLimit'] ?? 1000));
        return SyntheticSearchAnalytics::respond($answerRows);
    }

    /** @return array[] the data calls' bodies with these dimensions */
    public function findBodiesWithDimensions(string ...$dimensions): array
    {
        return array_values(array_filter($this->dataRequestBodies, static fn (array $requestBody): bool => ($requestBody['dimensions'] ?? []) === $dimensions));
    }

    /**
     * @param string[] $dimensions
     * @param string[] $keys
     */
    protected function matchesFilters(array $dimensions, array $keys, array $requestBody): bool
    {
        foreach ($requestBody['dimensionFilterGroups'][0]['filters'] ?? [] as $filter) {
            $dimensionIndex = array_search($filter['dimension'], $dimensions, true);
            if ($dimensionIndex === false) {
                continue;
            }
            $key = $keys[$dimensionIndex];
            $expression = (string)$filter['expression'];
            $matches = match ($filter['operator']) {
                'equals' => strcasecmp($key, $expression) === 0,
                'contains' => stripos($key, $expression) !== false,
                'includingRegex' => preg_match('~' . str_replace('~', '\\~', $expression) . '~u', $key) === 1,
                default => true,
            };
            if (!$matches) {
                return false;
            }
        }
        return true;
    }
}
