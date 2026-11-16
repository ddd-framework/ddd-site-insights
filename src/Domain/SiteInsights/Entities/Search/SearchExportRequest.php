<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Search;

/** A report paged to its end: the window, the dimensions, and whether it is asked day by day */
class SearchExportRequest extends SearchWindowRequest
{
    /** @var string[] Dimensions an export can be grouped by */
    public const array EXPORT_DIMENSIONS = ['query', 'page', 'country', 'device', 'date'];

    /** @var string[] Dimensions of the rows (subset of query, page, country, device, date) */
    public array $dimensions = ['query', 'page'];

    /** @var bool|null One window per day; unset: on when query and page are asked together (Google's per-day row limits) */
    public ?bool $byDay;

    /** @return bool whether the export asks one window per day */
    public function isAskedByDay(): bool
    {
        return $this->byDay ?? (in_array('query', $this->dimensions, true) && in_array('page', $this->dimensions, true));
    }
}
