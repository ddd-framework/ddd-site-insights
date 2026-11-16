<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Sites;

use DDD\Domain\Base\Entities\ValueObject;

/**
 * What a search data provider can answer: its dimensions, filters, metrics and limits, and the zone its days are
 * counted in.
 */
class InsightsProviderCapabilities extends ValueObject
{
    /** @var string Provider key, e.g. google-search-console */
    public string $provider;

    /** @var string[] Dimensions rows can be grouped by */
    public array $dimensions = [];

    /** @var string[] Filter operators */
    public array $filterOperators = [];

    /** @var string[] Metrics of every row */
    public array $metrics = [];

    /** @var string[] Search types */
    public array $searchTypes = [];

    /** @var string[] Data states (final: settled days only; all: fresh days that may still change) */
    public array $dataStates = [];

    /** @var string[] Aggregations */
    public array $aggregations = [];

    /** @var string Time zone the provider counts its days in */
    public string $dayTimeZone;

    /** @var int Months of data the provider keeps */
    public int $retentionMonths;

    /** @var int Most rows one call may ask for */
    public int $maxRowsPerCall;

    /** @var bool Whether hourly data is available (not used in version 1) */
    public bool $hasHourlyData = false;

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic($this->provider ?? '');
    }
}
