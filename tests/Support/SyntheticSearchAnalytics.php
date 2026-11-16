<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Support;

use GuzzleHttp\Psr7\Response;

/**
 * Invented Search Analytics answers computed from the question (no real data): every dimension has a deterministic
 * list of keys on example.test, clicks fall with the row index, the comparison window (any window starting before
 * {@see CURRENT_WINDOW_FIRST_DAY}) has 10 clicks less per row. Filters of the question are applied like Google.
 */
class SyntheticSearchAnalytics
{
    /** The first day of the default 28-day window ending on the probe's last day 2026-11-13 */
    public const string CURRENT_WINDOW_FIRST_DAY = '2026-10-17';

    /** @var string[] */
    public const array COUNTRIES = ['deu', 'usa', 'fra', 'esp', 'ita', 'aut', 'che', 'nld'];

    /** @var string[] */
    public const array DEVICES = ['DESKTOP', 'MOBILE', 'TABLET'];

    /** @var int Rows of a dimension that exist (Google answers fewer when a question asks beyond them) */
    public int $availableRows = 30;

    /** @var string[] Keys Google reports no row for in the comparison window */
    public array $keysMissingInComparison = [];

    /** @var string[] Days the window's series has no row for */
    public array $daysWithoutData = [];

    /** @var bool The comparison window has no row at all (a period without data) */
    public bool $comparisonWindowIsEmpty = false;

    /** @var string|null `responseAggregationType` of every answer */
    public ?string $responseAggregationType = null;

    public static function isProbe(array $requestBody): bool
    {
        return ($requestBody['dimensions'] ?? []) === ['date'] && ($requestBody['rowLimit'] ?? 0) === 11;
    }

    public static function isComparisonWindow(array $requestBody): bool
    {
        return strcmp((string)($requestBody['startDate'] ?? ''), self::CURRENT_WINDOW_FIRST_DAY) < 0;
    }

    /** @return Response the answer to the question as Google would give it (JSON with Content-Length) */
    public function answer(array $requestBody): Response
    {
        if (self::isProbe($requestBody)) {
            return GoogleFixtures::response('probe-ten-days');
        }
        $dimensions = $requestBody['dimensions'] ?? [];
        $isComparison = self::isComparisonWindow($requestBody);
        if ($isComparison && $this->comparisonWindowIsEmpty) {
            return self::respond([], $this->responseAggregationType);
        }
        $rows = [];
        if ($dimensions === []) {
            $rows[] = $this->buildRow([], $isComparison ? 800 : 1000, 20000, 7.5);
        } elseif ($dimensions === ['date']) {
            $day = (string)$requestBody['startDate'];
            while (strcmp($day, (string)$requestBody['endDate']) <= 0) {
                if (!in_array($day, $this->daysWithoutData, true) || $isComparison) {
                    $rows[] = $this->buildRow([$day], (int)substr($day, 8, 2) + ($isComparison ? 0 : 5), 300, 6.0);
                }
                $day = date('Y-m-d', strtotime($day . ' +1 day UTC'));
            }
        } else {
            for ($rowIndex = 0; $rowIndex < $this->availableRows; $rowIndex++) {
                $keys = array_map(fn (string $dimension): string => $this->buildKey($dimension, $rowIndex), $dimensions);
                if ($isComparison && array_intersect($keys, $this->keysMissingInComparison)) {
                    continue;
                }
                if (!$this->matchesFilters($dimensions, $keys, $requestBody)) {
                    continue;
                }
                $rows[] = $this->buildRow(
                    $keys,
                    max(0, 1000 - $rowIndex * 10 - ($isComparison ? 10 : 0)),
                    ($rowIndex * 37 % 101) * 10 + 5,
                    1.0 + $rowIndex * 0.5
                );
            }
            $rows = array_slice($rows, (int)($requestBody['startRow'] ?? 0), (int)($requestBody['rowLimit'] ?? 1000));
        }
        if ($dimensions === ['date'] || $dimensions === []) {
            $rows = array_slice($rows, 0, (int)($requestBody['rowLimit'] ?? 1000));
        }
        return self::respond($rows, $this->responseAggregationType);
    }

    public function buildKey(string $dimension, int $rowIndex): string
    {
        return match ($dimension) {
            'query' => sprintf('synthetic widget %03d', $rowIndex),
            'page' => sprintf('https://example.test/guides/item-%03d/', $rowIndex),
            'country' => self::COUNTRIES[$rowIndex % count(self::COUNTRIES)] . ($rowIndex >= count(self::COUNTRIES) ? (string)$rowIndex : ''),
            'device' => self::DEVICES[$rowIndex % count(self::DEVICES)] . ($rowIndex >= count(self::DEVICES) ? (string)$rowIndex : ''),
            default => 'value-' . $rowIndex,
        };
    }

    /**
     * @param array[] $rows
     * @param string|null $responseAggregationType
     */
    public static function respond(array $rows, ?string $responseAggregationType = null): Response
    {
        $answer = ['rows' => $rows];
        if ($responseAggregationType !== null) {
            $answer['responseAggregationType'] = $responseAggregationType;
        }
        $answerJson = (string)json_encode($answer, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        return new Response(200, ['Content-Type' => 'application/json; charset=UTF-8', 'Content-Length' => (string)strlen($answerJson)], $answerJson);
    }

    /** @param string[] $keys */
    protected function buildRow(array $keys, int $clicks, int $impressions, float $position): array
    {
        return ['keys' => $keys, 'clicks' => $clicks, 'impressions' => $impressions, 'ctr' => $impressions > 0 ? $clicks / $impressions : 0, 'position' => $position];
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
