<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Support;

use DDD\Infrastructure\Libs\Config;
use ReflectionProperty;

/**
 * The configuration of a test: the module's five files with the test's sites and credentials, and the Argus config
 * of an application (`Base.Repo.argus`). Restores the config tree afterwards.
 */
class TestConfig
{
    public const string CREDENTIALS_ENTRY_NAME = 'searchReader';
    public const string KEY_ENVIRONMENT_VARIABLE = 'SITE_INSIGHTS_TEST_SEARCH_READER_KEY_B64';
    public const string SITE_KEY = 'example-site';
    public const string PROPERTY = 'https://example.test/';

    /** @var array|null The config tree before the first override */
    protected static ?array $configTreeBefore = null;

    /** @return array one URL-prefix site with a brand pattern and a page group */
    public static function getDefaultSites(): array
    {
        return [[
            'key' => self::SITE_KEY,
            'label' => 'Example site',
            'provider' => 'google-search-console',
            'property' => self::PROPERTY,
            'credentials' => self::CREDENTIALS_ENTRY_NAME,
            'brandQueryPatterns' => ['example'],
            'pageGroups' => [
                ['key' => 'guides', 'label' => 'Guides', 'pathPatterns' => ['^/guides/']],
                ['key' => 'shop', 'label' => 'Shop', 'pathPatterns' => ['^/shop/', '^/cart$']],
            ],
        ]];
    }

    /**
     * @param array $siteInsightsOverrides file name => settings replacing the module file's settings
     * @param array|null $argusConfig `Base.Repo.argus`; null: no application headers, no auth, no debug flags
     * @return void
     */
    public static function apply(array $siteInsightsOverrides = [], ?array $argusConfig = null): void
    {
        $configTreeProperty = new ReflectionProperty(Config::class, 'configTree');
        $configTree = $configTreeProperty->isInitialized() ? $configTreeProperty->getValue() : [];
        self::$configTreeBefore ??= $configTree;
        $moduleConfigDirectory = dirname(__DIR__, 2) . '/config/app/SiteInsights';
        $siteInsightsConfig = [];
        foreach (['sites', 'credentials', 'cache', 'budgets', 'opportunities'] as $fileName) {
            $siteInsightsConfig[$fileName] = require $moduleConfigDirectory . '/' . $fileName . '.php';
        }
        $siteInsightsConfig['sites']['sites'] = self::getDefaultSites();
        $siteInsightsConfig['credentials']['entries'] = [
            self::CREDENTIALS_ENTRY_NAME => ['envName' => self::KEY_ENVIRONMENT_VARIABLE, 'revision' => 1],
        ];
        foreach ($siteInsightsOverrides as $fileName => $settings) {
            $siteInsightsConfig[$fileName] = array_replace($siteInsightsConfig[$fileName], $settings);
        }
        $configTree['SiteInsights'] = $siteInsightsConfig;
        $configTree['Base']['Repo']['argus'] = $argusConfig ?? [
            'microservices' => ['baseUrl' => 'https://services.example.test/'],
            'auth' => false,
            'appendDebugFlags' => false,
            'trackingHeader' => '',
        ];
        $configTreeProperty->setValue(null, $configTree);
    }

    public static function restore(): void
    {
        if (self::$configTreeBefore === null) {
            return;
        }
        (new ReflectionProperty(Config::class, 'configTree'))->setValue(null, self::$configTreeBefore);
        self::$configTreeBefore = null;
    }
}
