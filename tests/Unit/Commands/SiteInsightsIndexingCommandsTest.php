<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Unit\Commands;

use DDD\Domain\Base\Repo\Argus\Utils\ArgusApiOperations;
use DDD\Domain\SiteInsights\Services\SearchIndexingService;
use DDD\Domain\SiteInsights\Services\SiteInsightsAccessPolicyService;
use DDD\Infrastructure\Services\AuthService;
use DDD\Infrastructure\Services\DDDService;
use DDD\Infrastructure\Traits\Serializer\SerializerRegistry;
use DDD\Presentation\Api\Admin\SiteInsights\Dtos\SiteInsightsInspectionsPostRequestDto;
use DDD\Presentation\Api\Admin\SiteInsights\Dtos\SiteInsightsSiteGetRequestDto;
use DDD\Presentation\Base\Dtos\RestResponseDto;
use DDD\Symfony\Commands\SiteInsights\SiteInsightsInspect;
use DDD\Symfony\Commands\SiteInsights\SiteInsightsSitemaps;
use DDD\Tests\SiteInsights\Fixture\Controller\FixtureSiteInsightsIndexingController;
use DDD\Tests\SiteInsights\Support\AnsweringNetwork;
use DDD\Tests\SiteInsights\Support\FixedTokenCredentialsProviderService;
use DDD\Tests\SiteInsights\Support\GoogleFixtures;
use DDD\Tests\SiteInsights\Support\InMemoryCache;
use DDD\Tests\SiteInsights\Support\SiteInsightsTestCase;
use DDD\Tests\SiteInsights\Support\TestAccounts;
use DDD\Tests\SiteInsights\Support\TestCliAccountAccessPolicyService;
use DDD\Tests\SiteInsights\Support\TestConfig;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The indexing commands of B3: each command's JSON equals the payload of its endpoint (both built from the same
 * synthetic answers), the table format, exit codes and the bounded URL list.
 */
class SiteInsightsIndexingCommandsTest extends SiteInsightsTestCase
{
    public const string INDEXED_URL = 'https://example.test/guides/item-001/';
    public const string NOT_INDEXED_URL = 'https://example.test/guides/item-002/';
    public const string FOREIGN_URL = 'https://other.example.com/page/';

    protected TestCliAccountAccessPolicyService $accessPolicyService;

