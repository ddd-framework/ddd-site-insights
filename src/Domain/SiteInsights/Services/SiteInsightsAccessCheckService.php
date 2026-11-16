<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Services;

use DDD\Domain\SiteInsights\Entities\Access\SiteInsightsAccessCheck;
use DDD\Domain\SiteInsights\Entities\Access\SiteInsightsAccessCheckValues;
use DDD\Domain\SiteInsights\Entities\Access\SiteInsightsAccessChecks;
use DDD\Domain\SiteInsights\Entities\Errors\SiteInsightsErrorCause;
use DDD\Domain\SiteInsights\Entities\Google\GoogleApiFailure;
use DDD\Domain\SiteInsights\Entities\Search\SearchQuestion;
use DDD\Domain\SiteInsights\Entities\Search\SearchQuestions;
use DDD\Domain\SiteInsights\Entities\Search\SearchWindowRequest;
use DDD\Domain\SiteInsights\Entities\Sites\InsightsSite;
use DDD\Domain\SiteInsights\Exceptions\SiteInsightsBudgetExceededException;
use DDD\Domain\SiteInsights\Exceptions\SiteInsightsProviderUnavailableException;
use DDD\Domain\SiteInsights\Utils\SearchDays;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Services\DDDService;
use DDD\Infrastructure\Services\Service;

/**
 * Checks every capability of a site against the provider, live (no cache): the key, the token, the property in the
 * sites list, the permission level, a one-row Search Analytics call, the sitemaps list and one URL inspection of the
 * property's root (counted against the inspection quota), and whether the call counters are shared. A capability
 * whose prerequisite failed is skipped. The result holds states only.
 */
class SiteInsightsAccessCheckService extends Service
{
    public const string OPERATION = 'checkAccess';

    /** @var int Provider calls of one check: sites, site, search analytics, sitemaps, inspection */
    public const int PLANNED_PROVIDER_CALLS = 5;

