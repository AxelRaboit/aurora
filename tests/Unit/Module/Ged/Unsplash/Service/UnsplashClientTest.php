<?php

declare(strict_types=1);

namespace Aurora\Tests\Unit\Module\Ged\Unsplash\Service;

use Aurora\Module\Ged\Unsplash\Service\UnsplashClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The picker is only as good as what this hands it, and the two things that
 * can go wrong here are silent: a key that was never set, and a provider that
 * answered with something other than photos.
 */
final class UnsplashClientTest extends TestCase
{
    public function testSearchIsSkippedWithoutAnAccessKey(): void
    {
        $http = new MockHttpClient(static function (): MockResponse {
            self::fail('No request should be made when the key is missing.');
        });

        $client = new UnsplashClient($http, new NullLogger(), '');

        self::assertFalse($client->isConfigured());
        self::assertSame(['results' => [], 'totalPages' => 0], $client->search('desk'));
    }

    public function testSearchIsSkippedForAnEmptyQuery(): void
    {
        $http = new MockHttpClient(static function (): MockResponse {
            self::fail('An empty query must not reach the provider.');
        });

        self::assertSame(
            ['results' => [], 'totalPages' => 0],
            (new UnsplashClient($http, new NullLogger(), 'key'))->search('   '),
        );
    }

    public function testSearchKeepsOnlyTheFieldsThePickerNeeds(): void
    {
        $http = new MockHttpClient(new MockResponse(json_encode([
            'total_pages' => 7,
            'results' => [[
                'id' => 'abc123',
                'width' => 4000,
                'height' => 3000,
                'color' => '#0f172a',
                'description' => 'A tidy desk',
                'alt_description' => 'ignored when description is set',
                'urls' => ['regular' => 'https://images.unsplash.com/photo-1?w=1080', 'thumb' => 'https://images.unsplash.com/photo-1?w=200'],
                'links' => ['download_location' => 'https://api.unsplash.com/photos/abc123/download'],
                'user' => ['name' => 'Jane Doe', 'links' => ['html' => 'https://unsplash.com/@jane']],
                // Fields we deliberately drop rather than carry into the DB.
                'sponsorship' => ['tagline' => 'noise'],
                'exif' => ['make' => 'Canon'],
            ]],
        ], JSON_THROW_ON_ERROR)));

        $result = (new UnsplashClient($http, new NullLogger(), 'key'))->search('desk');

        self::assertSame(7, $result['totalPages']);
        self::assertCount(1, $result['results']);
        self::assertSame([
            'id' => 'abc123',
            'url' => 'https://images.unsplash.com/photo-1?w=1080',
            'thumbUrl' => 'https://images.unsplash.com/photo-1?w=200',
            'width' => 4000,
            'height' => 3000,
            'description' => 'A tidy desk',
            'color' => '#0f172a',
            'authorName' => 'Jane Doe',
            'authorUrl' => 'https://unsplash.com/@jane',
            'downloadLocation' => 'https://api.unsplash.com/photos/abc123/download',
        ], $result['results'][0]);
    }

    /**
     * A provider that is down must not take the editing screen with it: the
     * picker shows an empty tab, the page being written is untouched.
     */
    public function testAFailingProviderYieldsAnEmptyResultRatherThanAnException(): void
    {
        $http = new MockHttpClient(new MockResponse('', ['http_code' => 503]));

        self::assertSame(
            ['results' => [], 'totalPages' => 0],
            (new UnsplashClient($http, new NullLogger(), 'key'))->search('desk'),
        );
    }

    /**
     * The download ping is the one call whose URL comes from the payload, so
     * it is the one that could be pointed elsewhere.
     */
    public function testDownloadTrackingRefusesAnAddressOutsideTheApi(): void
    {
        $http = new MockHttpClient(static function (): MockResponse {
            self::fail('Only api.unsplash.com may be pinged.');
        });

        (new UnsplashClient($http, new NullLogger(), 'key'))->trackDownload('https://evil.example.com/steal');

        self::assertTrue(true);
    }

    public function testDownloadTrackingCallsTheProvidedApiLocation(): void
    {
        $calls = 0;
        $http = new MockHttpClient(static function (string $method, string $url) use (&$calls): MockResponse {
            ++$calls;
            self::assertSame('GET', $method);
            self::assertSame('https://api.unsplash.com/photos/abc123/download', $url);

            return new MockResponse('{}');
        });

        (new UnsplashClient($http, new NullLogger(), 'key'))
            ->trackDownload('https://api.unsplash.com/photos/abc123/download');

        self::assertSame(1, $calls);
    }
}
