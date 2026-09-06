<?php

declare(strict_types=1);

namespace Aurora\Module\Ged\Pexels\Service;

use Aurora\Module\Ged\Document\Dto\DocumentInputFactoryInterface;
use Aurora\Module\Ged\Document\Entity\DocumentInterface;
use Aurora\Module\Ged\Document\Manager\DocumentManagerInterface;
use Aurora\Module\Ged\Document\Service\GedDocumentUploader;
use Aurora\Module\Ged\Document\Service\InlineImageUploader;
use Aurora\Module\Ged\DocumentCategory\Service\InlineUploadCategoryProvider;
use Aurora\Module\Ged\Enum\DocumentStatusEnum;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Files a chosen Pexels photo as a document of ours, bytes included.
 *
 * The counterpart to {@see InlineImageUploader} for a picture that came from
 * a search rather than from a disk. Once the file is downloaded there is
 * nothing remote left about it: it is stored, resized and served exactly like
 * an upload, and the page that shows it makes no request to anyone else.
 *
 * That is a deliberate choice and not the only one available. Pexels' licence
 * grants the right to download and keep the picture, irrevocably, to whoever
 * downloads it - so a site built this way holds its own licence on its own
 * images, keeps working if the key is revoked or the photo is taken down, and
 * never sends a visitor's address to a third party. Linking to their CDN
 * would have been less code and none of that.
 *
 * `sourceUrl` and the two attribution columns survive the download: they are
 * where the picture came from and whose it is, which stays true and stays
 * needed - the credit is rendered from them.
 *
 * Published and filed in the inline-upload category for the same reasons the
 * uploader gives: the picker lists published documents only, and an
 * uncategorised one is unfindable afterwards.
 */
final readonly class PexelsImporter
{
    /**
     * The only host a photo may be fetched from.
     *
     * The URL arrives from the browser, and a browser can be told to send
     * anything. Without this, "import" would fetch whatever address it was
     * handed and file the result - which is a request forgery with a
     * document library attached.
     */
    private const string ALLOWED_HOST = 'images.pexels.com';

    /**
     * What we ask the CDN for, in pixels.
     *
     * Wide enough to still crop a 1920px banner out of, small enough that a
     * library of them is not a disk problem. The originals are whatever the
     * photographer uploaded, which is regularly twenty-five megapixels of
     * JPEG nobody will ever display.
     */
    private const int DOWNLOAD_WIDTH = 2560;

    /** A stop for a download that turns out not to be what it claimed. */
    private const int MAX_BYTES = 25_000_000;

    private const int TIMEOUT_SECONDS = 20;

    public function __construct(
        private DocumentManagerInterface $documentManager,
        private DocumentInputFactoryInterface $inputFactory,
        private InlineUploadCategoryProvider $inlineUploadCategoryProvider,
        private GedDocumentUploader $uploader,
        private HttpClientInterface $httpClient,
    ) {}

    /**
     * @param array<string, mixed> $photo as normalized by PexelsClient
     *
     * @throws InvalidArgumentException when the payload does not describe a Pexels photo
     * @throws RuntimeException         when the photo cannot be fetched
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

        $uploaded = $this->uploader->upload($this->fetch($url));

        // Guessed from the bytes by the uploader, not from anything the
        // payload claimed. A file that is not an image has no business in a
        // picture field, whatever the URL said it was.
        if (!str_starts_with($uploaded['mimeType'], 'image/')) {
            $this->uploader->deleteFile($uploaded['filePath']);

            throw new InvalidArgumentException('The fetched file is not an image.');
        }

        return $this->documentManager->create($this->inputFactory->fromArray([
            'title' => $this->buildTitle($photo, $authorName),
            'status' => DocumentStatusEnum::Published->value,
            'categoryId' => (int) $this->inlineUploadCategoryProvider->resolve()->getId(),
            'filePath' => $uploaded['filePath'],
            'fileName' => $uploaded['fileName'],
            'originalName' => $uploaded['originalName'],
            'mimeType' => $uploaded['mimeType'],
            'size' => $uploaded['size'],
            'width' => $uploaded['width'],
            'height' => $uploaded['height'],
            'thumbnailPath' => $uploaded['thumbnailPath'],
            // Provenance, kept for as long as the picture is: the credit is
            // rendered from these, and a year from now they are the only way
            // to answer where a photo in the library came from.
            'sourceUrl' => $url,
            'attributionName' => $authorName,
            'attributionUrl' => '' !== ($authorUrl = mb_trim((string) ($photo['authorUrl'] ?? ''))) ? $authorUrl : null,
            'alt' => $this->truncate((string) ($photo['description'] ?? '')),
        ]));
    }

    /**
     * Downloads the photo to a temporary file the uploader can consume.
     *
     * Streamed rather than read whole so a response that keeps coming is
     * stopped at {@see MAX_BYTES} instead of becoming the memory limit.
     */
    private function fetch(string $url): UploadedFile
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'pexels_');
        if (false === $temporaryPath) {
            throw new RuntimeException('Could not create a temporary file for the download.');
        }

        try {
            $response = $this->httpClient->request('GET', $this->downloadUrl($url), [
                'timeout' => self::TIMEOUT_SECONDS,
            ]);

            $handle = fopen($temporaryPath, 'wb');
            if (false === $handle) {
                throw new RuntimeException('Could not open the temporary file.');
            }

            $written = 0;
            try {
                foreach ($this->httpClient->stream($response) as $chunk) {
                    $content = $chunk->getContent();
                    $written += mb_strlen($content, '8bit');

                    if ($written > self::MAX_BYTES) {
                        throw new RuntimeException('The photo is larger than the import allows.');
                    }

                    fwrite($handle, $content);
                }
            } finally {
                fclose($handle);
            }

            if (0 === $written) {
                throw new RuntimeException('The provider returned an empty response.');
            }
        } catch (Throwable $throwable) {
            @unlink($temporaryPath);

            throw $throwable instanceof RuntimeException ? $throwable : new RuntimeException('The photo could not be downloaded.', 0, $throwable);
        }

        // `test: true` because nothing was uploaded through PHP: the file is
        // ours, written a moment ago, and the constructor's upload checks
        // would reject it otherwise.
        return new UploadedFile($temporaryPath, $this->fileName($url), null, null, true);
    }

    /**
     * Re-asks the CDN for the picture at a width worth storing.
     *
     * Pexels serves through imgix, which reads its instructions from the
     * query string. A height or a pixel ratio would turn the resize into a
     * crop, and their own renditions carry both, so those go.
     */
    private function downloadUrl(string $url): string
    {
        $parts = parse_url($url);
        if (false === $parts || !isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        parse_str($parts['query'] ?? '', $query);
        unset($query['h'], $query['dpr']);
        $query['w'] = (string) self::DOWNLOAD_WIDTH;
        $query['auto'] ??= 'compress';
        $query['cs'] ??= 'tinysrgb';

        return sprintf(
            '%s://%s%s?%s',
            $parts['scheme'],
            $parts['host'],
            $parts['path'] ?? '',
            http_build_query($query),
        );
    }

    /**
     * Their filenames are already descriptive - `pexels-photo-2014422.jpeg` -
     * and carry the extension the uploader needs to name the stored file.
     */
    private function fileName(string $url): string
    {
        $name = basename((string) parse_url($url, PHP_URL_PATH));

        return '' !== $name ? $name : 'pexels-photo.jpeg';
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
