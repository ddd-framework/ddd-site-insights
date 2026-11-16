<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Unit\Commands;

use DDD\Domain\Base\Repo\Argus\Utils\ArgusApiOperations;
use DDD\Domain\SiteInsights\Services\SiteInsightsAccessPolicyService;
use DDD\Presentation\Api\Admin\SiteInsights\Dtos\SiteInsightsSearchPageDetailRequestDto;
use DDD\Presentation\Api\Admin\SiteInsights\Dtos\SiteInsightsSearchQueryDetailRequestDto;
use DDD\Presentation\Api\Admin\SiteInsights\Dtos\SiteInsightsSearchRowsRequestDto;
use DDD\Presentation\Api\Admin\SiteInsights\Dtos\SiteInsightsSearchWindowRequestDto;
use DDD\Domain\SiteInsights\Services\SearchPerformanceService;
use DDD\Infrastructure\Services\AuthService;
use DDD\Infrastructure\Services\DDDService;
use DDD\Infrastructure\Traits\Serializer\SerializerRegistry;
use DDD\Presentation\Base\Dtos\RestResponseDto;
use DDD\Symfony\Commands\SiteInsights\SiteInsightsCountries;
use DDD\Symfony\Commands\SiteInsights\SiteInsightsDevices;
use DDD\Symfony\Commands\SiteInsights\SiteInsightsExport;
use DDD\Symfony\Commands\SiteInsights\SiteInsightsOverview;
use DDD\Symfony\Commands\SiteInsights\SiteInsightsPage;
use DDD\Symfony\Commands\SiteInsights\SiteInsightsPageGroups;
use DDD\Symfony\Commands\SiteInsights\SiteInsightsPages;
use DDD\Symfony\Commands\SiteInsights\SiteInsightsQueries;
use DDD\Symfony\Commands\SiteInsights\SiteInsightsQuery;
use DDD\Tests\SiteInsights\Fixture\Controller\FixtureSiteInsightsSearchController;
use DDD\Tests\SiteInsights\Support\AnsweringNetwork;
use DDD\Tests\SiteInsights\Support\FixedTokenCredentialsProviderService;
use DDD\Tests\SiteInsights\Support\InMemoryCache;
use DDD\Tests\SiteInsights\Support\SiteInsightsTestCase;
use DDD\Tests\SiteInsights\Support\SyntheticSearchAnalytics;
use DDD\Tests\SiteInsights\Support\TestAccounts;
use DDD\Tests\SiteInsights\Support\TestCliAccountAccessPolicyService;
use DDD\Tests\SiteInsights\Support\TestConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The search commands of B2: each command's JSON equals the payload of its endpoint (both built from the same
 * synthetic answers), formats, exit codes, filters and the export's NDJSON.
 */
class SiteInsightsSearchCommandsTest extends SiteInsightsTestCase
{
    protected TestCliAccountAccessPolicyService $accessPolicyService;

    protected SyntheticSearchAnalytics $syntheticSearchAnalytics;

