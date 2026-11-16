<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Config;

use DDD\Domain\Base\Entities\ValueObject;

/** One page group of a configured site, as written in `sites.php` (validated by the sites provider) */
class SiteInsightsPageGroupEntry extends ValueObject
{
    /** @var string|null Key of the group; unset when the entry has none */
    public ?string $key;

    /** @var string|null Label */
    public ?string $label;

    /** @var string[] RE2 patterns on the URL path */
    public array $pathPatterns = [];

    /** duplicates must survive: the sites provider reports a key configured twice */
    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic((string)spl_object_id($this));
    }
}
