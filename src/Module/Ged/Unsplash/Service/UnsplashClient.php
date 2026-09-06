<?php

declare(strict_types=1);

namespace Aurora\Module\Ged\Unsplash\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Talks to Unsplash on the server's behalf.
 *
 * The access key never reaches the browser. It could have: the search
 * endpoint is public and a Vue component could call it directly, which is
 * how most tutorials do it. But a key in a bundle is a key anyone can read
 * and spend, and the hourly quota is the installation's, not the visitor's -
 * so the admin asks Aurora, and Aurora asks Unsplash.
 *
 * Failures are swallowed into an empty result rather than raised. A stock
 * photo search that cannot reach its provider is a picker with nothing in
 * it, which is a disappointment; an exception here would be a 500 in the
 * middle of editing a page, which is a lost afternoon.
 */
final readonly class UnsplashClient
{
    private const string API_BASE = 'https://api.unsplash.com';

    private const int TIMEOUT_SECONDS = 5;

    private const int PER_PAGE = 24;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        #[Autowire(env: 'UNSPLASH_ACCESS_KEY')]
        private string $accessKey,
    ) {}

    public function isConfigured(): bool
    {
        return '' !== mb_trim($this->accessKey);
    }

    /**
     * @return array{results: list<array<string, mixed>>, totalPages: int}
     */
    public function search(string $query, int $page = 1): array
    {
        $empty = ['results' => [], 'totalPages' => 0];

        if (!$this->isConfigured() || '' === mb_trim($query)) {
            return $empty;
        }

        try {
            $response = $this->httpClient->request('GET', self::API_BASE.'/search/photos', [
                'headers' => [
                    'Authorization' => 'Client-ID '.$this->accessKey,
                    'Accept-Version' => 'v1',
                ],
                'query' => [
                    'query' => $query,
                    'page' => max(1, $page),
                    'per_page' => self::PER_PAGE,
                    // Unsplash's own default is permissive; an image picker
                    // inside a CMS is not the place to surprise anyone.
                    'content_filter' => 'high',
                ],
                'timeout' => self::TIMEOUT_SECONDS,
            ]);

            $payload = $response->toArray();
        } catch (Throwable $throwable) {
            $this->logger->warning('Unsplash search failed.', [
                'query' => $query,
                'exception' => $throwable->getMessage(),
            ]);

            return $empty;
        }

        return [
            'results' => array_map($this->normalizePhoto(...), $payload['results'] ?? []),
            'totalPages' => (int) ($payload['total_pages'] ?? 0),
        ];
    }

    /**
     * Tells Unsplash a photo was taken up.
     *
     * Required by their API terms, and the reason it exists is fair: it is
     * how a photographer sees their work being used. It is also the one call
     * here whose failure changes nothing for the editor, so it stays quiet.
     */
    public function trackDownload(string $downloadLocation): void
    {
        if (!$this->isConfigured() || !str_starts_with($downloadLocation, self::API_BASE.'/')) {
            return;
        }

        try {
            $this->httpClient->request('GET', $downloadLocation, [
                'headers' => [
                    'Authorization' => 'Client-ID '.$this->accessKey,
                    'Accept-Version' => 'v1',
                ],
                'timeout' => self::TIMEOUT_SECONDS,
            ])->getStatusCode();
        } catch (Throwable $throwable) {
            $this->logger->warning('Unsplash download tracking failed.', [
                'exception' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * Keeps only what the picker and the import need.
     *
     * A raw Unsplash photo is some eighty fields deep; passing it through
     * would put their whole schema in our Vue component and in our database.
     *
     * @param array<string, mixed> $photo
     *
     * @return array<string, mixed>
     */
    private function normalizePhoto(array $photo): array
    {
        $urls = (array) ($photo['urls'] ?? []);
        $user = (array) ($photo['user'] ?? []);
        $links = (array) ($photo['links'] ?? []);

        return [
            'id' => (string) ($photo['id'] ?? ''),
            // `regular` is a 1080px-wide rendition - the widest the picker
            // ever needs, and the base every variant width is asked from.
            'url' => (string) ($urls['regular'] ?? ''),
            'thumbUrl' => (string) ($urls['thumb'] ?? ''),
            'width' => (int) ($photo['width'] ?? 0),
            'height' => (int) ($photo['height'] ?? 0),
            'description' => $photo['description'] ?? $photo['alt_description'] ?? null,
            'color' => $photo['color'] ?? null,
            'authorName' => (string) ($user['name'] ?? ''),
            'authorUrl' => (string) ($user['links']['html'] ?? ''),
            'downloadLocation' => (string) ($links['download_location'] ?? ''),
        ];
    }
}
