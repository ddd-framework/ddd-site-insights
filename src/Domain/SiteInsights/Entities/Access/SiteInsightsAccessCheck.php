<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Access;

use DDD\Domain\Base\Entities\ValueObject;
use DDD\Domain\SiteInsights\Entities\Errors\SiteInsightsErrorCause;
use DDD\Domain\SiteInsights\Entities\Google\GoogleApiFailure;
use DDD\Infrastructure\Validation\Constraints\Choice;

/**
 * The state of one capability of a site: ok, failed with its cause, or skipped because a prerequisite failed. Holds
 * states only: never a part of a key, an e-mail address or a token.
 */
class SiteInsightsAccessCheck extends ValueObject
{
    public const string CAPABILITY_KEY_READABLE = 'keyReadable';
    public const string CAPABILITY_TOKEN_OBTAINED = 'tokenObtained';
    public const string CAPABILITY_PROPERTY_LISTED = 'propertyListed';
    public const string CAPABILITY_PERMISSION_LEVEL = 'permissionLevel';
    public const string CAPABILITY_SEARCH_ANALYTICS = 'searchAnalytics';
    public const string CAPABILITY_SITEMAPS = 'sitemaps';
    public const string CAPABILITY_URL_INSPECTION = 'urlInspection';
    public const string CAPABILITY_BUDGET_COUNTERS_SHARED = 'budgetCountersShared';

    /** @var string[] The capabilities in the order they are checked */
    public const array CAPABILITIES = [
        self::CAPABILITY_KEY_READABLE,
        self::CAPABILITY_TOKEN_OBTAINED,
        self::CAPABILITY_PROPERTY_LISTED,
        self::CAPABILITY_PERMISSION_LEVEL,
        self::CAPABILITY_SEARCH_ANALYTICS,
        self::CAPABILITY_SITEMAPS,
        self::CAPABILITY_URL_INSPECTION,
        self::CAPABILITY_BUDGET_COUNTERS_SHARED,
    ];

    public const string STATE_OK = 'ok';
    public const string STATE_FAILED = 'failed';
    public const string STATE_SKIPPED = 'skipped';

    /** Works, with a limit the operator should know (e.g. call counters per pod instead of shared) */
    public const string STATE_WARNING = 'warning';

    /** @var string Key of the site */
    public string $siteKey;

    /** @var string The capability checked */
    #[Choice(choices: self::CAPABILITIES)]
    public string $capability;

    /** @var string ok, warning (works with a limit, see detail), failed, or skipped because a prerequisite failed */
    #[Choice(choices: [self::STATE_OK, self::STATE_WARNING, self::STATE_FAILED, self::STATE_SKIPPED])]
    public string $state;

    /** @var string|null A SiteInsightsErrorCause value; set when failed */
    public ?string $cause;

    /** @var string|null What failed or what was found (e.g. the permission level), without secrets */
    public ?string $detail;

    /**
     * @param string $siteKey
     * @param string $capability
     * @param string $state
     * @param SiteInsightsErrorCause|null $siteInsightsErrorCause
     * @param string|null $detail
     * @return static
     */
    public static function create(
        string $siteKey,
        string $capability,
        string $state,
        ?SiteInsightsErrorCause $siteInsightsErrorCause = null,
        ?string $detail = null
    ): static {
        $siteInsightsAccessCheck = new static();
        $siteInsightsAccessCheck->siteKey = $siteKey;
        $siteInsightsAccessCheck->capability = $capability;
        $siteInsightsAccessCheck->state = $state;
        if ($siteInsightsErrorCause !== null) {
            $siteInsightsAccessCheck->cause = $siteInsightsErrorCause->value;
        }
        if ($detail !== null && $detail !== '') {
            $siteInsightsAccessCheck->detail = self::redactDetail($detail);
        }
        return $siteInsightsAccessCheck;
    }

    /**
     * @param string $detail
     * @return string the detail on one line, at most 160 characters, every e-mail address replaced
     */
    public static function redactDetail(string $detail): string
    {
        return GoogleApiFailure::shortenMessage(
            (string)preg_replace('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', '[e-mail address]', $detail)
        );
    }

    public function isFailed(): bool
    {
        return $this->state === self::STATE_FAILED;
    }

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic(($this->siteKey ?? '') . '_' . ($this->capability ?? ''));
    }
}
