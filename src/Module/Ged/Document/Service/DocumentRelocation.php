<?php

declare(strict_types=1);

namespace Aurora\Module\Ged\Document\Service;

use Aurora\Core\Storage\Enum\StorageDiskEnum;

/**
 * What a move did, or why it did not happen.
 *
 * `alreadyThere` is a success, not a refusal: asking for a document to be
 * somewhere it already is has been honoured. Saying so separately lets a
 * caller looping over a selection report "eleven moved, three were already
 * there" rather than counting them all as work.
 */
final readonly class DocumentRelocation
{
    private function __construct(
        public bool $ok,
        public bool $alreadyThere,
        public bool $busy,
        public int $filesMoved,
        public int $bytesMoved,
        public ?StorageDiskEnum $target = null,
        public ?string $error = null,
    ) {}

    public static function moved(StorageDiskEnum $target, int $filesMoved, int $bytesMoved): self
    {
        return new self(true, false, false, $filesMoved, $bytesMoved, $target);
    }

    public static function alreadyThere(StorageDiskEnum $target): self
    {
        return new self(true, true, false, 0, 0, $target);
    }

    /** Another move holds this document. Not an error: try later. */
    public static function busy(): self
    {
        return new self(false, false, true, 0, 0);
    }

    public static function failed(string $error): self
    {
        return new self(false, false, false, 0, 0, null, $error);
    }
}
