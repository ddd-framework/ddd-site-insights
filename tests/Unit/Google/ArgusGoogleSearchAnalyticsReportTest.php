<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Unit\Google;

use DDD\Domain\Base\Repo\Argus\Attributes\ArgusLoad;
use DDD\Domain\Base\Repo\Argus\Utils\ArgusApiOperation;
use DDD\Domain\Base\Repo\Argus\Utils\ArgusApiOperations;
use DDD\Domain\Base\Repo\Argus\Utils\ArgusCache;
use DDD\Domain\SiteInsights\Entities\Errors\SiteInsightsErrorCause;
use DDD\Domain\SiteInsights\Repo\Argus\Google\ArgusGoogleSearchAnalyticsReports;
use DDD\Domain\SiteInsights\Services\GoogleApiRequestsService;
use DDD\Domain\SiteInsights\Services\GoogleServiceAccountTokensService;
use DDD\Domain\SiteInsights\Services\SiteInsightsCredentialsProviderService;
use DDD\Domain\SiteInsights\Utils\SearchDays;
use DDD\Infrastructure\Services\DDDService;
use DDD\Tests\SiteInsights\Support\CapturingNetwork;
use DDD\Tests\SiteInsights\Support\FixedTokenCredentialsProviderService;
use DDD\Tests\SiteInsights\Support\GoogleFixtures;
use DDD\Tests\SiteInsights\Support\MockedTokenEndpointTokensService;
use DDD\Tests\SiteInsights\Support\SearchQuestionFactory;
use DDD\Tests\SiteInsights\Support\SiteInsightsTestCase;
use DDD\Tests\SiteInsights\Support\SyntheticServiceAccountKey;
use DDD\Tests\SiteInsights\Support\TestConfig;
use DDD\Tests\SiteInsights\Support\TransportCapturingArgusApiOperations;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;

/**
 * The B0 transport gate of the Search Analytics report: what is actually sent, that no secret leaves through any
 * channel, what the cache entry holds and how it is keyed, that failures are not cached, and Google's answer shape.
 */
class ArgusGoogleSearchAnalyticsReportTest extends SiteInsightsTestCase
{
    protected const string GATEWAY_TOKEN_ENVIRONMENT_VARIABLE = 'SITE_INSIGHTS_TEST_GATEWAY_TOKEN';
    protected const string GATEWAY_TOKEN = 'gateway-secret-of-the-application';

    /** The Argus config of an application that adds its own key, auth, debug flags, tracking and a gateway */
    protected const array APPLICATION_ARGUS_CONFIG = [
        'microservices' => ['baseUrl' => 'https://services.example.test/'],
        'requestSettings' => ['headers' => ['x-api-key' => 'application-services-key', 'Accept' => 'application/json', 'Content-Type' => 'application/json']],
        'auth' => true,
        'appendDebugFlags' => true,
        'trackingHeader' => 'x-argus-tracking',
        'gateways' => [
            'google' => ['origin' => 'https://www.googleapis.com', 'tokenHeader' => 'X-Gateway-Token', 'tokenEnvName' => self::GATEWAY_TOKEN_ENVIRONMENT_VARIABLE],
        ],
    ];

    /** @var string[] Headers an external Google call may carry */
    protected const array APPROVED_HEADERS = ['authorization', 'accept', 'content-type', 'user-agent', 'host', 'content-length'];

    protected function tearDown(): void
    {
        unset($_ENV[self::GATEWAY_TOKEN_ENVIRONMENT_VARIABLE]);
        ArgusApiOperations::$journalRequests = false;
        parent::tearDown();
    }

