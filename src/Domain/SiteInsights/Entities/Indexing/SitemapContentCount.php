<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Indexing;

use DDD\Domain\Base\Entities\ValueObject;

/** URLs of one content type a sitemap file submits */
class SitemapContentCount extends ValueObject
{
    /** @var string The provider's content type (web, image, video, news, …) */
    public string $type;

    /** @var int URLs of this type submitted in the file */
    public int $submitted = 0;

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic($this->type ?? '');
    }
}
