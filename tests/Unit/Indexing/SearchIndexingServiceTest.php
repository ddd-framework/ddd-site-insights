<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Unit\Indexing;

use DDD\Domain\SiteInsights\Entities\Errors\SiteInsightsErrorCause;
use DDD\Domain\SiteInsights\Services\SearchIndexingService;
use DDD\Domain\SiteInsights\Services\SiteInsightsAccessPolicyService;
use DDD\Domain\SiteInsights\Services\SiteInsightsProviderBudgetsService;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\ForbiddenException;
use DDD\Infrastructure\Services\AuthService;
use DDD\Infrastructure\Services\DDDService;
use DDD\Tests\SiteInsights\Support\AnsweringNetwork;
use DDD\Tests\SiteInsights\Support\FixedTokenCredentialsProviderService;
use DDD\Tests\SiteInsights\Support\GoogleFixtures;
use DDD\Tests\SiteInsights\Support\SiteInsightsTestCase;
use DDD\Tests\SiteInsights\Support\TestAccounts;
use DDD\Tests\SiteInsights\Support\TestConfig;
use DDD\Tests\SiteInsights\Support\ViewOnlyAccessPolicyService;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/** Sitemaps and URL inspection on synthetic answers: mapping, cache, quota, per-URL errors, access */
class SearchIndexingServiceTest extends SiteInsightsTestCase
{
    public const string INDEXED_URL = 'https://example.test/guides/item-001/';
    public const string NOT_INDEXED_URL = 'https://example.test/guides/item-002/';

    /** @var int[] inspection calls per inspected URL */
    protected array $inspectionCallsByUrl = [];

    protected function setUp(): void
    {
        parent::setUp();
        $administratorAccount = TestAccounts::admin();
        AuthService::instance()->setAccount($administratorAccount);
    }

    public function testSitemapsAreMappedWithSubmittedCountsAndCachedAsAnHour(): void
    {
        $answeringNetwork = AnsweringNetwork::install(static fn (): Response => GoogleFixtures::response('sitemaps-list'));

        $sitemapStatuses = $this->getSearchIndexingService()->getSitemapStatuses(TestConfig::SITE_KEY);

        self::assertFalse(isset($sitemapStatuses->error));
        self::assertSame(TestConfig::SITE_KEY, $sitemapStatuses->siteKey);
        self::assertSame(TestConfig::PROPERTY, $sitemapStatuses->property);
        self::assertCount(2, $sitemapStatuses->getElements());
        [$indexStatus, $guidesStatus] = $sitemapStatuses->getElements();
        self::assertSame('https://example.test/sitemap.xml', $indexStatus->path);
        self::assertTrue($indexStatus->isIndex);
        self::assertSame(0, $indexStatus->submitted);
        self::assertCount(0, $indexStatus->submittedByType->getElements());
        self::assertSame('2026-11-01 10:00:00', gmdate('Y-m-d H:i:s', $indexStatus->lastSubmitted->getTimestamp()));
        self::assertFalse(isset($guidesStatus->lastSubmitted), 'found, not submitted');
        self::assertSame(2, $guidesStatus->warnings);
        self::assertSame(1, $guidesStatus->errors);
        self::assertSame(120, $guidesStatus->submitted);
        self::assertSame('web', $guidesStatus->submittedByType->first()->type);
        self::assertSame(1, $sitemapStatuses->coverage->providerCalls);
        self::assertFalse($sitemapStatuses->coverage->fromCache);
        self::assertSame(3600, $sitemapStatuses->coverage->cacheLifetimeSeconds);
        self::assertSame(0, $sitemapStatuses->coverage->inspectionsCounted);

        $sentRequest = $answeringNetwork->capturingNetwork->getSentRequests()[0];
        self::assertSame('GET', $sentRequest->getMethod());
        self::assertSame('/webmasters/v3/sites/https%3A%2F%2Fexample.test%2F/sitemaps', $sentRequest->getUri()->getPath());
        self::assertSame('Bearer ' . FixedTokenCredentialsProviderService::TOKEN_MARKER, $sentRequest->getHeaderLine('Authorization'));
        self::assertStringNotContainsString(FixedTokenCredentialsProviderService::TOKEN_MARKER, (string)$sentRequest->getUri());
        $serializedPayload = (string)json_encode($sitemapStatuses->toObject(cached: false, forPersistence: false));
        self::assertStringNotContainsString(FixedTokenCredentialsProviderService::TOKEN_MARKER, $serializedPayload);
        self::assertStringNotContainsString('indexed', $serializedPayload, 'Google\'s deprecated indexed count is not read');

        $cachedSitemapStatuses = $this->getSearchIndexingService()->getSitemapStatuses(TestConfig::SITE_KEY);
        self::assertSame(1, $answeringNetwork->countSentRequests(), 'the second read comes from the cache');
        self::assertTrue($cachedSitemapStatuses->coverage->fromCache);
        self::assertSame(0, $cachedSitemapStatuses->coverage->providerCalls);
        self::assertCount(2, $cachedSitemapStatuses->getElements());
        self::assertSame(120, $cachedSitemapStatuses->getElements()[1]->submitted);
    }

