<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Services;

use DDD\Domain\SiteInsights\Entities\Config\SiteInsightsBudgetsConfig;
use DDD\Domain\SiteInsights\Entities\Errors\SiteInsightsErrorCause;
use DDD\Domain\SiteInsights\Entities\Indexing\SitemapStatuses;
use DDD\Domain\SiteInsights\Entities\Indexing\UrlIndexInspection;
use DDD\Domain\SiteInsights\Entities\Indexing\UrlIndexInspections;
use DDD\Domain\SiteInsights\Entities\Search\SearchPartError;
use DDD\Domain\SiteInsights\Entities\Sites\InsightsSite;
use DDD\Domain\SiteInsights\Exceptions\SiteInsightsBudgetExceededException;
use DDD\Domain\SiteInsights\Exceptions\SiteInsightsProviderUnavailableException;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\ForbiddenException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Infrastructure\Exceptions\NotFoundException;
use DDD\Infrastructure\Services\DDDService;
use DDD\Infrastructure\Services\Service;

/**
 * Indexing of a configured site: the state of its sitemap files, and the index state of a bounded list of its URLs.
 *
 * The sitemaps list is a read (cached `cache.sitemapsTtlSeconds`, counted against the per-minute budget of the other
 * resources only). Every URL inspection that is not answered from the cache counts against the provider's daily
 * inspection quota (Google: 2,000 per day and 600 per minute per property; the module uses `budgets.inspectionShare`
 * of it), so inspecting is never a GET and never refetched on its own; answers are cached a day per URL
 * (`cache.inspectionTtlSeconds`).
 */
class SearchIndexingService extends Service
{
    public const int URL_MAX_LENGTH = 2048;
    public const string LANGUAGE_CODE_PATTERN = '/^[a-z]{2}(-[A-Z]{2})?$/';

    /**
     * @param string $siteKey
     * @return SitemapStatuses the sitemap files of the site's property with their state, or the list's error
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws SiteInsightsBudgetExceededException
     * @throws SiteInsightsProviderUnavailableException when no token can be obtained
     */
    public function getSitemapStatuses(string $siteKey): SitemapStatuses
    {
        $this->getSiteInsightsAccessPolicyService()->requireViewer();
        $insightsSite = $this->findSite($siteKey);
        $this->assertPlannedCalls(SiteInsightsBudgetsConfig::OPERATION_SITEMAPS, 1);
        return $this->getGoogleSearchConsoleSourceService()->loadSitemapStatuses($insightsSite);
    }

    /**
     * Inspects 1 to `budgets.maxInspectionUrls` URLs of the site. Duplicates are inspected once; a URL that is no
     * http(s) URL of the property gets its own error (`invalidRequest`) without a provider call. The others are
     * answered from the cache or asked in one call round; when the inspection budget refuses them, each gets
     * `budgetRefused`.
     *
     * @param string $siteKey
     * @param string[] $urls
     * @param string|null $languageCode language of the provider's texts (`en` or `en-US`)
     * @return UrlIndexInspections one state or error per distinct URL, in the order given
     * @throws ForbiddenException when the account may not inspect
     * @throws NotFoundException
     * @throws BadRequestException for no URL, too many URLs or a malformed language code
     * @throws InternalErrorException
     * @throws SiteInsightsBudgetExceededException when the request plans more calls than the operation may make
     * @throws SiteInsightsProviderUnavailableException when no token can be obtained
     */
    public function inspectUrls(string $siteKey, array $urls, ?string $languageCode = null): UrlIndexInspections
    {
        $this->getSiteInsightsAccessPolicyService()->requireInspector();
        $insightsSite = $this->findSite($siteKey);
        $maxInspectionUrls = $this->getSiteInsightsConfigService()->getBudgetsConfig()->maxInspectionUrls;
        if (!$urls) {
            throw new BadRequestException('urls must name at least one URL');
        }
        if (count($urls) > $maxInspectionUrls) {
            throw new BadRequestException('urls may name at most ' . $maxInspectionUrls . ' URLs (budgets.maxInspectionUrls); ' . count($urls) . ' given');
        }
        if ($languageCode !== null && preg_match(self::LANGUAGE_CODE_PATTERN, $languageCode) !== 1) {
            throw new BadRequestException('languageCode must be written like en or en-US');
        }

        // method-local: the distinct URLs in the order given, and the error of each URL that is not inspected
        $distinctUrls = [];
        $partErrorsByUrl = [];
        foreach ($urls as $url) {
            $inspectionUrl = trim((string)$url);
            if (in_array($inspectionUrl, $distinctUrls, true)) {
                continue;
            }
            $distinctUrls[] = $inspectionUrl;
            $invalidUrlReason = $this->findInvalidUrlReason($insightsSite, $inspectionUrl);
            if ($invalidUrlReason !== null) {
                $partErrorsByUrl[$inspectionUrl] = SearchPartError::create(SiteInsightsErrorCause::INVALID_REQUEST, $invalidUrlReason);
            }
        }
        $urlsToInspect = array_values(array_filter($distinctUrls, static fn (string $inspectionUrl): bool => !isset($partErrorsByUrl[$inspectionUrl])));
        $this->assertPlannedCalls(SiteInsightsBudgetsConfig::OPERATION_INSPECTIONS, count($urlsToInspect));

        $sourceInspections = $this->getGoogleSearchConsoleSourceService()->inspectUrls($insightsSite, $urlsToInspect, $languageCode);
        $urlIndexInspections = new UrlIndexInspections();
        $urlIndexInspections->siteKey = $sourceInspections->siteKey;
        $urlIndexInspections->provider = $sourceInspections->provider;
        $urlIndexInspections->property = $sourceInspections->property;
        if (isset($sourceInspections->languageCode)) {
            $urlIndexInspections->languageCode = $sourceInspections->languageCode;
        }
        $urlIndexInspections->duplicateUrlsDropped = count($urls) - count($distinctUrls);
        $urlIndexInspections->inspectionCounterScope = $this->getSiteInsightsProviderBudgetsService()->getCounterScope();
        $urlIndexInspections->coverage = $sourceInspections->coverage;
        foreach ($distinctUrls as $inspectionUrl) {
            if (isset($partErrorsByUrl[$inspectionUrl])) {
                $failedInspection = UrlIndexInspection::createFailed(mb_substr($inspectionUrl, 0, self::URL_MAX_LENGTH), $partErrorsByUrl[$inspectionUrl]);
                $urlIndexInspections->add($failedInspection);
                $urlIndexInspections->coverage->requestedParts++;
                $urlIndexInspections->coverage->failedParts++;
                continue;
            }
            $urlIndexInspection = $sourceInspections->findInspectionByUrl($inspectionUrl);
            if ($urlIndexInspection !== null) {
                $urlIndexInspections->add($urlIndexInspection);
            }
        }
        $urlIndexInspections->coverage->fromCache = $urlIndexInspections->coverage->requestedParts > 0
            && $urlIndexInspections->coverage->partsFromCache === $urlIndexInspections->coverage->requestedParts;
        return $urlIndexInspections;
    }

