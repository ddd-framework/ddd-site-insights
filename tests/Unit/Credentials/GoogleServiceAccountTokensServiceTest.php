<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Unit\Credentials;

use DDD\Domain\SiteInsights\Credentials\GoogleAccessTokenGrant;
use DDD\Domain\SiteInsights\Credentials\GoogleServiceAccountKey;
use DDD\Domain\SiteInsights\Entities\Errors\SiteInsightsErrorCause;
use DDD\Domain\SiteInsights\Exceptions\SiteInsightsProviderUnavailableException;
use DDD\Domain\SiteInsights\Services\GoogleServiceAccountTokensService;
use DDD\Tests\SiteInsights\Support\FixedTokenCredentialsProviderService;
use DDD\Tests\SiteInsights\Support\GoogleFixtures;
use DDD\Tests\SiteInsights\Support\MockedTokenEndpointTokensService;
use DDD\Tests\SiteInsights\Support\SiteInsightsTestCase;
use DDD\Tests\SiteInsights\Support\SyntheticServiceAccountKey;
use DDD\Tests\SiteInsights\Support\TestConfig;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use LogicException;

/**
 * The token exchange outside Argus with a key generated at test time: assertion claims, the one form POST, the APC
 * cache with its lifetime, the process memory without APCu, and error mapping without secrets in any message.
 */
class GoogleServiceAccountTokensServiceTest extends SiteInsightsTestCase
{
    public function testAssertionClaims(): void
    {
        $tokensService = new MockedTokenEndpointTokensService(GoogleFixtures::response('token-ok'));
        $beforeExchange = time();

        self::assertSame(FixedTokenCredentialsProviderService::TOKEN_MARKER, $tokensService->getAccessToken(TestConfig::CREDENTIALS_ENTRY_NAME));

        $assertion = $tokensService->getLastAssertion();
        $claims = (array)JWT::decode($assertion, new Key(SyntheticServiceAccountKey::get()->publicKeyPem, 'RS256'));
        $header = json_decode((string)base64_decode(strtr(explode('.', $assertion)[0], '-_', '+/')), true);
        self::assertSame(SyntheticServiceAccountKey::CLIENT_EMAIL, $claims['iss']);
        self::assertSame('https://www.googleapis.com/auth/webmasters.readonly', $claims['scope']);
        self::assertSame('https://oauth2.googleapis.com/token', $claims['aud']);
        self::assertLessThanOrEqual(300, $claims['exp'] - $claims['iat']);
        self::assertGreaterThanOrEqual($beforeExchange - 31, $claims['iat']);
        self::assertLessThanOrEqual($beforeExchange + 300, $claims['exp']);
        self::assertArrayNotHasKey('sub', $claims, 'no Workspace delegation');
        self::assertSame(SyntheticServiceAccountKey::PRIVATE_KEY_ID, $header['kid']);
        self::assertSame('RS256', $header['alg']);
    }

    public function testTokenEndpointRequest(): void
    {
        $tokensService = new MockedTokenEndpointTokensService(GoogleFixtures::response('token-ok'));

        $tokensService->getAccessToken(TestConfig::CREDENTIALS_ENTRY_NAME);

        self::assertCount(1, $tokensService->sentRequests);
        $sentRequest = $tokensService->sentRequests[0]['request'];
        $sentOptions = $tokensService->sentRequests[0]['options'];
        self::assertSame('POST', $sentRequest->getMethod());
        self::assertSame('https://oauth2.googleapis.com/token', (string)$sentRequest->getUri());
        self::assertStringStartsWith('application/x-www-form-urlencoded', $sentRequest->getHeaderLine('Content-Type'));
        parse_str((string)$sentRequest->getBody(), $formFields);
        self::assertSame(['grant_type', 'assertion'], array_keys($formFields));
        self::assertSame(GoogleServiceAccountTokensService::GRANT_TYPE, $formFields['grant_type']);
        self::assertFalse($sentOptions['allow_redirects']);
        self::assertFalse($sentOptions['http_errors']);
        self::assertEquals(10, $sentOptions['timeout']);
        self::assertEquals(5, $sentOptions['connect_timeout']);
        self::assertFalse($sentRequest->hasHeader('Authorization'));
    }