    public function testAFailedSitemapsListCarriesItsErrorAndIsNotCached(): void
    {
        $answeringNetwork = AnsweringNetwork::install(static fn (): Response => GoogleFixtures::response('error-403-permission', 403));

        $sitemapStatuses = $this->getSearchIndexingService()->getSitemapStatuses(TestConfig::SITE_KEY);

        self::assertSame(SiteInsightsErrorCause::NOT_USER_OF_PROPERTY->value, $sitemapStatuses->error->cause);
        self::assertSame(403, $sitemapStatuses->error->providerStatus);
        self::assertCount(0, $sitemapStatuses->getElements());
        self::assertSame(1, $sitemapStatuses->coverage->failedParts);
        $this->getSearchIndexingService()->getSitemapStatuses(TestConfig::SITE_KEY);
        self::assertSame(2, $answeringNetwork->countSentRequests(), 'a failure is asked again');
    }

    public function testInspectionsAskDistinctUrlsOnceAndFailForeignUrlsWithoutACall(): void
    {
        $answeringNetwork = $this->installInspectionNetwork();
        $foreignUrl = 'https://other.example.com/page/';

        $urlIndexInspections = $this->getSearchIndexingService()->inspectUrls(
            TestConfig::SITE_KEY,
            [self::INDEXED_URL, ' ' . self::NOT_INDEXED_URL, self::INDEXED_URL, $foreignUrl, 'ftp://example.test/file'],
            'en-US'
        );

        self::assertSame(
            [self::INDEXED_URL, self::NOT_INDEXED_URL, $foreignUrl, 'ftp://example.test/file'],
            array_map(static fn ($urlIndexInspection) => $urlIndexInspection->url, $urlIndexInspections->getElements()),
            'distinct URLs in the order given'
        );
        self::assertSame(1, $urlIndexInspections->duplicateUrlsDropped);
        self::assertSame('en-US', $urlIndexInspections->languageCode);
        $indexedInspection = $urlIndexInspections->findInspectionByUrl(self::INDEXED_URL);
        self::assertSame('PASS', $indexedInspection->verdict);
        self::assertSame('Submitted and indexed', $indexedInspection->coverageState);
        self::assertSame('MOBILE', $indexedInspection->crawledAs);
        self::assertSame(['https://example.test/sitemap.xml'], $indexedInspection->sitemaps);
        self::assertSame('2026-11-14 08:30:00', gmdate('Y-m-d H:i:s', $indexedInspection->lastCrawlTime->getTimestamp()));
        self::assertFalse($indexedInspection->fromCache);
        $notIndexedInspection = $urlIndexInspections->findInspectionByUrl(self::NOT_INDEXED_URL);
        self::assertSame('NEUTRAL', $notIndexedInspection->verdict);
        self::assertFalse(isset($notIndexedInspection->lastCrawlTime));
        self::assertSame(['https://example.test/guides/'], $notIndexedInspection->referringUrls);
        foreach ([$foreignUrl, 'ftp://example.test/file'] as $invalidUrl) {
            self::assertSame(SiteInsightsErrorCause::INVALID_REQUEST->value, $urlIndexInspections->findInspectionByUrl($invalidUrl)->error->cause);
        }
        self::assertTrue($urlIndexInspections->hasFailures());

        self::assertSame(2, $answeringNetwork->countSentRequests(), 'one call per distinct URL of the property');
        $sentBodies = $answeringNetwork->getSentBodies();
        usort($sentBodies, static fn (array $firstBody, array $secondBody): int => strcmp($firstBody['inspectionUrl'], $secondBody['inspectionUrl']));
        self::assertSame([
            ['inspectionUrl' => self::INDEXED_URL, 'siteUrl' => TestConfig::PROPERTY, 'languageCode' => 'en-US'],
            ['inspectionUrl' => self::NOT_INDEXED_URL, 'siteUrl' => TestConfig::PROPERTY, 'languageCode' => 'en-US'],
        ], $sentBodies);
        foreach ($answeringNetwork->capturingNetwork->getSentRequests() as $sentRequest) {
            self::assertSame('POST', $sentRequest->getMethod());
            self::assertSame('searchconsole.googleapis.com', $sentRequest->getUri()->getHost());
            self::assertStringNotContainsString(FixedTokenCredentialsProviderService::TOKEN_MARKER, (string)$sentRequest->getBody());
        }

        $coverage = $urlIndexInspections->coverage;
        self::assertSame(4, $coverage->requestedParts);
        self::assertSame(2, $coverage->failedParts);
        self::assertSame(2, $coverage->providerCalls);
        self::assertSame(2, $coverage->inspectionsCounted);
        self::assertSame(86400, $coverage->cacheLifetimeSeconds);
        self::assertSame(SiteInsightsProviderBudgetsService::COUNTER_SCOPE_PER_POD, $urlIndexInspections->inspectionCounterScope);
        self::assertSame([2], array_values($this->findDailyInspectionCounters()), 'two inspections counted for the day');
    }

