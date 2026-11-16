<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Google;

use DDD\Domain\Base\Entities\ValueObject;
use DDD\Domain\Base\Repo\Argus\Utils\ArgusApiOperation;
use DDD\Domain\SiteInsights\Entities\Errors\SiteInsightsErrorCause;

/**
 * Google's error answer or a rejected call, as the module classifies it. Google's error JSON:
 * `{"error":{"code","message","status","errors":[{"reason"}],"details":[{"reason"}]}}`.
 */
class GoogleApiFailure extends ValueObject
{
    public const int MAX_MESSAGE_LENGTH = 160;

    /** @var string[] Reasons of a provider API that is not enabled for the project */
    public const array REASONS_API_NOT_ENABLED = ['SERVICE_DISABLED', 'accessNotConfigured'];

    /** @var string[] Reasons of an exhausted quota */
    public const array REASONS_QUOTA = ['quotaExceeded', 'rateLimitExceeded', 'RATE_LIMIT_EXCEEDED', 'userRateLimitExceeded', 'dailyLimitExceeded'];

    /** @var int|null HTTP status; unset for a rejected call */
    public ?int $httpStatus;

    /** @var string|null Google's `error.status` (PERMISSION_DENIED, RESOURCE_EXHAUSTED, …) */
    public ?string $providerStatus;

    /** @var string|null Google's `error.errors[0].reason` or `error.details[].reason` (forbidden, SERVICE_DISABLED, quotaExceeded, …) */
    public ?string $providerReason;

    /** @var string Google's `error.message` or the rejection reason, at most 160 characters */
    public string $message;

    /** @var string SiteInsightsErrorCause value */
    public string $cause;

    /**
     * @param mixed $callResponseData the decoded answer (null for a rejected call)
     * @param ArgusApiOperation|null $apiOperation the operation with status and rejection reason
     * @return static|null null for a 2xx answer with a body
     */
    public static function fromAnswer(mixed $callResponseData, ?ArgusApiOperation $apiOperation): ?static
    {
        $statusCode = $apiOperation?->responseStatusCode;
        $googleApiFailure = new static();
        if ($statusCode === null) {
            $rejectionReason = $apiOperation?->rejectionReason;
            $googleApiFailure->cause = ($rejectionReason !== null
                ? SiteInsightsErrorCause::TRANSPORT
                : SiteInsightsErrorCause::PROVIDER_FAILED)->value;
            $googleApiFailure->message = self::shortenMessage($rejectionReason ?? 'no answer');
            return $googleApiFailure;
        }
        if ($statusCode >= 200 && $statusCode < 300) {
            if (is_object($callResponseData)) {
                return null;
            }
            $googleApiFailure->httpStatus = $statusCode;
            $googleApiFailure->cause = SiteInsightsErrorCause::ANSWER_UNREADABLE->value;
            $googleApiFailure->message = 'the answer is not a JSON object';
            return $googleApiFailure;
        }
        $googleApiFailure->httpStatus = $statusCode;
        $message = '';
        $reasons = [];
        $error = is_object($callResponseData) ? ($callResponseData->error ?? null) : null;
        if (is_object($error)) {
            if (is_string($error->status ?? null)) {
                $googleApiFailure->providerStatus = $error->status;
            }
            $message = is_string($error->message ?? null) ? $error->message : '';
            foreach ([...(array)($error->errors ?? []), ...(array)($error->details ?? [])] as $errorEntry) {
                if (is_object($errorEntry) && is_string($errorEntry->reason ?? null)) {
                    $reasons[] = $errorEntry->reason;
                }
            }
        } elseif (is_string($error)) {
            $message = $error;
        }
        if ($reasons) {
            $googleApiFailure->providerReason = $reasons[0];
        }
        $googleApiFailure->message = self::shortenMessage($message !== '' ? $message : 'http ' . $statusCode);
        $googleApiFailure->cause = self::classifyCause(
            $statusCode,
            $googleApiFailure->providerStatus ?? null,
            $reasons,
            $message
        )->value;
        return $googleApiFailure;
    }

    /**
     * @param int $httpStatus
     * @param string|null $providerStatus
     * @param string[] $providerReasons
     * @param string $message
     * @return SiteInsightsErrorCause
     */
    public static function classifyCause(int $httpStatus, ?string $providerStatus, array $providerReasons, string $message): SiteInsightsErrorCause
    {
        if ($httpStatus === 401) {
            return SiteInsightsErrorCause::TOKEN_REFUSED;
        }
        if ($httpStatus === 429 || $providerStatus === 'RESOURCE_EXHAUSTED' || array_intersect($providerReasons, self::REASONS_QUOTA)) {
            return SiteInsightsErrorCause::QUOTA_EXCEEDED;
        }
        if ($httpStatus === 403) {
            if (array_intersect($providerReasons, self::REASONS_API_NOT_ENABLED)
                || preg_match('/has not been used .* or it is disabled/is', $message) === 1
            ) {
                return SiteInsightsErrorCause::API_NOT_ENABLED;
            }
            return SiteInsightsErrorCause::NOT_USER_OF_PROPERTY;
        }
        if ($httpStatus >= 500) {
            return SiteInsightsErrorCause::PROVIDER_FAILED;
        }
        return SiteInsightsErrorCause::INVALID_REQUEST;
    }

    /** @return SiteInsightsErrorCause */
    public function getCause(): SiteInsightsErrorCause
    {
        return SiteInsightsErrorCause::from($this->cause);
    }

    /**
     * @param string $message
     * @return string at most MAX_MESSAGE_LENGTH characters, one line
     */
    public static function shortenMessage(string $message): string
    {
        return mb_substr(trim((string)preg_replace('/\s+/', ' ', $message)), 0, self::MAX_MESSAGE_LENGTH);
    }

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic((string)spl_object_id($this));
    }
}
