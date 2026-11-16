<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Services;

use DDD\Domain\SiteInsights\Entities\Errors\SiteInsightsErrorCause;
use DDD\Domain\SiteInsights\Exceptions\SiteInsightsProviderUnavailableException;
use DDD\Infrastructure\Services\DDDService;
use DDD\Infrastructure\Services\Service;

/**
 * Extension point: where the access token of a credentials entry comes from. The default exchanges a Google
 * service-account key ({@see GoogleServiceAccountTokensService}). An application with another source subclasses this
 * service and registers the subclass under this class' service id.
 */
class SiteInsightsCredentialsProviderService extends Service
{
    /**
     * @param string $credentialsEntryName
     * @return string an access token
     * @throws SiteInsightsProviderUnavailableException cause keyUnreadable | tokenRefused | tokenEndpointFailed
     */
    public function getAccessToken(string $credentialsEntryName): string
    {
        return $this->getGoogleServiceAccountTokensService()->getAccessToken($credentialsEntryName);
    }

    /**
     * @param string $credentialsEntryName
     * @return void
     * @throws SiteInsightsProviderUnavailableException cause keyUnreadable
     */
    public function forgetAccessToken(string $credentialsEntryName): void
    {
        $this->getGoogleServiceAccountTokensService()->forgetAccessToken($credentialsEntryName);
    }

    /**
     * @param string $credentialsEntryName
     * @return void
     * @throws SiteInsightsProviderUnavailableException cause keyUnreadable, naming the check that failed
     */
    public function assertCredentialsReadable(string $credentialsEntryName): void
    {
        $this->getGoogleServiceAccountTokensService()->assertServiceAccountKeyReadable($credentialsEntryName);
    }

    /**
     * @param string $credentialsEntryName
     * @return int the revision of the entry (part of every cache identity)
     * @throws SiteInsightsProviderUnavailableException cause keyUnreadable when the entry is not configured
     */
    public function getRevision(string $credentialsEntryName): int
    {
        /** @var SiteInsightsConfigService $siteInsightsConfigService */
        $siteInsightsConfigService = DDDService::instance()->getService(SiteInsightsConfigService::class);
        $siteInsightsCredentialsEntry = $siteInsightsConfigService->getCredentialsConfig()->entries->findEntryByName($credentialsEntryName);
        if ($siteInsightsCredentialsEntry === null) {
            throw SiteInsightsProviderUnavailableException::forCause(
                SiteInsightsErrorCause::KEY_UNREADABLE,
                'Credentials entry "' . $credentialsEntryName . '": the entry is not configured'
            );
        }
        return $siteInsightsCredentialsEntry->revision;
    }

    protected function getGoogleServiceAccountTokensService(): GoogleServiceAccountTokensService
    {
        return DDDService::instance()->getService(GoogleServiceAccountTokensService::class);
    }
}
