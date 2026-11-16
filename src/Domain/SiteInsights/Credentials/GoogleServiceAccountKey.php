<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Credentials;

use LogicException;

/**
 * The parts of a Google service-account key the token exchange needs. A plain holder of protected properties: no
 * framework object, so no serializer, cache or log path can reach it; `serialize()` throws and a dump shows no value.
 */
class GoogleServiceAccountKey
{
    protected string $clientEmail;

    protected string $privateKey;

    protected string $privateKeyId;

    /**
     * @param string $clientEmail
     * @param string $privateKey PEM
     * @param string $privateKeyId
     * @return static
     */
    public static function create(
        string $clientEmail,
        #[\SensitiveParameter] string $privateKey,
        string $privateKeyId
    ): static {
        $googleServiceAccountKey = new static();
        $googleServiceAccountKey->clientEmail = $clientEmail;
        $googleServiceAccountKey->privateKey = $privateKey;
        $googleServiceAccountKey->privateKeyId = $privateKeyId;
        return $googleServiceAccountKey;
    }

    public function getClientEmail(): string
    {
        return $this->clientEmail;
    }

    public function getPrivateKey(): string
    {
        return $this->privateKey;
    }

    public function getPrivateKeyId(): string
    {
        return $this->privateKeyId;
    }

    /** @throws LogicException always: a key is never serialised */
    public function __serialize(): array
    {
        throw new LogicException('A service-account key is never serialised');
    }

    /** @throws LogicException always */
    public function __unserialize(array $data): void
    {
        throw new LogicException('A service-account key is never unserialised');
    }

    /** @return string[] what a dump shows: no value */
    public function __debugInfo(): array
    {
        return ['redacted' => 'service-account key'];
    }
}
