<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Unit\Access;

use DDD\Domain\SiteInsights\Services\SiteInsightsAccessPolicyService;
use DDD\Infrastructure\Exceptions\ForbiddenException;
use DDD\Infrastructure\Services\AuthService;
use DDD\Infrastructure\Services\DDDService;
use DDD\Tests\SiteInsights\Support\SiteInsightsTestCase;
use DDD\Tests\SiteInsights\Support\TestAccounts;

/** The default policy: admin accounts view, inspect and export; others nothing */
class SiteInsightsAccessPolicyServiceTest extends SiteInsightsTestCase
{
    public function testDefaultPolicy(): void
    {
        $siteInsightsAccessPolicyService = $this->getSiteInsightsAccessPolicyService();

        self::assertTrue($siteInsightsAccessPolicyService->canView(TestAccounts::admin()));
        self::assertTrue($siteInsightsAccessPolicyService->canInspect(TestAccounts::admin()));
        self::assertTrue($siteInsightsAccessPolicyService->canExport(TestAccounts::admin()));
        self::assertFalse($siteInsightsAccessPolicyService->canView(TestAccounts::nonAdmin()));
        self::assertFalse($siteInsightsAccessPolicyService->canView(null));
    }

    public function testRequireViewer(): void
    {
        $adminAccount = TestAccounts::admin();
        AuthService::instance()->setAccount($adminAccount);
        self::assertSame($adminAccount, $this->getSiteInsightsAccessPolicyService()->requireViewer());

        $nonAdminAccount = TestAccounts::nonAdmin();
        AuthService::instance()->setAccount($nonAdminAccount);
        $this->expectException(ForbiddenException::class);
        $this->getSiteInsightsAccessPolicyService()->requireViewer();
    }

    public function testWithoutAccountNobodyViews(): void
    {
        $this->expectException(ForbiddenException::class);
        $this->getSiteInsightsAccessPolicyService()->requireViewer();
    }

    protected function getSiteInsightsAccessPolicyService(): SiteInsightsAccessPolicyService
    {
        return DDDService::instance()->getService(SiteInsightsAccessPolicyService::class);
    }
}