    public function testCachedInspectionsCountNothing(): void
    {
        $answeringNetwork = $this->installInspectionNetwork();
        $this->getSearchIndexingService()->inspectUrls(TestConfig::SITE_KEY, [self::INDEXED_URL]);

        $urlIndexInspections = $this->getSearchIndexingService()->inspectUrls(TestConfig::SITE_KEY, [self::INDEXED_URL, self::NOT_INDEXED_URL]);

        self::assertSame(2, $answeringNetwork->countSentRequests(), 'only the URL not yet cached is asked');
        self::assertTrue($urlIndexInspections->findInspectionByUrl(self::INDEXED_URL)->fromCache);
        self::assertFalse($urlIndexInspections->findInspectionByUrl(self::NOT_INDEXED_URL)->fromCache);
        self::assertSame(1, $urlIndexInspections->coverage->partsFromCache);
        self::assertSame(1, $urlIndexInspections->coverage->inspectionsCounted);
        self::assertFalse($urlIndexInspections->coverage->fromCache);
        self::assertSame([2], array_values($this->findDailyInspectionCounters()));

        $allCachedInspections = $this->getSearchIndexingService()->inspectUrls(TestConfig::SITE_KEY, [self::NOT_INDEXED_URL, self::INDEXED_URL]);
        self::assertTrue($allCachedInspections->coverage->fromCache);
        self::assertSame(0, $allCachedInspections->coverage->inspectionsCounted);
        self::assertSame(2, $answeringNetwork->countSentRequests());
    }

    public function testTheDailyShareRefusesInspectionsWithoutACall(): void
    {
        // a share of 1 inspection per day: two URLs together are refused as a whole
        TestConfig::apply(['budgets' => ['inspectionsPerDayPerSite' => 2, 'inspectionShare' => 0.5]]);
        $answeringNetwork = $this->installInspectionNetwork();

        $urlIndexInspections = $this->getSearchIndexingService()->inspectUrls(TestConfig::SITE_KEY, [self::INDEXED_URL, self::NOT_INDEXED_URL]);

        self::assertSame(0, $answeringNetwork->countSentRequests());
        foreach ($urlIndexInspections->getElements() as $urlIndexInspection) {
            self::assertSame(SiteInsightsErrorCause::BUDGET_REFUSED->value, $urlIndexInspection->error->cause);
            self::assertTrue($urlIndexInspection->error->retryable);
        }
        self::assertSame(0, $urlIndexInspections->coverage->inspectionsCounted);
        self::assertSame([], array_values(array_filter($this->findDailyInspectionCounters())), 'nothing stays counted');

        $singleInspection = $this->getSearchIndexingService()->inspectUrls(TestConfig::SITE_KEY, [self::INDEXED_URL]);
        self::assertSame('PASS', $singleInspection->first()->verdict, 'one URL fits the share');
    }

