<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Contract\Exception;

use LogicException;

use function sprintf;

/**
 * Something asked for a second PDF of the same contract.
 *
 * Refused loudly rather than answered with the existing path, because the two
 * are different questions and confusing them is how a regenerated document
 * ends up differing from the one the parties were sent. A caller that wants the
 * file reads `getPdfPath()`; a caller that wants to generate one has
 * misunderstood the state it is in.
 *
 * The fourth immutability guard of the module, after the template version, the
 * contract and the signature.
 */
final class ContractPdfAlreadyGeneratedException extends LogicException
{
    public static function forContract(?string $reference, ?string $path): self
    {
        return new self(sprintf(
            'Contract %s already has a PDF at %s. It is generated once, at the countersignature, and read afterwards.',
            $reference ?? 'without a reference',
            $path ?? 'an unknown path',
        ));
    }
}
