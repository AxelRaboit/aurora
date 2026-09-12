<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Contract\Signature\Exception;

use LogicException;

use function sprintf;

/**
 * Something tried to write to a signature.
 *
 * A signature is the one row in this module that is append-only in the strict
 * sense: it records what somebody did at a moment, and there is no version of
 * "correcting" it that is not falsifying it. A mistaken signature is answered
 * by a new contract, never by an edit.
 *
 * The third of the module's immutability guards, and the strictest. A template
 * version stops changing when it is published, a contract when it is sealed,
 * and a signature the instant it exists.
 */
final class SignedContractIsImmutableException extends LogicException
{
    public static function forSignature(?int $signatureId): self
    {
        return new self(sprintf(
            'Signature %s records what somebody did and cannot be written to. A mistake is answered by a new contract.',
            $signatureId ?? 'unsaved',
        ));
    }
}
