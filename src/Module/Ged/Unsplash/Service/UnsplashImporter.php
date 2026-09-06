<?php

declare(strict_types=1);

namespace Aurora\Module\Ged\Unsplash\Service;

use Aurora\Module\Ged\Document\Dto\DocumentInputFactoryInterface;
use Aurora\Module\Ged\Document\Entity\DocumentInterface;
use Aurora\Module\Ged\Document\Manager\DocumentManagerInterface;
use Aurora\Module\Ged\Document\Service\InlineImageUploader;
use Aurora\Module\Ged\DocumentCategory\Service\InlineUploadCategoryProvider;
use Aurora\Module\Ged\Enum\DocumentStatusEnum;
use InvalidArgumentException;

/**
 * Files a chosen Unsplash photo as a document of ours, without the bytes.
 *
 * The counterpart to {@see InlineImageUploader}
 * for pictures we are not allowed to keep: Unsplash's API terms require the
 * image to be served from their CDN, so what lands in the library is the
 * address and the credit, and `filePath` stays null.
 *
 * Published and filed in the inline-upload category for the same reasons the
 * uploader gives: the picker lists published documents only, and an
 * uncategorised one is unfindable afterwards.
 */
final readonly class UnsplashImporter
{
    /**
     * The only host a stored URL may point at.
     *
     * The photo URL arrives from the browser, and a browser can be told to
     * send anything. Without this, "import" would be an open invitation to
     * store an arbitrary address and have every page on the site render it.
     */
    private const string ALLOWED_HOST = 'images.unsplash.com';

    /**
     * Appended to every link back, as the API guidelines require.
     *
     * They ask for `utm_source` and `utm_medium` so a photographer can see
     * where a view came from; without them the attribution is decorative.
     */
    private const string UTM = 'utm_source=aurora&utm_medium=referral';

    public function __construct(
        private DocumentManagerInterface $documentManager,
        private DocumentInputFactoryInterface $inputFactory,
        private InlineUploadCategoryProvider $inlineUploadCategoryProvider,
        private UnsplashClient $client,
    ) {}

    /**
     * @param array<string, mixed> $photo as normalized by UnsplashClient
     *
     * @throws InvalidArgumentException when the payload does not describe an Unsplash photo
     */
    public function import(array $photo): DocumentInterface
    {
        $url = mb_trim((string) ($photo['url'] ?? ''));
        $authorName = mb_trim((string) ($photo['authorName'] ?? ''));

        if (!$this->isUnsplashUrl($url)) {
            throw new InvalidArgumentException('Not an Unsplash image URL.');
        }

        if ('' === $authorName) {
            throw new InvalidArgumentException('Missing photographer attribution.');
        }

        // Told before the document is written, not after: if the call fails
        // the picture is still imported, and if the write fails we have not
        // reported a download that never happened.
        $downloadLocation = mb_trim((string) ($photo['downloadLocation'] ?? ''));
        if ('' !== $downloadLocation) {
            $this->client->trackDownload($downloadLocation);
        }

        return $this->documentManager->create($this->inputFactory->fromArray([
            'title' => $this->buildTitle($photo, $authorName),
            'status' => DocumentStatusEnum::Published->value,
            'categoryId' => (int) $this->inlineUploadCategoryProvider->resolve()->getId(),
            'sourceUrl' => $url,
            'attributionName' => $authorName,
            'attributionUrl' => $this->withUtm(mb_trim((string) ($photo['authorUrl'] ?? ''))),
            // Unsplash renders through imgix, which answers whatever format
            // the URL asks for; WebP is what our own variants use.
            'mimeType' => 'image/webp',
            'width' => (int) ($photo['width'] ?? 0),
            'height' => (int) ($photo['height'] ?? 0),
            'alt' => $this->truncate((string) ($photo['description'] ?? '')),
        ]));
    }

    private function isUnsplashUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return self::ALLOWED_HOST === $host;
    }

    /** @param array<string, mixed> $photo */
    private function buildTitle(array $photo, string $authorName): string
    {
        $description = $this->truncate((string) ($photo['description'] ?? ''));

        return '' !== $description
            ? $description
            : sprintf('Unsplash - %s', $authorName);
    }

    private function withUtm(string $url): ?string
    {
        if ('' === $url) {
            return null;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').self::UTM;
    }

    /** Titles and alt text are 255-column values; a caption can be a paragraph. */
    private function truncate(string $value): string
    {
        $value = mb_trim($value);

        return mb_strlen($value) > 200 ? mb_substr($value, 0, 197).'...' : $value;
    }
}
