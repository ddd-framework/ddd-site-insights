<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Search;

use DDD\Domain\Base\Entities\ValueObject;
use DDD\Domain\SiteInsights\Utils\SearchDays;
use DDD\Infrastructure\Base\DateTime\Date;

/** One row of a search performance list: the dimension values it was asked by and its metrics */
class SearchPerformanceRow extends ValueObject
{
    /** @var Date|null Day (provider days, SearchWindow::$dayTimeZone); set when grouped by date */
    public ?Date $date;

    /** @var string|null Search query; set when grouped by query */
    public ?string $query;

    /** @var string|null Page URL as the provider reports it; set when grouped by page */
    public ?string $page;

    /** @var string|null Country, ISO 3166-1 alpha-3 lower case; set when grouped by country */
    public ?string $country;

    /** @var string|null desktop, mobile, tablet; set when grouped by device */
    public ?string $device;

    /** @var string|null Search appearance; set when grouped by it */
    public ?string $searchAppearance;

    /** @var SearchMetrics Metrics of the window */
    public SearchMetrics $metrics;

    /** @var SearchMetricsComparison|null Counterpart in the comparison window; set when a comparison was asked */
    public ?SearchMetricsComparison $comparison;

    /** @var bool|null Series only: true for a day the provider reported nothing for (filled with zeros) */
    public ?bool $isFilledDay;

    /** @return string identity = the dimension values that are set */
    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic(hash('sha256', (string)json_encode([
            isset($this->date) ? SearchDays::fromDate($this->date) : null,
            $this->query ?? null,
            $this->page ?? null,
            $this->country ?? null,
            $this->device ?? null,
            $this->searchAppearance ?? null,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)));
    }
}
