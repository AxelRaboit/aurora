<?php

declare(strict_types=1);

namespace Aurora\Module\Ged\Pexels\Service;

use Aurora\Module\Ged\Document\Dto\DocumentInputFactoryInterface;
use Aurora\Module\Ged\Document\Entity\DocumentInterface;
use Aurora\Module\Ged\Document\Manager\DocumentManagerInterface;
use Aurora\Module\Ged\Document\Service\InlineImageUploader;
use Aurora\Module\Ged\DocumentCategory\Service\InlineUploadCategoryProvider;
use Aurora\Module\Ged\Enum\DocumentStatusEnum;
use InvalidArgumentException;

/**
 * Files a chosen Pexels photo as a document of ours, without the bytes.
 *
 * The counterpart to {@see InlineImageUploader} for pictures we would rather
 * not copy: the photo stays on the provider's CDN, and what lands in the
 * library is the address and the credit, with `filePath` left null. That
 * keeps the credit attached to the picture for as long as the picture is
 * shown, which is what the API guidelines are asking for, and it costs the
 * server no disk.
 *
 * Published and filed in the inline-upload category for the same reasons the
 * uploader gives: the picker lists published documents only, and an
 * uncategorised one is unfindable afterwards.
 */
final readonly class PexelsImporter
{
    /**
     * The only host a stored URL may point at.
     *
     * The photo URL arrives from the browser, and a browser can be told to
     * send anything. Without this, "import" would be an open invitation to
     * store an arbitrary address and have every page on the site render it.
     */
    private const string ALLOWED_HOST = 'images.pexels.com';

    public function __construct(
        private DocumentManagerInterface $documentManager,
        private DocumentInputFactoryInterface $inputFactory,
        private InlineUploadCategoryProvider $inlineUploadCategoryProvider,
    ) {}

    /**
     * @param array<string, mixed> $photo as normalized by PexelsClient
     *
     * @throws InvalidArgumentException when the payload does not describe a Pexels photo
     */
    public function import(array $photo): DocumentInterface
    {
        $url = mb_trim((string) ($photo['url'] ?? ''));
        $authorName = mb_trim((string) ($photo['authorName'] ?? ''));

        if (!$this->isPexelsUrl($url)) {
            throw new InvalidArgumentException('Not a Pexels image URL.');
        }

        if ('' === $authorName) {
            throw new InvalidArgumentException('Missing photographer attribution.');
        }

        return $this->documentManager->create($this->inputFactory->fromArray([
            'title' => $this->buildTitle($photo, $authorName),
            'status' => DocumentStatusEnum::Published->value,
            'categoryId' => (int) $this->inlineUploadCategoryProvider->resolve()->getId(),
            'sourceUrl' => $url,
            'attributionName' => $authorName,
            'attributionUrl' => '' !== ($authorUrl = mb_trim((string) ($photo['authorUrl'] ?? ''))) ? $authorUrl : null,
            // Pexels serves JPEG and re-encodes nothing on the way out, so
            // this is the truth rather than a placeholder - and it is what
            // MimeGroupEnum reads to decide the document is an image at all.
            'mimeType' => 'image/jpeg',
            'width' => (int) ($photo['width'] ?? 0),
            'height' => (int) ($photo['height'] ?? 0),
            'alt' => $this->truncate((string) ($photo['description'] ?? '')),
        ]));
    }

    private function isPexelsUrl(string $url): bool
    {
        return self::ALLOWED_HOST === parse_url($url, PHP_URL_HOST);
    }

    /** @param array<string, mixed> $photo */
    private function buildTitle(array $photo, string $authorName): string
    {
        $description = $this->truncate((string) ($photo['description'] ?? ''));

        return '' !== $description
            ? $description
            : sprintf('Pexels - %s', $authorName);
    }

    /** Titles and alt text are 255-column values; a caption can be a paragraph. */
    private function truncate(string $value): string
    {
        $value = mb_trim($value);

        return mb_strlen($value) > 200 ? mb_substr($value, 0, 197).'...' : $value;
    }
}
