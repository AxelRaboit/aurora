<?php

declare(strict_types=1);

namespace Aurora\Core\Storage\Exception;

use RuntimeException;

use function sprintf;

/**
 * A storage operation that could not be carried out.
 *
 * Raised for the failures a caller cannot do anything about in the moment - a
 * key that is missing when it should not be, a write that did not land, a
 * backend that refused. Absence on its own is not one of these: `exists()`
 * answers false and `delete()` returns quietly, because asking whether
 * something is there, and removing something twice, are both legitimate.
 */
class StorageException extends RuntimeException
{
    public static function missingKey(string $key): self
    {
        return new self(sprintf('No object stored at key "%s".', $key));
    }

    public static function writeFailed(string $key, string $reason): self
    {
        return new self(sprintf('Could not write "%s": %s', $key, $reason));
    }

    public static function readFailed(string $key, string $reason): self
    {
        return new self(sprintf('Could not read "%s": %s', $key, $reason));
    }
}
