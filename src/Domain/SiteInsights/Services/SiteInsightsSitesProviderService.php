<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Services;

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
        foreach ($siteInsightsConfigService->getSiteEntries() as $siteIndex => $siteEntry) {
            $insightsSite = $this->buildSite(is_array($siteEntry) ? $siteEntry : [], (string)$siteIndex);
            if ($insightsSites->findSiteByKey($insightsSite->key) !== null) {
                throw new InternalErrorException('SiteInsights.sites: the site key "' . $insightsSite->key . '" is configured twice');
            }
            $insightsSites->add($insightsSite);
        }
        return $insightsSites;
    }

    /**
     * @param array $siteEntry
     * @param string $siteIndex position in the config, named in errors before the key is known
     * @return InsightsSite
     * @throws InternalErrorException
     */
    protected function buildSite(array $siteEntry, string $siteIndex): InsightsSite
    {
        $siteKey = $siteEntry['key'] ?? null;
        if (!is_string($siteKey) || preg_match(self::SITE_KEY_PATTERN, $siteKey) !== 1) {
            throw new InternalErrorException('SiteInsights.sites[' . $siteIndex . ']: the key must match ' . self::SITE_KEY_PATTERN);
        }
        $insightsSite = new InsightsSite();
        $insightsSite->key = $siteKey;
        $insightsSite->label = is_string($siteEntry['label'] ?? null) && $siteEntry['label'] !== '' ? $siteEntry['label'] : $siteKey;
        $provider = $siteEntry['provider'] ?? GoogleSearchConsoleSourceService::PROVIDER;
        if (!in_array($provider, self::SUPPORTED_PROVIDERS, true)) {
            throw $this->createSiteConfigException($siteKey, 'the provider is not supported');
        }
        $insightsSite->provider = $provider;
        $property = $siteEntry['property'] ?? null;
        if (!is_string($property) || !$this->isValidProperty($property)) {
            throw $this->createSiteConfigException($siteKey, 'the property must be "https://host/…/" or "sc-domain:host"');
        }
        $insightsSite->property = $property;
        $credentialsEntryName = $siteEntry['credentials'] ?? null;
        if (!is_string($credentialsEntryName) || $credentialsEntryName === '') {
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
        $insightsSite->brandQueryPatterns = $this->readPatterns($siteEntry['brandQueryPatterns'] ?? [], $siteKey, 'brandQueryPatterns');
        $insightsSite->pageGroups = new InsightsPageGroups();
        foreach ((array)($siteEntry['pageGroups'] ?? []) as $pageGroupIndex => $pageGroupEntry) {
            $pageGroupKey = is_array($pageGroupEntry) ? ($pageGroupEntry['key'] ?? null) : null;
            if (!is_string($pageGroupKey) || preg_match(self::SITE_KEY_PATTERN, $pageGroupKey) !== 1) {
                throw $this->createSiteConfigException($siteKey, 'pageGroups[' . $pageGroupIndex . ']: the key must match ' . self::SITE_KEY_PATTERN);
            }
            foreach ($insightsSite->pageGroups->getElements() as $existingPageGroup) {
                if ($existingPageGroup->key === $pageGroupKey) {
                    throw $this->createSiteConfigException($siteKey, 'the page group "' . $pageGroupKey . '" is configured twice');
                }
            }
            $insightsPageGroup = new InsightsPageGroup();
            $insightsPageGroup->key = $pageGroupKey;
            $insightsPageGroup->label = is_string($pageGroupEntry['label'] ?? null) ? $pageGroupEntry['label'] : $pageGroupKey;
            $insightsPageGroup->pathPatterns = $this->readPatterns($pageGroupEntry['pathPatterns'] ?? [], $siteKey, 'pageGroups.' . $pageGroupKey);
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
     * @param mixed $patterns
     * @param string $siteKey
     * @param string $settingName
     * @return string[] the patterns, each compilable and free of constructs RE2 rejects
     * @throws InternalErrorException
     */
    protected function readPatterns(mixed $patterns, string $siteKey, string $settingName): array
    {
        if (!is_array($patterns)) {
            throw $this->createSiteConfigException($siteKey, $settingName . ' must be a list of patterns');
        }
        $validPatterns = [];
        foreach ($patterns as $pattern) {
            if (!is_string($pattern) || $pattern === '') {
                throw $this->createSiteConfigException($siteKey, $settingName . ' holds an empty or non-string pattern');
            }
            if (preg_match(self::NON_RE2_CONSTRUCT_PATTERN, $pattern) === 1) {
                throw $this->createSiteConfigException($siteKey, $settingName . ' holds a pattern RE2 rejects (lookaround, backreference or atomic group)');
            }
            if (!self::isCompilablePattern($pattern)) {
                throw $this->createSiteConfigException($siteKey, $settingName . ' holds a pattern that does not compile');
            }
            $validPatterns[] = $pattern;
        }
        return $validPatterns;
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
