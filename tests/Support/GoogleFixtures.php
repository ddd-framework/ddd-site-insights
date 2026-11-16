<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Support;

use GuzzleHttp\Psr7\Response;
use RuntimeException;

/**
 * Hand-written answers in Google's documented shape with invented hosts, queries and numbers (`tests/Fixtures/Google`).
 */
class GoogleFixtures
{
    public static function read(string $fixtureName): string
    {
        $fixturePath = dirname(__DIR__) . '/Fixtures/Google/' . $fixtureName . '.json';
        $fixtureContent = file_get_contents($fixturePath);
        if ($fixtureContent === false) {
            throw new RuntimeException('Missing fixture ' . $fixtureName);
        }
        return $fixtureContent;
    }

    /**
     * @param string $fixtureName
     * @param int $statusCode
     * @return Response the fixture as Google answers it (JSON with Content-Length)
     */
    public static function response(string $fixtureName, int $statusCode = 200): Response
    {
        $fixtureContent = self::read($fixtureName);
        return new Response($statusCode, ['Content-Type' => 'application/json; charset=UTF-8', 'Content-Length' => (string)strlen($fixtureContent)], $fixtureContent);
    }

    /**
     * @param int $bytes
     * @return Response a chunked answer (no Content-Length) larger than `$bytes`
     */
    public static function oversizeChunkedResponse(int $bytes): Response
    {
        $rowJson = '{"keys":["alpha widget oversize"],"clicks":1,"impressions":2,"ctr":0.5,"position":3.0}';
        $rows = str_repeat($rowJson . ',', (int)ceil($bytes / (strlen($rowJson) + 1)) + 1);
        return new Response(200, ['Content-Type' => 'application/json; charset=UTF-8', 'Transfer-Encoding' => 'chunked'], '{"rows":[' . rtrim($rows, ',') . ']}');
    }
}
