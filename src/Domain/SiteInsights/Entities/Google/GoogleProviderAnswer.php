<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Google;

use DDD\Domain\Base\Entities\ValueObject;
use DDD\Infrastructure\Base\DateTime\DateTime;
use DDD\Domain\SiteInsights\Services\SiteInsightsConfigService;
use DDD\Infrastructure\Services\DDDService;

/**
 * One question to a Google Search Console resource and Google's answer, in Google's own shape. Carries the question
 * and the answer only, never a token or a key: an Argus cache entry is this object serialised with its hidden
 * properties. The identity of the question ({@see buildQuestionIdentity()}) is the cache identity.
 */
abstract class GoogleProviderAnswer extends ValueObject
{
    public const string PROVIDER = 'google-search-console';

    /** @var int Raised when the shape of a class or its question changes: old cache entries are not read */
    public const int SCHEMA_VERSION = 1;

    /** @var string The Google resource the question is asked of (part of the identity) */
    public const string RESOURCE = '';

    /** @var string Name of the credentials entry (config SiteInsights.credentials); a name, never a secret */
    public string $credentialsEntryName;

    /** @var int Revision of the credentials entry: part of the cache identity, raised when the account changes */
    public int $credentialsRevision;

    /** @var DateTime|null When Google answered (UTC); the module's own freshness check */
    public ?DateTime $fetchedAt;

    /** @var GoogleApiFailure|null Why the call failed; never cached */
    public ?GoogleApiFailure $failure;

    /**
     * @return array the resource's own question fields in a fixed order (no answer field, no token)
     */
    abstract protected function buildQuestionParts(): array;

    /**
     * @return string canonical JSON of the complete normalised question
     */
    public function buildQuestionIdentity(): string
    {
        return (string)json_encode(
            [
                'v' => static::SCHEMA_VERSION . '.' . self::getCacheSchemaVersion(),
                'provider' => self::PROVIDER,
                'resource' => static::RESOURCE,
                'credentials' => $this->credentialsEntryName ?? '',
                'revision' => $this->credentialsRevision ?? 0,
                ...$this->buildQuestionParts(),
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    /** @return string the identity of the complete normalised question */
    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic(hash('sha256', $this->buildQuestionIdentity()));
    }

    /** @return bool whether Google answered (no failure) */
    public function hasAnswer(): bool
    {
        return !isset($this->failure) && isset($this->fetchedAt);
    }

    /**
     * @param int $lifetimeSeconds
     * @param int|null $nowTimestamp
     * @return bool whether the answer is older than the lifetime (a cache hit that counts as a miss)
     */
    public function isOlderThan(int $lifetimeSeconds, ?int $nowTimestamp = null): bool
    {
        if (!isset($this->fetchedAt)) {
            return true;
        }
        return $this->fetchedAt->getTimestamp() + $lifetimeSeconds < ($nowTimestamp ?? time());
    }

    /** @return int config SiteInsights.cache.schemaVersion */
    public static function getCacheSchemaVersion(): int
    {
        /** @var SiteInsightsConfigService $siteInsightsConfigService */
        $siteInsightsConfigService = DDDService::instance()->getService(SiteInsightsConfigService::class);
        return $siteInsightsConfigService->getCacheConfig()->schemaVersion;
    }

    /**
     * @param mixed $value an RFC 3339 time from Google
     * @return DateTime|null
     */
    protected static function parseProviderTime(mixed $value): ?DateTime
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        $timestamp = strtotime($value);
        return $timestamp === false ? null : DateTime::fromTimestamp($timestamp);
    }
}
