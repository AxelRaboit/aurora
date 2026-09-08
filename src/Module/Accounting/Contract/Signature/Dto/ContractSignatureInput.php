<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Signature\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * What somebody types to sign.
 *
 * The five fields the paper contract asks for, plus the consent and the code.
 * Every one is validated before the code is consumed, deliberately: a typo in a
 * name should not burn a code and force the signer to ask for another. The code
 * is a credential, not a form field, and spending it on a validation failure is
 * a cost with no security benefit.
 */
class ContractSignatureInput implements ContractSignatureInputInterface
{
    /** A PNG data URI, and nothing else. */
    public const string IMAGE_PREFIX = 'data:image/png;base64,';

    /**
     * The cap on the drawn signature.
     *
     * 400 KB of base64 is a generous full-width signature at retina density.
     * Anything past it is not a signature, and the column is encrypted, which
     * makes an unbounded blob expensive twice over.
     */
    public const int MAX_IMAGE_BYTES = 400_000;

    public function __construct(
        #[Assert\NotBlank(message: 'accounting.public.sign.errors.first_name_required')]
        #[Assert\Length(max: 100)]
        public readonly string $firstName = '',
        #[Assert\NotBlank(message: 'accounting.public.sign.errors.last_name_required')]
        #[Assert\Length(max: 100)]
        public readonly string $lastName = '',
        #[Assert\NotBlank(message: 'accounting.public.sign.errors.email_required')]
        #[Assert\Email(message: 'accounting.public.sign.errors.email_invalid')]
        #[Assert\Length(max: 180)]
        public readonly string $email = '',
        #[Assert\NotBlank(message: 'accounting.public.sign.errors.place_required')]
        #[Assert\Length(max: 120)]
        public readonly string $place = '',
        // The date the signer states, which the paper version leaves blank for
        // them to fill. Kept as a string and parsed by the manager, so an
        // unparseable one is a field error rather than a type error.
        #[Assert\NotBlank(message: 'accounting.public.sign.errors.date_required')]
        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/', message: 'accounting.public.sign.errors.date_invalid')]
        public readonly string $date = '',
        #[Assert\NotBlank(message: 'accounting.public.sign.errors.image_required')]
        #[Assert\Length(max: self::MAX_IMAGE_BYTES, maxMessage: 'accounting.public.sign.errors.image_too_large')]
        #[Assert\Regex(pattern: '#^data:image/png;base64,[A-Za-z0-9+/=]+$#', message: 'accounting.public.sign.errors.image_invalid')]
        public readonly string $signatureImage = '',
        // Refused rather than assumed. The scroll gate on the page is an
        // affordance a server cannot verify; this is the assertion that
        // travels with the payload and is stored with the signature.
        #[Assert\IsTrue(message: 'accounting.public.sign.errors.consent_required')]
        public readonly bool $consent = false,
        #[Assert\NotBlank(message: 'accounting.public.sign.errors.code_required')]
        public readonly string $code = '',
    ) {}

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPlace(): string
    {
        return $this->place;
    }

    public function getDate(): string
    {
        return $this->date;
    }

    public function getSignatureImage(): string
    {
        return $this->signatureImage;
    }

    public function hasConsented(): bool
    {
        return $this->consent;
    }

    public function getCode(): string
    {
        return $this->code;
    }
}