    public function testTokenIsCachedInApcOnlyWithLifetime(): void
    {
        $tokensService = new MockedTokenEndpointTokensService(GoogleFixtures::response('token-ok'));
        $tokensService->tokenCache = $this->getApcCache();

        $tokensService->getAccessToken(TestConfig::CREDENTIALS_ENTRY_NAME);
        $secondToken = $tokensService->getAccessToken(TestConfig::CREDENTIALS_ENTRY_NAME);

        self::assertSame(FixedTokenCredentialsProviderService::TOKEN_MARKER, $secondToken);
        self::assertCount(1, $tokensService->sentRequests, 'the second call is answered from the APC cache');
        self::assertCount(1, $this->getApcCache()->valuesByKey);
        $cacheKey = array_key_first($this->getApcCache()->valuesByKey);
        self::assertStringStartsWith(GoogleServiceAccountTokensService::CACHE_KEY_PREFIX, $cacheKey);
        self::assertSame(3599 - 300, $this->getApcCache()->ttlsByKey[$cacheKey]);
        self::assertSame([], $this->getRedisCache()->valuesByKey, 'never Redis');

        $shortLivedTokensService = new MockedTokenEndpointTokensService(GoogleFixtures::response('token-ok-short'), GoogleFixtures::response('token-ok-short'));
        $shortLivedTokensService->tokenCache = $this->getRedisCache();
        $shortLivedTokensService->getAccessToken(TestConfig::CREDENTIALS_ENTRY_NAME);
        $shortLivedTokensService->getAccessToken(TestConfig::CREDENTIALS_ENTRY_NAME);
        self::assertSame([], $this->getRedisCache()->valuesByKey, 'a token living 300 s is not cached');
        self::assertCount(2, $shortLivedTokensService->sentRequests);
    }

    public function testNoCacheWithoutApcu(): void
    {
        $tokensService = new MockedTokenEndpointTokensService(GoogleFixtures::response('token-ok'));

        $tokensService->getAccessToken(TestConfig::CREDENTIALS_ENTRY_NAME);
        $tokensService->getAccessToken(TestConfig::CREDENTIALS_ENTRY_NAME);
        self::assertCount(1, $tokensService->sentRequests, 'within one process the token is kept in memory');

        $nextProcessTokensService = new MockedTokenEndpointTokensService(GoogleFixtures::response('token-ok'));
        $nextProcessTokensService->getAccessToken(TestConfig::CREDENTIALS_ENTRY_NAME);
        self::assertCount(1, $nextProcessTokensService->sentRequests, 'another process exchanges again');
        self::assertSame([], $this->getApcCache()->valuesByKey);
        self::assertSame([], $this->getRedisCache()->valuesByKey);
    }

    public function testForgetAccessTokenExchangesAgain(): void
    {
        $tokensService = new MockedTokenEndpointTokensService(GoogleFixtures::response('token-ok'), GoogleFixtures::response('token-ok'));
        $tokensService->tokenCache = $this->getApcCache();

        $tokensService->getAccessToken(TestConfig::CREDENTIALS_ENTRY_NAME);
        $tokensService->forgetAccessToken(TestConfig::CREDENTIALS_ENTRY_NAME);
        self::assertSame([], $this->getApcCache()->valuesByKey);
        $tokensService->getAccessToken(TestConfig::CREDENTIALS_ENTRY_NAME);

        self::assertCount(2, $tokensService->sentRequests);
    }

    /** @return array[] answer or key manipulation, expected cause */
    public static function provideErrors(): array
    {
        return [
            'invalid grant' => ['answer', 'token-invalid-grant', 400, SiteInsightsErrorCause::TOKEN_REFUSED],
            'service unavailable' => ['answer', null, 503, SiteInsightsErrorCause::TOKEN_ENDPOINT_FAILED],
            '200 without token' => ['answer', 'searchanalytics-empty', 200, SiteInsightsErrorCause::TOKEN_ENDPOINT_FAILED],
            'connection failure' => ['connect', null, 0, SiteInsightsErrorCause::TOKEN_ENDPOINT_FAILED],
            'garbage base64' => ['key', '%%% not base64 %%%', 0, SiteInsightsErrorCause::KEY_UNREADABLE],
            'json without private_key' => ['key', 'withoutPrivateKey', 0, SiteInsightsErrorCause::KEY_UNREADABLE],
            'type user' => ['key', 'typeUser', 0, SiteInsightsErrorCause::KEY_UNREADABLE],
            'unreadable private key' => ['key', 'brokenPrivateKey', 0, SiteInsightsErrorCause::KEY_UNREADABLE],
            'variable not set' => ['key', 'unset', 0, SiteInsightsErrorCause::KEY_UNREADABLE],
        ];
    }

