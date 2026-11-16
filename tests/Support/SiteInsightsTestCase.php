<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Support;

use DDD\DDDBundle;
use DDD\Domain\Base\Repo\Argus\Attributes\ArgusLoad;
use DDD\Domain\Base\Repo\Argus\Utils\ArgusApiOperations;
use DDD\Domain\SiteInsights\Entities\Sites\InsightsSite;
use DDD\Domain\SiteInsights\Services\GoogleApiRequestsService;
use DDD\Domain\SiteInsights\Services\GoogleSearchConsoleSourceService;
use DDD\Domain\SiteInsights\Services\GoogleServiceAccountTokensService;
use DDD\Domain\SiteInsights\Services\InsightsSitesService;
use DDD\Domain\SiteInsights\Services\SearchWindowsService;
use DDD\Domain\SiteInsights\Services\SiteInsightsAccessCheckService;
use DDD\Domain\SiteInsights\Services\SiteInsightsAccessPolicyService;
use DDD\Domain\SiteInsights\Services\SiteInsightsConfigService;
use DDD\Domain\SiteInsights\Services\SiteInsightsCredentialsProviderService;
use DDD\Domain\SiteInsights\Services\SiteInsightsProviderBudgetsService;
use DDD\Domain\SiteInsights\Services\SiteInsightsSitesProviderService;
use DDD\Infrastructure\Cache\Cache;
use DDD\Infrastructure\Services\AuthService;
use DDD\Infrastructure\Traits\Serializer\SerializerRegistry;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * The shared setup: in-memory APC and Redis groups, the test configuration, the synthetic key in its environment
 * variable, a fixed clock, budgets counted in memory, a fixed synthetic token for data calls, and a fresh instance of
 * every module service per test.
 */
abstract class SiteInsightsTestCase extends TestCase
{
    /** @var string[] Module services whose instances are dropped after every test */
    protected const array MODULE_SERVICE_CLASSES = [
        GoogleApiRequestsService::class,
        GoogleSearchConsoleSourceService::class,
        GoogleServiceAccountTokensService::class,
        InsightsSitesService::class,
        SearchWindowsService::class,
        SiteInsightsAccessCheckService::class,
        SiteInsightsAccessPolicyService::class,
        SiteInsightsConfigService::class,
        SiteInsightsCredentialsProviderService::class,
        SiteInsightsProviderBudgetsService::class,
        SiteInsightsSitesProviderService::class,
    ];

    /** @var InMemoryCache[] by cache group */
    protected array $caches = [];

    protected FixedClockGoogleSearchConsoleSourceService $googleSearchConsoleSourceService;

    protected FixedTokenCredentialsProviderService $credentialsProviderService;

    protected TestBudgetsService $budgetsService;

    protected function setUp(): void
    {
        $this->caches = InMemoryCache::installForCacheGroups();
        TestConfig::apply();
        $_ENV[TestConfig::KEY_ENVIRONMENT_VARIABLE] = SyntheticServiceAccountKey::get()->encodedKeyFile;
        ArgusApiOperations::resetForNewJob();
        ArgusLoad::$logArgusCalls = false;
        $this->googleSearchConsoleSourceService = new FixedClockGoogleSearchConsoleSourceService();
        $this->credentialsProviderService = new FixedTokenCredentialsProviderService();
        $this->budgetsService = new TestBudgetsService();
        $this->setService(GoogleSearchConsoleSourceService::class, $this->googleSearchConsoleSourceService);
        $this->setService(SiteInsightsCredentialsProviderService::class, $this->credentialsProviderService);
        $this->setService(SiteInsightsProviderBudgetsService::class, $this->budgetsService);
    }

    protected function tearDown(): void
    {
        foreach (self::MODULE_SERVICE_CLASSES as $moduleServiceClass) {
            $this->setService($moduleServiceClass, null);
        }
        unset($_ENV[TestConfig::KEY_ENVIRONMENT_VARIABLE]);
        TestConfig::restore();
        InMemoryCache::uninstall();
        ArgusApiOperations::resetForNewJob();
        ArgusLoad::$logArgusCalls = false;
        $noAccount = null;
        AuthService::instance()->setAccount($noAccount);
        SerializerRegistry::$toOjectCache = [];
    }

    protected function setService(string $serviceClass, ?object $service): void
    {
        $testContainer = (new ReflectionProperty(DDDBundle::class, 'defaultContainer'))->getValue();
        /** @var TestContainer $testContainer */
        $testContainer->setTestInstance($serviceClass, $service);
    }

    protected function getSite(): InsightsSite
    {
        /** @var InsightsSitesService $insightsSitesService */
        $insightsSitesService = \DDD\Infrastructure\Services\DDDService::instance()->getService(InsightsSitesService::class);
        return $insightsSitesService->getSite(TestConfig::SITE_KEY);
    }

    protected function getApcCache(): InMemoryCache
    {
        return $this->caches[Cache::CACHE_GROUP_APC];
    }

    protected function getRedisCache(): InMemoryCache
    {
        return $this->caches[Cache::CACHE_GROUP_REDIS_SENTINEL];
    }
}