    protected AnsweringNetwork $answeringNetwork;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accessPolicyService = new TestCliAccountAccessPolicyService();
        $this->accessPolicyService->cliAccount = TestAccounts::admin();
        $this->setService(SiteInsightsAccessPolicyService::class, $this->accessPolicyService);
        $this->syntheticSearchAnalytics = new SyntheticSearchAnalytics();
        $this->syntheticSearchAnalytics->daysWithoutData = ['2026-11-01'];
        $this->answeringNetwork = AnsweringNetwork::install(fn (array $requestBody) => $this->syntheticSearchAnalytics->answer($requestBody));
    }

    /** @return array[] command class, command input, endpoint method, request DTO class, request parameters */
    public static function provideCommandsAndEndpoints(): array
    {
        return [
            'overview' => [SiteInsightsOverview::class, ['--compare' => 'previousPeriod'], 'getSiteInsightsSearchOverview', SiteInsightsSearchWindowRequestDto::class, ['compare' => 'previousPeriod']],
            'queries' => [SiteInsightsQueries::class, ['--compare' => 'previousYear', '--limit' => '5', '--queryContains' => 'widget'], 'getSiteInsightsSearchQueries', SiteInsightsSearchRowsRequestDto::class, ['compare' => 'previousYear', 'limit' => 5, 'queryContains' => 'widget']],
            'pages' => [SiteInsightsPages::class, ['--sortBy' => 'position', '--limit' => '3'], 'getSiteInsightsSearchPages', SiteInsightsSearchRowsRequestDto::class, ['sortBy' => 'position', 'limit' => 3]],
            'countries' => [SiteInsightsCountries::class, ['--device' => 'mobile', '--compare' => 'previousPeriod'], 'getSiteInsightsSearchCountries', SiteInsightsSearchRowsRequestDto::class, ['device' => 'mobile', 'compare' => 'previousPeriod']],
            'devices' => [SiteInsightsDevices::class, ['--from' => '2026-11-01', '--to' => '2026-11-07'], 'getSiteInsightsSearchDevices', SiteInsightsSearchRowsRequestDto::class, ['from' => '2026-11-01', 'to' => '2026-11-07']],
            'page groups' => [SiteInsightsPageGroups::class, ['--preset' => '7d', '--compare' => 'previousPeriod'], 'getSiteInsightsSearchPageGroups', SiteInsightsSearchWindowRequestDto::class, ['preset' => '7d', 'compare' => 'previousPeriod']],
            'page' => [SiteInsightsPage::class, ['url' => 'https://example.test/guides/item-004/'], 'getSiteInsightsSearchPageDetail', SiteInsightsSearchPageDetailRequestDto::class, ['url' => 'https://example.test/guides/item-004/']],
            'query' => [SiteInsightsQuery::class, ['query' => 'synthetic widget 004', '--dataState' => 'all'], 'getSiteInsightsSearchQueryDetail', SiteInsightsSearchQueryDetailRequestDto::class, ['query' => 'synthetic widget 004', 'dataState' => 'all']],
        ];
    }

    /** @dataProvider provideCommandsAndEndpoints */
    public function testTheCommandJsonEqualsTheEndpointPayload(string $commandClass, array $commandInput, string $endpointMethod, string $requestDtoClass, array $requestParameters): void
    {
        $commandTester = new CommandTester(new $commandClass());
        $exitCode = $commandTester->execute(['site' => TestConfig::SITE_KEY, '--format' => 'json', ...$commandInput]);
        self::assertSame(Command::SUCCESS, $exitCode, $commandTester->getDisplay());
        $commandPayload = json_decode($commandTester->getDisplay(), true);

        // the endpoint asks the same synthetic provider afresh (empty caches), as another process would
        $this->caches = InMemoryCache::installForCacheGroups();
        ArgusApiOperations::resetForNewJob();
        $this->answeringNetwork = AnsweringNetwork::install(fn (array $requestBody) => $this->syntheticSearchAnalytics->answer($requestBody));
        SerializerRegistry::$toOjectCache = [];
        $administratorAccount = TestAccounts::admin();
        AuthService::instance()->setAccount($administratorAccount);
        $requestDto = new $requestDtoClass();
        // hydrated as a request is: the days arrive as strings and become framework dates
        $requestParametersObject = (object)(['siteKey' => TestConfig::SITE_KEY] + $requestParameters);
        $requestDto->setPropertiesFromObject($requestParametersObject);
        $requestStack = new RequestStack();
        $requestStack->push(new Request());
        $fixtureController = new FixtureSiteInsightsSearchController($requestStack);
        /** @var RestResponseDto $responseDto */
        $responseDto = $fixtureController->$endpointMethod($requestDto, DDDService::instance()->getService(SearchPerformanceService::class));
        $endpointPayload = json_decode((string)$responseDto->getContent(), true);

        self::assertSame(array_keys($commandPayload), array_keys($endpointPayload), 'the same property');
        self::assertEquals($this->withoutAnswerTimes($endpointPayload), $this->withoutAnswerTimes($commandPayload));
        $this->assertNoSecretIn($commandTester->getDisplay());
    }

    public function testTableAndToonFormats(): void
    {
        $tableTester = new CommandTester(new SiteInsightsQueries());
        self::assertSame(Command::SUCCESS, $tableTester->execute(['site' => TestConfig::SITE_KEY, '--compare' => 'previousPeriod', '--limit' => '3']));
        $tableDisplay = $tableTester->getDisplay();
        self::assertStringContainsString('synthetic widget 000', $tableDisplay);
        self::assertStringContainsString('observed', $tableDisplay);
        self::assertStringContainsString('Window 2026-10-17 – 2026-11-13 (28 days, America/Los_Angeles, dataState final, final data through 2026-11-13 by probe)', $tableDisplay);
        self::assertStringContainsString('compared with 2026-09-19 – 2026-10-16 (previousPeriod)', $tableDisplay);
        self::assertStringContainsString('Anonymised queries are omitted', $tableDisplay);

        $toonTester = new CommandTester(new SiteInsightsOverview());
        self::assertSame(Command::SUCCESS, $toonTester->execute(['site' => TestConfig::SITE_KEY, '--format' => 'toon']));
        $toonPayload = json_decode($toonTester->getDisplay(), true);
        self::assertArrayHasKey('elementsInToonFormat', $toonPayload['searchPerformanceOverview']['topQueries']);
        self::assertArrayHasKey('coverage', $toonPayload['searchPerformanceOverview']['topQueries'], 'the metadata stays JSON');
    }

    public function testAFailedPartExitsWithOneAndStillPrintsThePayload(): void
    {
        $this->syntheticSearchAnalytics->availableRows = 30;
        $this->answeringNetwork->answerForBody = fn (array $requestBody) => ($requestBody['dimensions'] ?? []) === ['country']
            ? SyntheticSearchAnalytics::respond([])->withStatus(500)
            : $this->syntheticSearchAnalytics->answer($requestBody);
        $commandTester = new CommandTester(new SiteInsightsOverview());

        $exitCode = $commandTester->execute(['site' => TestConfig::SITE_KEY, '--format' => 'json']);

        self::assertSame(Command::FAILURE, $exitCode);
        $payload = json_decode($commandTester->getDisplay(), true);
        self::assertSame('providerFailed', $payload['searchPerformanceOverview']['countries']['error']['cause']);
        self::assertSame(1000, $payload['searchPerformanceOverview']['totals']['metrics']['current']['clicks']);
    }

    public function testInvalidInputAndAccess(): void
    {
        $invalidLimitTester = new CommandTester(new SiteInsightsQueries());
        self::assertSame(Command::INVALID, $invalidLimitTester->execute(['site' => TestConfig::SITE_KEY, '--limit' => 'many']));
        $invalidCountryTester = new CommandTester(new SiteInsightsCountries());
        self::assertSame(Command::INVALID, $invalidCountryTester->execute(['site' => TestConfig::SITE_KEY, '--country' => 'de']));
        $foreignPageTester = new CommandTester(new SiteInsightsPage());
        self::assertSame(Command::INVALID, $foreignPageTester->execute(['site' => TestConfig::SITE_KEY, 'url' => 'https://other.example.com/']));

        $this->accessPolicyService->cliAccount = TestAccounts::nonAdmin();
        $forbiddenTester = new CommandTester(new SiteInsightsOverview());
        self::assertSame(Command::FAILURE, $forbiddenTester->execute(['site' => TestConfig::SITE_KEY, '--format' => 'json']));
        self::assertStringNotContainsString('searchPerformanceOverview', $forbiddenTester->getDisplay(), 'no payload');
        self::assertSame(0, $this->answeringNetwork->countSentRequests());
    }

    public function testExportWritesNdjsonThenTheSummary(): void
    {
        TestConfig::apply(['budgets' => ['maxRowsPerCall' => 25]]);
        $outputPath = sys_get_temp_dir() . '/site-insights-export-' . getmypid() . '.ndjson';
        $commandTester = new CommandTester(new SiteInsightsExport());

        $exitCode = $commandTester->execute(['site' => TestConfig::SITE_KEY, '--from' => '2026-11-12', '--to' => '2026-11-13', '--dimensions' => 'query,page', '--output' => $outputPath]);

        self::assertSame(Command::SUCCESS, $exitCode, $commandTester->getDisplay());
        $rowLines = array_values(array_filter(explode("\n", (string)file_get_contents($outputPath))));
        unlink($outputPath);
        self::assertCount(60, $rowLines);
        self::assertSame('synthetic widget 000', json_decode($rowLines[0], true)['query']);
        $summary = json_decode($commandTester->getDisplay(), true)['searchExportSummary'];
        self::assertSame(60, $summary['rowsWritten']);
        self::assertTrue($summary['byDay']);
        self::assertSame('2026-11-13', $summary['lastFinishedDay']);
        self::assertCount(2, $summary['days']['elements']);

        $stdoutTester = new CommandTester(new SiteInsightsExport());
        self::assertSame(Command::SUCCESS, $stdoutTester->execute(['site' => TestConfig::SITE_KEY, '--from' => '2026-11-12', '--to' => '2026-11-13', '--dimensions' => 'country', '--no-byDay']));
        $stdoutLines = explode("\n", trim($stdoutTester->getDisplay()));
        self::assertCount(31, $stdoutLines, '30 rows, then the summary');
        self::assertArrayHasKey('searchExportSummary', json_decode(end($stdoutLines), true));

        TestConfig::apply(['budgets' => ['maxRowsPerCall' => 25, 'callsPerOperation' => ['export' => 1]]]);
        $limitedTester = new CommandTester(new SiteInsightsExport());
        self::assertSame(Command::FAILURE, $limitedTester->execute(['site' => TestConfig::SITE_KEY, '--from' => '2026-11-12', '--to' => '2026-11-13']), 'stopped before its end');
    }

    /** fetchedAt is when the provider answered: the two reads are seconds apart */
    protected function withoutAnswerTimes(array $payload): array
    {
        unset($payload['fetchedAt']);
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
