<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Services;

use DDD\Domain\SiteInsights\Entities\Config\SiteInsightsSiteEntry;
use DDD\Domain\SiteInsights\Entities\Sites\InsightsPageGroup;
use DDD\Domain\SiteInsights\Entities\Sites\InsightsPageGroups;
use DDD\Domain\SiteInsights\Entities\Sites\InsightsProviderCapabilities;
use DDD\Domain\SiteInsights\Entities\Sites\InsightsSite;
use DDD\Domain\SiteInsights\Entities\Sites\InsightsSites;
use DDD\Domain\SiteInsights\Exceptions\SiteInsightsProviderUnavailableException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Infrastructure\Services\DDDService;
use DDD\Infrastructure\Services\Service;

/**
 * Extension point: where the sites come from. The default reads `SiteInsights.sites` and validates every entry (key
 * pattern, duplicates, provider, property form, credentials entry, patterns). An application with another source
 * subclasses this service and registers the subclass under this class' service id.
 */
class SiteInsightsSitesProviderService extends Service
{
    public const string SITE_KEY_PATTERN = '/^[a-z0-9][a-z0-9-]{0,62}$/';

    /**
     * RE2 rejects lookarounds, backreferences and atomic groups; the patterns are compiled with PCRE, so these are
     * refused up front.
     */
    public const string NON_RE2_CONSTRUCT_PATTERN = '/\(\?<?[=!]|\(\?>|\\\\[1-9]|\\\\k[<{\']|\\\\g[{\d-]/';

    /** @var string[] Providers the module can read */
    public const array SUPPORTED_PROVIDERS = [GoogleSearchConsoleSourceService::PROVIDER];

    /**
     * @return InsightsSites the configured sites
     * @throws InternalErrorException when an entry is invalid (names the site and the problem)
     */
    public function getSites(): InsightsSites
    {
        /** @var SiteInsightsConfigService $siteInsightsConfigService */
        $siteInsightsConfigService = DDDService::instance()->getService(SiteInsightsConfigService::class);
        $insightsSites = new InsightsSites();
        foreach ($siteInsightsConfigService->getSiteEntries()->getElements() as $siteInsightsSiteEntry) {
            $insightsSite = $this->buildSite($siteInsightsSiteEntry);
            if ($insightsSites->findSiteByKey($insightsSite->key) !== null) {
                throw new InternalErrorException('SiteInsights.sites: the site key "' . $insightsSite->key . '" is configured twice');
            }
            $insightsSites->add($insightsSite);
        }
        return $insightsSites;
    }

