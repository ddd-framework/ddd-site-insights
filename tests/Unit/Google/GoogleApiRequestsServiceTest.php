<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Unit\Google;

use DDD\Domain\Base\Repo\Argus\Utils\ArgusApiOperation;
use DDD\Domain\SiteInsights\Entities\Errors\SiteInsightsErrorCause;
use DDD\Domain\SiteInsights\Exceptions\SiteInsightsProviderUnavailableException;
use DDD\Domain\SiteInsights\Services\GoogleApiRequestsService;
use DDD\Infrastructure\Services\DDDService;
use DDD\Tests\SiteInsights\Support\FixedTokenCredentialsProviderService;
use DDD\Tests\SiteInsights\Support\SiteInsightsTestCase;
use DDD\Tests\SiteInsights\Support\TestConfig;

/** The one builder of Google payloads */
class GoogleApiRequestsServiceTest extends SiteInsightsTestCase
{
    public function testPayloadShape(): void
    {
        $payload = $this->getGoogleApiRequestsService()->buildPayload(TestConfig::CREDENTIALS_ENTRY_NAME, ['siteUrl' => 'https://example.test/'], ['rowLimit' => 1]);

        self::assertTrue($payload[ArgusApiOperation::EXTERNAL_CALL]);
        self::assertSame(['response' => 'json', 'max_response_bytes' => 4000000], $payload[ArgusApiOperation::PRIVATE_TRANSPORT]);
        self::assertSame(['Authorization' => 'Bearer ' . FixedTokenCredentialsProviderService::TOKEN_MARKER, 'Accept' => 'application/json'], $payload['headers']);
        self::assertSame(['siteUrl' => 'https%3A%2F%2Fexample.test%2F'], $payload['path']);
        self::assertSame(['rowLimit' => 1], $payload['body']);
        self::assertStringNotContainsString(FixedTokenCredentialsProviderService::TOKEN_MARKER, json_encode($payload['body']));
        self::assertArrayNotHasKey('query', $payload);

        $domainPayload = $this->getGoogleApiRequestsService()->buildPayload(TestConfig::CREDENTIALS_ENTRY_NAME, ['siteUrl' => 'sc-domain:example.test'], null);
        self::assertSame(['siteUrl' => 'sc-domain%3Aexample.test'], $domainPayload['path']);
        self::assertArrayNotHasKey('body', $domainPayload, 'a GET has no body');
    }

    public function testTheResponseLimitStaysWithinArgusLimit(): void
    {
        TestConfig::apply(['budgets' => ['maxResponseBytes' => 9000000]]);

        $payload = $this->getGoogleApiRequestsService()->buildPayload(TestConfig::CREDENTIALS_ENTRY_NAME, [], null);

        self::assertSame(ArgusApiOperation::PRIVATE_RESPONSE_MAX_BYTES_LIMIT, $payload[ArgusApiOperation::PRIVATE_TRANSPORT]['max_response_bytes']);
    }

    /** @return array[] */
    public static function provideMissingTokens(): array
    {
        return [[''], ['   '], [null]];
    }

    /** @dataProvider provideMissingTokens */
    public function testEmptyTokenThrows(mixed $accessToken): void
    {
        $this->credentialsProviderService->accessToken = $accessToken;
        try {
            $this->getGoogleApiRequestsService()->buildPayload(TestConfig::CREDENTIALS_ENTRY_NAME, ['siteUrl' => 'https://example.test/'], []);
            self::fail('a payload without token was built');
        } catch (SiteInsightsProviderUnavailableException $siteInsightsProviderUnavailableException) {
            self::assertSame(SiteInsightsErrorCause::TOKEN_MISSING, $siteInsightsProviderUnavailableException->getCause());
        }
    }

    protected function getGoogleApiRequestsService(): GoogleApiRequestsService
    {
        return DDDService::instance()->getService(GoogleApiRequestsService::class);
    }
}
