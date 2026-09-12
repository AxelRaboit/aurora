<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Contract\Exception;

use LogicException;

use function sprintf;

/**
 * Something tried to write to a published template version.
 *
 * A `LogicException` rather than a validation error, and that is the whole
 * point of the class: this is not a person typing something wrong, it is code
 * doing something that must never happen. A contract signed against version 2
 * has to still say in a year what it said on the day it was signed, and the
 * only way to guarantee that is for the write to fail loudly rather than for
 * everyone to remember not to attempt it.
 *
 * Editing a published version is not forbidden as a workflow - it is done by
 * opening a new draft, which is what the manager offers.
 */
final class PublishedVersionIsImmutableException extends LogicException
{
    public static function forVersion(?int $versionId, int $number): self
    {
        return new self(sprintf(
            'Version %d (id %s) is published and cannot be written to. Open a new draft instead.',
            $number,
            $versionId ?? 'unsaved',
        ));
    }
}
