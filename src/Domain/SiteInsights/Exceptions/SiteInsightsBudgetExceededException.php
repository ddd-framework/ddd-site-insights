<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Exceptions;

use DDD\Domain\SiteInsights\Entities\Errors\SiteInsightsErrorCause;
use DDD\Infrastructure\Exceptions\Exception;
use Symfony\Component\HttpFoundation\Response;

/**
 * The module's call budget refused provider calls (cause budgetRefused): an operation planned more calls than it may,
 * or a quota bucket is used up. The message names the quota and when its bucket ends.
 */
class SiteInsightsBudgetExceededException extends Exception
{
    protected static int $defaultCode = Response::HTTP_TOO_MANY_REQUESTS;

    /** @var string A SiteInsightsErrorCause value (always budgetRefused) */
    public string $cause = SiteInsightsErrorCause::BUDGET_REFUSED->value;

    /** @return SiteInsightsErrorCause */
    public function getCause(): SiteInsightsErrorCause
    {
        return SiteInsightsErrorCause::from($this->cause);
    }
}
