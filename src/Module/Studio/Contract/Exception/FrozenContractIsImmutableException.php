<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Contract\Exception;

use LogicException;

use function sprintf;

/**
 * Something tried to write to a contract that has already been frozen.
 *
 * The counterpart of {@see PublishedVersionIsImmutableException}, one level
 * down: a template version stops changing when it is published, and a contract
 * stops changing when it is frozen, which happens before its link goes out.
 *
 * A `LogicException` because no user action should be able to reach it. The
 * screens do not offer editing a sent contract; this is what answers a stale
 * tab, a replayed request, or a mistake in code written later.
 */
final class FrozenContractIsImmutableException extends LogicException
{
    public static function forContract(?int $contractId, ?string $reference): self
    {
        return new self(sprintf(
            'Contract %s (id %s) is frozen and cannot be written to. A sent contract is what somebody was asked to sign.',
            $reference ?? 'without a reference',
            $contractId ?? 'unsaved',
        ));
    }
}
