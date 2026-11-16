<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Google;

use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;
use DDD\Domain\SiteInsights\Repo\Argus\Google\ArgusGoogleSitemaps;

/** The sitemaps list of a property (`GET …/webmasters/v3/sites/{siteUrl}/sitemaps`) and Google's answer */
#[LazyLoadRepo(LazyLoadRepo::ARGUS, ArgusGoogleSitemaps::class)]
class GoogleSitemaps extends GoogleProviderAnswer
{
    public const string RESOURCE = 'sitemaps';

    /** @var string Search Console property */
    public string $siteUrl;

    /** @var GoogleSitemapEntries|null Google's `sitemap`; empty set when Google sent none */
    public ?GoogleSitemapEntries $sitemaps;

    protected function buildQuestionParts(): array
    {
        return ['property' => $this->siteUrl];
    }
}
