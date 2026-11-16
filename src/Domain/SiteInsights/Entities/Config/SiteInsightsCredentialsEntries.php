<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Config;

use DDD\Domain\Base\Entities\ObjectSet;

/**
 * The credentials entries by name.
 *
 * @property SiteInsightsCredentialsEntry[] $elements
 * @method SiteInsightsCredentialsEntry[] getElements()
 * @method SiteInsightsCredentialsEntry|null first()
 */
class SiteInsightsCredentialsEntries extends ObjectSet
{
    /**
     * @param string $credentialsEntryName
     * @return SiteInsightsCredentialsEntry|null
     */
    public function findEntryByName(string $credentialsEntryName): ?SiteInsightsCredentialsEntry
    {
        foreach ($this->getElements() as $siteInsightsCredentialsEntry) {
            if ($siteInsightsCredentialsEntry->name === $credentialsEntryName) {
                return $siteInsightsCredentialsEntry;
            }
        }
        return null;
    }
}
