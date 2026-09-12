<?php

declare(strict_types=1);

namespace Aurora\Core\Storage\Probe;

/**
 * One thing that was tried against a backend, and how it went.
 *
 * `key` is a translation key rather than a sentence: the same run is rendered
 * on a console for an operator and in a settings tab for an administrator who
 * may not read English.
 */
final readonly class StorageProbeStep
{
    public function __construct(
        public string $key,
        public bool $ok,
        public ?string $error = null,
    ) {}
}