    /**
     * @param InsightsSite $insightsSite
     * @return SiteInsightsAccessChecks
     * @throws SiteInsightsBudgetExceededException when the operation would need more calls than it may
     * @throws BadRequestException
     */
    public function checkAccess(InsightsSite $insightsSite): SiteInsightsAccessChecks
    {
        /** @var SiteInsightsProviderBudgetsService $siteInsightsProviderBudgetsService */
        $siteInsightsProviderBudgetsService = DDDService::instance()->getService(SiteInsightsProviderBudgetsService::class);
        $siteInsightsProviderBudgetsService->assertOperationBudget(self::OPERATION, self::PLANNED_PROVIDER_CALLS);
        /** @var SiteInsightsCredentialsProviderService $siteInsightsCredentialsProviderService */
        $siteInsightsCredentialsProviderService = DDDService::instance()->getService(SiteInsightsCredentialsProviderService::class);
        /** @var GoogleSearchConsoleSourceService $googleSearchConsoleSourceService */
        $googleSearchConsoleSourceService = DDDService::instance()->getService(GoogleSearchConsoleSourceService::class);

        $siteInsightsAccessChecks = new SiteInsightsAccessChecks();
        $prerequisitesMet = true;
        try {
            $siteInsightsCredentialsProviderService->assertCredentialsReadable($insightsSite->credentialsEntryName);
            $this->addCheck($siteInsightsAccessChecks, $insightsSite, SiteInsightsAccessCheck::CAPABILITY_KEY_READABLE, SiteInsightsAccessCheck::STATE_OK);
        } catch (SiteInsightsProviderUnavailableException $siteInsightsProviderUnavailableException) {
            $prerequisitesMet = false;
            $this->addFailedCheck($siteInsightsAccessChecks, $insightsSite, SiteInsightsAccessCheck::CAPABILITY_KEY_READABLE, $siteInsightsProviderUnavailableException->getCause(), $siteInsightsProviderUnavailableException->getMessage());
        }

        if ($prerequisitesMet) {
            try {
                $siteInsightsCredentialsProviderService->getAccessToken($insightsSite->credentialsEntryName);
                $this->addCheck($siteInsightsAccessChecks, $insightsSite, SiteInsightsAccessCheck::CAPABILITY_TOKEN_OBTAINED, SiteInsightsAccessCheck::STATE_OK);
            } catch (SiteInsightsProviderUnavailableException $siteInsightsProviderUnavailableException) {
                $prerequisitesMet = false;
                $this->addFailedCheck($siteInsightsAccessChecks, $insightsSite, SiteInsightsAccessCheck::CAPABILITY_TOKEN_OBTAINED, $siteInsightsProviderUnavailableException->getCause(), $siteInsightsProviderUnavailableException->getMessage());
            }
        } else {
            $this->addCheck($siteInsightsAccessChecks, $insightsSite, SiteInsightsAccessCheck::CAPABILITY_TOKEN_OBTAINED, SiteInsightsAccessCheck::STATE_SKIPPED);
        }

        if ($prerequisitesMet) {
            $googleSites = $googleSearchConsoleSourceService->loadSitesList($insightsSite);
            if (isset($googleSites->failure)) {
                $this->addFailureCheck($siteInsightsAccessChecks, $insightsSite, SiteInsightsAccessCheck::CAPABILITY_PROPERTY_LISTED, $googleSites->failure);
            } elseif ($googleSiteEntry = $googleSites->siteEntries?->findBySiteUrl($insightsSite->property)) {
                $this->addCheck($siteInsightsAccessChecks, $insightsSite, SiteInsightsAccessCheck::CAPABILITY_PROPERTY_LISTED, SiteInsightsAccessCheck::STATE_OK, null, $googleSiteEntry->permissionLevel, SiteInsightsAccessCheck::DETAIL_CODE_PROPERTY_LISTED, SiteInsightsAccessCheckValues::forLevel($googleSiteEntry->permissionLevel ?? 'unknown'));
            } else {
                $listedProperties = count($googleSites->siteEntries?->getElements() ?? []);
                $this->addCheck(
                    $siteInsightsAccessChecks,
                    $insightsSite,
                    SiteInsightsAccessCheck::CAPABILITY_PROPERTY_LISTED,
                    SiteInsightsAccessCheck::STATE_FAILED,
                    SiteInsightsErrorCause::NOT_USER_OF_PROPERTY,
                    'the property is not in the sites list of the credentials entry (' . $listedProperties . ' listed)',
                    SiteInsightsAccessCheck::DETAIL_CODE_PROPERTY_NOT_LISTED,
                    SiteInsightsAccessCheckValues::forListedProperties($listedProperties)
                );
            }

            $googleSite = $googleSearchConsoleSourceService->loadSite($insightsSite);
            if (isset($googleSite->failure)) {
                $this->addFailureCheck($siteInsightsAccessChecks, $insightsSite, SiteInsightsAccessCheck::CAPABILITY_PERMISSION_LEVEL, $googleSite->failure);
            } else {
                $this->addCheck($siteInsightsAccessChecks, $insightsSite, SiteInsightsAccessCheck::CAPABILITY_PERMISSION_LEVEL, SiteInsightsAccessCheck::STATE_OK, null, $googleSite->permissionLevel ?? 'unknown', SiteInsightsAccessCheck::DETAIL_CODE_PERMISSION_LEVEL, SiteInsightsAccessCheckValues::forLevel($googleSite->permissionLevel ?? 'unknown'));
            }

            $searchReports = $googleSearchConsoleSourceService->loadReports(
                $insightsSite,
                $this->buildSearchAnalyticsCheckQuestions($googleSearchConsoleSourceService->getToday()),
                false
            );
            $searchPerformanceRows = $searchReports->first()?->performanceRows;
            if ($searchPerformanceRows === null || isset($searchPerformanceRows->error)) {
                $this->addCheck(
                    $siteInsightsAccessChecks,
                    $insightsSite,
                    SiteInsightsAccessCheck::CAPABILITY_SEARCH_ANALYTICS,
                    SiteInsightsAccessCheck::STATE_FAILED,
                    $searchPerformanceRows?->error?->getCause() ?? SiteInsightsErrorCause::PROVIDER_FAILED,
                    $searchPerformanceRows?->error?->message ?? 'no answer'
                );
            } else {
                $rowsRead = count($searchPerformanceRows->getElements());
                $this->addCheck($siteInsightsAccessChecks, $insightsSite, SiteInsightsAccessCheck::CAPABILITY_SEARCH_ANALYTICS, SiteInsightsAccessCheck::STATE_OK, null, $rowsRead . ' row(s)', SiteInsightsAccessCheck::DETAIL_CODE_ROWS_READ, SiteInsightsAccessCheckValues::forRows($rowsRead));
            }

            $googleSitemaps = $googleSearchConsoleSourceService->loadSitemaps($insightsSite, false);
            if (isset($googleSitemaps->failure)) {
                $this->addFailureCheck($siteInsightsAccessChecks, $insightsSite, SiteInsightsAccessCheck::CAPABILITY_SITEMAPS, $googleSitemaps->failure);
            } else {
                $sitemapFiles = count($googleSitemaps->sitemaps?->getElements() ?? []);
                $this->addCheck($siteInsightsAccessChecks, $insightsSite, SiteInsightsAccessCheck::CAPABILITY_SITEMAPS, SiteInsightsAccessCheck::STATE_OK, null, $sitemapFiles . ' sitemap file(s)', SiteInsightsAccessCheck::DETAIL_CODE_SITEMAPS_LISTED, SiteInsightsAccessCheckValues::forFiles($sitemapFiles));
            }

            $googleUrlInspection = $googleSearchConsoleSourceService->inspectUrl($insightsSite, $insightsSite->getPropertyRootUrl(), null, false);
            if (isset($googleUrlInspection->failure)) {
                $this->addFailureCheck($siteInsightsAccessChecks, $insightsSite, SiteInsightsAccessCheck::CAPABILITY_URL_INSPECTION, $googleUrlInspection->failure);
            } else {
                $inspectionVerdict = $googleUrlInspection->verdict ?? 'unknown';
                $this->addCheck($siteInsightsAccessChecks, $insightsSite, SiteInsightsAccessCheck::CAPABILITY_URL_INSPECTION, SiteInsightsAccessCheck::STATE_OK, null, 'verdict ' . $inspectionVerdict, SiteInsightsAccessCheck::DETAIL_CODE_INSPECTION_VERDICT, SiteInsightsAccessCheckValues::forVerdict($inspectionVerdict));
            }
        } else {
            foreach ([
                SiteInsightsAccessCheck::CAPABILITY_PROPERTY_LISTED,
                SiteInsightsAccessCheck::CAPABILITY_PERMISSION_LEVEL,
                SiteInsightsAccessCheck::CAPABILITY_SEARCH_ANALYTICS,
                SiteInsightsAccessCheck::CAPABILITY_SITEMAPS,
                SiteInsightsAccessCheck::CAPABILITY_URL_INSPECTION,
            ] as $skippedCapability) {
                $this->addCheck($siteInsightsAccessChecks, $insightsSite, $skippedCapability, SiteInsightsAccessCheck::STATE_SKIPPED);
            }
        }

        // shared: every pod counts against one budget; perPod or notCounted work, but N pods may use N times a quota
        $counterScope = $siteInsightsProviderBudgetsService->getCounterScope();
        $this->addCheck(
            $siteInsightsAccessChecks,
            $insightsSite,
            SiteInsightsAccessCheck::CAPABILITY_BUDGET_COUNTERS_SHARED,
            $counterScope === SiteInsightsProviderBudgetsService::COUNTER_SCOPE_SHARED ? SiteInsightsAccessCheck::STATE_OK : SiteInsightsAccessCheck::STATE_WARNING,
            null,
            match ($counterScope) {
                SiteInsightsProviderBudgetsService::COUNTER_SCOPE_SHARED => 'Call counters are shared by every pod (Redis).',
                SiteInsightsProviderBudgetsService::COUNTER_SCOPE_PER_POD => 'Call counters are kept per pod (APCu): N pods may use up to N times a quota.',
                default => 'Calls are not counted: neither the Redis group nor APCu works here.',
            },
            $counterScope
        );
        return $siteInsightsAccessChecks;
    }

