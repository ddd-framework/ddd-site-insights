<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Unit\Budgets;

use DDD\Domain\SiteInsights\Exceptions\SiteInsightsBudgetExceededException;
use DDD\Domain\SiteInsights\Services\SiteInsightsProviderBudgetsService;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Tests\SiteInsights\Support\SiteInsightsTestCase;
use DDD\Tests\SiteInsights\Support\TestConfig;

/** Counters per site, quota and bucket; refusals above the share of Google's limits */
class SiteInsightsProviderBudgetsServiceTest extends SiteInsightsTestCase
{
    public function testSearchAnalyticsCallsAreCountedPerMinute(): void
    {
        $this->budgetsService->reserve($this->getSite(), SiteInsightsProviderBudgetsService::QUOTA_SEARCH_ANALYTICS, 9);

        $counterKey = 'siteInsights.budget.searchAnalytics.' . sha1(TestConfig::PROPERTY) . '.202611162000';
        self::assertSame([$counterKey => 9], $this->getRedisCache()->countersByKey);
        self::assertSame(120, $this->getRedisCache()->counterTtlsByKey[$counterKey]);

        $this->budgetsService->release($this->getSite(), SiteInsightsProviderBudgetsService::QUOTA_SEARCH_ANALYTICS, 4);
        self::assertSame(5, $this->getRedisCache()->countersByKey[$counterKey]);
    }

    public function testCallsAboveTheLimitAreRefusedAndNotCounted(): void
    {
        $this->budgetsService->reserve($this->getSite(), SiteInsightsProviderBudgetsService::QUOTA_SEARCH_ANALYTICS, 600);

        try {
            $this->budgetsService->reserve($this->getSite(), SiteInsightsProviderBudgetsService::QUOTA_SEARCH_ANALYTICS, 1);
            self::fail('the 601st call was not refused');
        } catch (SiteInsightsBudgetExceededException $siteInsightsBudgetExceededException) {
            self::assertSame(429, $siteInsightsBudgetExceededException->getCode());
            self::assertSame('budgetRefused', $siteInsightsBudgetExceededException->cause);
            self::assertStringContainsString('minute ending 20:01 UTC', $siteInsightsBudgetExceededException->getMessage());
        }
        self::assertSame([600], array_values($this->getRedisCache()->countersByKey));

        // the next minute has its own bucket
        $this->budgetsService->nowTimestamp += 60;
        $this->budgetsService->reserve($this->getSite(), SiteInsightsProviderBudgetsService::QUOTA_SEARCH_ANALYTICS, 1);
        self::assertCount(2, $this->getRedisCache()->countersByKey);
    }

    public function testInspectionsAreCountedPerProviderDayAndPerMinute(): void
    {
        $this->budgetsService->reserve($this->getSite(), SiteInsightsProviderBudgetsService::QUOTA_INSPECTION, 3);

        $propertyHash = sha1(TestConfig::PROPERTY);
        self::assertSame(3, $this->getRedisCache()->countersByKey['siteInsights.budget.inspection.' . $propertyHash . '.20261116']);
        self::assertSame(172800, $this->getRedisCache()->counterTtlsByKey['siteInsights.budget.inspection.' . $propertyHash . '.20261116']);
        self::assertSame(3, $this->getRedisCache()->countersByKey['siteInsights.budget.inspectionPerMinute.' . $propertyHash . '.202611162000']);

        $this->expectException(SiteInsightsBudgetExceededException::class);
        $this->budgetsService->reserve($this->getSite(), SiteInsightsProviderBudgetsService::QUOTA_INSPECTION, 298);
    }

    public function testOperationBudgets(): void
    {
        $this->budgetsService->assertOperationBudget('checkAccess', 6);

        try {
            $this->budgetsService->assertOperationBudget('checkAccess', 7);
            self::fail('more calls than the operation may make');
        } catch (SiteInsightsBudgetExceededException $siteInsightsBudgetExceededException) {
            self::assertStringContainsString('checkAccess', $siteInsightsBudgetExceededException->getMessage());
        }
        $this->expectException(BadRequestException::class);
        $this->budgetsService->assertOperationBudget('unknownOperation', 1);
    }

    public function testCounterScope(): void
    {
        // the test cache stands in for APCu (no Redis Sentinel group configured)
        self::assertSame(SiteInsightsProviderBudgetsService::COUNTER_SCOPE_PER_POD, $this->budgetsService->getCounterScope());
    }
}
