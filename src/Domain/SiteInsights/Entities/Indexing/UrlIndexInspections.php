<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Indexing;

use DDD\Domain\Base\Entities\ObjectSet;

/**
 * The inspections of a list of URLs of one site, in the order asked (duplicates once). Endpoint and command property
 * `urlIndexInspections`. Each URL carries its state or its own `error`.
 *
 * @property UrlIndexInspection[] $elements
 * @method UrlIndexInspection[] getElements()
 * @method UrlIndexInspection|null first()
 */
class UrlIndexInspections extends ObjectSet
{
    /** @var string Key of the site */
    public string $siteKey;

    /** @var string Provider key, e.g. google-search-console */
    public string $provider;

    /** @var string The property the URLs were inspected in */
    public string $property;

    /** @var string|null Language of the provider's texts, as asked (e.g. en-US) */
    public ?string $languageCode;

    /** @var int URLs given more than once and inspected once */
    public int $duplicateUrlsDropped = 0;

    /** @var string shared, perPod or notCounted: how the inspection counters are kept (see the module skill) */
    public string $inspectionCounterScope;

    /** @var IndexingCoverage What the inspections cover */
    public IndexingCoverage $coverage;

    /**
     * @param string $url
     * @return UrlIndexInspection|null the inspection of the URL as asked
     */
    public function findInspectionByUrl(string $url): ?UrlIndexInspection
    {
        return $this->getByUniqueKey(UrlIndexInspection::uniqueKeyStatic(hash('sha256', $url)));
    }

    /** @return bool whether any URL carries an error */
    public function hasFailures(): bool
    {
        foreach ($this->getElements() as $urlIndexInspection) {
            if (isset($urlIndexInspection->error)) {
                return true;
            }
        }
        return false;
    }
}
