<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Services;

use DDD\Domain\SiteInsights\Entities\Sites\InsightsPageGroup;
use DDD\Domain\SiteInsights\Entities\Sites\InsightsSite;
use DDD\Domain\SiteInsights\Entities\Sites\InsightsSites;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Infrastructure\Exceptions\NotFoundException;
use DDD\Infrastructure\Services\DDDService;
use DDD\Infrastructure\Services\Service;

/** Lookup of the configured sites (from the sites provider extension point) */
class InsightsSitesService extends Service
{
    /**
     * @return InsightsSites
     * @throws InternalErrorException when the configuration is invalid
     */
    public function getSites(): InsightsSites
    {
        /** @var SiteInsightsSitesProviderService $siteInsightsSitesProviderService */
        $siteInsightsSitesProviderService = DDDService::instance()->getService(SiteInsightsSitesProviderService::class);
        $insightsSites = $siteInsightsSitesProviderService->getSites();
        /** @var SiteInsightsConfigService $siteInsightsConfigService */
        $siteInsightsConfigService = DDDService::instance()->getService(SiteInsightsConfigService::class);
        $maxInspectionUrls = $siteInsightsConfigService->getBudgetsConfig()->maxInspectionUrls;
        foreach ($insightsSites->getElements() as $insightsSite) {
            $insightsSite->maxInspectionUrls = $maxInspectionUrls;
        }
        return $insightsSites;
    }

    /**
     * @param string $siteKey
     * @return InsightsSite
     * @throws NotFoundException when no site has the key
     * @throws InternalErrorException
     */
    public function getSite(string $siteKey): InsightsSite
    {
        $insightsSite = $this->getSites()->findSiteByKey($siteKey);
        if ($insightsSite === null) {
            throw new NotFoundException('No site "' . mb_substr($siteKey, 0, 64) . '" is configured');
        }
        return $insightsSite;
    }

    /**
     * @param InsightsSite $insightsSite
     * @param string $url
     * @return InsightsPageGroup|null the first configured group whose path pattern matches the URL's path
     */
    public function findPageGroupOfUrl(InsightsSite $insightsSite, string $url): ?InsightsPageGroup
    {
        $urlPath = parse_url($url, PHP_URL_PATH);
        if (!is_string($urlPath) || $urlPath === '') {
            $urlPath = '/';
        }
        foreach ($insightsSite->pageGroups->getElements() as $insightsPageGroup) {
            if ($insightsPageGroup->matchesPath($urlPath)) {
                return $insightsPageGroup;
            }
        }
        return null;
    }
}
