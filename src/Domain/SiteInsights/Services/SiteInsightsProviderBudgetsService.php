<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Services;

use DateTimeImmutable;
use DateTimeZone;
use DDD\Domain\SiteInsights\Entities\Budgets\SiteInsightsBudgetCounter;
use DDD\Domain\SiteInsights\Entities\Budgets\SiteInsightsBudgetCounters;
use DDD\Domain\SiteInsights\Entities\Sites\InsightsSite;
use DDD\Domain\SiteInsights\Exceptions\SiteInsightsBudgetExceededException;
use DDD\Infrastructure\Cache\Apc;
use DDD\Infrastructure\Cache\Cache;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Services\DDDService;
use DDD\Infrastructure\Services\Service;

/**
 * Call counters per site and quota, and refusals before the provider refuses. Counters are atomic increments in the
 * Redis Sentinel cache group (shared by every pod); without Redis they fall back to APCu counters (per pod) and,
 * where APCu does not work either, calls are not counted. Retries count; answers from the cache do not.
 */
class SiteInsightsProviderBudgetsService extends Service
{
    public const string QUOTA_SEARCH_ANALYTICS = 'searchAnalytics';
    public const string QUOTA_INSPECTION = 'inspection';
    public const string QUOTA_OTHER = 'other';

    public const string COUNTER_SCOPE_SHARED = 'shared';
    public const string COUNTER_SCOPE_PER_POD = 'perPod';
    public const string COUNTER_SCOPE_NOT_COUNTED = 'notCounted';

    public const string COUNTER_KEY_PREFIX = 'siteInsights.budget.';
    public const int MINUTE_BUCKET_TTL_SECONDS = 120;
    public const int DAY_BUCKET_TTL_SECONDS = 172800;

    /**
     * Counts `$calls` against the quota of the site; above a limit nothing is counted and the calls are refused.
     *
     * @param InsightsSite $insightsSite
     * @param string $quotaKind searchAnalytics, inspection or other
     * @param int $calls
     * @return void
     * @throws SiteInsightsBudgetExceededException naming the quota and when its bucket ends
     * @throws BadRequestException for an unknown quota kind
     */
    public function reserve(InsightsSite $insightsSite, string $quotaKind, int $calls): void
    {
        if ($calls <= 0) {
            return;
        }
        $counterCache = $this->getCounterCache();
        if ($counterCache === null) {
            return;
        }
        $incrementedCounterKeys = [];
        foreach ($this->buildCounters($insightsSite, $quotaKind)->getElements() as $siteInsightsBudgetCounter) {
            $counterValue = $counterCache->increment($siteInsightsBudgetCounter->key, $calls, $siteInsightsBudgetCounter->ttlSeconds);
            $incrementedCounterKeys[] = $siteInsightsBudgetCounter->key;
            if ($counterValue > $siteInsightsBudgetCounter->limit) {
                foreach ($incrementedCounterKeys as $incrementedCounterKey) {
                    $counterCache->decrement($incrementedCounterKey, $calls);
                }
                throw new SiteInsightsBudgetExceededException(sprintf(
                    'Budget of %s calls for site "%s" used up: %d of %d calls in the %s; %d more refused',
                    $quotaKind,
                    $insightsSite->key,
                    $counterValue - $calls,
                    $siteInsightsBudgetCounter->limit,
                    $siteInsightsBudgetCounter->bucketDescription,
                    $calls
                ));
            }
        }
    }

    /**
     * Gives back calls that were reserved but not made.
     *
     * @param InsightsSite $insightsSite
     * @param string $quotaKind
     * @param int $calls
     * @return void
     * @throws BadRequestException for an unknown quota kind
     */
    public function release(InsightsSite $insightsSite, string $quotaKind, int $calls): void
    {
        if ($calls <= 0) {
            return;
        }
        $counterCache = $this->getCounterCache();
        if ($counterCache === null) {
            return;
        }
        foreach ($this->buildCounters($insightsSite, $quotaKind)->getElements() as $siteInsightsBudgetCounter) {
            $counterCache->decrement($siteInsightsBudgetCounter->key, $calls);
        }
    }

    /**
     * @param string $operation a key of budgets.callsPerOperation
     * @param int $plannedCalls
     * @return void
     * @throws SiteInsightsBudgetExceededException when the operation plans more calls than it may
     * @throws BadRequestException for an unknown operation
     */
    public function assertOperationBudget(string $operation, int $plannedCalls): void
    {
        $allowedCalls = $this->getSiteInsightsConfigService()->getBudgetsConfig()->getCallsAllowedForOperation($operation);
        if ($allowedCalls === null) {
            throw new BadRequestException('Unknown site insights operation "' . mb_substr($operation, 0, 40) . '"');
        }
        if ($plannedCalls > $allowedCalls) {
            throw new SiteInsightsBudgetExceededException(sprintf(
                'The operation %s would need %d provider calls; it may make %d',
                $operation,
                $plannedCalls,
                $allowedCalls
            ));
        }
    }

