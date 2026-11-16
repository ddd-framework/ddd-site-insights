<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Repo\Argus\Google;

use DDD\Domain\Base\Repo\Argus\Attributes\ArgusLoad;
use DDD\Domain\Base\Repo\Argus\Traits\ArgusTrait;
use DDD\Domain\Base\Repo\Argus\Utils\ArgusApiOperation;
use DDD\Domain\Base\Repo\Argus\Utils\ArgusCache;
use DDD\Domain\SiteInsights\Entities\Google\GoogleApiFailure;
use DDD\Domain\SiteInsights\Entities\Google\GoogleSiteEntries;
use DDD\Domain\SiteInsights\Entities\Google\GoogleSiteEntry;
use DDD\Domain\SiteInsights\Entities\Google\GoogleSites;
use DDD\Domain\SiteInsights\Services\GoogleApiRequestsService;
use DDD\Infrastructure\Base\DateTime\DateTime;
use DDD\Infrastructure\Services\DDDService;

/** Loads the sites list of a credentials entry; a single load, never cached (read by the access check only) */
#[ArgusLoad(
    loadEndpoint: 'GET:https://www.googleapis.com/webmasters/v3/sites',
    cacheLevel: ArgusCache::CACHELEVEL_NONE,
    timeout: 20
)]
class ArgusGoogleSites extends GoogleSites
{
    use ArgusTrait;

    protected function getLoadPayload(): ?array
    {
        /** @var GoogleApiRequestsService $googleApiRequestsService */
        $googleApiRequestsService = DDDService::instance()->getService(GoogleApiRequestsService::class);
        return $googleApiRequestsService->buildPayload($this->credentialsEntryName, [], null);
    }

    public function handleLoadResponse(mixed &$callResponseData = null, ?ArgusApiOperation &$apiOperation = null): void
    {
        $googleApiFailure = GoogleApiFailure::fromAnswer($callResponseData, $apiOperation);
        if ($googleApiFailure !== null) {
            $this->failure = $googleApiFailure;
            $this->postProcessLoadResponse($callResponseData, false, cacheFailure: false);
            return;
        }
        $this->siteEntries = new GoogleSiteEntries();
        foreach ((array)($callResponseData->siteEntry ?? []) as $siteEntryData) {
            if (!is_object($siteEntryData) || !is_string($siteEntryData->siteUrl ?? null)) {
                continue;
            }
            $googleSiteEntry = new GoogleSiteEntry();
            $googleSiteEntry->siteUrl = $siteEntryData->siteUrl;
            $googleSiteEntry->permissionLevel = (string)($siteEntryData->permissionLevel ?? '');
            $this->siteEntries->add($googleSiteEntry);
        }
        $this->fetchedAt = DateTime::fromTimestamp(time());
        $this->postProcessLoadResponse($callResponseData, true);
    }
}