    public function testRequestActuallySent(): void
    {
        TestConfig::apply([], self::APPLICATION_ARGUS_CONFIG);
        $_ENV[self::GATEWAY_TOKEN_ENVIRONMENT_VARIABLE] = self::GATEWAY_TOKEN;
        ArgusLoad::$logArgusCalls = true;
        $argusReport = $this->googleSearchConsoleSourceService->buildArgusReportOfQuestion($this->getSite(), SearchQuestionFactory::create(['query']));
        /** @var GoogleApiRequestsService $googleApiRequestsService */
        $googleApiRequestsService = DDDService::instance()->getService(GoogleApiRequestsService::class);
        $payload = $googleApiRequestsService->buildPayload(TestConfig::CREDENTIALS_ENTRY_NAME, ['siteUrl' => $argusReport->siteUrl], $argusReport->buildRequestBody());
        $loadEndpoint = (string)$argusReport->getArgusSettings()->getLoadEndpoint();
        $argusApiOperation = new ArgusApiOperation($argusReport, $argusReport->cacheKey(), $loadEndpoint, $payload);
        $argusApiOperations = new TransportCapturingArgusApiOperations(GoogleFixtures::response('searchanalytics-queries'));
        $argusApiOperations->addOperation($argusApiOperation);

        $argusApiOperations->execute(timeout: 25.0);

        self::assertCount(1, $argusApiOperations->sentRequests);
        $sentRequest = $argusApiOperations->sentRequests[0]['request'];
        $sentHeaderNames = array_map('strtolower', array_keys($sentRequest->getHeaders()));
        self::assertSame([], array_values(array_diff($sentHeaderNames, self::APPROVED_HEADERS)), 'only approved headers: ' . implode(', ', $sentHeaderNames));
        self::assertSame('Bearer ' . FixedTokenCredentialsProviderService::TOKEN_MARKER, $sentRequest->getHeaderLine('Authorization'));
        self::assertSame('application/json', $sentRequest->getHeaderLine('Accept'));
        self::assertSame('', $sentRequest->getUri()->getQuery(), 'no debug flags, no added query parameter');
        self::assertSame('POST', $sentRequest->getMethod());
        self::assertSame('www.googleapis.com', $sentRequest->getUri()->getHost());
        self::assertSame('/webmasters/v3/sites/https%3A%2F%2Fexample.test%2F/searchAnalytics/query', $sentRequest->getUri()->getPath());
        self::assertStringNotContainsString(FixedTokenCredentialsProviderService::TOKEN_MARKER, (string)$sentRequest->getBody());
        self::assertSame(['startDate', 'endDate', 'dimensions', 'type', 'dataState', 'aggregationType', 'rowLimit', 'startRow'], array_keys(json_decode((string)$sentRequest->getBody(), true)));
        $sentOptions = $argusApiOperations->sentRequests[0]['options'];
        self::assertFalse($sentOptions['allow_redirects']);
        self::assertEquals(25.0, $sentOptions['timeout']);
        self::assertSame([], ArgusApiOperations::getExecutedArgusCalls(), 'a private call is not in the call log');
        self::assertCount(3, $argusReport->rows->getElements(), 'the answer reached the report');
    }

