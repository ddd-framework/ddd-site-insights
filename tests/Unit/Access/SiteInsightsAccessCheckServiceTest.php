<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Unit\Access;

use DDD\Domain\SiteInsights\Entities\Access\SiteInsightsAccessCheck;
use DDD\Domain\SiteInsights\Entities\Errors\SiteInsightsErrorCause;
use DDD\Domain\SiteInsights\Services\SiteInsightsAccessCheckService;
use DDD\Domain\SiteInsights\Services\SiteInsightsCredentialsProviderService;
use DDD\Infrastructure\Services\DDDService;
use DDD\Tests\SiteInsights\Support\CapturingNetwork;
use DDD\Tests\SiteInsights\Support\GoogleFixtures;
use DDD\Tests\SiteInsights\Support\SiteInsightsTestCase;
use DDD\Tests\SiteInsights\Support\TestConfig;
use DDD\Domain\SiteInsights\Services\SiteInsightsProviderBudgetsService;
use DDD\Tests\SiteInsights\Support\TestBudgetsService;
use GuzzleHttp\Psr7\Response;

/** Every capability of a site, checked live on synthetic answers */
class SiteInsightsAccessCheckServiceTest extends SiteInsightsTestCase
{
    public function testEveryCapabilityOk(): void
    {
        $capturingNetwork = CapturingNetwork::install(
            GoogleFixtures::response('sites-list'),
            GoogleFixtures::response('site-get'),
            GoogleFixtures::response('searchanalytics-totals'),
            GoogleFixtures::response('sitemaps-list'),
            GoogleFixtures::response('inspection-indexed')
        );

        $siteInsightsAccessChecks = $this->getSiteInsightsAccessCheckService()->checkAccess($this->getSite());

        self::assertSame(SiteInsightsAccessCheck::CAPABILITIES, array_map(static fn ($check) => $check->capability, $siteInsightsAccessChecks->getElements()));
        foreach ($siteInsightsAccessChecks->getElements() as $siteInsightsAccessCheck) {
            $expectedState = $siteInsightsAccessCheck->capability === SiteInsightsAccessCheck::CAPABILITY_BUDGET_COUNTERS_SHARED
                ? SiteInsightsAccessCheck::STATE_WARNING
                : SiteInsightsAccessCheck::STATE_OK;
            self::assertSame($expectedState, $siteInsightsAccessCheck->state, $siteInsightsAccessCheck->capability);
        }
        self::assertTrue($siteInsightsAccessChecks->hasWarnings(), 'counters per pod work, but are no pass');
        self::assertFalse($siteInsightsAccessChecks->hasFailures());
        self::assertSame('siteRestrictedUser', $siteInsightsAccessChecks->findCheck(TestConfig::SITE_KEY, SiteInsightsAccessCheck::CAPABILITY_PERMISSION_LEVEL)->detail);
        self::assertSame('verdict PASS', $siteInsightsAccessChecks->findCheck(TestConfig::SITE_KEY, SiteInsightsAccessCheck::CAPABILITY_URL_INSPECTION)->detail);
        self::assertSame('2 sitemap file(s)', $siteInsightsAccessChecks->findCheck(TestConfig::SITE_KEY, SiteInsightsAccessCheck::CAPABILITY_SITEMAPS)->detail);
        $countersCheckPerPod = $siteInsightsAccessChecks->findCheck(TestConfig::SITE_KEY, SiteInsightsAccessCheck::CAPABILITY_BUDGET_COUNTERS_SHARED);
        self::assertSame('perPod', $countersCheckPerPod->detailCode);
        self::assertStringContainsString('per pod', $countersCheckPerPod->detail, 'a readable sentence');
        self::assertFalse(isset($siteInsightsAccessChecks->findCheck(TestConfig::SITE_KEY, SiteInsightsAccessCheck::CAPABILITY_URL_INSPECTION)->detailCode), 'free text only');
        self::assertSame(5, $capturingNetwork->countSentRequests());
        $sentPaths = array_map(static fn ($sentRequest) => $sentRequest->getMethod() . ' ' . $sentRequest->getUri()->getHost() . $sentRequest->getUri()->getPath(), $capturingNetwork->getSentRequests());
        self::assertSame([
            'GET www.googleapis.com/webmasters/v3/sites',
            'GET www.googleapis.com/webmasters/v3/sites/https%3A%2F%2Fexample.test%2F',
            'POST www.googleapis.com/webmasters/v3/sites/https%3A%2F%2Fexample.test%2F/searchAnalytics/query',
            'GET www.googleapis.com/webmasters/v3/sites/https%3A%2F%2Fexample.test%2F/sitemaps',
            'POST searchconsole.googleapis.com/v1/urlInspection/index:inspect',
        ], $sentPaths);
        self::assertSame(['inspectionUrl' => 'https://example.test/', 'siteUrl' => 'https://example.test/'], json_decode((string)$capturingNetwork->getSentRequests()[4]->getBody(), true));
        $searchAnalyticsBody = json_decode((string)$capturingNetwork->getSentRequests()[2]->getBody(), true);
        self::assertSame(['startDate' => '2026-11-13', 'endDate' => '2026-11-13', 'type' => 'web', 'dataState' => 'final', 'aggregationType' => 'byProperty', 'rowLimit' => 1, 'startRow' => 0], $searchAnalyticsBody);
        $inspectionCounters = array_filter($this->getRedisCache()->countersByKey, static fn (string $counterKey) => str_contains($counterKey, '.inspection.'), ARRAY_FILTER_USE_KEY);
        self::assertSame([1], array_values($inspectionCounters), 'one inspection is counted');
    }

