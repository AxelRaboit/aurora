<?php

declare(strict_types=1);

namespace Aurora\Core\Storage\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * What a browser sends alongside a film it just decoded: one frame, and the
 * film's own pixel dimensions.
 *
 * The dimensions travel separately rather than being read back off the frame
 * because the frame is capped on its long side - a poster is decoration, and
 * a 4K still would weigh more than it is worth. `videoWidth` and
 * `videoHeight` are what the player reported, so the document records the
 * film's real size and not the poster's.
 *
 * Absent dimensions are not an error: {@see VideoPosterGenerator::fromCapture()}
 * still stores the frame, and the document keeps the null it had before.
 */
final readonly class VideoCapture
{
    public function __construct(
        public UploadedFile $poster,
        public ?int $videoWidth = null,
        public ?int $videoHeight = null,
    ) {}

    /**
     * True when both dimensions are present and plausible.
     *
     * A player that failed to load metadata reports `0`, and a request can
     * post whatever it likes in a form field, so neither is taken on trust.
     *
     * @phpstan-assert-if-true int $this->videoWidth
     * @phpstan-assert-if-true int $this->videoHeight
     */
    public function hasDimensions(): bool
    {
        return null !== $this->videoWidth
            && null !== $this->videoHeight
            && $this->videoWidth > 0
            && $this->videoHeight > 0;
    }
}