    /**
     * @param string $today
     * @return SearchQuestions one row of totals over the assumed last final day (today − 3), by property
     */
    protected function buildSearchAnalyticsCheckQuestions(string $today): SearchQuestions
    {
        $checkDay = SearchDays::addDays($today, -GoogleSearchConsoleSourceService::FALLBACK_DATA_LAG_DAYS);
        $searchQuestion = new SearchQuestion();
        $searchQuestion->startDate = SearchDays::toDate($checkDay);
        $searchQuestion->endDate = SearchDays::toDate($checkDay);
        $searchQuestion->searchType = SearchWindowRequest::SEARCH_TYPE_WEB;
        $searchQuestion->dataState = SearchWindowRequest::DATA_STATE_FINAL;
        $searchQuestion->aggregationType = SearchQuestion::AGGREGATION_BY_PROPERTY;
        $searchQuestion->rowLimit = 1;
        $searchQuestions = new SearchQuestions();
        $searchQuestions->add($searchQuestion);
        return $searchQuestions;
    }

    protected function addCheck(
        SiteInsightsAccessChecks $siteInsightsAccessChecks,
        InsightsSite $insightsSite,
        string $capability,
        string $state,
        ?SiteInsightsErrorCause $siteInsightsErrorCause = null,
        ?string $detail = null,
        ?string $detailCode = null,
        ?SiteInsightsAccessCheckValues $siteInsightsAccessCheckValues = null
    ): void {
        $siteInsightsAccessCheck = SiteInsightsAccessCheck::create($insightsSite->key, $capability, $state, $siteInsightsErrorCause, $detail, $detailCode, $siteInsightsAccessCheckValues);
        $siteInsightsAccessChecks->add($siteInsightsAccessCheck);
    }

    protected function addFailedCheck(
        SiteInsightsAccessChecks $siteInsightsAccessChecks,
        InsightsSite $insightsSite,
        string $capability,
        SiteInsightsErrorCause $siteInsightsErrorCause,
        string $detail
    ): void {
        $this->addCheck($siteInsightsAccessChecks, $insightsSite, $capability, SiteInsightsAccessCheck::STATE_FAILED, $siteInsightsErrorCause, $detail);
    }

    protected function addFailureCheck(
        SiteInsightsAccessChecks $siteInsightsAccessChecks,
        InsightsSite $insightsSite,
        string $capability,
        GoogleApiFailure $googleApiFailure
    ): void {
        $this->addFailedCheck(
            $siteInsightsAccessChecks,
            $insightsSite,
            $capability,
            $googleApiFailure->getCause(),
            (isset($googleApiFailure->httpStatus) ? 'http ' . $googleApiFailure->httpStatus . ': ' : '') . $googleApiFailure->message
        );
    }
}
