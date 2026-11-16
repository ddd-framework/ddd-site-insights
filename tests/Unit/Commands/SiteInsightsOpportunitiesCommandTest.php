<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Unit\Commands;

use DDD\Domain\Base\Repo\Argus\Utils\ArgusApiOperations;
use DDD\Domain\SiteInsights\Services\SearchOpportunitiesService;
use DDD\Domain\SiteInsights\Services\SiteInsightsAccessPolicyService;
use DDD\Infrastructure\Services\AuthService;
use DDD\Infrastructure\Services\DDDService;
use DDD\Infrastructure\Traits\Serializer\SerializerRegistry;
use DDD\Presentation\Api\Admin\SiteInsights\Dtos\SiteInsightsSearchOpportunitiesRequestDto;
use DDD\Presentation\Base\Dtos\RestResponseDto;
use DDD\Symfony\Commands\SiteInsights\SiteInsightsOpportunities;
use DDD\Tests\SiteInsights\Fixture\Controller\FixtureSiteInsightsSearchController;
use DDD\Tests\SiteInsights\Support\AnsweringNetwork;
use DDD\Tests\SiteInsights\Support\FixedTokenCredentialsProviderService;
use DDD\Tests\SiteInsights\Support\InMemoryCache;
use DDD\Tests\SiteInsights\Support\SiteInsightsTestCase;
use DDD\Tests\SiteInsights\Support\SyntheticOpportunityAnalytics;
use DDD\Tests\SiteInsights\Support\TestAccounts;
use DDD\Tests\SiteInsights\Support\TestCliAccountAccessPolicyService;
use DDD\Tests\SiteInsights\Support\TestConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/** `app:site-insights:opportunities`: its JSON equals the endpoint's payload; table, TOON, exit codes */
class SiteInsightsOpportunitiesCommandTest extends SiteInsightsTestCase
{
    protected TestCliAccountAccessPolicyService $accessPolicyService;

    protected SyntheticOpportunityAnalytics $syntheticOpportunityAnalytics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accessPolicyService = new TestCliAccountAccessPolicyService();
        $this->accessPolicyService->cliAccount = TestAccounts::admin();
        $this->setService(SiteInsightsAccessPolicyService::class, $this->accessPolicyService);
        $this->syntheticOpportunityAnalytics = new SyntheticOpportunityAnalytics();
        $this->syntheticOpportunityAnalytics->setRows(SyntheticOpportunityAnalytics::PERIOD_WINDOW, ['query'], [
            [['alpha guide'], 40, 500, 9.0],
            [['rising guide'], 100, 1000, 4.0],
        ]);
        $this->syntheticOpportunityAnalytics->setRows(SyntheticOpportunityAnalytics::PERIOD_COMPARISON, ['query'], [
            [['rising guide'], 50, 900, 6.0],
            [['alpha guide'], 40, 500, 9.0],
        ]);
        $this->installNetwork();
    }

    public function testTheCommandJsonEqualsTheEndpointPayload(): void
    {
        $commandTester = new CommandTester(new SiteInsightsOpportunities());
        $exitCode = $commandTester->execute(['site' => TestConfig::SITE_KEY, '--format' => 'json', '--compare' => 'previousPeriod']);
        self::assertSame(Command::SUCCESS, $exitCode, $commandTester->getDisplay());
        $commandPayload = json_decode($commandTester->getDisplay(), true);

        // the endpoint asks the same synthetic provider afresh (empty caches), as another process would
        $this->caches = InMemoryCache::installForCacheGroups();
        ArgusApiOperations::resetForNewJob();
        $this->installNetwork();
        SerializerRegistry::$toOjectCache = [];
        $administratorAccount = TestAccounts::admin();
        AuthService::instance()->setAccount($administratorAccount);
        $requestDto = new SiteInsightsSearchOpportunitiesRequestDto();
        $requestParametersObject = (object)['siteKey' => TestConfig::SITE_KEY, 'compare' => 'previousPeriod'];
        $requestDto->setPropertiesFromObject($requestParametersObject);
        $requestStack = new RequestStack();
        $requestStack->push(new Request());
        $fixtureController = new FixtureSiteInsightsSearchController($requestStack);
        /** @var RestResponseDto $responseDto */
        $responseDto = $fixtureController->getSiteInsightsSearchOpportunities($requestDto, DDDService::instance()->getService(SearchOpportunitiesService::class));
        $endpointPayload = json_decode((string)$responseDto->getContent(), true);

        self::assertSame(['searchOpportunities'], array_keys($commandPayload));
        self::assertEquals($this->withoutAnswerTimes($endpointPayload), $this->withoutAnswerTimes($commandPayload));
        $kindNames = array_column($commandPayload['searchOpportunities']['kinds']['elements'], 'kind');
        self::assertCount(7, $kindNames);
        self::assertSame(['alpha guide', 'rising guide'], array_column($commandPayload['searchOpportunities']['elements'], 'query'));
        self::assertStringNotContainsString(FixedTokenCredentialsProviderService::TOKEN_MARKER, $commandTester->getDisplay());
    }

    public function testTableToonKindAndExitCodes(): void
    {
        $tableTester = new CommandTester(new SiteInsightsOpportunities());
        self::assertSame(Command::SUCCESS, $tableTester->execute(['site' => TestConfig::SITE_KEY, '--kind' => 'strikingDistanceQuery']));
        $tableDisplay = $tableTester->getDisplay();
        self::assertStringContainsString('strikingDistanceQuery 1 result(s)', $tableDisplay);
        self::assertStringContainsString('alpha guide', $tableDisplay);
        self::assertStringNotContainsString('risingQuery', $tableDisplay, 'one kind only');

        $toonTester = new CommandTester(new SiteInsightsOpportunities());
        self::assertSame(Command::SUCCESS, $toonTester->execute(['site' => TestConfig::SITE_KEY, '--kind' => 'risingQuery', '--format' => 'toon']));
        $toonPayload = json_decode($toonTester->getDisplay(), true);
        self::assertArrayHasKey('elementsInToonFormat', $toonPayload['searchOpportunities']['kinds']);

        self::assertSame(Command::INVALID, (new CommandTester(new SiteInsightsOpportunities()))->execute(['site' => TestConfig::SITE_KEY, '--kind' => 'everything']));

        TestConfig::apply(['budgets' => ['callsPerOperation' => ['opportunities' => 2]]]);
        $refusedTester = new CommandTester(new SiteInsightsOpportunities());
        self::assertSame(Command::FAILURE, $refusedTester->execute(['site' => TestConfig::SITE_KEY, '--format' => 'json']), 'a kind failed: exit 1, the payload is printed');
        $refusedPayload = json_decode($refusedTester->getDisplay(), true);
        self::assertSame('budgetRefused', $refusedPayload['searchOpportunities']['kinds']['elements'][0]['error']['cause']);
    }

    protected function installNetwork(): void
    {
        AnsweringNetwork::install(fn (array $requestBody) => $this->syntheticOpportunityAnalytics->answer($requestBody));
    }

    /** fetchedAt is when the provider answered */
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
}
