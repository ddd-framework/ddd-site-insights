<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Config;

use DDD\Domain\Base\Entities\ValueObject;

/** `SiteInsights.credentials`: the entries and the token exchange. Holds names of secrets only, never a secret. */
class SiteInsightsCredentialsConfig extends ValueObject
{
    /** @var SiteInsightsCredentialsEntries The entries by name */
    public SiteInsightsCredentialsEntries $entries;

    /** @var string Google's token endpoint */
    public string $tokenEndpoint = 'https://oauth2.googleapis.com/token';

    /** @var string The scope asked for; must end in `.readonly` */
    public string $scope = 'https://www.googleapis.com/auth/webmasters.readonly';

    /** @var int Lifetime of the signed assertion (Google accepts at most 3600) */
    public int $assertionLifetimeSeconds = 300;

    /** @var int A token is cached for its lifetime minus this margin */
    public int $tokenSafetyMarginSeconds = 300;

    /** @var int Timeout of the token exchange */
    public int $httpTimeoutSeconds = 10;

    /**
     * @param array $configuredSettings the file `SiteInsights.credentials` as configured (wrapped here, never passed on)
     * @return static
     */
    public static function fromConfigSettings(array $configuredSettings): static
    {
        $credentialsConfig = new static();
        $credentialsConfig->entries = new SiteInsightsCredentialsEntries();
        foreach ((array)($configuredSettings['entries'] ?? []) as $credentialsEntryName => $entrySettings) {
            if (!is_array($entrySettings)) {
                continue;
            }
            $siteInsightsCredentialsEntry = new SiteInsightsCredentialsEntry();
            $siteInsightsCredentialsEntry->name = (string)$credentialsEntryName;
            if (is_string($entrySettings['envName'] ?? null) && $entrySettings['envName'] !== '') {
                $siteInsightsCredentialsEntry->envName = $entrySettings['envName'];
            }
            $siteInsightsCredentialsEntry->revision = max(1, (int)($entrySettings['revision'] ?? 1));
            $credentialsConfig->entries->add($siteInsightsCredentialsEntry);
        }
        foreach (['tokenEndpoint', 'scope'] as $stringSettingName) {
            if (isset($configuredSettings[$stringSettingName])) {
                $credentialsConfig->$stringSettingName = (string)$configuredSettings[$stringSettingName];
            }
        }
        foreach (['assertionLifetimeSeconds', 'tokenSafetyMarginSeconds', 'httpTimeoutSeconds'] as $integerSettingName) {
            if (isset($configuredSettings[$integerSettingName])) {
                $credentialsConfig->$integerSettingName = (int)$configuredSettings[$integerSettingName];
            }
        }
        return $credentialsConfig;
    }

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic((string)spl_object_id($this));
    }
}
