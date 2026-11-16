<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Support;

use RuntimeException;

/**
 * A service-account key generated at test time (openssl_pkey_new): no key file exists in the repository. One key per
 * test process.
 */
class SyntheticServiceAccountKey
{
    public const string CLIENT_EMAIL = 'reader@example-project.test';
    public const string PRIVATE_KEY_ID = '0000synthetic';

    protected static ?self $instance = null;

    public string $privateKeyPem;

    public string $publicKeyPem;

    /** @var string base64 of the JSON key file, as the environment variable holds it */
    public string $encodedKeyFile;

    public static function get(): self
    {
        if (self::$instance !== null) {
            return self::$instance;
        }
        $privateKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if ($privateKey === false || !openssl_pkey_export($privateKey, $privateKeyPem)) {
            throw new RuntimeException('openssl could not generate the synthetic test key');
        }
        $syntheticServiceAccountKey = new self();
        $syntheticServiceAccountKey->privateKeyPem = $privateKeyPem;
        $syntheticServiceAccountKey->publicKeyPem = openssl_pkey_get_details($privateKey)['key'];
        $syntheticServiceAccountKey->encodedKeyFile = self::encodeKeyFile([
            'type' => 'service_account',
            'project_id' => 'example-project',
            'private_key_id' => self::PRIVATE_KEY_ID,
            'private_key' => $privateKeyPem,
            'client_email' => self::CLIENT_EMAIL,
            'client_id' => '000000000000000000000',
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]);
        self::$instance = $syntheticServiceAccountKey;
        return $syntheticServiceAccountKey;
    }

    /**
     * @param array $keyFields
     * @return string base64 of the JSON
     */
    public static function encodeKeyFile(array $keyFields): string
    {
        return base64_encode((string)json_encode($keyFields, JSON_UNESCAPED_SLASHES));
    }

    /** @return string[] lines of the private key that must never appear anywhere (for greps) */
    public function getPrivateKeyMarkerLines(): array
    {
        $keyLines = array_values(array_filter(
            explode("\n", $this->privateKeyPem),
            static fn (string $line): bool => $line !== '' && !str_starts_with($line, '-----')
        ));
        return [$keyLines[1], $keyLines[(int)(count($keyLines) / 2)]];
    }
}
