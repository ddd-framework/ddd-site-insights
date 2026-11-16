<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Google;

use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;
use DDD\Domain\SiteInsights\Repo\Argus\Google\ArgusGoogleSites;

/** The sites list of a credentials entry (`GET …/webmasters/v3/sites`) and Google's answer */
#[LazyLoadRepo(LazyLoadRepo::ARGUS, ArgusGoogleSites::class)]
class GoogleSites extends GoogleProviderAnswer
{
    public const string RESOURCE = 'sites';

    /** @var GoogleSiteEntries|null Google's `siteEntry`; empty set when Google sent none */
    public ?GoogleSiteEntries $siteEntries;

    protected function buildQuestionParts(): array
    {
        return [];
    }
}
