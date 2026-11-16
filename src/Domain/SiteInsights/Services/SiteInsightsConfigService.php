<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Services;

use DDD\Infrastructure\Libs\Config;
use DDD\Infrastructure\Services\Service;

/**
 * Reads the module's configuration (`config/app/SiteInsights/*.php`). An application replaces a file as a whole; a key
 * its file leaves out falls back to the module default below. Each file is read as a whole, never key by key.
 */
class SiteInsightsConfigService extends Service
{
    public const array CREDENTIALS_DEFAULTS = [
        'entries' => [],
        'tokenEndpoint' => 'https://oauth2.googleapis.com/token',
        'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
        'assertionLifetimeSeconds' => 300,
        'tokenSafetyMarginSeconds' => 300,
        'httpTimeoutSeconds' => 10,
    ];

    public const array CACHE_DEFAULTS = [
        'schemaVersion' => 1,
        'finalWindowTtlSeconds' => 86400,
        'freshWindowTtlSeconds' => 1800,
        'probeTtlSeconds' => 3600,
        'sitemapsTtlSeconds' => 3600,
        'inspectionTtlSeconds' => 86400,
        'joinWaitMilliseconds' => 10000,
    ];

    public const array BUDGETS_DEFAULTS = [
        'searchAnalyticsPerMinutePerSite' => 1200,
        'searchAnalyticsShare' => 0.5,
        'inspectionsPerDayPerSite' => 2000,
        'inspectionsPerMinutePerSite' => 600,
        'inspectionShare' => 0.5,
        'otherPerMinute' => 200,
        'otherShare' => 0.5,
        'rowsPerCall' => 2500,
        'maxRowsPerCall' => 10000,
        'sortFetchCeiling' => 5000,
        'maxResponseBytes' => 4000000,
        'batchTimeoutSeconds' => 25,
        'singleCallTimeoutSeconds' => 20,
        'retention' => ['months' => 16, 'safetyDays' => 3],
        'comparison' => ['minDays' => 7, 'minShare' => 0.25],
        'callsPerOperation' => [
            'overview' => 10,
            'list' => 8,
            'pageGroups' => 41,
            'pageDetail' => 7,
            'queryDetail' => 7,
            'opportunities' => 20,
            'sitemaps' => 1,
            'inspections' => 20,
            'export' => 1500,
            'checkAccess' => 6,
        ],
        'counterpart' => ['keysPerChunk' => 100, 'maxChunks' => 5, 'regexMaxLength' => 4000],
        'maxPageGroups' => 20,
        'maxInspectionUrls' => 20,
    ];

    /** @return array the configured site entries (`SiteInsights.sites.sites`) */
    public function getSiteEntries(): array
    {
        $sitesConfig = Config::get('SiteInsights.sites');
        $siteEntries = is_array($sitesConfig) ? ($sitesConfig['sites'] ?? []) : [];
        return is_array($siteEntries) ? $siteEntries : [];
    }

    /** @return array `SiteInsights.credentials` over the module defaults */
    public function getCredentialsConfig(): array
    {
        return $this->readConfigFile('credentials', self::CREDENTIALS_DEFAULTS);
    }

    /** @return array `SiteInsights.cache` over the module defaults */
    public function getCacheConfig(): array
    {
        return $this->readConfigFile('cache', self::CACHE_DEFAULTS);
    }

    /** @return array `SiteInsights.budgets` over the module defaults */
    public function getBudgetsConfig(): array
    {
        return $this->readConfigFile('budgets', self::BUDGETS_DEFAULTS);
    }

    /**
     * @param string $key
     * @return int a cache setting as int
     */
    public function getCacheSettingInt(string $key): int
    {
        return (int)($this->getCacheConfig()[$key] ?? 0);
    }

    /**
     * @param string $key
     * @return int|float|array|string|null a budgets setting
     */
    public function getBudgetSetting(string $key): int|float|array|string|null
    {
        return $this->getBudgetsConfig()[$key] ?? null;
    }

    /**
     * @param string $fileName
     * @param array $defaults
     * @return array the file's settings over the defaults (nested settings merged key by key)
     */
    protected function readConfigFile(string $fileName, array $defaults): array
    {
        $configuredSettings = Config::get('SiteInsights.' . $fileName);
        return array_replace_recursive($defaults, is_array($configuredSettings) ? $configuredSettings : []);
    }
}
