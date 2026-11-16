<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Services;

use DDD\Domain\SiteInsights\Credentials\GoogleAccessTokenGrant;
use DDD\Domain\SiteInsights\Credentials\GoogleServiceAccountKey;
use DDD\Domain\SiteInsights\Entities\Errors\SiteInsightsErrorCause;
use DDD\Domain\SiteInsights\Entities\Google\GoogleApiFailure;
use DDD\Domain\SiteInsights\Exceptions\SiteInsightsProviderUnavailableException;
use DDD\Infrastructure\Cache\Cache;
use DDD\Infrastructure\Libs\Config;
use DDD\Infrastructure\Services\DDDService;
use DDD\Infrastructure\Services\Service;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

/**
 * Exchanges a Google service-account key for an access token (OAuth 2.0 JWT bearer grant). Outside Argus: its own
 * HTTP client, so neither the signed assertion nor the token reaches a transport recording, the call log or a cache
 * entry of an entity. The token lives in the per-pod APCu memory when APCu works in this SAPI, else in this process
 * only (a console run without `apc.enable_cli` exchanges once per run).
 */
class GoogleServiceAccountTokensService extends Service
{
    public const string GRANT_TYPE = 'urn:ietf:params:oauth:grant-type:jwt-bearer';
    public const string CACHE_KEY_PREFIX = 'siteInsights.googleToken.';
    public const string READ_ONLY_SCOPE_SUFFIX = '.readonly';
    public const int CLOCK_SKEW_SECONDS = 30;
    public const int MAX_ASSERTION_LIFETIME_SECONDS = 3600;
    public const int MIN_CACHED_TOKEN_LIFETIME_SECONDS = 60;
    public const int CONNECT_TIMEOUT_SECONDS = 5;
    public const int MAX_ERROR_DESCRIPTION_LENGTH = 160;

    /**
     * @var array[] Tokens of this process when APCu does not work, by cache key: `accessToken`, `expiresAt` (an
     * implementation-detail cache: never serialised, never returned)
     */
    protected array $processTokensByCacheKey = [];

    /**
     * @param string $credentialsEntryName entry of SiteInsights.credentials
     * @return string an access token valid for at least the safety margin
     * @throws SiteInsightsProviderUnavailableException cause keyUnreadable | tokenRefused | tokenEndpointFailed
     */
    public function getAccessToken(string $credentialsEntryName): string
    {
        $serviceAccountKey = $this->readServiceAccountKey($credentialsEntryName);
        $scope = $this->getScope($credentialsEntryName);
        $cacheKey = $this->buildCacheKey($serviceAccountKey, $scope, $this->getCredentialsRevision($credentialsEntryName));
        $tokenCache = $this->getTokenCache();
        if ($tokenCache !== null) {
            $cachedAccessToken = $tokenCache->get($cacheKey);
            if (is_string($cachedAccessToken) && $cachedAccessToken !== '') {
                return $cachedAccessToken;
            }
        } elseif (isset($this->processTokensByCacheKey[$cacheKey]) && $this->processTokensByCacheKey[$cacheKey]['expiresAt'] > time()) {
            return $this->processTokensByCacheKey[$cacheKey]['accessToken'];
        }
        $accessTokenGrant = $this->exchangeAssertion(
            $this->buildAssertion($serviceAccountKey, $scope, time() - self::CLOCK_SKEW_SECONDS),
            $credentialsEntryName
        );
        $cacheLifetimeSeconds = $accessTokenGrant->getExpiresInSeconds() - $this->getTokenSafetyMarginSeconds();
        if ($cacheLifetimeSeconds >= self::MIN_CACHED_TOKEN_LIFETIME_SECONDS) {
            if ($tokenCache !== null) {
                $tokenCache->set($cacheKey, $accessTokenGrant->getAccessToken(), $cacheLifetimeSeconds);
            } else {
                $this->processTokensByCacheKey[$cacheKey] = [
                    'accessToken' => $accessTokenGrant->getAccessToken(),
                    'expiresAt' => time() + $cacheLifetimeSeconds,
                ];
            }
        }
        return $accessTokenGrant->getAccessToken();
    }