    public function testSecretsNeverLeave(): void
    {
        // the real credentials provider over a token endpoint double: the assertion and the token are real strings
        $this->setService(SiteInsightsCredentialsProviderService::class, null);
        $tokensService = new MockedTokenEndpointTokensService(GoogleFixtures::response('token-ok'));
        $tokensService->tokenCache = $this->getApcCache();
        $this->setService(GoogleServiceAccountTokensService::class, $tokensService);
        $capturingNetwork = CapturingNetwork::install(
            GoogleFixtures::response('searchanalytics-queries'),
            GoogleFixtures::response('searchanalytics-totals'),
            GoogleFixtures::response('searchanalytics-queries')
        );
        ArgusLoad::$logArgusCalls = true;
        ArgusApiOperations::$journalRequests = true;
        $insightsSite = $this->getSite();

        $searchReports = $this->googleSearchConsoleSourceService->loadReports(
            $insightsSite,
            SearchQuestionFactory::createSet(SearchQuestionFactory::create(['query', 'device'])),
            false
        );
        ob_start();
        $displayedCallBatch = new ArgusGoogleSearchAnalyticsReports();
        $displayedCallReport = $this->googleSearchConsoleSourceService->buildArgusReportOfQuestion($insightsSite, SearchQuestionFactory::create(['page']));
        $displayedCallBatch->add($displayedCallReport);
        $displayedCallBatch->setPropertiesToLoad($displayedCallReport::class);
        $displayedCallBatch->argusLoad(useArgusEntityCache: false, displayCall: true, autoloadCurrentObject: false);
        $displayedResponseBatch = new ArgusGoogleSearchAnalyticsReports();
        $displayedResponseReport = $this->googleSearchConsoleSourceService->buildArgusReportOfQuestion($insightsSite, SearchQuestionFactory::create());
        $displayedResponseBatch->add($displayedResponseReport);
        $displayedResponseBatch->setPropertiesToLoad($displayedResponseReport::class);
        $displayedResponseBatch->argusLoad(useArgusEntityCache: false, displayResponse: true, autoloadCurrentObject: false);
        $bufferedOutput = (string)ob_get_clean();
        // a load with cache writes the cache entry
        $cachedSearchReports = $this->googleSearchConsoleSourceService->loadReports(
            $insightsSite,
            SearchQuestionFactory::createSet(SearchQuestionFactory::create(['query', 'device']))
        );

        $assertion = $tokensService->getLastAssertion();
        self::assertNotSame('', $assertion);
        self::assertStringContainsString('redacted', $bufferedOutput);
        $channels = [
            'recording' => $capturingNetwork->getRecordingAsText(),
            'call log' => json_encode(ArgusApiOperations::getExecutedArgusCalls()),
            'call journal' => json_encode(ArgusApiOperations::getCallJournal()->toObject(cached: false)),
            'display output' => $bufferedOutput,
            'redis cache' => $this->getRedisCache()->dumpAsText(),
            'serialised reports' => json_encode($searchReports->toObject(cached: false, forPersistence: false)) . json_encode($cachedSearchReports->toObject(cached: false, forPersistence: true)),
        ];
        self::assertNotEmpty(json_decode($channels['recording'], true), 'the recording holds the calls');
        self::assertNotSame('[]', $channels['call journal']);
        self::assertNotSame('[[],[]]', $channels['redis cache']);
        $secretMarkers = [FixedTokenCredentialsProviderService::TOKEN_MARKER, $assertion, SyntheticServiceAccountKey::CLIENT_EMAIL, ...SyntheticServiceAccountKey::get()->getPrivateKeyMarkerLines()];
        foreach ($channels as $channelName => $channelText) {
            foreach ($secretMarkers as $secretMarker) {
                self::assertStringNotContainsString($secretMarker, (string)$channelText, 'secret in ' . $channelName);
            }
            self::assertStringNotContainsString('Authorization', (string)$channelText, 'header in ' . $channelName);
        }
        // the APC group holds the token itself (that is its job) and nothing of an entity: no other value
        foreach ($this->getApcCache()->valuesByKey as $apcCacheKey => $apcValue) {
            if (str_starts_with($apcCacheKey, GoogleServiceAccountTokensService::CACHE_KEY_PREFIX)) {
                continue;
            }
            self::assertStringNotContainsString(FixedTokenCredentialsProviderService::TOKEN_MARKER, (string)$apcValue);
            self::assertStringNotContainsString($assertion, (string)$apcValue);
        }
        self::assertCount(1, $tokensService->sentRequests, 'one token exchange for every call');
    }

