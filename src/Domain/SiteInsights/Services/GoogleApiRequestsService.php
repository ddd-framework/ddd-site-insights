<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Services;

use DDD\Domain\Base\Repo\Argus\Utils\ArgusApiOperation;
use DDD\Domain\SiteInsights\Entities\Errors\SiteInsightsErrorCause;
use DDD\Domain\SiteInsights\Exceptions\SiteInsightsProviderUnavailableException;
use DDD\Infrastructure\Services\DDDService;
use DDD\Infrastructure\Services\Service;

/**
 * The one builder of Google payloads. Every Google call of the module is an Argus EXTERNAL_CALL (none of the
 * application's headers, tracking, debug flags or bearer) on the PRIVATE_TRANSPORT (bounded read, no redirect, not in
 * the call log, redacted in displayed calls and answers); the token travels in the Authorization header only, never in
 * a body or a query. Without a token nothing is built.
 */
class GoogleApiRequestsService extends Service
{
    /**
     * @param string $credentialsEntryName
     * @param array $pathParameters path placeholders => raw values (e.g. the property), URL-encoded here
     * @param array|null $body the JSON body; null for a GET
     * @return array the Argus payload
     * @throws SiteInsightsProviderUnavailableException cause tokenMissing, or the token's own causes
     */
    public function buildPayload(string $credentialsEntryName, array $pathParameters, ?array $body): array
    {
        /** @var SiteInsightsCredentialsProviderService $siteInsightsCredentialsProviderService */
        $siteInsightsCredentialsProviderService = DDDService::instance()->getService(SiteInsightsCredentialsProviderService::class);
        return $this->assemblePayload(
            $siteInsightsCredentialsProviderService->getAccessToken($credentialsEntryName),
            $pathParameters,
            $body
        );
    }

    /**
     * @param mixed $accessToken
     * @param array $pathParameters
     * @param array|null $body
     * @return array
     * @throws SiteInsightsProviderUnavailableException cause tokenMissing when the token is empty or not a string
     */
    protected function assemblePayload(#[\SensitiveParameter] mixed $accessToken, array $pathParameters, ?array $body): array
    {
        if (!is_string($accessToken) || trim($accessToken) === '') {
            throw SiteInsightsProviderUnavailableException::forCause(
                SiteInsightsErrorCause::TOKEN_MISSING,
                'A Google call was to be built without an access token; nothing was sent'
            );
        }
        $payload = [];
        if ($pathParameters) {
            $payload['path'] = array_map(static fn (mixed $value): string => rawurlencode((string)$value), $pathParameters);
        }
        if ($body !== null) {
            $payload['body'] = $body;
        }
        $payload['headers'] = ['Authorization' => 'Bearer ' . $accessToken, 'Accept' => 'application/json'];
        $payload[ArgusApiOperation::EXTERNAL_CALL] = true;
        $payload[ArgusApiOperation::PRIVATE_TRANSPORT] = [
            'response' => ArgusApiOperation::PRIVATE_RESPONSE_FORMAT_JSON,
            'max_response_bytes' => $this->getMaxResponseBytes(),
        ];
        return $payload;
    }

    /** @return int config budgets.maxResponseBytes, within 1 and Argus' limit */
    protected function getMaxResponseBytes(): int
    {
        /** @var SiteInsightsConfigService $siteInsightsConfigService */
        $siteInsightsConfigService = DDDService::instance()->getService(SiteInsightsConfigService::class);
        return max(1, min(
            ArgusApiOperation::PRIVATE_RESPONSE_MAX_BYTES_LIMIT,
            $siteInsightsConfigService->getBudgetsConfig()->maxResponseBytes
        ));
    }
}