    /**
     * Drops the cached token of an entry (after a 401 from a data call); the next call exchanges again.
     *
     * @param string $credentialsEntryName
     * @return void
     * @throws SiteInsightsProviderUnavailableException cause keyUnreadable
     */
    public function forgetAccessToken(string $credentialsEntryName): void
    {
        $cacheKey = $this->buildCacheKey(
            $this->readServiceAccountKey($credentialsEntryName),
            $this->getScope($credentialsEntryName),
            $this->getCredentialsRevision($credentialsEntryName)
        );
        unset($this->processTokensByCacheKey[$cacheKey]);
        $this->getTokenCache()?->delete($cacheKey);
    }

    /**
     * @param string $credentialsEntryName
     * @return void
     * @throws SiteInsightsProviderUnavailableException cause keyUnreadable, naming the check that failed
     */
    public function assertServiceAccountKeyReadable(string $credentialsEntryName): void
    {
        $this->readServiceAccountKey($credentialsEntryName);
        $this->getScope($credentialsEntryName);
    }

    /**
     * Reads the key from the environment variable the credentials entry names: base64 (strict) of the JSON key file
     * of a service account.
     *
     * @param string $credentialsEntryName
     * @return GoogleServiceAccountKey
     * @throws SiteInsightsProviderUnavailableException cause keyUnreadable (entry name and failed check, never a part of the value)
     */
    protected function readServiceAccountKey(string $credentialsEntryName): GoogleServiceAccountKey
    {
        $credentialsEntry = $this->getCredentialsEntry($credentialsEntryName);
        $environmentVariableName = $credentialsEntry['envName'] ?? null;
        if (!is_string($environmentVariableName) || $environmentVariableName === '') {
            throw $this->createKeyUnreadableException($credentialsEntryName, 'the entry names no environment variable (envName)');
        }
        $encodedKey = Config::getEnv($environmentVariableName);
        if (!is_string($encodedKey) || trim($encodedKey) === '') {
            throw $this->createKeyUnreadableException($credentialsEntryName, 'its environment variable is not set');
        }
        $decodedKey = base64_decode(trim($encodedKey), true);
        if ($decodedKey === false) {
            throw $this->createKeyUnreadableException($credentialsEntryName, 'the value is not base64');
        }
        $keyFields = json_decode($decodedKey, true);
        if (!is_array($keyFields)) {
            throw $this->createKeyUnreadableException($credentialsEntryName, 'the decoded value is not JSON');
        }
        if (($keyFields['type'] ?? null) !== 'service_account') {
            throw $this->createKeyUnreadableException($credentialsEntryName, 'the key is not of type service_account');
        }
        foreach (['client_email', 'private_key', 'private_key_id'] as $requiredField) {
            if (!is_string($keyFields[$requiredField] ?? null) || $keyFields[$requiredField] === '') {
                throw $this->createKeyUnreadableException($credentialsEntryName, 'the key has no ' . $requiredField);
            }
        }
        if (openssl_pkey_get_private($keyFields['private_key']) === false) {
            while (openssl_error_string() !== false) {
                // the OpenSSL error queue is emptied, its texts are not used
            }
            throw $this->createKeyUnreadableException($credentialsEntryName, 'the private key cannot be read');
        }
        return GoogleServiceAccountKey::create($keyFields['client_email'], $keyFields['private_key'], $keyFields['private_key_id']);
    }

