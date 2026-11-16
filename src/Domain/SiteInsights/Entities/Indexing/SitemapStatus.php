<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Indexing;

use DDD\Domain\Base\Entities\ValueObject;
use DDD\Infrastructure\Base\DateTime\DateTime;

/**
 * The state of one sitemap file as the provider reports it. Submitted URLs only: the provider reports no indexed count
 * per file (its `indexed` field is deprecated); which URLs are indexed is answered by URL inspection.
 */
class SitemapStatus extends ValueObject
{
    /** @var string URL of the sitemap file */
    public string $path;

    /** @var string|null The provider's type of the file (sitemap, rssFeed, atomFeed, urlList, …) */
    public ?string $type;

    /** @var bool Whether the file is a sitemap index */
    public bool $isIndex = false;

    /** @var bool Whether the provider has not processed the file yet */
    public bool $isPending = false;

    /** @var DateTime|null When the file was last submitted (unset when it was found, not submitted) */
    public ?DateTime $lastSubmitted;

    /** @var DateTime|null When the provider last downloaded the file */
    public ?DateTime $lastDownloaded;

    /** @var int Warnings the provider reported for the file */
    public int $warnings = 0;

    /** @var int Errors the provider reported for the file */
    public int $errors = 0;

    /** @var int URLs submitted in the file, over every content type */
    public int $submitted = 0;

    /** @var SitemapContentCounts Submitted URLs per content type (empty when the provider reported none, e.g. for an index) */
    public SitemapContentCounts $submittedByType;

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic(hash('sha256', $this->path ?? ''));
    }
}
