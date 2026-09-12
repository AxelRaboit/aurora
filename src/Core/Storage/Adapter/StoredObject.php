<?php

declare(strict_types=1);

namespace Aurora\Core\Storage\Adapter;

use DateTimeImmutable;

/**
 * One object in storage, described without fetching its bytes.
 *
 * Listings yield these rather than bare keys, and that is the whole point.
 * On a local disk, asking a file its size after listing it costs a `stat()`
 * and nobody notices. On object storage the same question is a billed HTTP
 * request, so a listing that returns keys alone turns one call over a
 * thousand objects into a thousand and one.
 *
 * Every adapter therefore fills `size` and `lastModifiedAt` from data it
 * already holds. `checksum` stays nullable because not every backend offers
 * one cheaply; a caller that needs certainty compares bytes.
 */
final readonly class StoredObject
{
    public function __construct(
        public string $key,
        public int $size,
        public DateTimeImmutable $lastModifiedAt,
        public ?string $checksum = null,
    ) {}
}