    /** @dataProvider provideErrors */
    public function testErrorMapping(string $kind, ?string $detail, int $statusCode, SiteInsightsErrorCause $expectedCause): void
    {
        $syntheticServiceAccountKey = SyntheticServiceAccountKey::get();
        $keyFields = json_decode((string)base64_decode($syntheticServiceAccountKey->encodedKeyFile), true);
        $tokensService = match ($kind) {
            'answer' => new MockedTokenEndpointTokensService(new Response($statusCode, [], $detail !== null ? GoogleFixtures::read($detail) : '<html>unavailable</html>')),
            'connect' => new MockedTokenEndpointTokensService(new ConnectException(
                'cURL error 7 while posting assertion=' . FixedTokenCredentialsProviderService::TOKEN_MARKER,
                new Request('POST', 'https://oauth2.googleapis.com/token')
            )),
            default => new MockedTokenEndpointTokensService(),
        };
        if ($kind === 'key') {
            $_ENV[TestConfig::KEY_ENVIRONMENT_VARIABLE] = match ($detail) {
                'withoutPrivateKey' => SyntheticServiceAccountKey::encodeKeyFile(array_diff_key($keyFields, ['private_key' => true])),
                'typeUser' => SyntheticServiceAccountKey::encodeKeyFile(['type' => 'authorized_user'] + $keyFields),
                'brokenPrivateKey' => SyntheticServiceAccountKey::encodeKeyFile(['private_key' => "-----BEGIN PRIVATE KEY-----\nAAAA\n-----END PRIVATE KEY-----\n"] + $keyFields),
                'unset' => '',
                default => $detail,
            };
        }
        $thrownException = null;
        try {
            $tokensService->getAccessToken(TestConfig::CREDENTIALS_ENTRY_NAME);
        } catch (SiteInsightsProviderUnavailableException $siteInsightsProviderUnavailableException) {
            $thrownException = $siteInsightsProviderUnavailableException;
        }

        self::assertNotNull($thrownException);
        self::assertSame($expectedCause, $thrownException->getCause());
        self::assertSame(503, $thrownException->getCode());
        $exceptionText = $thrownException->getMessage() . ' ' . json_encode($thrownException->toObject());
        self::assertStringContainsString(TestConfig::CREDENTIALS_ENTRY_NAME, $exceptionText);
        self::assertStringNotContainsString(FixedTokenCredentialsProviderService::TOKEN_MARKER, $exceptionText);
        self::assertStringNotContainsString(SyntheticServiceAccountKey::CLIENT_EMAIL, $exceptionText);
        foreach ($syntheticServiceAccountKey->getPrivateKeyMarkerLines() as $privateKeyLine) {
            self::assertStringNotContainsString($privateKeyLine, $exceptionText);
        }
        if ($tokensService->sentRequests) {
            self::assertStringNotContainsString($tokensService->getLastAssertion(), $exceptionText);
        }
        if ($detail === 'token-invalid-grant') {
            self::assertStringContainsString('invalid_grant', $thrownException->getMessage());
            self::assertStringContainsString('Invalid JWT Signature.', $thrownException->getMessage());
        }
    }

    public function testKeyAndGrantHoldersAreNeverSerialised(): void
    {
        $googleServiceAccountKey = GoogleServiceAccountKey::create(SyntheticServiceAccountKey::CLIENT_EMAIL, SyntheticServiceAccountKey::get()->privateKeyPem, SyntheticServiceAccountKey::PRIVATE_KEY_ID);
        $googleAccessTokenGrant = GoogleAccessTokenGrant::create(FixedTokenCredentialsProviderService::TOKEN_MARKER, 3599);

        self::assertStringNotContainsString(SyntheticServiceAccountKey::CLIENT_EMAIL, print_r($googleServiceAccountKey, true));
        self::assertStringNotContainsString(FixedTokenCredentialsProviderService::TOKEN_MARKER, print_r($googleAccessTokenGrant, true));
        $this->expectException(LogicException::class);
        serialize($googleServiceAccountKey);
    }

    public function testAScopeThatIsNotReadOnlyIsRefused(): void
    {
        TestConfig::apply(['credentials' => ['scope' => 'https://www.googleapis.com/auth/webmasters']]);
        $tokensService = new MockedTokenEndpointTokensService(GoogleFixtures::response('token-ok'));

        $this->expectException(SiteInsightsProviderUnavailableException::class);
        $tokensService->getAccessToken(TestConfig::CREDENTIALS_ENTRY_NAME);
    }
}