    public function testSharedCountersAreOk(): void
    {
        $this->setService(SiteInsightsProviderBudgetsService::class, new class extends TestBudgetsService {
            public function getCounterScope(): string
            {
                return SiteInsightsProviderBudgetsService::COUNTER_SCOPE_SHARED;
            }
        });
        CapturingNetwork::install(
            GoogleFixtures::response('sites-list'),
            GoogleFixtures::response('site-get'),
            GoogleFixtures::response('searchanalytics-totals'),
            GoogleFixtures::response('sitemaps-list'),
            GoogleFixtures::response('inspection-indexed')
        );

        $siteInsightsAccessChecks = $this->getSiteInsightsAccessCheckService()->checkAccess($this->getSite());

        $countersCheck = $siteInsightsAccessChecks->findCheck(TestConfig::SITE_KEY, SiteInsightsAccessCheck::CAPABILITY_BUDGET_COUNTERS_SHARED);
        self::assertSame(SiteInsightsAccessCheck::STATE_OK, $countersCheck->state);
        self::assertSame('shared', $countersCheck->detailCode);
        self::assertSame('Call counters are shared by every pod (Redis).', $countersCheck->detail);
        self::assertFalse($siteInsightsAccessChecks->hasWarnings());
    }

    public function testProviderRefusals(): void
    {
        CapturingNetwork::install(
            GoogleFixtures::response('sites-list-without-property'),
            GoogleFixtures::response('error-403-permission', 403),
            GoogleFixtures::response('error-403-service-disabled', 403),
            GoogleFixtures::response('error-429', 429),
            new Response(403, ['Content-Type' => 'application/json'], '{"error":{"code":403,"message":"The account reader@example-project.test may not inspect this URL.","status":"PERMISSION_DENIED","errors":[{"reason":"forbidden"}]}}')
        );

        $siteInsightsAccessChecks = $this->getSiteInsightsAccessCheckService()->checkAccess($this->getSite());

        $causesByCapability = [];
        foreach ($siteInsightsAccessChecks->getElements() as $siteInsightsAccessCheck) {
            $causesByCapability[$siteInsightsAccessCheck->capability] = $siteInsightsAccessCheck->cause ?? $siteInsightsAccessCheck->state;
        }
        self::assertSame([
            'keyReadable' => 'ok',
            'tokenObtained' => 'ok',
            'propertyListed' => SiteInsightsErrorCause::NOT_USER_OF_PROPERTY->value,
            'permissionLevel' => SiteInsightsErrorCause::NOT_USER_OF_PROPERTY->value,
            'searchAnalytics' => SiteInsightsErrorCause::API_NOT_ENABLED->value,
            'sitemaps' => SiteInsightsErrorCause::QUOTA_EXCEEDED->value,
            'urlInspection' => SiteInsightsErrorCause::NOT_USER_OF_PROPERTY->value,
            'budgetCountersShared' => 'warning',
        ], $causesByCapability);
        self::assertTrue($siteInsightsAccessChecks->hasFailures());
        $inspectionDetail = $siteInsightsAccessChecks->findCheck(TestConfig::SITE_KEY, SiteInsightsAccessCheck::CAPABILITY_URL_INSPECTION)->detail;
        self::assertStringNotContainsString('reader@example-project.test', $inspectionDetail);
        self::assertStringContainsString('[e-mail address]', $inspectionDetail);
    }

    public function testAnUnreadableKeySkipsEveryProviderCapability(): void
    {
        $this->setService(SiteInsightsCredentialsProviderService::class, null);
        $_ENV[TestConfig::KEY_ENVIRONMENT_VARIABLE] = 'not base64 %%%';
        $capturingNetwork = CapturingNetwork::install();

        $siteInsightsAccessChecks = $this->getSiteInsightsAccessCheckService()->checkAccess($this->getSite());

        $keyCheck = $siteInsightsAccessChecks->findCheck(TestConfig::SITE_KEY, SiteInsightsAccessCheck::CAPABILITY_KEY_READABLE);
        self::assertSame(SiteInsightsAccessCheck::STATE_FAILED, $keyCheck->state);
        self::assertSame(SiteInsightsErrorCause::KEY_UNREADABLE->value, $keyCheck->cause);
        self::assertStringContainsString('not base64', $keyCheck->detail);
        foreach (['tokenObtained', 'propertyListed', 'permissionLevel', 'searchAnalytics', 'sitemaps', 'urlInspection'] as $skippedCapability) {
            self::assertSame(SiteInsightsAccessCheck::STATE_SKIPPED, $siteInsightsAccessChecks->findCheck(TestConfig::SITE_KEY, $skippedCapability)->state, $skippedCapability);
        }
        self::assertSame(0, $capturingNetwork->countSentRequests());
        self::assertStringNotContainsString('%%%', json_encode($siteInsightsAccessChecks->toObject(cached: false, forPersistence: false)));
    }

    protected function getSiteInsightsAccessCheckService(): SiteInsightsAccessCheckService
    {
        return DDDService::instance()->getService(SiteInsightsAccessCheckService::class);
    }
}
