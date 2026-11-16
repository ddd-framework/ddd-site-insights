<?php

declare(strict_types=1);

namespace DDD\Presentation\Api\Admin\SiteInsights\Traits;

use DDD\Domain\Common\Entities\Accounts\Account;
use DDD\Domain\SiteInsights\Services\SiteInsightsAccessPolicyService;
use DDD\Infrastructure\Exceptions\ForbiddenException;
use DDD\Infrastructure\Services\DDDService;

/**
 * Shared by the site insights controller traits: every action asks the access policy (resolved by its class id, so an
 * application's subclass decides) before anything is read.
 */
trait SiteInsightsViewerTrait
{
    /**
     * @return Account the authenticated account, which may view site insights
     * @throws ForbiddenException
     */
    protected function requireSiteInsightsViewer(): Account
    {
        /** @var SiteInsightsAccessPolicyService $siteInsightsAccessPolicyService */
        $siteInsightsAccessPolicyService = DDDService::instance()->getService(SiteInsightsAccessPolicyService::class);
        return $siteInsightsAccessPolicyService->requireViewer();
    }
}
