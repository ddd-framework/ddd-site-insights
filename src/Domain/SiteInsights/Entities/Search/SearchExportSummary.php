<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Search;

use DDD\Domain\Base\Entities\ValueObject;
use DDD\Infrastructure\Base\DateTime\Date;

/**
 * What an export wrote and covered. The rows themselves are streamed (never cached, never held); this summary
 * follows them. Command property `searchExportSummary`.
 */
class SearchExportSummary extends ValueObject
{
    /** @var string Key of the site */
    public string $siteKey;

    /** @var SearchWindow The window */
    public SearchWindow $window;

    /** @var string[] Dimensions of the rows */
    public array $dimensions = [];

    /** @var bool Whether each day was asked on its own */
    public bool $byDay = false;

    /** @var int Rows written */
    public int $rowsWritten = 0;

    /** @var int Provider calls made */
    public int $providerCalls = 0;

    /** @var int Calls asked again after a retryable failure (they count as provider calls) */
    public int $retries = 0;

    /** @var Date|null The day the export continued after (--resumeAfter); the parts start the day after */
    public ?Date $resumedAfter;

    /** @var bool The call budget (budgets.callsPerOperation.export) stopped the export before its end */
    public bool $limitReached = false;

    /** @var Date|null The last part written to its end; unset when none was */
    public ?Date $lastFinishedDay;

    /** @var SearchPartError|null Why the export stopped early (after budgets.exportRetries for a retryable cause); continue with --resumeAfter=<lastFinishedDay> */
    public ?SearchPartError $error;

    /** @var bool Grouped by query: rare queries are missing, sums are below the totals */
    public bool $anonymizedQueriesOmitted = false;

    /** @var bool Query × page: the provider drops rows in joint reports */
    public bool $joinedDimensionsMayLoseRows = false;

    /** @var SearchExportDays The parts in order */
    public SearchExportDays $days;

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic((string)spl_object_id($this));
    }
}
