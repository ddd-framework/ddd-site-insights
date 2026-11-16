<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Config;

use DDD\Domain\Base\Entities\ValueObject;

/** One entry of `SiteInsights.credentials.entries`: names, never holds, the secret */
class SiteInsightsCredentialsEntry extends ValueObject
{
    /** @var string Name of the entry (named by a site's `credentials`) */
    public string $name;

    /** @var string|null The environment variable holding the service-account JSON key, base64 in one line */
    public ?string $envName;

    /** @var int Raised when the account behind the entry changes (part of every cache identity) */
    public int $revision = 1;

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic($this->name ?? '');
    }
}
