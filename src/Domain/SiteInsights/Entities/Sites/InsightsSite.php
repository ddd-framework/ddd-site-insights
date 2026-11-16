<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Sites;

use DDD\Domain\Base\Entities\ValueObject;

/**
 * One configured site: a Search Console property, the credentials entry that reads it, its brand queries and page
 * groups, and what its provider can answer.
 */
class InsightsSite extends ValueObject
{
    public const string PROPERTY_PREFIX_DOMAIN = 'sc-domain:';

    /** @var string Key of the site (`^[a-z0-9][a-z0-9-]{0,62}$`), used in URLs and commands */
    public string $key;

    /** @var string Label shown in the admin */
    public string $label;

    /** @var string Provider key, e.g. google-search-console */
    public string $provider;

    /** @var string Search Console property: `https://example.test/` (URL prefix) or `sc-domain:example.test` */
    public string $property;

    /** @var string Name of the credentials entry that reads the property; a name, never a secret */
    public string $credentialsEntryName;

    /** @var int Revision of the credentials entry (part of every cache identity) */
    public int $credentialsRevision;

    /** @var string[] RE2 patterns of brand queries */
    public array $brandQueryPatterns = [];

    /** @var InsightsPageGroups Configured page groups */
    public InsightsPageGroups $pageGroups;

    /** @var InsightsProviderCapabilities What the provider can answer */
    public InsightsProviderCapabilities $capabilities;

    /** @return bool whether the property is a domain property (`sc-domain:`) */
    public function isDomainProperty(): bool
    {
        return str_starts_with($this->property, self::PROPERTY_PREFIX_DOMAIN);
    }

    /** @return string the host of a domain property, else the host of the URL prefix */
    public function getPropertyHost(): string
    {
        if ($this->isDomainProperty()) {
            return substr($this->property, strlen(self::PROPERTY_PREFIX_DOMAIN));
        }
        return (string)parse_url($this->property, PHP_URL_HOST);
    }

    /** @return string the root URL of the property: the prefix itself, or `https://<domain>/` */
    public function getPropertyRootUrl(): string
    {
        return $this->isDomainProperty() ? 'https://' . $this->getPropertyHost() . '/' : $this->property;
    }

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic($this->key ?? '');
    }
}
