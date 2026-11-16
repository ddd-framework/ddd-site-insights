<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Exceptions;

use DDD\Domain\SiteInsights\Entities\Errors\SiteInsightsErrorCause;
use DDD\Infrastructure\Exceptions\Exception;
use Symfony\Component\HttpFoundation\Response;

/**
 * The provider cannot be asked or did not answer usably: a missing or unreadable key, a refused token, a failed token
 * endpoint. The message names the credentials entry and the check that failed, never a part of a key or a token.
 */
class SiteInsightsProviderUnavailableException extends Exception
{
    protected static int $defaultCode = Response::HTTP_SERVICE_UNAVAILABLE;

    /** @var string A SiteInsightsErrorCause value */
    public string $cause = SiteInsightsErrorCause::PROVIDER_FAILED->value;

    /**
     * @param SiteInsightsErrorCause $siteInsightsErrorCause
     * @param string $message
     * @return static
     */
    public static function forCause(SiteInsightsErrorCause $siteInsightsErrorCause, string $message): static
    {
        $exception = new static($message);
        $exception->cause = $siteInsightsErrorCause->value;
        return $exception;
    }

    /** @return SiteInsightsErrorCause */
    public function getCause(): SiteInsightsErrorCause
    {
        return SiteInsightsErrorCause::from($this->cause);
    }
}
