<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Support;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * The network of a test that answers each call by its request body (the calls of a batch arrive in any order):
 * {@see $answerForBody} receives the decoded JSON body and the request and returns Google's answer.
 */
class AnsweringNetwork
{
    public CapturingNetwork $capturingNetwork;

    /** @var callable fn(array $requestBody, RequestInterface $request): Response */
    public $answerForBody;

    public static function install(callable $answerForBody): self
    {
        $answeringNetwork = new self();
        $answeringNetwork->answerForBody = $answerForBody;
        $answeringNetwork->capturingNetwork = CapturingNetwork::installAnsweringBy(
            static fn (RequestInterface $request): Response => ($answeringNetwork->answerForBody)((array)json_decode((string)$request->getBody(), true), $request)
        );
        return $answeringNetwork;
    }

    /** @return array[] the decoded bodies of every call sent, in order */
    public function getSentBodies(): array
    {
        return array_map(
            static fn (RequestInterface $request): array => (array)json_decode((string)$request->getBody(), true),
            $this->capturingNetwork->getSentRequests()
        );
    }

    /** @return array[] the bodies of data calls (the availability probe left out) */
    public function getSentDataBodies(): array
    {
        return array_values(array_filter(
            $this->getSentBodies(),
            static fn (array $requestBody): bool => !SyntheticSearchAnalytics::isProbe($requestBody)
        ));
    }

    public function countSentRequests(): int
    {
        return $this->capturingNetwork->countSentRequests();
    }
}
