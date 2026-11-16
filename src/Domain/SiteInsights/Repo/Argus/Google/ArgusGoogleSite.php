<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Repo\Argus\Google;

use DDD\Domain\Base\Repo\Argus\Attributes\ArgusLoad;
use DDD\Domain\Base\Repo\Argus\Traits\ArgusTrait;
use DDD\Domain\Base\Repo\Argus\Utils\ArgusApiOperation;
use DDD\Domain\Base\Repo\Argus\Utils\ArgusCache;
use DDD\Domain\SiteInsights\Entities\Google\GoogleApiFailure;
use DDD\Domain\SiteInsights\Entities\Google\GoogleSite;
use DDD\Domain\SiteInsights\Services\GoogleApiRequestsService;
use DDD\Infrastructure\Base\DateTime\DateTime;
use DDD\Infrastructure\Services\DDDService;

/** Loads one property; a single load, never cached (read by the access check only) */
#[ArgusLoad(
    loadEndpoint: 'GET:https://www.googleapis.com/webmasters/v3/sites/{siteUrl}',
    cacheLevel: ArgusCache::CACHELEVEL_NONE,
    timeout: 20
)]
class ArgusGoogleSite extends GoogleSite
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
        if (is_string($callResponseData->permissionLevel ?? null)) {
            $this->permissionLevel = $callResponseData->permissionLevel;
        }
        $this->fetchedAt = DateTime::fromTimestamp(time());
        $this->postProcessLoadResponse($callResponseData, true);
    }
}
