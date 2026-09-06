<?php

declare(strict_types=1);

namespace Aurora\Module\Ged\Document\Service;

use Aurora\Module\Ged\Document\Entity\DocumentInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Turns a {@see DocumentInterface} (or one of its responsive variants) into
 * the user-facing URL pointing at the `/uploads/{path}` catch-all, plus
 * presentation helpers (focal-point CSS, best-variant cascade).
 *
 * Lives here rather than on `AbstractDocument` so the entity stays a pure
 * domain object - URL building requires `UrlGeneratorInterface`, a
 * presentation concern entities should not depend on (CLAUDE.md §3bis).
 *
 * Sole storage URL generator since the Media library was retired in
 * Phase 5 of the Media → GED merge - see
 * `docs/aurora-core/todo/media-ged-merge.md`.
 *
 * All methods accept `null` so call sites can fold `$doc?->getPublicUrl()`
 * into a single call without re-introducing null-safe checks.
 */
final readonly class DocumentUrlGenerator
{
    /**
     * Pixel widths asked of a provider's CDN, one per variant name, so a
     * remote document answers the same three sizes a local one does.
     *
     * They match ImageVariantGenerator::VARIANT_SIZES deliberately: a
     * consumer picking "medium" gets an 800px-wide picture either way, and
     * nothing downstream has to know which kind it received.
     */
    private const array REMOTE_VARIANT_WIDTHS = [
        'thumbnail' => 256,
        'medium' => 800,
        'large' => 1920,
    ];

    /**
     * What a remote document answers when nobody asked for a variant.
     *
     * A local document's `publicUrl` is its original file, and that is the
     * right answer because we chose what we stored. The original behind a
     * remote one is whatever the photographer uploaded - twenty-five
     * megapixels is normal - so the plain address is a page-weight accident
     * waiting to happen. `getSourceUrl()` still returns it untouched, for
     * the one place that wants the full-size picture: the view controller.
     */
    private const string REMOTE_DEFAULT_VARIANT = 'large';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function publicUrl(?DocumentInterface $document): ?string
    {
        if (true === $document?->isRemote()) {
            return $this->remoteDefaultUrl($document);
        }

        $filePath = $document?->getFilePath();
        if (null === $filePath) {
            return null;
        }

        return $this->urlGenerator->generate('uploads_serve', ['path' => $filePath]);
    }

    /**
     * Absolute URL flavor for cross-origin contexts (RSS feeds, emails,
     * social sharing, JSON-LD payloads). Same null-safe contract.
     */
    public function publicUrlAbsolute(?DocumentInterface $document): ?string
    {
        // A CDN address is already absolute, and is the only address these
        // have - there is no local route to fall back on.
        if (true === $document?->isRemote()) {
            return $this->remoteDefaultUrl($document);
        }

        $filePath = $document?->getFilePath();
        if (null === $filePath) {
            return null;
        }

        return $this->urlGenerator->generate(
            'uploads_serve',
            ['path' => $filePath],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }

    public function variantUrl(?DocumentInterface $document, string $variant): ?string
    {
        if (!$document instanceof DocumentInterface) {
            return null;
        }

        // Remote documents have no variants column to read: the provider
        // resizes on demand, so the variant is a width appended to the URL
        // rather than a file we generated. Unknown variant names fall
        // through to null, exactly as they do for local documents.
        if ($document->isRemote()) {
            $width = self::REMOTE_VARIANT_WIDTHS[$variant] ?? null;

            return null === $width
                ? null
                : $this->remoteUrlAtWidth((string) $document->getSourceUrl(), $width);
        }

        $path = $document->getVariants()[$variant] ?? null;

        return null === $path
            ? null
            : $this->urlGenerator->generate('uploads_serve', ['path' => $path]);
    }

    /**
     * Best variant for thumbnail-size display: tries `medium` first, falls
     * back to `large`, finally to the original. Matches the cascade most
     * consumers do inline against MediaUrlGenerator.
     */
    public function thumbUrl(?DocumentInterface $document): ?string
    {
        if (!$document instanceof DocumentInterface) {
            return null;
        }

        return $this->variantUrl($document, 'medium')
            ?? $this->variantUrl($document, 'large')
            ?? $this->publicUrl($document);
    }

    /**
     * Returns a CSS `object-position` value like "50% 25%" based on the
     * focal point, or "50% 50%" (centered) when no focal point is set.
     */
    public function focalPositionCss(?DocumentInterface $document): string
    {
        $focalX = $document?->getFocalX();
        $focalY = $document?->getFocalY();

        $x = null !== $focalX ? round($focalX * 100, 2) : 50;
        $y = null !== $focalY ? round($focalY * 100, 2) : 50;

        return sprintf('%s%% %s%%', $x, $y);
    }

    private function remoteDefaultUrl(DocumentInterface $document): string
    {
        return $this->remoteUrlAtWidth(
            (string) $document->getSourceUrl(),
            self::REMOTE_VARIANT_WIDTHS[self::REMOTE_DEFAULT_VARIANT],
        );
    }

    /**
     * Re-asks a provider's CDN for the same picture at a given width.
     *
     * Pexels serves through imgix, which reads its instructions from the
     * query string - so the resizing we do with GD for our own files is a
     * parameter here, and a page still gets a 256px thumbnail instead of a
     * 4000px original scaled down by the browser.
     *
     * A height or a pixel ratio would turn the resize into a crop, and the
     * renditions Pexels names in its payload carry both; they are dropped so
     * that asking for a width means only that. Anything else on the URL is
     * left alone - a provider that signs or tags its addresses has a reason
     * to, and rebuilding one without its own parameters breaks that.
     */
    private function remoteUrlAtWidth(string $sourceUrl, int $width): string
    {
        $parts = parse_url($sourceUrl);
        if (false === $parts || !isset($parts['host'], $parts['scheme'])) {
            return $sourceUrl;
        }

        parse_str($parts['query'] ?? '', $query);
        unset($query['h'], $query['dpr']);
        $query['w'] = (string) $width;
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
}