    /**
     * RS256 JWT: header kid = private_key_id; claims iss, scope, aud (the token endpoint), iat, exp. No sub: no
     * Workspace delegation.
     *
     * @param GoogleServiceAccountKey $serviceAccountKey
     * @param string $scope
     * @param int $issuedAt
     * @return string
     */
    protected function buildAssertion(#[\SensitiveParameter] GoogleServiceAccountKey $serviceAccountKey, string $scope, int $issuedAt): string
    {
        $assertionLifetimeSeconds = min(
            self::MAX_ASSERTION_LIFETIME_SECONDS,
            max(60, (int)$this->getCredentialsConfig()['assertionLifetimeSeconds'])
        );
        return JWT::encode(
            [
                'iss' => $serviceAccountKey->getClientEmail(),
                'scope' => $scope,
                'aud' => $this->getTokenEndpoint(),
                'iat' => $issuedAt,
                'exp' => $issuedAt + $assertionLifetimeSeconds,
            ],
            $serviceAccountKey->getPrivateKey(),
            'RS256',
            $serviceAccountKey->getPrivateKeyId()
        );
    }

    /**
     * One form-encoded POST to the token endpoint; never throws with response content or request data in the message.
     *
     * @param string $assertion
     * @param string $credentialsEntryName named in error messages
     * @return GoogleAccessTokenGrant
     * @throws SiteInsightsProviderUnavailableException cause tokenRefused | tokenEndpointFailed
     */
    protected function exchangeAssertion(#[\SensitiveParameter] string $assertion, string $credentialsEntryName = ''): GoogleAccessTokenGrant
    {
        try {
            $response = $this->createHttpClient()->post($this->getTokenEndpoint(), [
                'form_params' => ['grant_type' => self::GRANT_TYPE, 'assertion' => $assertion],
                'http_errors' => false,
                'allow_redirects' => false,
                'timeout' => (float)$this->getCredentialsConfig()['httpTimeoutSeconds'],
                'connect_timeout' => self::CONNECT_TIMEOUT_SECONDS,
                'headers' => ['Accept' => 'application/json'],
            ]);
        } catch (GuzzleException $guzzleException) {
            // the exception's message can carry request data: only its class is named
            throw SiteInsightsProviderUnavailableException::forCause(
                SiteInsightsErrorCause::TOKEN_ENDPOINT_FAILED,
                'Credentials entry "' . $credentialsEntryName . '": the token endpoint failed (' . $guzzleException::class . ')'
            );
        }
        return $this->readTokenResponse($response, $credentialsEntryName);
    }

    /**
     * @param ResponseInterface $response
     * @param string $credentialsEntryName
     * @return GoogleAccessTokenGrant
     * @throws SiteInsightsProviderUnavailableException
     */
    protected function readTokenResponse(#[\SensitiveParameter] ResponseInterface $response, string $credentialsEntryName): GoogleAccessTokenGrant
    {
        $statusCode = $response->getStatusCode();
        $responseFields = json_decode((string)$response->getBody(), true);
        if ($statusCode === 200 && is_array($responseFields)
            && is_string($responseFields['access_token'] ?? null) && $responseFields['access_token'] !== ''
            && strcasecmp((string)($responseFields['token_type'] ?? ''), 'Bearer') === 0
            && is_int($responseFields['expires_in'] ?? null)
        ) {
            return GoogleAccessTokenGrant::create($responseFields['access_token'], $responseFields['expires_in']);
        }
        if (($statusCode === 400 || $statusCode === 401) && is_array($responseFields) && is_string($responseFields['error'] ?? null)) {
            $errorDescription = is_string($responseFields['error_description'] ?? null)
                ? ': ' . mb_substr(GoogleApiFailure::shortenMessage($responseFields['error_description']), 0, self::MAX_ERROR_DESCRIPTION_LENGTH)
                : '';
            throw SiteInsightsProviderUnavailableException::forCause(
                SiteInsightsErrorCause::TOKEN_REFUSED,
                'Credentials entry "' . $credentialsEntryName . '": the token endpoint refused the key ('
                . mb_substr($responseFields['error'], 0, 40) . $errorDescription . ')'
            );
        }
        throw SiteInsightsProviderUnavailableException::forCause(
            SiteInsightsErrorCause::TOKEN_ENDPOINT_FAILED,
            'Credentials entry "' . $credentialsEntryName . '": the token endpoint answered http ' . $statusCode . ' without a usable token'
        );
    }

    /** Seam for tests: the HTTP client of the exchange (no middleware, no debug, no logging handler) */
    protected function createHttpClient(): Client
    {
        return new Client();
    }

    /**
     * Seam for tests and SAPIs without APCu: the APC cache group when APCu works here, else null (no shared token cache).
     * Never Redis, never an entity.
     */
    protected function getTokenCache(): ?Cache
    {
        if (function_exists('apcu_enabled') && apcu_enabled()) {
            return Cache::instance(Cache::CACHE_GROUP_APC);
        }
        return null;
    }

    /**
     * @param GoogleServiceAccountKey $serviceAccountKey
     * @param string $scope
     * @param int $credentialsRevision
     * @return string
     */
    protected function buildCacheKey(GoogleServiceAccountKey $serviceAccountKey, string $scope, int $credentialsRevision): string
    {
        return self::CACHE_KEY_PREFIX . hash(
            'sha256',
            $serviceAccountKey->getClientEmail() . '|' . $scope . '|' . $serviceAccountKey->getPrivateKeyId() . '|' . $credentialsRevision
        );
    }

    /**
     * @param string $credentialsEntryName
     * @return string the configured scope
     * @throws SiteInsightsProviderUnavailableException cause keyUnreadable when the scope is not read-only
     */
    protected function getScope(string $credentialsEntryName): string
    {
        $scope = (string)$this->getCredentialsConfig()['scope'];
        if (!str_ends_with($scope, self::READ_ONLY_SCOPE_SUFFIX)) {
            throw $this->createKeyUnreadableException($credentialsEntryName, 'the configured scope is not read-only');
        }
        return $scope;
    }

    /**
     * @param string $credentialsEntryName
     * @return array the entry: envName, revision
     * @throws SiteInsightsProviderUnavailableException cause keyUnreadable when the entry is not configured
     */
    protected function getCredentialsEntry(string $credentialsEntryName): array
    {
        $credentialsEntry = $this->getCredentialsConfig()['entries'][$credentialsEntryName] ?? null;
        if (!is_array($credentialsEntry)) {
            throw $this->createKeyUnreadableException($credentialsEntryName, 'the entry is not configured');
        }
        return $credentialsEntry;
    }

    /**
     * @param string $credentialsEntryName
     * @return int
     * @throws SiteInsightsProviderUnavailableException
     */
    protected function getCredentialsRevision(string $credentialsEntryName): int
    {
        return (int)($this->getCredentialsEntry($credentialsEntryName)['revision'] ?? 1);
    }

    protected function getTokenEndpoint(): string
    {
        return (string)$this->getCredentialsConfig()['tokenEndpoint'];
    }

    protected function getTokenSafetyMarginSeconds(): int
    {
        return (int)$this->getCredentialsConfig()['tokenSafetyMarginSeconds'];
    }

    protected function getCredentialsConfig(): array
    {
        /** @var SiteInsightsConfigService $siteInsightsConfigService */
        $siteInsightsConfigService = DDDService::instance()->getService(SiteInsightsConfigService::class);
        return $siteInsightsConfigService->getCredentialsConfig();
    }

    /**
     * @param string $credentialsEntryName
     * @param string $failedCheck
     * @return SiteInsightsProviderUnavailableException
     */
    protected function createKeyUnreadableException(string $credentialsEntryName, string $failedCheck): SiteInsightsProviderUnavailableException
    {
        return SiteInsightsProviderUnavailableException::forCause(
            SiteInsightsErrorCause::KEY_UNREADABLE,
            'Credentials entry "' . $credentialsEntryName . '": ' . $failedCheck
        );
    }
}