    public function testOneFailedUrlLeavesTheOthers(): void
    {
        $answeringNetwork = $this->installInspectionNetwork(static fn (string $inspectionUrl): ?Response => $inspectionUrl === self::NOT_INDEXED_URL
            ? GoogleFixtures::response('inspection-403', 403)
            : null);

        $urlIndexInspections = $this->getSearchIndexingService()->inspectUrls(TestConfig::SITE_KEY, [self::INDEXED_URL, self::NOT_INDEXED_URL]);

        self::assertSame('PASS', $urlIndexInspections->findInspectionByUrl(self::INDEXED_URL)->verdict);
        $failedInspection = $urlIndexInspections->findInspectionByUrl(self::NOT_INDEXED_URL);
        self::assertSame(SiteInsightsErrorCause::NOT_USER_OF_PROPERTY->value, $failedInspection->error->cause);
        self::assertFalse(isset($failedInspection->verdict));
        self::assertSame(1, $urlIndexInspections->coverage->failedParts);

        $this->getSearchIndexingService()->inspectUrls(TestConfig::SITE_KEY, [self::NOT_INDEXED_URL]);
        self::assertSame(3, $answeringNetwork->countSentRequests(), 'a failed inspection is not cached');
    }

    public function testA401DropsTheTokenAndAsksOnceMore(): void
    {
        $answeringNetwork = $this->installInspectionNetwork(fn (string $inspectionUrl): ?Response => ($this->inspectionCallsByUrl[$inspectionUrl] ?? 0) === 1
            ? GoogleFixtures::response('error-401', 401)
            : null);

        $urlIndexInspections = $this->getSearchIndexingService()->inspectUrls(TestConfig::SITE_KEY, [self::INDEXED_URL]);

        self::assertSame('PASS', $urlIndexInspections->first()->verdict);
        self::assertSame(2, $answeringNetwork->countSentRequests());
        self::assertSame(1, $this->credentialsProviderService->forgottenTokens);
        self::assertSame(2, $urlIndexInspections->coverage->providerCalls);
        self::assertSame(2, $urlIndexInspections->coverage->inspectionsCounted, 'the retry counts as well');
    }

    public function testAnOversizedAnswerFailsItsOwnUrlOnly(): void
    {
        TestConfig::apply(['budgets' => ['maxResponseBytes' => 20000]]);
        $answeringNetwork = $this->installInspectionNetwork(static fn (string $inspectionUrl): ?Response => $inspectionUrl === self::NOT_INDEXED_URL
            ? GoogleFixtures::oversizeChunkedResponse(40000)
            : null);

        $urlIndexInspections = $this->getSearchIndexingService()->inspectUrls(TestConfig::SITE_KEY, [self::INDEXED_URL, self::NOT_INDEXED_URL]);

        self::assertSame('PASS', $urlIndexInspections->findInspectionByUrl(self::INDEXED_URL)->verdict);
        self::assertSame(SiteInsightsErrorCause::ANSWER_UNREADABLE->value, $urlIndexInspections->findInspectionByUrl(self::NOT_INDEXED_URL)->error->cause);
        self::assertSame(2, $this->inspectionCallsByUrl[self::NOT_INDEXED_URL], 'asked once more alone');
        self::assertLessThanOrEqual(2, $this->inspectionCallsByUrl[self::INDEXED_URL]);
        self::assertSame($answeringNetwork->countSentRequests(), $urlIndexInspections->coverage->inspectionsCounted, 'every call sent is counted');
    }

    public function testInvalidRequestsAreRefusedBeforeAnyCall(): void
    {
        TestConfig::apply(['budgets' => ['maxInspectionUrls' => 2]]);
        $answeringNetwork = $this->installInspectionNetwork();
        $searchIndexingService = $this->getSearchIndexingService();

        foreach ([
            'too many' => [[self::INDEXED_URL, self::NOT_INDEXED_URL, 'https://example.test/third/'], null],
            'none' => [[], null],
            'language' => [[self::INDEXED_URL], 'english'],
        ] as $caseName => [$urls, $languageCode]) {
            try {
                $searchIndexingService->inspectUrls(TestConfig::SITE_KEY, $urls, $languageCode);
                self::fail($caseName . ' must be refused');
            } catch (BadRequestException $badRequestException) {
                self::assertNotSame('', $badRequestException->getMessage(), $caseName);
            }
        }
        self::assertSame(0, $answeringNetwork->countSentRequests());
    }

