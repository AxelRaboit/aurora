<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Customer\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

use function array_sum;
use function mb_str_split;
use function preg_match;
use function str_starts_with;

final class SiretValidator extends ConstraintValidator
{
    /**
     * La Poste's SIREN, whose SIRET numbers are the registry's documented
     * exception to the checksum: theirs follow a different rule, the digits
     * summing to a multiple of five.
     *
     * Numbers on this SIREN are accepted when EITHER rule holds, not only the
     * second. Some of them satisfy Luhn as well, and refusing those in the
     * name of the exception would reject a valid counterparty - the failure
     * this branch exists to prevent. Two rules on one SIREN is still a check;
     * it is the falsest of the two negatives that is worth avoiding here.
     */
    private const string LA_POSTE_SIREN = '356000000';

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Siret) {
            throw new UnexpectedTypeException($constraint, Siret::class);
        }

        // An absent SIRET is not an invalid one: the field is optional, and
        // `NotBlank` is the constraint that would say otherwise.
        if (null === $value || '' === $value) {
            return;
        }

        $siret = (string) $value;

        if (1 !== preg_match('/^\d{14}$/', $siret)) {
            $this->context->buildViolation($constraint->message)->addViolation();

            return;
        }

        if (!$this->hasValidChecksum($siret)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }

    /**
     * Luhn over the fourteen digits, right to left, doubling every second one.
     */
    private function hasValidChecksum(string $siret): bool
    {
        if ($this->passesLuhn($siret)) {
            return true;
        }

        return str_starts_with($siret, self::LA_POSTE_SIREN)
            && 0 === array_sum(array_map(intval(...), mb_str_split($siret))) % 5;
    }

    private function passesLuhn(string $siret): bool
    {
        $total = 0;

        foreach (mb_str_split($siret) as $index => $digit) {
            $digit = (int) $digit;

            // Positions are counted from the left here, and the fourteen-digit
            // length is already guaranteed, so the even indexes are the ones
            // Luhn doubles when counting from the right.
            if (0 === $index % 2) {
                $digit *= 2;

                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $total += $digit;
        }

        return 0 === $total % 10;
    }
}
