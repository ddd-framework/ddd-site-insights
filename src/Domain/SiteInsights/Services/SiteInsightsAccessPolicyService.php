<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Services;

use DDD\Domain\Common\Entities\Accounts\Account;
use DDD\Infrastructure\Exceptions\ForbiddenException;
use DDD\Infrastructure\Services\AuthService;
use DDD\Infrastructure\Services\DDDService;
use DDD\Infrastructure\Services\Service;

/**
 * Extension point: who may view site insights. The default lets accounts with an admin role view, inspect and
 * export, and everyone else nothing. An application with its own rule subclasses this service and registers the
 * subclass under this class' service id. Every endpoint and command asks it before any cache read.
 */
class SiteInsightsAccessPolicyService extends Service
{
    /**
     * @param Account|null $account the authenticated account, null without one
     * @return bool may read sites, search data and indexing states
     */
    public function canView(?Account $account): bool
    {
        return (bool)($account?->roles?->isAdmin());
    }

    /**
     * @param Account|null $account
     * @return bool may inspect URLs (counted against the provider's inspection quota)
     */
    public function canInspect(?Account $account): bool
    {
        return $this->canView($account);
    }

    /**
     * @param Account|null $account
     * @return bool may export rows
     */
    public function canExport(?Account $account): bool
    {
        return $this->canView($account);
    }

    /**
     * @return Account|null the account the console commands run as (default: the application's account for CLI
     *         operations, `CLI_DEFAULT_ACCOUNT_ID_FOR_CLI_OPERATIONS`); it must pass {@see canView()} like any other
     */
    public function findAccountForCliOperations(): ?Account
    {
        return DDDService::instance()->getDefaultAccountForCliOperations();
    }

    /**
     * @return Account the authenticated account, which may view site insights
     * @throws ForbiddenException
     */
    public function requireViewer(): Account
    {
        $authAccount = AuthService::instance()->getAccount();
        if (!$authAccount || !$this->canView($authAccount)) {
            throw new ForbiddenException('This account may not use site insights');
        }
        return $authAccount;
    }

    /**
     * @return Account the authenticated account, which may inspect URLs (counted against the inspection quota)
     * @throws ForbiddenException
     */
    public function requireInspector(): Account
    {
        $authAccount = $this->requireViewer();
        if (!$this->canInspect($authAccount)) {
            throw new ForbiddenException('This account may not inspect URLs');
        }
        return $authAccount;
    }
}