    /** @return string shared (Redis), perPod (APCu) or notCounted */
    public function getCounterScope(): string
    {
        $counterCache = $this->getCounterCache();
        if ($counterCache === null) {
            return self::COUNTER_SCOPE_NOT_COUNTED;
        }
        return $counterCache instanceof Apc ? self::COUNTER_SCOPE_PER_POD : self::COUNTER_SCOPE_SHARED;
    }

    /**
     * @param InsightsSite $insightsSite
     * @param string $quotaKind
     * @return SiteInsightsBudgetCounters the counters of the quota (inspection: per day and per minute)
     * @throws BadRequestException
     */
    protected function buildCounters(InsightsSite $insightsSite, string $quotaKind): SiteInsightsBudgetCounters
    {
        $budgetsConfig = $this->getSiteInsightsConfigService()->getBudgetsConfig();
        $nowTimestamp = $this->getNowTimestamp();
        $utcMoment = (new DateTimeImmutable('@' . $nowTimestamp))->setTimezone(new DateTimeZone('UTC'));
        $minuteBucket = $utcMoment->format('YmdHi');
        $minuteBucketDescription = 'minute ending ' . $utcMoment->modify('+1 minute')->format('H:i') . ' UTC';
        $propertyHash = sha1($insightsSite->property);
        $siteInsightsBudgetCounters = new SiteInsightsBudgetCounters();
        switch ($quotaKind) {
            case self::QUOTA_SEARCH_ANALYTICS:
            case self::QUOTA_OTHER:
                $perMinuteLimit = $quotaKind === self::QUOTA_SEARCH_ANALYTICS
                    ? $budgetsConfig->searchAnalyticsPerMinutePerSite * $budgetsConfig->searchAnalyticsShare
                    : $budgetsConfig->otherPerMinute * $budgetsConfig->otherShare;
                $minuteCounter = SiteInsightsBudgetCounter::create(
                    $this->buildCounterKey($quotaKind, $propertyHash, $minuteBucket),
                    self::MINUTE_BUCKET_TTL_SECONDS,
                    (int)floor($perMinuteLimit),
                    $minuteBucketDescription
                );
                $siteInsightsBudgetCounters->add($minuteCounter);
                break;
            case self::QUOTA_INSPECTION:
                $providerDayBucket = $this->getProviderDayBucket($nowTimestamp);
                $dayCounter = SiteInsightsBudgetCounter::create(
                    $this->buildCounterKey($quotaKind, $propertyHash, $providerDayBucket),
                    self::DAY_BUCKET_TTL_SECONDS,
                    (int)floor($budgetsConfig->inspectionsPerDayPerSite * $budgetsConfig->inspectionShare),
                    'day ' . $providerDayBucket . ' (Pacific Time)'
                );
                $minuteCounter = SiteInsightsBudgetCounter::create(
                    $this->buildCounterKey($quotaKind . 'PerMinute', $propertyHash, $minuteBucket),
                    self::MINUTE_BUCKET_TTL_SECONDS,
                    (int)floor($budgetsConfig->inspectionsPerMinutePerSite * $budgetsConfig->inspectionShare),
                    $minuteBucketDescription
                );
                $siteInsightsBudgetCounters->add($dayCounter, $minuteCounter);
                break;
            default:
                throw new BadRequestException('Unknown quota kind "' . mb_substr($quotaKind, 0, 40) . '"');
        }
        return $siteInsightsBudgetCounters;
    }

    /**
     * @param string $quotaKind
     * @param string $propertyHash
     * @param string $bucket
     * @return string
     */
    protected function buildCounterKey(string $quotaKind, string $propertyHash, string $bucket): string
    {
        return self::COUNTER_KEY_PREFIX . $quotaKind . '.' . $propertyHash . '.' . $bucket;
    }

    /**
     * @param int $nowTimestamp
     * @return string the provider's day (Pacific Time) as `Ymd`
     */
    protected function getProviderDayBucket(int $nowTimestamp): string
    {
        return (new DateTimeImmutable('@' . $nowTimestamp))
            ->setTimezone(new DateTimeZone(GoogleSearchConsoleSourceService::DAY_TIME_ZONE))
            ->format('Ymd');
    }

    /**
     * @return Cache|null the Redis Sentinel group when the application configured it, else APCu when it works in
     *         this SAPI, else null (calls are not counted)
     */
    protected function getCounterCache(): ?Cache
    {
        $counterCache = Cache::instance(Cache::CACHE_GROUP_REDIS_SENTINEL);
        if ($counterCache instanceof Apc && !$this->isApcuUsable()) {
            return null;
        }
        return $counterCache;
    }

    /** Seam for tests: whether APCu counters work in this SAPI */
    protected function isApcuUsable(): bool
    {
        return function_exists('apcu_enabled') && apcu_enabled();
    }

    /** Seam for tests: the current time */
    protected function getNowTimestamp(): int
    {
        return time();
    }

    protected function getSiteInsightsConfigService(): SiteInsightsConfigService
    {
        return DDDService::instance()->getService(SiteInsightsConfigService::class);
    }
}
