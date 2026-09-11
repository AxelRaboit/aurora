<?php

declare(strict_types=1);

namespace Aurora\Core\Storage\R2;

use AsyncAws\Core\Configuration;
use AsyncAws\SimpleS3\SimpleS3Client;
use Aurora\Core\Storage\Exception\StorageException;

use function sprintf;

/**
 * Builds the S3 client pointed at Cloudflare R2.
 *
 * Three things differ from Amazon and all three are required, which is why
 * this exists rather than a constructor argument somewhere:
 *
 *  - `region` is `auto`. R2 has no regions, but the S3 signature has a region
 *    in it, and every client insists on one.
 *  - `pathStyleEndpoint` is on. R2 addresses buckets as a path under the
 *    account endpoint, not as a subdomain of it.
 *  - the endpoint is the account URL with no bucket in it. The console shows
 *    an S3 URL ending in the bucket name, and pasting that whole string is the
 *    mistake this comment exists to catch: the bucket would then appear twice
 *    in every request.
 *
 * The client is built once and kept, because building one parses
 * configuration and sets up an HTTP client, and the adapter makes many calls.
 */
final class S3ClientFactory
{
    private ?SimpleS3Client $client = null;

    private ?string $builtFor = null;

    public function __construct(
        private readonly R2ConfigurationProviderInterface $configurationProvider,
    ) {}

    /**
     * @throws StorageException when the configuration is incomplete
     */
    public function create(): SimpleS3Client
    {
        $configuration = $this->configurationProvider->current();

        if (!$configuration->isComplete()) {
            throw new StorageException(sprintf('Cloudflare R2 is not configured: missing %s.', implode(', ', $configuration->missingFields())));
        }

        // Rebuilt when the configuration changed under us, which is what
        // happens the moment an administrator saves the settings tab and the
        // provider starts reading the database instead of the environment.
        $fingerprint = $configuration->endpoint.'|'.$configuration->bucket.'|'.$configuration->accessKeyId;

        if ($this->client instanceof SimpleS3Client && $this->builtFor === $fingerprint) {
            return $this->client;
        }

        $this->builtFor = $fingerprint;

        return $this->client = new SimpleS3Client(Configuration::create([
            'endpoint' => $configuration->endpoint,
            'region' => 'auto',
            // A string, not a bool: async-aws reads its options as the strings
            // an environment variable would carry, and its own defaults are
            // spelled 'false'.
            'pathStyleEndpoint' => 'true',
            'accessKeyId' => $configuration->accessKeyId,
            'accessKeySecret' => $configuration->secretAccessKey,
        ]));
    }
}
