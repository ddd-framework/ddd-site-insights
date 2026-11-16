<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Support;

use DDD\Domain\Common\Entities\Accounts\Account;
use DDD\Domain\Common\Entities\Roles\Roles;

/** Accounts of tests, built in memory (no database) */
class TestAccounts
{
    public static function admin(): Account
    {
        return self::create(1, true);
    }

    public static function nonAdmin(): Account
    {
        return self::create(2, false);
    }

    protected static function create(int $accountId, bool $isAdmin): Account
    {
        $account = new Account();
        $account->id = $accountId;
        $roles = new Roles();
        $roles->isAdmin = $isAdmin;
        $account->roles = $roles;
        return $account;
    }
}