    public function testInspectingNeedsTheInspectRight(): void
    {
        $this->setService(SiteInsightsAccessPolicyService::class, new ViewOnlyAccessPolicyService());
        $answeringNetwork = $this->installInspectionNetwork();

        $this->getSearchIndexingService()->getSitemapStatuses(TestConfig::SITE_KEY);
        $this->expectException(ForbiddenException::class);
        try {
            $this->getSearchIndexingService()->inspectUrls(TestConfig::SITE_KEY, [self::INDEXED_URL]);
        } finally {
            self::assertSame(1, $answeringNetwork->countSentRequests(), 'the sitemaps read only');
        }
    }

    public function testANonAdminMayNeitherReadNorInspect(): void
    {
        $nonAdminAccount = TestAccounts::nonAdmin();
        AuthService::instance()->setAccount($nonAdminAccount);
        $answeringNetwork = $this->installInspectionNetwork();
        foreach ([
            fn () => $this->getSearchIndexingService()->getSitemapStatuses(TestConfig::SITE_KEY),
            fn () => $this->getSearchIndexingService()->inspectUrls(TestConfig::SITE_KEY, [self::INDEXED_URL]),
        ] as $indexingCall) {
            try {
                $indexingCall();
                self::fail('a non-admin account is refused');
            } catch (ForbiddenException $forbiddenException) {
                self::assertStringContainsString('may not', $forbiddenException->getMessage());
            }
        }
        self::assertSame(0, $answeringNetwork->countSentRequests());
    }

    public function testADomainPropertyAcceptsItsSubdomains(): void
    {
        $domainSite = TestConfig::getDefaultSites()[0];
        $domainSite['property'] = 'sc-domain:example.test';
        TestConfig::apply(['sites' => ['sites' => [$domainSite]]]);
        $answeringNetwork = $this->installInspectionNetwork();

        $urlIndexInspections = $this->getSearchIndexingService()->inspectUrls(
            TestConfig::SITE_KEY,
            ['https://shop.example.test/cart', 'https://example.test.other.example.com/']
        );

        self::assertSame('PASS', $urlIndexInspections->findInspectionByUrl('https://shop.example.test/cart')->verdict);
        self::assertSame(SiteInsightsErrorCause::INVALID_REQUEST->value, $urlIndexInspections->findInspectionByUrl('https://example.test.other.example.com/')->error->cause);
        self::assertSame(['inspectionUrl' => 'https://shop.example.test/cart', 'siteUrl' => 'sc-domain:example.test'], $answeringNetwork->getSentBodies()[0]);
    }

    /**
     * @param callable|null $answerOverride fn(string $inspectionUrl): ?Response, null for the default answer
     * @return AnsweringNetwork answers NOT_INDEXED_URL as not indexed, every other URL as indexed
     */
    protected function installInspectionNetwork(?callable $answerOverride = null): AnsweringNetwork
    {
        return AnsweringNetwork::install(function (array $requestBody, RequestInterface $request) use ($answerOverride): Response {
            $inspectionUrl = (string)($requestBody['inspectionUrl'] ?? '');
            $this->inspectionCallsByUrl[$inspectionUrl] = ($this->inspectionCallsByUrl[$inspectionUrl] ?? 0) + 1;
            $overriddenAnswer = $answerOverride !== null ? $answerOverride($inspectionUrl) : null;
            if ($overriddenAnswer !== null) {
                return $overriddenAnswer;
            }
            return GoogleFixtures::response($inspectionUrl === self::NOT_INDEXED_URL ? 'inspection-not-indexed' : 'inspection-indexed');
        });
    }

    /** @return int[] the daily inspection counters by key */
    protected function findDailyInspectionCounters(): array
    {
        return array_filter(
            $this->getRedisCache()->countersByKey,
            static fn (string $counterKey): bool => str_contains($counterKey, '.inspection.'),
            ARRAY_FILTER_USE_KEY
        );
    }

    protected function getSearchIndexingService(): SearchIndexingService
    {
        return DDDService::instance()->getService(SearchIndexingService::class);
    }
}