    /**
     * @param InsightsSite $insightsSite
     * @param string $url trimmed
     * @return string|null why the URL is not inspected; null when it is an http(s) URL of the property (URL prefix:
     *         starts with it; domain property: its host is the domain or a subdomain of it)
     */
    protected function findInvalidUrlReason(InsightsSite $insightsSite, string $url): ?string
    {
        if ($url === '' || strlen($url) > self::URL_MAX_LENGTH || preg_match('#^https?://[^/\s]+#i', $url) !== 1) {
            return 'not an http(s) URL of at most ' . self::URL_MAX_LENGTH . ' characters';
        }
        if ($insightsSite->isDomainProperty()) {
            $urlHost = strtolower((string)parse_url($url, PHP_URL_HOST));
            $domain = strtolower($insightsSite->getPropertyHost());
            if ($urlHost !== $domain && !str_ends_with($urlHost, '.' . $domain)) {
                return 'the URL does not belong to the property of site "' . $insightsSite->key . '"';
            }
            return null;
        }
        if (!str_starts_with($url, $insightsSite->property)) {
            return 'the URL does not start with the property of site "' . $insightsSite->key . '"';
        }
        return null;
    }

    /**
     * @param string $operation
     * @param int $plannedCalls
     * @return void
     * @throws SiteInsightsBudgetExceededException
     * @throws BadRequestException
     */
    protected function assertPlannedCalls(string $operation, int $plannedCalls): void
    {
        $this->getSiteInsightsProviderBudgetsService()->assertOperationBudget($operation, $plannedCalls);
    }

    /**
     * @param string $siteKey
     * @return InsightsSite
     * @throws NotFoundException
     * @throws InternalErrorException
     */
    protected function findSite(string $siteKey): InsightsSite
    {
        /** @var InsightsSitesService $insightsSitesService */
        $insightsSitesService = DDDService::instance()->getService(InsightsSitesService::class);
        return $insightsSitesService->getSite($siteKey);
    }

    protected function getGoogleSearchConsoleSourceService(): GoogleSearchConsoleSourceService
    {
        return DDDService::instance()->getService(GoogleSearchConsoleSourceService::class);
    }

    protected function getSiteInsightsConfigService(): SiteInsightsConfigService
    {
        return DDDService::instance()->getService(SiteInsightsConfigService::class);
    }

    protected function getSiteInsightsAccessPolicyService(): SiteInsightsAccessPolicyService
    {
        return DDDService::instance()->getService(SiteInsightsAccessPolicyService::class);
    }

    protected function getSiteInsightsProviderBudgetsService(): SiteInsightsProviderBudgetsService
    {
        return DDDService::instance()->getService(SiteInsightsProviderBudgetsService::class);
    }
}
