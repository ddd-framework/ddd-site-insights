<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Google;

use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;
use DDD\Domain\SiteInsights\Repo\Argus\Google\ArgusGoogleSite;

/** One property (`GET …/webmasters/v3/sites/{siteUrl}`) and Google's answer */
#[LazyLoadRepo(LazyLoadRepo::ARGUS, ArgusGoogleSite::class)]
class GoogleSite extends GoogleProviderAnswer
{
    public const string RESOURCE = 'site';

    /** @var string Search Console property */
    public string $siteUrl;

    /** @var string|null Google's `permissionLevel` */
    public ?string $permissionLevel;

    protected function buildQuestionParts(): array
    {
        return ['property' => $this->siteUrl];
    }
}
