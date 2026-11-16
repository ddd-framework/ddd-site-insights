<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Support;

use DDD\Domain\Base\Repo\Argus\Utils\ArgusApiOperations;
use DDD\Domain\Base\Repo\Argus\Utils\ArgusTransportRecording;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;

/**
 * The network of every Argus call while installed: a transport recording (as a recorded run would use) whose network
 * is a queue of answers; every FINAL request — after Argus merged the headers — is kept in {@see $sentRequests}.
 */
class CapturingNetwork
{
    public MockHandler $mockHandler;

    /** @var array[] Guzzle history entries: request, response, error, options */
    public array $sentRequests = [];

    public ArgusTransportRecording $argusTransportRecording;

    public static function install(mixed ...$queuedAnswers): self
    {
        $capturingNetwork = new self();
        $capturingNetwork->mockHandler = new MockHandler($queuedAnswers);
        $networkHandlerStack = HandlerStack::create($capturingNetwork->mockHandler);
        $networkHandlerStack->push(Middleware::history($capturingNetwork->sentRequests));
        $capturingNetwork->argusTransportRecording = ArgusTransportRecording::forRecording($networkHandlerStack);
        ArgusApiOperations::$transportRecording = $capturingNetwork->argusTransportRecording;
        return $capturingNetwork;
    }

    /** @param mixed ...$answers more answers at the end of the queue */
    public function queue(mixed ...$answers): void
    {
        foreach ($answers as $answer) {
            $this->mockHandler->append($answer);
        }
    }

    /** @return RequestInterface[] */
    public function getSentRequests(): array
    {
        return array_map(static fn (array $sentRequestEntry): RequestInterface => $sentRequestEntry['request'], $this->sentRequests);
    }

    /** @return int calls sent, rejected ones included (as the recording counts them) */
    public function countSentRequests(): int
    {
        return count($this->argusTransportRecording->entries->getElements());
    }

    /** @return string the recording as stored (bodies and endpoints of every call) */
    public function getRecordingAsText(): string
    {
        return (string)json_encode($this->argusTransportRecording->toStorageArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