    public function testCacheEntryHoldsQuestionAndAnswerOnly(): void
    {
        CapturingNetwork::install(GoogleFixtures::response('searchanalytics-queries'));
        $searchQuestion = SearchQuestionFactory::create(['query', 'device']);

        $this->googleSearchConsoleSourceService->loadReports($this->getSite(), SearchQuestionFactory::createSet($searchQuestion));

        $argusReport = $this->googleSearchConsoleSourceService->buildArgusReportOfQuestion($this->getSite(), $searchQuestion);
        $cacheKey = ArgusCache::getCacheKey($argusReport->cacheKey());
        self::assertArrayHasKey($cacheKey, $this->getRedisCache()->valuesByKey);
        self::assertArrayNotHasKey($cacheKey, $this->getApcCache()->valuesByKey, 'a row list is cached in Redis only');
        self::assertSame(1800, $this->getRedisCache()->ttlsByKey[$cacheKey], 'no last final day known: the fresh lifetime');
        $cacheEntry = json_decode($this->getRedisCache()->valuesByKey[$cacheKey], true);
        foreach (['siteUrl', 'credentialsEntryName', 'credentialsRevision', 'startDate', 'endDate', 'dimensions', 'searchType', 'dataState', 'aggregationType', 'filters', 'rowLimit', 'startRow', 'rows', 'fetchedAt', 'responseAggregationType'] as $expectedField) {
            self::assertArrayHasKey($expectedField, $cacheEntry, $expectedField);
        }
        self::assertArrayNotHasKey('failure', $cacheEntry);
        self::assertSame(TestConfig::CREDENTIALS_ENTRY_NAME, $cacheEntry['credentialsEntryName']);
        $cacheEntryText = $this->getRedisCache()->valuesByKey[$cacheKey];
        self::assertStringNotContainsString('Authorization', $cacheEntryText);
        self::assertStringNotContainsString(FixedTokenCredentialsProviderService::TOKEN_MARKER, $cacheEntryText);
        self::assertStringNotContainsString(TestConfig::KEY_ENVIRONMENT_VARIABLE, $cacheEntryText);
        self::assertStringNotContainsString(SyntheticServiceAccountKey::CLIENT_EMAIL, $cacheEntryText);
    }

    public function testCacheIdentity(): void
    {
        $insightsSite = $this->getSite();
        $cacheKeyOf = fn ($searchQuestion): string => $this->googleSearchConsoleSourceService->buildArgusReportOfQuestion($insightsSite, $searchQuestion)->cacheKey();
        $baseQuestion = SearchQuestionFactory::create(['query', 'date'], filterTriples: [['page', 'contains', '/guides/'], ['country', 'equals', 'deu']]);
        $baseKey = $cacheKeyOf($baseQuestion);

        self::assertSame($baseKey, $cacheKeyOf(SearchQuestionFactory::create(['query', 'date'], filterTriples: [['page', 'contains', '/guides/'], ['country', 'equals', 'deu']])));
        self::assertSame($baseKey, $cacheKeyOf(SearchQuestionFactory::create(['date', 'query'], filterTriples: [['country', 'equals', 'deu'], ['page', 'contains', '/guides/']])), 'filter and dimension order are normalised');
        self::assertNotSame($baseKey, $cacheKeyOf(SearchQuestionFactory::create(['query', 'date'], filterTriples: [['page', 'contains', '/guides/'], ['country', 'equals', 'deu']], startRow: 25)));
        self::assertNotSame($baseKey, $cacheKeyOf(SearchQuestionFactory::create(['query'], filterTriples: [['page', 'contains', '/guides/'], ['country', 'equals', 'deu']])));
        self::assertNotSame($baseKey, $cacheKeyOf(SearchQuestionFactory::create(['query', 'date'], endDay: '2026-11-12', filterTriples: [['page', 'contains', '/guides/'], ['country', 'equals', 'deu']])));
        self::assertNotSame($baseKey, $cacheKeyOf(SearchQuestionFactory::create(['query', 'date'], filterTriples: [['page', 'contains', '/guides/'], ['country', 'equals', 'deu']], dataState: 'all')));

        $this->credentialsProviderService->accessToken = 'another-token';
        self::assertSame($baseKey, $cacheKeyOf($baseQuestion), 'the token does not change the key');

        $revisedSite = clone $insightsSite;
        $revisedSite->credentialsRevision = 2;
        self::assertNotSame($baseKey, $this->googleSearchConsoleSourceService->buildArgusReportOfQuestion($revisedSite, $baseQuestion)->cacheKey(), 'a raised credentials revision');

        TestConfig::apply(['cache' => ['schemaVersion' => 2]]);
        self::assertNotSame($baseKey, $cacheKeyOf($baseQuestion), 'a raised cache schema version');
    }

