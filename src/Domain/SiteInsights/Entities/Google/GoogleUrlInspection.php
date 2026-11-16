<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Google;

use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;
use DDD\Domain\SiteInsights\Repo\Argus\Google\ArgusGoogleUrlInspection;
use DDD\Infrastructure\Base\DateTime\DateTime;

/**
 * One URL inspection (`POST https://searchconsole.googleapis.com/v1/urlInspection/index:inspect`) and the index
 * state Google answered (`inspectionResult.indexStatusResult`). The current state only, no history.
 */
#[LazyLoadRepo(LazyLoadRepo::ARGUS, ArgusGoogleUrlInspection::class)]
class GoogleUrlInspection extends GoogleProviderAnswer
{
    public const string RESOURCE = 'urlInspection';

    /** @var int 2: crawledAs, sitemaps and referring URLs are read (older cache entries are not read) */
    public const int SCHEMA_VERSION = 2;

    /** @var string Search Console property the URL belongs to */
    public string $siteUrl;

    /** @var string The URL inspected */
    public string $inspectionUrl;

    /** @var string|null Language of Google's texts, e.g. en-US */
    public ?string $languageCode;

    /** @var string|null PASS, PARTIAL, FAIL, NEUTRAL, VERDICT_UNSPECIFIED */
    public ?string $verdict;

    /** @var string|null Google's coverage state text */
    public ?string $coverageState;

    /** @var string|null INDEXING_ALLOWED, BLOCKED_BY_META_TAG, … */
    public ?string $indexingState;

    /** @var string|null ALLOWED, DISALLOWED, … */
    public ?string $robotsTxtState;

    /** @var string|null SUCCESSFUL, SOFT_404, … */
    public ?string $pageFetchState;

    /** @var DateTime|null When Google last crawled the URL */
    public ?DateTime $lastCrawlTime;

    /** @var string|null The canonical Google chose */
    public ?string $googleCanonical;

    /** @var string|null The canonical the page declares */
    public ?string $userCanonical;

    /** @var string|null MOBILE or DESKTOP: the user agent of the last crawl */
    public ?string $crawledAs;

    /** @var string[] Sitemap files that list the URL, as Google knows them */
    public array $sitemaps = [];

    /** @var string[] URLs Google knows linking to the URL */
    public array $referringUrls = [];

    /** @var string|null Link to the inspection in Search Console */
    public ?string $inspectionResultLink;

    protected function buildQuestionParts(): array
    {
        return [
            'property' => $this->siteUrl,
            'inspectionUrl' => $this->inspectionUrl,
            'languageCode' => $this->languageCode ?? null,
        ];
    }

    /** @return array Google's request body (holds no secret) */
    public function buildRequestBody(): array
    {
        $requestBody = ['inspectionUrl' => $this->inspectionUrl, 'siteUrl' => $this->siteUrl];
        if (isset($this->languageCode)) {
            $requestBody['languageCode'] = $this->languageCode;
        }
        return $requestBody;
    }
}
