<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Search;

/**
 * The window a comparison is made with. When the comparison is not possible `from`, `to` and `days` stay unset and
 * `comparisonUnavailableReason` says why; nothing is asked of the provider then.
 */
class SearchComparisonWindow extends SearchWindow
{
    public const string KIND_PREVIOUS_PERIOD = 'previousPeriod';
    public const string KIND_PREVIOUS_YEAR = 'previousYear';

    public const string UNAVAILABLE_OUTSIDE_RETENTION = 'outsideRetention';
    public const string UNAVAILABLE_TOO_SHORT_INSIDE_RETENTION = 'tooShortInsideRetention';

    /** @var string previousPeriod or previousYear (52 weeks, weekdays aligned) */
    public string $kind;

    /** @var bool Whether the comparison was shortened to the provider's retention (deltas per day) */
    public bool $comparisonIsPartial = false;

    /** @var string|null outsideRetention or tooShortInsideRetention; unset when the comparison is available */
    public ?string $comparisonUnavailableReason;

    /** @return bool whether the comparison can be asked */
    public function isAvailable(): bool
    {
        return !isset($this->comparisonUnavailableReason);
    }
}
