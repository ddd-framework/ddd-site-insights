<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Search;

use DDD\Domain\Base\Entities\ValueObject;

/** The answer to one question: its rows with coverage, or its error */
class SearchReport extends ValueObject
{
    /** @var SearchQuestion The question */
    public SearchQuestion $question;

    /** @var SearchPerformanceRows Its rows and coverage; `error` set when it failed */
    public SearchPerformanceRows $performanceRows;

    /** @var string|null The last day the provider reported (questions grouped by date) */
    public ?string $latestReportedDay;

    /** @var string|null The provider's first incomplete day (dataState all, grouped by date) */
    public ?string $firstIncompleteDay;

    public function uniqueKey(): string
    {
        return isset($this->question) ? $this->question->uniqueKey() : static::uniqueKeyStatic((string)spl_object_id($this));
    }
}
