<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Search;

use DDD\Domain\Base\Entities\ValueObject;

/**
 * An aggregate of a window, asked of the provider as its own question without dimensions (never a sum of rows),
 * compared with the same aggregate of the comparison window. The coverage of both questions is stated.
 */
class SearchTotals extends ValueObject
{
    /** @var SearchMetricsComparison|null The metrics and their comparison; unset when the window's question failed */
    public ?SearchMetricsComparison $metrics;

    /** @var SearchRowsCoverage Coverage of the window's question */
    public SearchRowsCoverage $coverage;

    /** @var SearchRowsCoverage|null Coverage of the comparison window's question; set when it was asked */
    public ?SearchRowsCoverage $comparisonCoverage;

    /** @var SearchPartError|null Why the window's question failed */
    public ?SearchPartError $error;

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic((string)spl_object_id($this));
    }
}
