<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Indexing;

use DDD\Domain\Base\Entities\ValueObject;
use DDD\Domain\SiteInsights\Entities\Search\SearchPartError;
use DDD\Infrastructure\Base\DateTime\DateTime;

/**
 * The index state of one URL as the provider reports it now (no history). When the URL could not be inspected,
 * `error` is set and the state fields are unset.
 */
class UrlIndexInspection extends ValueObject
{
    /** @var string The URL as asked */
    public string $url;

    /** @var string|null PASS, PARTIAL, FAIL, NEUTRAL or VERDICT_UNSPECIFIED */
    public ?string $verdict;

    /** @var string|null The provider's coverage state text, e.g. "Submitted and indexed" */
    public ?string $coverageState;

    /** @var string|null INDEXING_ALLOWED, BLOCKED_BY_META_TAG, BLOCKED_BY_HTTP_HEADER, BLOCKED_BY_ROBOTS_TXT, … */
    public ?string $indexingState;

    /** @var string|null ALLOWED, DISALLOWED or ROBOTS_TXT_STATE_UNSPECIFIED */
    public ?string $robotsTxtState;

    /** @var string|null SUCCESSFUL, SOFT_404, BLOCKED_ROBOTS_TXT, NOT_FOUND, ACCESS_DENIED, SERVER_ERROR, … */
    public ?string $pageFetchState;

    /** @var DateTime|null When the provider last crawled the URL */
    public ?DateTime $lastCrawlTime;

    /** @var string|null MOBILE or DESKTOP: the user agent of the last crawl */
    public ?string $crawledAs;

    /** @var string|null The canonical the provider chose */
    public ?string $googleCanonical;

    /** @var string|null The canonical the page declares */
    public ?string $userCanonical;

    /** @var string[] Sitemap files that list the URL, as the provider knows them */
    public array $sitemaps = [];

    /** @var string[] URLs the provider knows linking to the URL */
    public array $referringUrls = [];

    /** @var string|null Link to the inspection in Search Console */
    public ?string $inspectionResultLink;

    /** @var DateTime|null When the provider answered this inspection */
    public ?DateTime $inspectedAt;

    /** @var bool Whether the answer came from the cache (no inspection was counted for it) */
    public bool $fromCache = false;

    /** @var SearchPartError|null Set instead of the state when this URL failed */
    public ?SearchPartError $error;

    /**
     * @param string $url
     * @param SearchPartError $searchPartError
     * @return static an inspection that failed
     */
    public static function createFailed(string $url, SearchPartError $searchPartError): static
    {
        $urlIndexInspection = new static();
        $urlIndexInspection->url = $url;
        $urlIndexInspection->error = $searchPartError;
        return $urlIndexInspection;
    }

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic(hash('sha256', $this->url ?? ''));
    }
}
