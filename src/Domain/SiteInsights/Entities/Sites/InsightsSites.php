<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Sites;

use DDD\Domain\Base\Entities\ObjectSet;

/**
 * The configured sites. Endpoint and command property `insightsSites`.
 *
 * @property InsightsSite[] $elements
 * @method InsightsSite[] getElements()
 * @method InsightsSite|null first()
 * @method InsightsSite|null getByUniqueKey(string $uniqueKey)
 */
class InsightsSites extends ObjectSet
{
    /**
     * @param string $siteKey
     * @return InsightsSite|null
     */
    public function findSiteByKey(string $siteKey): ?InsightsSite
    {
        foreach ($this->getElements() as $insightsSite) {
            if ($insightsSite->key === $siteKey) {
                return $insightsSite;
            }
        }
        return null;
    }
}
