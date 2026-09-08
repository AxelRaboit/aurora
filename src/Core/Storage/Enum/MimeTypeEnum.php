<?php

declare(strict_types=1);

namespace Aurora\Core\Storage\Enum;

/**
 * Document mime types known to Aurora.
 *
 * @see src/Core/assets/utils/enums/media/mimeType.js - JavaScript mirror used by
 *      Vue components. **Keep the two in sync**: every case added here must be
 *      reflected there (and vice-versa) so the front-end and back-end agree on
 *      what's supported.
 */
enum MimeTypeEnum: string
{
    case Jpeg = 'image/jpeg';
    case Jpg = 'image/jpg';
    case Png = 'image/png';
    case Gif = 'image/gif';
    case Webp = 'image/webp';
    case Svg = 'image/svg+xml';
    case Pdf = 'application/pdf';
    case Mp4 = 'video/mp4';
    case Webm = 'video/webm';

    /** True for any image/* (raster or vector). Excludes PDFs. */
    public function isImage(): bool
    {
        return match ($this) {
            self::Jpeg, self::Jpg, self::Png, self::Gif, self::Webp, self::Svg => true,
            default => false,
        };
    }

    public function isRasterImage(): bool
    {
        return match ($this) {
            self::Jpeg, self::Jpg, self::Png, self::Gif, self::Webp => true,
            default => false,
        };
    }

    /**
     * True for a file a browser can play in a `<video>`.
     *
     * The two formats worth naming: MP4 with H.264 plays everywhere, WebM
     * covers the rest. Anything else uploaded stays a file to download, which
     * is what it was before this existed.
     */
    public function isVideo(): bool
    {
        return match ($this) {
            self::Mp4, self::Webm => true,
            default => false,
        };
    }

    public function isJpeg(): bool
    {
        return self::Jpeg === $this || self::Jpg === $this;
    }

    public function supportsAlpha(): bool
    {
        return match ($this) {
            self::Png, self::Gif, self::Webp => true,
            default => false,
        };
    }

    public function supportsAnimation(): bool
    {
        return self::Gif === $this;
    }

    public static function isRasterMimeTypeEnum(string $mimeType): bool
    {
        $case = self::tryFrom($mimeType);

        return $case?->isRasterImage() ?? false;
    }

    /**
     * Canonical filesystem extension (no leading dot) - used when an
     * uploaded file is renamed by the server (e.g. UUID-based names in
     * `MarkdownNoteImageService`) and the original client filename is dropped.
     */
    public function extension(): string
    {
        return match ($this) {
            self::Jpeg, self::Jpg => 'jpg',
            self::Png => 'png',
            self::Gif => 'gif',
            self::Webp => 'webp',
            self::Svg => 'svg',
            self::Pdf => 'pdf',
            self::Mp4 => 'mp4',
            self::Webm => 'webm',
        };
    }
}
