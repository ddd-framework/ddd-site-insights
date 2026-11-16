<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Indexing;

use DDD\Domain\Base\Entities\ObjectSet;
use DDD\Domain\SiteInsights\Entities\Search\SearchPartError;

/**
 * The sitemap files of a site's property with their state. Endpoint and command property `sitemapStatuses`. When the
 * list failed, `error` is set and there are no files.
 *
 * @property SitemapStatus[] $elements
 * @method SitemapStatus[] getElements()
 * @method SitemapStatus|null first()
 */
class SitemapStatuses extends ObjectSet
{
    /** @var string Key of the site */
    public string $siteKey;

    /** @var string Provider key, e.g. google-search-console */
    public string $provider;

    /** @var string The property the files belong to */
    public string $property;

    /** @var IndexingCoverage What the list covers */
    public IndexingCoverage $coverage;

    /** @var SearchPartError|null Set instead of files when the list failed */
    public ?SearchPartError $error;
}
