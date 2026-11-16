<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Indexing;

use DDD\Domain\Base\Entities\ObjectSet;

/**
 * Submitted URLs of a sitemap file per content type.
 *
 * @property SitemapContentCount[] $elements
 * @method SitemapContentCount[] getElements()
 * @method SitemapContentCount|null first()
 */
class SitemapContentCounts extends ObjectSet
{
}
