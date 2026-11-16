<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Repo\Argus\Google;

use DDD\Domain\Base\Repo\Argus\Attributes\ArgusLoad;
use DDD\Domain\Base\Repo\Argus\Traits\ArgusTrait;
use DDD\Domain\Base\Repo\Argus\Utils\ArgusApiOperation;
use DDD\Domain\Base\Repo\Argus\Utils\ArgusCache;
use DDD\Domain\SiteInsights\Entities\Google\GoogleApiFailure;
use DDD\Domain\SiteInsights\Entities\Google\GoogleUrlInspection;
use DDD\Domain\SiteInsights\Services\GoogleApiRequestsService;
use DDD\Infrastructure\Base\DateTime\DateTime;
use DDD\Infrastructure\Services\DDDService;

/**
 * Inspects one URL, cached in Redis (`cache.inspectionTtlSeconds`): a single load (access check) or a child of an
 * {@see ArgusGoogleUrlInspections} batch.
 */
#[ArgusLoad(
    loadEndpoint: 'POST:https://searchconsole.googleapis.com/v1/urlInspection/index:inspect',
    cacheLevel: ArgusCache::CACHELEVEL_DB,
    cacheTtl: ArgusCache::CACHE_TTL_ONE_DAY,
    timeout: 20
)]
class ArgusGoogleUrlInspection extends GoogleUrlInspection
{
    use ArgusTrait;

    /** @var int Calls built for this inspection in this process (retries included); never serialised */
    protected int $providerCallAttempts = 0;

    /** @return int calls built for this inspection in this process (0 for an answer from the cache) */
    public function getProviderCallAttempts(): int
    {
        return $this->providerCallAttempts;
    }

    protected function getLoadPayload(): ?array
    {
        $this->providerCallAttempts++;
        /** @var GoogleApiRequestsService $googleApiRequestsService */
        $googleApiRequestsService = DDDService::instance()->getService(GoogleApiRequestsService::class);
        return $googleApiRequestsService->buildPayload($this->credentialsEntryName, [], $this->buildRequestBody());
    }

    public function handleLoadResponse(mixed &$callResponseData = null, ?ArgusApiOperation &$apiOperation = null): void
    {
        $googleApiFailure = GoogleApiFailure::fromAnswer($callResponseData, $apiOperation);
        if ($googleApiFailure !== null) {
            $this->failure = $googleApiFailure;
            $this->postProcessLoadResponse($callResponseData, false, cacheFailure: false);
            return;
        }
        $inspectionResult = $callResponseData->inspectionResult ?? null;
        $indexStatusResult = is_object($inspectionResult) ? ($inspectionResult->indexStatusResult ?? null) : null;
        if (is_object($inspectionResult) && is_string($inspectionResult->inspectionResultLink ?? null)) {
            $this->inspectionResultLink = $inspectionResult->inspectionResultLink;
        }
        if (is_object($indexStatusResult)) {
            foreach (['verdict', 'coverageState', 'indexingState', 'robotsTxtState', 'pageFetchState', 'googleCanonical', 'userCanonical', 'crawledAs'] as $propertyName) {
                if (is_string($indexStatusResult->$propertyName ?? null)) {
                    $this->$propertyName = $indexStatusResult->$propertyName;
                }
            }
            foreach (['sitemap' => 'sitemaps', 'referringUrls' => 'referringUrls'] as $providerFieldName => $propertyName) {
                $this->$propertyName = array_values(array_filter(
                    (array)($indexStatusResult->$providerFieldName ?? []),
                    static fn (mixed $listedUrl): bool => is_string($listedUrl) && $listedUrl !== ''
                ));
            }
            $lastCrawlTime = self::parseProviderTime($indexStatusResult->lastCrawlTime ?? null);
            if ($lastCrawlTime !== null) {
                $this->lastCrawlTime = $lastCrawlTime;
            }
        }
        $this->fetchedAt = DateTime::fromTimestamp(time());
        $this->postProcessLoadResponse($callResponseData, true);
    }
}
