<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Budgets;

use DDD\Domain\Base\Entities\ValueObject;

/** One call counter of a quota: its key, the lifetime of its bucket and its limit */
class SiteInsightsBudgetCounter extends ValueObject
{
    /** @var string Counter key: prefix, quota, property hash, bucket */
    public string $key;

    /** @var int Lifetime of the counter, set when it is created */
    public int $ttlSeconds;

    /** @var int Most calls in the bucket */
    public int $limit;

    /** @var string When the bucket ends, for messages */
    public string $bucketDescription;

    /**
     * @param string $key
     * @param int $ttlSeconds
     * @param int $limit
     * @param string $bucketDescription
     * @return static
     */
    public static function create(string $key, int $ttlSeconds, int $limit, string $bucketDescription): static
    {
        $siteInsightsBudgetCounter = new static();
        $siteInsightsBudgetCounter->key = $key;
        $siteInsightsBudgetCounter->ttlSeconds = $ttlSeconds;
        $siteInsightsBudgetCounter->limit = $limit;
        $siteInsightsBudgetCounter->bucketDescription = $bucketDescription;
        return $siteInsightsBudgetCounter;
    }

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic($this->key ?? '');
    }
}
