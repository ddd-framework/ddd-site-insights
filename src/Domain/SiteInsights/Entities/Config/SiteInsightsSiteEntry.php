<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Config;

use DDD\Domain\Base\Entities\ValueObject;

/**
 * One entry of `SiteInsights.sites.sites` as written: string settings that are no string stay unset, so the sites
 * provider names them as missing. The semantic checks (key pattern, property form, RE2) are the provider's.
 */
class SiteInsightsSiteEntry extends ValueObject
{
    /** @var string Position in the config, named in errors before the key is known */
    public string $position;

    /** @var string|null Key of the site */
    public ?string $key;

    /** @var string|null Label */
    public ?string $label;

    /** @var string|null Provider key */
    public ?string $provider;

    /** @var string|null Search Console property */
    public ?string $property;

    /** @var string|null Name of the credentials entry */
    public ?string $credentials;

    /** @var string[] RE2 patterns of brand queries */
    public array $brandQueryPatterns = [];

    /** @var SiteInsightsPageGroupEntries The page groups */
    public SiteInsightsPageGroupEntries $pageGroups;

    /** duplicates must survive: the sites provider reports a key configured twice */
    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic((string)spl_object_id($this));
    }
}
