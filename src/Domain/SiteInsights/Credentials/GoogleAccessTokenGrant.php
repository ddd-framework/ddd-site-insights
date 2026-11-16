<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Credentials;

use LogicException;

/**
 * An access token the token endpoint granted, with its lifetime. A plain holder like GoogleServiceAccountKey: never
 * serialised, never dumped.
 */
class GoogleAccessTokenGrant
{
    protected string $accessToken;

    protected int $expiresInSeconds;

    /**
     * @param string $accessToken
     * @param int $expiresInSeconds
     * @return static
     */
    public static function create(#[\SensitiveParameter] string $accessToken, int $expiresInSeconds): static
    {
        $googleAccessTokenGrant = new static();
        $googleAccessTokenGrant->accessToken = $accessToken;
        $googleAccessTokenGrant->expiresInSeconds = $expiresInSeconds;
        return $googleAccessTokenGrant;
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function getExpiresInSeconds(): int
    {
        return $this->expiresInSeconds;
    }

    /** @throws LogicException always: a token is never serialised */
    public function __serialize(): array
    {
        throw new LogicException('An access token is never serialised');
    }

    /** @throws LogicException always */
    public function __unserialize(array $data): void
    {
        throw new LogicException('An access token is never unserialised');
    }

    /** @return string[] what a dump shows: no value */
    public function __debugInfo(): array
    {
        return ['redacted' => 'access token'];
    }
}