    /**
     * @param SiteInsightsSiteEntry $siteInsightsSiteEntry
     * @return InsightsSite
     * @throws InternalErrorException
     */
    protected function buildSite(SiteInsightsSiteEntry $siteInsightsSiteEntry): InsightsSite
    {
        $siteKey = $siteInsightsSiteEntry->key ?? null;
        if ($siteKey === null || preg_match(self::SITE_KEY_PATTERN, $siteKey) !== 1) {
            throw new InternalErrorException('SiteInsights.sites[' . $siteInsightsSiteEntry->position . ']: the key must match ' . self::SITE_KEY_PATTERN);
        }
        $insightsSite = new InsightsSite();
        $insightsSite->key = $siteKey;
        $label = $siteInsightsSiteEntry->label ?? '';
        $insightsSite->label = $label !== '' ? $label : $siteKey;
        $provider = $siteInsightsSiteEntry->provider ?? GoogleSearchConsoleSourceService::PROVIDER;
        if (!in_array($provider, self::SUPPORTED_PROVIDERS, true)) {
            throw $this->createSiteConfigException($siteKey, 'the provider is not supported');
        }
        $insightsSite->provider = $provider;
        $property = $siteInsightsSiteEntry->property ?? null;
        if ($property === null || !$this->isValidProperty($property)) {
            throw $this->createSiteConfigException($siteKey, 'the property must be "https://host/…/" or "sc-domain:host"');
        }
        $insightsSite->property = $property;
        $credentialsEntryName = $siteInsightsSiteEntry->credentials ?? '';
        if ($credentialsEntryName === '') {
            throw $this->createSiteConfigException($siteKey, 'the site names no credentials entry');
        }
        $insightsSite->credentialsEntryName = $credentialsEntryName;
        /** @var SiteInsightsCredentialsProviderService $siteInsightsCredentialsProviderService */
        $siteInsightsCredentialsProviderService = DDDService::instance()->getService(SiteInsightsCredentialsProviderService::class);
        try {
            $insightsSite->credentialsRevision = $siteInsightsCredentialsProviderService->getRevision($credentialsEntryName);
        } catch (SiteInsightsProviderUnavailableException $siteInsightsProviderUnavailableException) {
            throw $this->createSiteConfigException($siteKey, 'its credentials entry "' . $credentialsEntryName . '" is not configured');
        }
        $insightsSite->brandQueryPatterns = $this->validatePatterns($siteInsightsSiteEntry->brandQueryPatterns, $siteKey, 'brandQueryPatterns');
        $insightsSite->pageGroups = new InsightsPageGroups();
        foreach (array_values($siteInsightsSiteEntry->pageGroups->getElements()) as $pageGroupPosition => $siteInsightsPageGroupEntry) {
            $pageGroupKey = $siteInsightsPageGroupEntry->key ?? null;
            if ($pageGroupKey === null || preg_match(self::SITE_KEY_PATTERN, $pageGroupKey) !== 1) {
                throw $this->createSiteConfigException($siteKey, 'pageGroups[' . $pageGroupPosition . ']: the key must match ' . self::SITE_KEY_PATTERN);
            }
            if ($insightsSite->pageGroups->findPageGroupByKey($pageGroupKey) !== null) {
                throw $this->createSiteConfigException($siteKey, 'the page group "' . $pageGroupKey . '" is configured twice');
            }
            $insightsPageGroup = new InsightsPageGroup();
            $insightsPageGroup->key = $pageGroupKey;
            $insightsPageGroup->label = $siteInsightsPageGroupEntry->label ?? $pageGroupKey;
            $insightsPageGroup->pathPatterns = $this->validatePatterns($siteInsightsPageGroupEntry->pathPatterns, $siteKey, 'pageGroups.' . $pageGroupKey);
            if (!$insightsPageGroup->pathPatterns) {
                throw $this->createSiteConfigException($siteKey, 'the page group "' . $pageGroupKey . '" has no path pattern');
            }
            $insightsSite->pageGroups->add($insightsPageGroup);
        }
        $insightsSite->capabilities = $this->getCapabilitiesOfProvider($provider);
        return $insightsSite;
    }

    /**
     * @param string $provider
     * @return InsightsProviderCapabilities
     */
    protected function getCapabilitiesOfProvider(string $provider): InsightsProviderCapabilities
    {
        /** @var GoogleSearchConsoleSourceService $googleSearchConsoleSourceService */
        $googleSearchConsoleSourceService = DDDService::instance()->getService(GoogleSearchConsoleSourceService::class);
        return $googleSearchConsoleSourceService->getCapabilities();
    }

    /**
     * @param string $property
     * @return bool a URL-prefix property (`https://host/…/`, ending in a slash) or a domain property (`sc-domain:host`)
     */
    protected function isValidProperty(string $property): bool
    {
        if (str_starts_with($property, InsightsSite::PROPERTY_PREFIX_DOMAIN)) {
            return preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/i', substr($property, strlen(InsightsSite::PROPERTY_PREFIX_DOMAIN))) === 1;
        }
        return preg_match('#^https?://[^/\s?\#]+/(\S*/)?$#', $property) === 1;
    }

    /**
     * @param string[] $patterns
     * @param string $siteKey
     * @param string $settingName
     * @return string[] the patterns, each compilable and free of constructs RE2 rejects
     * @throws InternalErrorException
     */
    protected function validatePatterns(array $patterns, string $siteKey, string $settingName): array
    {
        foreach ($patterns as $pattern) {
            if (preg_match(self::NON_RE2_CONSTRUCT_PATTERN, $pattern) === 1) {
                throw $this->createSiteConfigException($siteKey, $settingName . ' holds a pattern RE2 rejects (lookaround, backreference or atomic group)');
            }
            if (!self::isCompilablePattern($pattern)) {
                throw $this->createSiteConfigException($siteKey, $settingName . ' holds a pattern that does not compile');
            }
        }
        return array_values($patterns);
    }

    /**
     * @param string $pattern without delimiters
     * @return bool
     */
    public static function isCompilablePattern(string $pattern): bool
    {
        set_error_handler(static fn (): bool => true);
        try {
            return preg_match('~' . str_replace('~', '\\~', $pattern) . '~u', '') !== false;
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @param string $siteKey
     * @param string $problem
     * @return InternalErrorException
     */
    protected function createSiteConfigException(string $siteKey, string $problem): InternalErrorException
    {
        return new InternalErrorException('SiteInsights.sites "' . $siteKey . '": ' . $problem);
    }
}
