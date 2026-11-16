<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Google;

use DDD\Domain\Base\Entities\ObjectSet;

/**
 * The properties a credentials entry can read.
 *
 * @property GoogleSiteEntry[] $elements
 * @method GoogleSiteEntry[] getElements()
 * @method GoogleSiteEntry|null first()
 */
class GoogleSiteEntries extends ObjectSet
{
    /**
     * @param string $siteUrl
     * @return GoogleSiteEntry|null
     */
    public function findBySiteUrl(string $siteUrl): ?GoogleSiteEntry
    {
        foreach ($this->getElements() as $googleSiteEntry) {
            if ($googleSiteEntry->siteUrl === $siteUrl) {
                return $googleSiteEntry;
            }
        }
        return null;
    }
}
