<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Indexing;

use DDD\Domain\Base\Entities\ValueObject;
use DDD\Infrastructure\Base\DateTime\DateTime;

/** What an indexing answer covers: how many parts were asked, answered from the cache, failed, and when */
class IndexingCoverage extends ValueObject
{
    /** @var int Parts asked: 1 for the sitemaps list, the distinct URLs of an inspection request */
    public int $requestedParts = 0;

    /** @var int Parts answered from the cache (no provider call, no quota counted) */
    public int $partsFromCache = 0;

    /** @var int Parts that carry an error instead of an answer */
    public int $failedParts = 0;

    /** @var int Provider calls made (retries included, cache hits not counted) */
    public int $providerCalls = 0;

    /** @var int Calls counted against the provider's daily URL inspection quota by this request (0 for sitemaps) */
    public int $inspectionsCounted = 0;

    /** @var bool Whether every answered part came from the cache */
    public bool $fromCache = false;

    /** @var DateTime|null When the provider answered (the oldest answered part) */
    public ?DateTime $fetchedAt;

    /** @var int How long an answer is served from the cache (cache.sitemapsTtlSeconds or cache.inspectionTtlSeconds) */
    public int $cacheLifetimeSeconds = 0;

    /**
     * @param DateTime $answeredAt when the provider answered a part
     * @return void keeps the oldest
     */
    public function addAnsweredAt(DateTime $answeredAt): void
    {
        if (!isset($this->fetchedAt) || $answeredAt->getTimestamp() < $this->fetchedAt->getTimestamp()) {
            $this->fetchedAt = $answeredAt;
        }
    }

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic((string)spl_object_id($this));
    }
}
