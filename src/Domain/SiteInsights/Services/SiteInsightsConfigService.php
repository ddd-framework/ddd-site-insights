<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Services;

use DDD\Domain\SiteInsights\Entities\Config\SiteInsightsBudgetsConfig;
use DDD\Domain\SiteInsights\Entities\Config\SiteInsightsCacheConfig;
use DDD\Domain\SiteInsights\Entities\Config\SiteInsightsCredentialsConfig;
use DDD\Domain\SiteInsights\Entities\Config\SiteInsightsOpportunitiesConfig;
use DDD\Domain\SiteInsights\Entities\Config\SiteInsightsPageGroupEntries;
use DDD\Domain\SiteInsights\Entities\Config\SiteInsightsPageGroupEntry;
use DDD\Domain\SiteInsights\Entities\Config\SiteInsightsSiteEntries;
use DDD\Domain\SiteInsights\Entities\Config\SiteInsightsSiteEntry;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Infrastructure\Libs\Config;
use DDD\Infrastructure\Services\Service;

/**
 * Reads the module's configuration (`config/app/SiteInsights/*.php`) into typed value objects. An application replaces
 * a file as a whole; a setting its file leaves out keeps the default of the value object. Each file is read as a
 * whole on every call, never cached in the service.
 */
class SiteInsightsConfigService extends Service
{
    /**
     * @return SiteInsightsSiteEntries the configured site entries (`SiteInsights.sites.sites`), in config order
     * @throws InternalErrorException when an entry or a list in it has the wrong shape
     */
    public function getSiteEntries(): SiteInsightsSiteEntries
    {
        $sitesConfig = Config::get('SiteInsights.sites');
        $configuredSites = is_array($sitesConfig) ? ($sitesConfig['sites'] ?? []) : [];
        $siteInsightsSiteEntries = new SiteInsightsSiteEntries();
        foreach (is_array($configuredSites) ? $configuredSites : [] as $sitePosition => $siteSettings) {
            $siteInsightsSiteEntry = $this->buildSiteEntry(is_array($siteSettings) ? $siteSettings : [], (string)$sitePosition);
            $siteInsightsSiteEntries->add($siteInsightsSiteEntry);
        }
        return $siteInsightsSiteEntries;
    }

    /** @return SiteInsightsCredentialsConfig `SiteInsights.credentials` over the defaults */
    public function getCredentialsConfig(): SiteInsightsCredentialsConfig
    {
        return SiteInsightsCredentialsConfig::fromConfigSettings((array)(Config::get('SiteInsights.credentials') ?? []));
    }

    /** @return SiteInsightsCacheConfig `SiteInsights.cache` over the defaults */
    public function getCacheConfig(): SiteInsightsCacheConfig
    {
        return SiteInsightsCacheConfig::fromConfigSettings((array)(Config::get('SiteInsights.cache') ?? []));
    }

    /** @return SiteInsightsBudgetsConfig `SiteInsights.budgets` over the defaults */
    public function getBudgetsConfig(): SiteInsightsBudgetsConfig
    {
        return SiteInsightsBudgetsConfig::fromConfigSettings((array)(Config::get('SiteInsights.budgets') ?? []));
    }

