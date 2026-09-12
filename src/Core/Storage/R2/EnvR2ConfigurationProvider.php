<?php

declare(strict_types=1);

namespace Aurora\Core\Storage\R2;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Reads the credentials from the environment.
 *
 * Not the service the application resolves: the Configuration module wraps
 * this one so an administrator can configure a bucket without touching a
 * server, and lets the environment win field by field. It stays a first-class
 * source because a server that would rather keep its secrets out of a database
 * it backs up nightly is taking a defensible position.
 *
 * Every variable resolves through `default::`, so an installation that has
 * never heard of R2 boots with an empty configuration rather than a container
 * error about a missing environment variable.
 */
final readonly class EnvR2ConfigurationProvider implements R2ConfigurationProviderInterface
{
    public function __construct(
        #[Autowire(env: 'default::R2_ENDPOINT')]
        private ?string $endpoint,
        #[Autowire(env: 'default::R2_BUCKET')]
        private ?string $bucket,
        #[Autowire(env: 'default::R2_ACCESS_KEY_ID')]
        private ?string $accessKeyId,
        #[Autowire(env: 'default::R2_SECRET_ACCESS_KEY')]
        private ?string $secretAccessKey,
        #[Autowire(env: 'default::R2_PUBLIC_BASE_URL')]
        private ?string $publicBaseUrl,
    ) {}

    public function current(): R2Configuration
    {
        $publicBaseUrl = mb_trim((string) $this->publicBaseUrl);

        return new R2Configuration(
            endpoint: mb_rtrim(mb_trim((string) $this->endpoint), '/'),
            bucket: mb_trim((string) $this->bucket),
            accessKeyId: mb_trim((string) $this->accessKeyId),
            secretAccessKey: mb_trim((string) $this->secretAccessKey),
            publicBaseUrl: '' === $publicBaseUrl ? null : mb_rtrim($publicBaseUrl, '/'),
        );
    }
}
