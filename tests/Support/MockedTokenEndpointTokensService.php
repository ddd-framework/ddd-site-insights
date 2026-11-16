<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Support;

use DDD\Domain\SiteInsights\Services\GoogleServiceAccountTokensService;
use DDD\Infrastructure\Cache\Cache;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

/**
 * The token service with the token endpoint replaced by a queue of answers and every request kept in
 * {@see $sentRequests}; the token cache is whatever {@see $tokenCache} holds (null: no APCu).
 */
class MockedTokenEndpointTokensService extends GoogleServiceAccountTokensService
{
    public MockHandler $mockHandler;

    /** @var array[] Guzzle history entries: request, response, error, options */
    public array $sentRequests = [];

    public ?Cache $tokenCache = null;

    public function __construct(mixed ...$queuedAnswers)
    {
        $this->mockHandler = new MockHandler($queuedAnswers);
    }

    protected function createHttpClient(): Client
    {
        $handlerStack = HandlerStack::create($this->mockHandler);
        $handlerStack->push(Middleware::history($this->sentRequests));
        return new Client(['handler' => $handlerStack]);
    }

    protected function getTokenCache(): ?Cache
    {
        return $this->tokenCache;
    }

    /** @return string the assertion of the last exchange */
    public function getLastAssertion(): string
    {
        $lastRequest = end($this->sentRequests)['request'];
        parse_str((string)$lastRequest->getBody(), $formFields);
        return (string)($formFields['assertion'] ?? '');
    }
}