    /**
     * @param string|null $siteKey the site whose own `opportunities` settings (in `sites.php`) apply over the file's
     * @return SiteInsightsOpportunitiesConfig the defaults, then `SiteInsights.opportunities`, then the site's settings
     * @throws InternalErrorException for an unknown rule or setting, or a value of the wrong type (named)
     */
    public function getOpportunitiesConfig(?string $siteKey = null): SiteInsightsOpportunitiesConfig
    {
        $opportunitiesConfig = new SiteInsightsOpportunitiesConfig();
        $opportunitiesConfig->applyConfigSettings((array)(Config::get('SiteInsights.opportunities') ?? []), 'SiteInsights.opportunities');
        if ($siteKey === null) {
            return $opportunitiesConfig;
        }
        $sitesConfig = Config::get('SiteInsights.sites');
        $configuredSites = is_array($sitesConfig) ? ($sitesConfig['sites'] ?? []) : [];
        foreach (is_array($configuredSites) ? $configuredSites : [] as $sitePosition => $siteSettings) {
            if (!is_array($siteSettings) || ($siteSettings['key'] ?? null) !== $siteKey || !isset($siteSettings['opportunities'])) {
                continue;
            }
            $siteSettingPath = 'SiteInsights.sites[' . $sitePosition . '].opportunities';
            if (!is_array($siteSettings['opportunities'])) {
                throw new InternalErrorException($siteSettingPath . ' must be a list of settings');
            }
            $opportunitiesConfig->applyConfigSettings($siteSettings['opportunities'], $siteSettingPath);
        }
        return $opportunitiesConfig;
    }

    /**
     * @param array $siteSettings one entry of `sites.php` (wrapped here, never passed on)
     * @param string $sitePosition
     * @return SiteInsightsSiteEntry
     * @throws InternalErrorException
     */
    protected function buildSiteEntry(array $siteSettings, string $sitePosition): SiteInsightsSiteEntry
    {
        $siteInsightsSiteEntry = new SiteInsightsSiteEntry();
        $siteInsightsSiteEntry->position = $sitePosition;
        foreach (['key', 'label', 'provider', 'property', 'credentials'] as $stringSettingName) {
            if (is_string($siteSettings[$stringSettingName] ?? null)) {
                $siteInsightsSiteEntry->$stringSettingName = $siteSettings[$stringSettingName];
            }
        }
        $siteName = 'SiteInsights.sites[' . $sitePosition . ']';
        $siteInsightsSiteEntry->brandQueryPatterns = $this->readStringList($siteSettings['brandQueryPatterns'] ?? [], $siteName . '.brandQueryPatterns');
        $siteInsightsSiteEntry->pageGroups = new SiteInsightsPageGroupEntries();
        $pageGroupsSettings = $siteSettings['pageGroups'] ?? [];
        if (!is_array($pageGroupsSettings)) {
            throw new InternalErrorException($siteName . '.pageGroups must be a list');
        }
        foreach ($pageGroupsSettings as $pageGroupPosition => $pageGroupSettings) {
            if (!is_array($pageGroupSettings)) {
                throw new InternalErrorException($siteName . '.pageGroups[' . $pageGroupPosition . '] must be a list of settings');
            }
            $siteInsightsPageGroupEntry = new SiteInsightsPageGroupEntry();
            foreach (['key', 'label'] as $stringSettingName) {
                if (is_string($pageGroupSettings[$stringSettingName] ?? null)) {
                    $siteInsightsPageGroupEntry->$stringSettingName = $pageGroupSettings[$stringSettingName];
                }
            }
            $siteInsightsPageGroupEntry->pathPatterns = $this->readStringList(
                $pageGroupSettings['pathPatterns'] ?? [],
                $siteName . '.pageGroups[' . $pageGroupPosition . '].pathPatterns'
            );
            $siteInsightsSiteEntry->pageGroups->add($siteInsightsPageGroupEntry);
        }
        return $siteInsightsSiteEntry;
    }

    /**
     * @param mixed $configuredList
     * @param string $settingName
     * @return string[]
     * @throws InternalErrorException when the setting is no list of non-empty strings
     */
    protected function readStringList(mixed $configuredList, string $settingName): array
    {
        if (!is_array($configuredList)) {
            throw new InternalErrorException($settingName . ' must be a list of patterns');
        }
        $strings = [];
        foreach ($configuredList as $configuredValue) {
            if (!is_string($configuredValue) || $configuredValue === '') {
                throw new InternalErrorException($settingName . ' holds an empty or non-string pattern');
            }
            $strings[] = $configuredValue;
        }
        return $strings;
    }
}