    public function testFailureIsNotCached(): void
    {
        $capturingNetwork = CapturingNetwork::install(
            GoogleFixtures::response('error-403-permission', 403),
            new ConnectException('Connection refused', new Request('POST', 'https://www.googleapis.com/'))
        );
        $permissionQuestion = SearchQuestionFactory::create(['query']);
        $rejectedQuestion = SearchQuestionFactory::create(['page']);

        $searchReports = $this->googleSearchConsoleSourceService->loadReports($this->getSite(), SearchQuestionFactory::createSet($permissionQuestion, $rejectedQuestion));

        self::assertSame(2, $capturingNetwork->countSentRequests());
        $causes = [];
        foreach ($searchReports->getElements() as $searchReport) {
            self::assertTrue(isset($searchReport->performanceRows->error));
            $causes[] = $searchReport->performanceRows->error->cause;
        }
        sort($causes);
        self::assertSame([SiteInsightsErrorCause::NOT_USER_OF_PROPERTY->value, SiteInsightsErrorCause::TRANSPORT->value], $causes);
        self::assertSame([], $this->getRedisCache()->valuesByKey, 'no failure, no pending marker stays');
        self::assertSame([], $this->getApcCache()->valuesByKey);

        // asked again: the provider is asked again
        $capturingNetwork->queue(GoogleFixtures::response('searchanalytics-queries'), GoogleFixtures::response('searchanalytics-pages'));
        $this->googleSearchConsoleSourceService->loadReports($this->getSite(), SearchQuestionFactory::createSet($permissionQuestion, $rejectedQuestion));
        self::assertSame(4, $capturingNetwork->countSentRequests());
    }

    public function testParsesGoogleShape(): void
    {
        CapturingNetwork::install(
            GoogleFixtures::response('searchanalytics-queries'),
            GoogleFixtures::response('searchanalytics-empty'),
            GoogleFixtures::response('searchanalytics-dates-all')
        );
        $insightsSite = $this->getSite();
        $argusGoogleSearchAnalyticsReports = new ArgusGoogleSearchAnalyticsReports();
        $queriesReport = $this->googleSearchConsoleSourceService->buildArgusReportOfQuestion($insightsSite, SearchQuestionFactory::create(['query', 'device']));
        $emptyReport = $this->googleSearchConsoleSourceService->buildArgusReportOfQuestion($insightsSite, SearchQuestionFactory::create());
        $datesReport = $this->googleSearchConsoleSourceService->buildArgusReportOfQuestion($insightsSite, SearchQuestionFactory::create(['date'], dataState: 'all'));
        $argusGoogleSearchAnalyticsReports->add($queriesReport, $emptyReport, $datesReport);
        $argusGoogleSearchAnalyticsReports->setPropertiesToLoad($queriesReport::class);
        $argusGoogleSearchAnalyticsReports->getArgusSettings()->keepFulfilledCallsWhenOneIsRejected = true;

        $argusGoogleSearchAnalyticsReports->argusLoad(useArgusEntityCache: false, autoloadCurrentObject: false);

        $queryRows = $queriesReport->rows->getElements();
        self::assertCount(3, $queryRows);
        self::assertSame(['alpha widget', 'DESKTOP'], $queryRows[0]->keys);
        self::assertSame(40.0, $queryRows[0]->clicks);
        self::assertSame(4.2, $queryRows[0]->position);
        self::assertFalse(isset($queryRows[2]->position), 'a row without position');
        self::assertSame('auto', $queriesReport->responseAggregationType);
        self::assertTrue(isset($queriesReport->fetchedAt));
        self::assertCount(0, $emptyReport->rows->getElements(), 'no rows: an empty set');
        self::assertSame('byProperty', $emptyReport->responseAggregationType);
        self::assertSame('2026-11-15', SearchDays::fromDate($datesReport->firstIncompleteDate));
        self::assertFalse(isset($datesReport->failure));
    }
}
