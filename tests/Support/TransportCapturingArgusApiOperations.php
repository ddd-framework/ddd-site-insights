<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Support;

use DDD\Domain\Base\Repo\Argus\Utils\ArgusApiOperations;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

/**
 * The Argus executor with the network replaced by a queue of answers, every FINAL request kept in
 * {@see $sentRequests}, and a fixed bearer token of the application's default CLI account (no account lookup): what
 * an application with `auth: true` would add to its own calls.
 */
class TransportCapturingArgusApiOperations extends ArgusApiOperations
{
    public const string DEFAULT_ACCOUNT_TOKEN = 'default-account-token';

    public MockHandler $mockHandler;

    /** @var array[] Guzzle history entries: request, response, error, options */
    public array $sentRequests = [];

    public function __construct(mixed ...$queuedAnswers)
    {
        parent::__construct();
        $this->mockHandler = new MockHandler($queuedAnswers);
    }

    protected function createHttpClient(?CurlMultiHandler $curlMultiHandler = null): Client
    {
        $handlerStack = HandlerStack::create($this->mockHandler);
        $handlerStack->push(Middleware::history($this->sentRequests));
        return new Client(['handler' => $handlerStack]);
    }

    protected static function isSendingBearerTokenOfDefaultAccount(): bool
    {
        return true;
    }

    protected static function createBearerTokenOfDefaultAccount(): ?string
    {
        return self::DEFAULT_ACCOUNT_TOKEN;
    }
}
