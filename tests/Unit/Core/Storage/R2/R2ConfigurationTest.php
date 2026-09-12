<?php

declare(strict_types=1);

namespace Aurora\Tests\Unit\Core\Storage\R2;

use Aurora\Core\Storage\R2\R2Configuration;
use PHPUnit\Framework\TestCase;

final class R2ConfigurationTest extends TestCase
{
    private const string KEY = '0123456789abcdef0123456789abcdef';
    private const string SECRET = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    /**
     * The mistake this whole normalisation exists for: Cloudflare's console
     * shows an S3 address ending in the bucket, and pasting it whole makes the
     * bucket appear twice in every request.
     */
    public function testTheBucketPastedOntoTheEndpointIsTakenBackOff(): void
    {
        $configuration = $this->make(endpoint: 'https://account.r2.cloudflarestorage.com/my-bucket', bucket: 'my-bucket');

        self::assertSame(
            'https://account.r2.cloudflarestorage.com',
            $configuration->withNormalisedEndpoint()->endpoint,
        );
    }

    public function testATrailingSlashGoesToo(): void
    {
        $configuration = $this->make(endpoint: 'https://account.r2.cloudflarestorage.com/my-bucket/', bucket: 'my-bucket');

        self::assertSame(
            'https://account.r2.cloudflarestorage.com',
            $configuration->withNormalisedEndpoint()->endpoint,
        );
    }

    public function testAnEndpointWithoutTheBucketIsLeftAlone(): void
    {
        $configuration = $this->make(endpoint: 'https://account.r2.cloudflarestorage.com', bucket: 'my-bucket');

        self::assertSame(
            'https://account.r2.cloudflarestorage.com',
            $configuration->withNormalisedEndpoint()->endpoint,
        );
    }

    /**
     * Only a whole trailing segment is removed. A bucket whose name appears
     * earlier in the address, or as the tail of a longer segment, is not a
     * duplicate and must survive untouched.
     */
    public function testOnlyAWholeTrailingSegmentIsRemoved(): void
    {
        $configuration = $this->make(endpoint: 'https://my-bucket.example.com/prefix-my-bucket', bucket: 'my-bucket');

        self::assertSame(
            'https://my-bucket.example.com/prefix-my-bucket',
            $configuration->withNormalisedEndpoint()->endpoint,
        );
    }

    public function testAWellFormedConfigurationHasNoShapeProblem(): void
    {
        self::assertSame([], $this->make()->shapeProblems());
    }

    /**
     * The error Cloudflare returned on 12/09/2026, reduced to something that
     * names the field: `Credential access key has length 24, should be 32`.
     */
    public function testAnAccessKeyOfTheWrongLengthIsNamed(): void
    {
        $configuration = $this->make(accessKeyId: str_repeat('a', 24));

        self::assertContains('access_key_length', $configuration->shapeProblems());
    }

    public function testASecretOfTheWrongLengthIsNamed(): void
    {
        $configuration = $this->make(secretAccessKey: str_repeat('a', 40));

        self::assertContains('secret_key_length', $configuration->shapeProblems());
    }

    public function testAnEndpointCarryingTheBucketIsNamed(): void
    {
        $configuration = $this->make(endpoint: 'https://account.r2.cloudflarestorage.com/my-bucket', bucket: 'my-bucket');

        self::assertContains('endpoint_contains_bucket', $configuration->shapeProblems());
    }

    public function testAnEndpointThatIsNotHttpsIsNamed(): void
    {
        $configuration = $this->make(endpoint: 'http://account.r2.cloudflarestorage.com');

        self::assertContains('endpoint_not_https', $configuration->shapeProblems());
    }

    /**
     * An empty field is missing, not malformed. The two are reported by
     * different methods because they call for different sentences: one asks
     * for something, the other says what was typed cannot work.
     */
    public function testEmptyFieldsAreNotShapeProblems(): void
    {
        self::assertSame([], R2Configuration::empty()->shapeProblems());
        self::assertCount(4, R2Configuration::empty()->missingFields());
    }

    /**
     * The credentials survive normalisation. Rebuilding the object field by
     * field is exactly where one could be dropped.
     */
    public function testNormalisationKeepsEveryOtherField(): void
    {
        $normalised = $this->make(
            endpoint: 'https://account.r2.cloudflarestorage.com/my-bucket',
            publicBaseUrl: 'https://files.example.com',
        )->withNormalisedEndpoint();

        self::assertSame('my-bucket', $normalised->bucket);
        self::assertSame(self::KEY, $normalised->accessKeyId);
        self::assertSame(self::SECRET, $normalised->secretAccessKey);
        self::assertSame('https://files.example.com', $normalised->publicBaseUrl);
        self::assertTrue($normalised->isComplete());
    }

    private function make(
        string $endpoint = 'https://account.r2.cloudflarestorage.com',
        string $bucket = 'my-bucket',
        string $accessKeyId = self::KEY,
        string $secretAccessKey = self::SECRET,
        ?string $publicBaseUrl = null,
    ): R2Configuration {
        return new R2Configuration($endpoint, $bucket, $accessKeyId, $secretAccessKey, $publicBaseUrl);
    }
}
