<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Errors;

/**
 * Why a part of an answer, a capability or a whole request failed. Carried by `SearchPartError::$cause`,
 * `GoogleApiFailure::$cause`, `SiteInsightsAccessCheck::$cause` and the module's exceptions.
 */
enum SiteInsightsErrorCause: string
{
    /** The service-account key of a credentials entry is missing or cannot be read (which check failed is named) */
    case KEY_UNREADABLE = 'keyUnreadable';
    /** A provider call was to be built without an access token */
    case TOKEN_MISSING = 'tokenMissing';
    /** The token endpoint refused the key (invalid grant, deleted key, clock skew) or a data call answered 401 twice */
    case TOKEN_REFUSED = 'tokenRefused';
    /** The token endpoint failed (5xx, no answer, unreadable answer) */
    case TOKEN_ENDPOINT_FAILED = 'tokenEndpointFailed';
    /** The account is no user of the property (403 permission) */
    case NOT_USER_OF_PROPERTY = 'notUserOfProperty';
    /** The provider API is not enabled for the account's project */
    case API_NOT_ENABLED = 'apiNotEnabled';
    /** The provider's quota is exhausted (429 or a rate-limit reason); retryable */
    case QUOTA_EXCEEDED = 'quotaExceeded';
    /** The module's own call budget refused the calls */
    case BUDGET_REFUSED = 'budgetRefused';
    /** The provider rejected the question (400): an unsupported filter or a date outside retention */
    case INVALID_REQUEST = 'invalidRequest';
    /** The provider failed (5xx or no answer for a question); retryable */
    case PROVIDER_FAILED = 'providerFailed';
    /** The call was rejected at the transport level (timeout, connection, DNS); retryable */
    case TRANSPORT = 'transport';
    /** The answer was too large, stalled or incomplete and could not be read */
    case ANSWER_UNREADABLE = 'answerUnreadable';
    /** The requested window or comparison lies outside the provider's retention */
    case OUTSIDE_RETENTION = 'outsideRetention';

    /** @return bool Whether asking again later may succeed */
    public function isRetryable(): bool
    {
        return match ($this) {
            self::QUOTA_EXCEEDED, self::PROVIDER_FAILED, self::TRANSPORT, self::TOKEN_ENDPOINT_FAILED, self::BUDGET_REFUSED => true,
            default => false,
        };
    }
}
