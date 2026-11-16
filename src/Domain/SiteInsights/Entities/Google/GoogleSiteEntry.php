<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Google;

use DDD\Domain\Base\Entities\ValueObject;

/** One property of Google's sites list (`siteEntry[]`) */
class GoogleSiteEntry extends ValueObject
{
    /** @var string The property as Google names it */
    public string $siteUrl;

    /** @var string siteOwner, siteFullUser, siteRestrictedUser, siteUnverifiedUser */
    public string $permissionLevel;

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic(hash('sha256', $this->siteUrl ?? ''));
    }
}
