<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Support;

use DDD\Domain\SiteInsights\Services\SearchPerformanceService;

/** The search performance service with a clock that advances a fixed step per reading, and retries without waiting */
class SteppingClockSearchPerformanceService extends SearchPerformanceService
{
    /** @var int Milliseconds the clock advances per reading: a call takes this long */
    public int $millisecondsPerReading = 50;

    /** @var int[] The waits asked before each export retry, in order */
    public array $waitedMilliseconds = [];

    protected int $clockMilliseconds = 0;

    protected function getMonotonicMilliseconds(): int
    {
        $this->clockMilliseconds += $this->millisecondsPerReading;
        return $this->clockMilliseconds;
    }

    protected function waitBeforeExportRetry(int $milliseconds): void
    {
        $this->waitedMilliseconds[] = $milliseconds;
    }
}
