<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Support;

use DDD\Domain\SiteInsights\Services\SiteInsightsCredentialsProviderService;

/**
 * Credentials provider of data-call tests: a fixed synthetic token (null or '' to test the builder's refusal),
 * counting how often a token was forgotten.
 */
class FixedTokenCredentialsProviderService extends SiteInsightsCredentialsProviderService
{
    public const string TOKEN_MARKER = 'SYNTHETIC-TOKEN-7f3a';

    public mixed $accessToken = self::TOKEN_MARKER;

    public int $forgottenTokens = 0;

    public function getAccessToken(string $credentialsEntryName): string
    {
        return (string)$this->accessToken;
    }

    public function forgetAccessToken(string $credentialsEntryName): void
    {
        $this->forgottenTokens++;
    }

    public function assertCredentialsReadable(string $credentialsEntryName): void
    {
    }
}
