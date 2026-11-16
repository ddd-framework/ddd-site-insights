<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Repo\Argus\Google;

use DDD\Domain\Base\Repo\Argus\Attributes\ArgusLoad;
use DDD\Domain\Base\Repo\Argus\Traits\ArgusTrait;
use DDD\Domain\Base\Repo\Argus\Utils\ArgusApiOperation;
use DDD\Domain\Base\Repo\Argus\Utils\ArgusCache;
use DDD\Domain\SiteInsights\Entities\Google\GoogleApiFailure;
use DDD\Domain\SiteInsights\Entities\Google\GoogleSitemapContent;
use DDD\Domain\SiteInsights\Entities\Google\GoogleSitemapContents;
use DDD\Domain\SiteInsights\Entities\Google\GoogleSitemapEntries;
use DDD\Domain\SiteInsights\Entities\Google\GoogleSitemapEntry;
use DDD\Domain\SiteInsights\Entities\Google\GoogleSitemaps;
use DDD\Domain\SiteInsights\Services\GoogleApiRequestsService;
use DDD\Infrastructure\Base\DateTime\DateTime;
use DDD\Infrastructure\Services\DDDService;

/** Loads the sitemaps list of a property; a single load, cached in memory and Redis (`cache.sitemapsTtlSeconds`) */
#[ArgusLoad(
    loadEndpoint: 'GET:https://www.googleapis.com/webmasters/v3/sites/{siteUrl}/sitemaps',
    cacheLevel: ArgusCache::CACHELEVEL_MEMORY_AND_DB,
    cacheTtl: ArgusCache::CACHE_TTL_ONE_HOUR,
    timeout: 20
)]
class ArgusGoogleSitemaps extends GoogleSitemaps
{
    use ArgusTrait;

    protected function getLoadPayload(): ?array
    {
        /** @var GoogleApiRequestsService $googleApiRequestsService */
        $googleApiRequestsService = DDDService::instance()->getService(GoogleApiRequestsService::class);
        return $googleApiRequestsService->buildPayload($this->credentialsEntryName, ['siteUrl' => $this->siteUrl], null);
    }

    public function handleLoadResponse(mixed &$callResponseData = null, ?ArgusApiOperation &$apiOperation = null): void
    {
        $googleApiFailure = GoogleApiFailure::fromAnswer($callResponseData, $apiOperation);
        if ($googleApiFailure !== null) {
            $this->failure = $googleApiFailure;
            $this->postProcessLoadResponse($callResponseData, false, cacheFailure: false);
            return;
        }
        $this->sitemaps = new GoogleSitemapEntries();
        foreach ((array)($callResponseData->sitemap ?? []) as $sitemapData) {
            if (!is_object($sitemapData) || !is_string($sitemapData->path ?? null)) {
                continue;
            }
            $googleSitemapEntry = new GoogleSitemapEntry();
            $googleSitemapEntry->path = $sitemapData->path;
            if (is_string($sitemapData->type ?? null)) {
                $googleSitemapEntry->type = $sitemapData->type;
            }
            $googleSitemapEntry->isPending = (bool)($sitemapData->isPending ?? false);
            $googleSitemapEntry->isSitemapsIndex = (bool)($sitemapData->isSitemapsIndex ?? false);
            $googleSitemapEntry->warnings = (int)($sitemapData->warnings ?? 0);
            $googleSitemapEntry->errors = (int)($sitemapData->errors ?? 0);
            $lastSubmitted = self::parseProviderTime($sitemapData->lastSubmitted ?? null);
            if ($lastSubmitted !== null) {
                $googleSitemapEntry->lastSubmitted = $lastSubmitted;
            }
            $lastDownloaded = self::parseProviderTime($sitemapData->lastDownloaded ?? null);
            if ($lastDownloaded !== null) {
                $googleSitemapEntry->lastDownloaded = $lastDownloaded;
            }
            $googleSitemapContents = $this->mapSitemapContents($sitemapData->contents ?? null);
            if ($googleSitemapContents !== null) {
                $googleSitemapEntry->contents = $googleSitemapContents;
            }
            $this->sitemaps->add($googleSitemapEntry);
        }
        $this->fetchedAt = DateTime::fromTimestamp(time());
        $this->postProcessLoadResponse($callResponseData, true);
    }

    /**
     * @param mixed $contentsData Google's `contents` of one file
     * @return GoogleSitemapContents|null the content types with their submitted counts; null when Google sent none
     */
    protected function mapSitemapContents(mixed $contentsData): ?GoogleSitemapContents
    {
        if (!is_array($contentsData) || !$contentsData) {
            return null;
        }
        $googleSitemapContents = new GoogleSitemapContents();
        foreach ($contentsData as $contentData) {
            if (!is_object($contentData) || !is_string($contentData->type ?? null)) {
                continue;
            }
            $googleSitemapContent = new GoogleSitemapContent();
            $googleSitemapContent->type = $contentData->type;
            $googleSitemapContent->submitted = (int)($contentData->submitted ?? 0);
            $googleSitemapContents->add($googleSitemapContent);
        }
        return $googleSitemapContents;
    }
}
