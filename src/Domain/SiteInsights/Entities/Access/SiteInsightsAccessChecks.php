<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Access;

use DDD\Domain\Base\Entities\ObjectSet;

/**
 * The capability states of one or more sites. Endpoint and command property `siteInsightsAccessChecks`.
 *
 * @property SiteInsightsAccessCheck[] $elements
 * @method SiteInsightsAccessCheck[] getElements()
 * @method SiteInsightsAccessCheck|null first()
 */
class SiteInsightsAccessChecks extends ObjectSet
{
    /** @return bool whether any capability failed */
    public function hasWarnings(): bool
    {
        foreach ($this->getElements() as $siteInsightsAccessCheck) {
            if ($siteInsightsAccessCheck->state === SiteInsightsAccessCheck::STATE_WARNING) {
                return true;
            }
        }
        return false;
    }

    public function hasFailures(): bool
    {
        foreach ($this->getElements() as $siteInsightsAccessCheck) {
            if ($siteInsightsAccessCheck->isFailed()) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $siteKey
     * @param string $capability
     * @return SiteInsightsAccessCheck|null
     */
    public function findCheck(string $siteKey, string $capability): ?SiteInsightsAccessCheck
    {
        foreach ($this->getElements() as $siteInsightsAccessCheck) {
            if ($siteInsightsAccessCheck->siteKey === $siteKey && $siteInsightsAccessCheck->capability === $capability) {
                return $siteInsightsAccessCheck;
            }
        }
        return null;
    }
}
