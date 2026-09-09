<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Refusal\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * What somebody types to decline.
 *
 * One optional field, and the fact that it is optional is the design. Asking a
 * person to justify a refusal before accepting it would be a small piece of
 * coercion in a document whose whole subject is consent; the reason is offered
 * because it is useful to the provider, not required because it is owed.
 *
 * No code and no consent box either. Refusing creates no obligation and the
 * provider can send the contract again, so the ceremony that protects a
 * signature would only stand between somebody and the word no.
 */
class ContractRefusalInput implements ContractRefusalInputInterface
{
    /**
     * Long enough for a paragraph, short enough that the column is not a
     * place to paste a document.
     */
    public const int MAX_REASON_LENGTH = 2000;

    public function __construct(
        #[Assert\Length(max: self::MAX_REASON_LENGTH, maxMessage: 'accounting.public.refuse.errors.reason_too_long')]
        public readonly string $reason = '',
    ) {}

    public function getReason(): string
    {
        return $this->reason;
    }
}
