<?php

declare(strict_types=1);

namespace Aurora\Tests\Unit\Module\Ged\Pexels\Service;

use Aurora\Module\Ged\Pexels\Service\PexelsClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The picker is only as good as what this hands it, and the two things that
 * can go wrong here are silent: a key that was never set, and a provider that
 * answered with something other than photos.
 */
final class PexelsClientTest extends TestCase
{
    public function testSearchIsSkippedWithoutAnApiKey(): void
    {
        $http = new MockHttpClient(static function (): MockResponse {
            self::fail('No request should be made when the key is missing.');
        });

        $client = new PexelsClient($http, new NullLogger(), '');

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
            (new PexelsClient($http, new NullLogger(), 'key'))->search('   '),
        );
    }

    public function testSearchKeepsOnlyTheFieldsThePickerNeeds(): void
    {
        $http = new MockHttpClient(new MockResponse(json_encode([
            'total_results' => 100,
            'per_page' => 24,
            'page' => 1,
            'photos' => [[
                'id' => 2014422,
                'width' => 4000,
                'height' => 3000,
                'avg_color' => '#0f172a',
                'alt' => 'A tidy desk',
                'photographer' => 'Jane Doe',
                'photographer_url' => 'https://www.pexels.com/@jane',
                'src' => [
                    'original' => 'https://images.pexels.com/photos/2014422/pexels-photo-2014422.jpeg',
                    'large' => 'https://images.pexels.com/photos/2014422/pexels-photo-2014422.jpeg?auto=compress&cs=tinysrgb&h=650&w=940',
                    'tiny' => 'https://images.pexels.com/photos/2014422/pexels-photo-2014422.jpeg?auto=compress&cs=tinysrgb&h=130&w=280',
                ],
                // Fields we deliberately drop rather than carry into the DB.
                'liked' => false,
                'photographer_id' => 42,
                'url' => 'https://www.pexels.com/photo/a-tidy-desk-2014422/',
            ]],
        ], JSON_THROW_ON_ERROR)));

        $result = (new PexelsClient($http, new NullLogger(), 'key'))->search('desk');

        self::assertCount(1, $result['results']);
        self::assertSame([
            'id' => '2014422',
            // The original, not the 940x650 rendition next to it.
            'url' => 'https://images.pexels.com/photos/2014422/pexels-photo-2014422.jpeg',
            'thumbUrl' => 'https://images.pexels.com/photos/2014422/pexels-photo-2014422.jpeg?auto=compress&cs=tinysrgb&h=130&w=280',
            'width' => 4000,
            'height' => 3000,
            'description' => 'A tidy desk',
            'color' => '#0f172a',
            'authorName' => 'Jane Doe',
            'authorUrl' => 'https://www.pexels.com/@jane',
        ], $result['results'][0]);
    }

    /**
     * Pexels counts results where Unsplash counted pages, so the pager it
     * feeds is a division we do - and one that has to round up, or the last
     * few photos of a search are unreachable.
     */
    public function testPageCountIsDerivedFromTheResultCount(): void
    {
        $http = new MockHttpClient(new MockResponse(json_encode([
            'total_results' => 49,
            'per_page' => 24,
            'photos' => [],
        ], JSON_THROW_ON_ERROR)));

        self::assertSame(3, (new PexelsClient($http, new NullLogger(), 'key'))->search('desk')['totalPages']);
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
            (new PexelsClient($http, new NullLogger(), 'key'))->search('desk'),
        );
    }

    /** Pexels wants the bare key; prefixing it the way Unsplash wanted is a 401. */
    public function testTheKeyIsSentAsAPlainAuthorizationHeader(): void
    {
        $seen = null;
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen = $options['headers'] ?? [];

            return new MockResponse('{"photos":[],"total_results":0,"per_page":24}');
        });

        (new PexelsClient($http, new NullLogger(), 's3cret'))->search('desk');

        self::assertContains('Authorization: s3cret', $seen);
    }
}