    protected AnsweringNetwork $answeringNetwork;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accessPolicyService = new TestCliAccountAccessPolicyService();
        $this->accessPolicyService->cliAccount = TestAccounts::admin();
        $this->setService(SiteInsightsAccessPolicyService::class, $this->accessPolicyService);
        $this->answeringNetwork = $this->installNetwork();
    }

    public function testTheSitemapsJsonEqualsTheEndpointPayload(): void
    {
        $commandTester = new CommandTester(new SiteInsightsSitemaps());
        self::assertSame(Command::SUCCESS, $commandTester->execute(['site' => TestConfig::SITE_KEY, '--format' => 'json']), $commandTester->getDisplay());
        $commandPayload = json_decode($commandTester->getDisplay(), true);

        $this->startAsAnotherProcess();
        $requestDto = new SiteInsightsSiteGetRequestDto();
        $requestParametersObject = (object)['siteKey' => TestConfig::SITE_KEY];
        $requestDto->setPropertiesFromObject($requestParametersObject);
        $responseDto = $this->createController()->getSiteInsightsIndexingSitemaps($requestDto, $this->getSearchIndexingService());
        $endpointPayload = $this->decodeResponse($responseDto);

        self::assertSame(['sitemapStatuses'], array_keys($commandPayload));
        self::assertEquals($this->withoutAnswerTimes($endpointPayload), $this->withoutAnswerTimes($commandPayload));
        self::assertSame(120, $commandPayload['sitemapStatuses']['elements'][1]['submitted']);
        $this->assertNoSecretIn($commandTester->getDisplay());
    }

    public function testTheInspectJsonEqualsTheEndpointPayload(): void
    {
        $commandTester = new CommandTester(new SiteInsightsInspect());
        $exitCode = $commandTester->execute(['site' => TestConfig::SITE_KEY, 'urls' => [self::INDEXED_URL, self::NOT_INDEXED_URL], '--languageCode' => 'en', '--format' => 'json']);
        self::assertSame(Command::SUCCESS, $exitCode, $commandTester->getDisplay());
        $commandPayload = json_decode($commandTester->getDisplay(), true);

        $this->startAsAnotherProcess();
        // hydrated from a request: the path parameter and the JSON body
        $request = Request::create(
            '/api/admin/siteInsights/sites/' . TestConfig::SITE_KEY . '/indexing/inspections',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string)json_encode(['urls' => [self::INDEXED_URL, self::NOT_INDEXED_URL], 'languageCode' => 'en'])
        );
        $request->attributes->set('siteKey', TestConfig::SITE_KEY);
        $requestDto = new SiteInsightsInspectionsPostRequestDto();
        $requestDto->setPropertiesFromRequest($request);
        self::assertSame([self::INDEXED_URL, self::NOT_INDEXED_URL], $requestDto->urls);
        $responseDto = $this->createController()->postSiteInsightsIndexingInspections($requestDto, $this->getSearchIndexingService());
        $endpointPayload = $this->decodeResponse($responseDto);

        self::assertSame(['urlIndexInspections'], array_keys($commandPayload));
        self::assertEquals($this->withoutAnswerTimes($endpointPayload), $this->withoutAnswerTimes($commandPayload));
        self::assertSame('PASS', $commandPayload['urlIndexInspections']['elements'][0]['verdict']);
        self::assertSame(2, $commandPayload['urlIndexInspections']['coverage']['inspectionsCounted']);
        $this->assertNoSecretIn($commandTester->getDisplay());
    }

    public function testTableFormatAndAFailedUrl(): void
    {
        $commandTester = new CommandTester(new SiteInsightsInspect());

        $exitCode = $commandTester->execute(['site' => TestConfig::SITE_KEY, 'urls' => [self::INDEXED_URL, self::FOREIGN_URL, self::INDEXED_URL]]);

        self::assertSame(Command::FAILURE, $exitCode, 'a URL failed: exit 1, the others are printed');
        $tableDisplay = $commandTester->getDisplay();
        self::assertStringContainsString('Submitted and indexed', $tableDisplay);
        self::assertStringContainsString('failed: invalidRequest', $tableDisplay);
        self::assertStringContainsString('1 inspection(s) counted against the daily quota (counters perPod)', $tableDisplay);
        self::assertStringContainsString('1 duplicate URL(s) inspected once', $tableDisplay);

        $sitemapsTester = new CommandTester(new SiteInsightsSitemaps());
        self::assertSame(Command::SUCCESS, $sitemapsTester->execute(['site' => TestConfig::SITE_KEY]));
        self::assertStringContainsString('https://example.test/sitemap-guides.xml', $sitemapsTester->getDisplay());
        self::assertStringContainsString('2026-11-15 04:01 UTC', $sitemapsTester->getDisplay());

        $toonTester = new CommandTester(new SiteInsightsInspect());
        self::assertSame(Command::SUCCESS, $toonTester->execute(['site' => TestConfig::SITE_KEY, 'urls' => [self::INDEXED_URL], '--format' => 'toon']));
        $toonPayload = json_decode($toonTester->getDisplay(), true);
        self::assertArrayHasKey('elementsInToonFormat', $toonPayload['urlIndexInspections']);
        self::assertArrayHasKey('coverage', $toonPayload['urlIndexInspections']);
    }

    public function testTheListIsBoundedAndAFailedSitemapsListExitsWithOne(): void
    {
        TestConfig::apply(['budgets' => ['maxInspectionUrls' => 2]]);
        $tooManyTester = new CommandTester(new SiteInsightsInspect());
        self::assertSame(Command::INVALID, $tooManyTester->execute(['site' => TestConfig::SITE_KEY, 'urls' => [self::INDEXED_URL, self::NOT_INDEXED_URL, 'https://example.test/third/']]));
        self::assertSame(0, $this->answeringNetwork->countSentRequests());

        $this->accessPolicyService->cliAccount = TestAccounts::nonAdmin();
        $forbiddenTester = new CommandTester(new SiteInsightsInspect());
        self::assertSame(Command::FAILURE, $forbiddenTester->execute(['site' => TestConfig::SITE_KEY, 'urls' => [self::INDEXED_URL], '--format' => 'json']));
        self::assertStringNotContainsString('urlIndexInspections', $forbiddenTester->getDisplay(), 'no payload');
        self::assertSame(0, $this->answeringNetwork->countSentRequests());

        $this->accessPolicyService->cliAccount = TestAccounts::admin();
        $this->answeringNetwork->answerForBody = static fn (): Response => GoogleFixtures::response('error-500', 500);
        $failedSitemapsTester = new CommandTester(new SiteInsightsSitemaps());
        self::assertSame(Command::FAILURE, $failedSitemapsTester->execute(['site' => TestConfig::SITE_KEY, '--format' => 'json']));
        self::assertSame('providerFailed', json_decode($failedSitemapsTester->getDisplay(), true)['sitemapStatuses']['error']['cause']);
    }

    protected function installNetwork(): AnsweringNetwork
    {
        return AnsweringNetwork::install(static function (array $requestBody): Response {
            if (!isset($requestBody['inspectionUrl'])) {
                return GoogleFixtures::response('sitemaps-list');
            }
            return GoogleFixtures::response($requestBody['inspectionUrl'] === self::NOT_INDEXED_URL ? 'inspection-not-indexed' : 'inspection-indexed');
        });
    }

    /** The endpoint asks the same synthetic provider afresh (empty caches and counters), as another process would */
    protected function startAsAnotherProcess(): void
    {
        $this->caches = InMemoryCache::installForCacheGroups();
        ArgusApiOperations::resetForNewJob();
        $this->answeringNetwork = $this->installNetwork();
        SerializerRegistry::$toOjectCache = [];
        $administratorAccount = TestAccounts::admin();
        AuthService::instance()->setAccount($administratorAccount);
    }

    protected function createController(): FixtureSiteInsightsIndexingController
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request());
        return new FixtureSiteInsightsIndexingController($requestStack);
    }

    protected function decodeResponse(RestResponseDto $responseDto): array
    {
        return json_decode((string)$responseDto->getContent(), true);
    }

    protected function getSearchIndexingService(): SearchIndexingService
    {
        return DDDService::instance()->getService(SearchIndexingService::class);
    }

    protected function withoutAnswerTimes(array $payload): array
    {
        unset($payload['fetchedAt'], $payload['inspectedAt']);
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->withoutAnswerTimes($value);
            }
        }
        return $payload;
    }

    protected function assertNoSecretIn(string $output): void
    {
        self::assertStringNotContainsString(FixedTokenCredentialsProviderService::TOKEN_MARKER, $output);
        self::assertStringNotContainsString(TestConfig::KEY_ENVIRONMENT_VARIABLE, $output);
    }
}
