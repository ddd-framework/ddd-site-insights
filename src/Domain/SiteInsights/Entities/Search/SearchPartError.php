<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Search;

use DDD\Domain\Base\Entities\ValueObject;
use DDD\Domain\SiteInsights\Entities\Errors\SiteInsightsErrorCause;
use DDD\Domain\SiteInsights\Entities\Google\GoogleApiFailure;

/** A part of an answer that failed, in place of its rows */
class SearchPartError extends ValueObject
{
    /** @var string A SiteInsightsErrorCause value */
    public string $cause;

    /** @var string What failed, at most 160 characters; never a key, token or e-mail address */
    public string $message;

    /** @var int|null The provider's HTTP status, when it answered */
    public ?int $providerStatus;

    /** @var bool Whether asking again later may succeed */
    public bool $retryable = false;

    /** @var int|null How often the part was asked before it was given up (set by the export, retries included) */
    public ?int $attempts;

    /** @var SearchTransportFailure|null Set for cause transport where the module measured the failed call (the export) */
    public ?SearchTransportFailure $transportFailure;

    /**
     * @param SiteInsightsErrorCause $siteInsightsErrorCause
     * @param string $message
     * @param int|null $providerStatus
     * @return static
     */
    public static function create(SiteInsightsErrorCause $siteInsightsErrorCause, string $message, ?int $providerStatus = null): static
    {
        $searchPartError = new static();
        $searchPartError->cause = $siteInsightsErrorCause->value;
        $searchPartError->message = GoogleApiFailure::shortenMessage($message);
        if ($providerStatus !== null) {
            $searchPartError->providerStatus = $providerStatus;
        }
        $searchPartError->retryable = $siteInsightsErrorCause->isRetryable();
        return $searchPartError;
    }

    /** @return SiteInsightsErrorCause */
    public function getCause(): SiteInsightsErrorCause
    {
        return SiteInsightsErrorCause::from($this->cause);
    }

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic((string)spl_object_id($this));
    }
}
