<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Signature\Dto;

use Aurora\Core\Support\Str;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

use function mb_trim;
use function preg_replace;

#[AsAlias(ContractSignatureInputFactoryInterface::class)]
class ContractSignatureInputFactory implements ContractSignatureInputFactoryInterface
{
    /** @param array<string, mixed> $data */
    public function fromArray(array $data): ContractSignatureInputInterface
    {
        return new ContractSignatureInput(
            firstName: Str::trimFromArray($data, 'firstName'),
            lastName: Str::trimFromArray($data, 'lastName'),
            email: Str::emailFromArray($data, 'email'),
            place: Str::trimFromArray($data, 'place'),
            date: Str::trimFromArray($data, 'date'),
            // Whitespace stripped rather than trimmed: a data URI copied
            // through a form can carry newlines, and they are not part of it.
            signatureImage: (string) preg_replace('/\s+/', '', (string) ($data['signatureImage'] ?? '')),
            consent: true === ($data['consent'] ?? false),
            // Spaces and dashes out: people type "123 456" from a mail, and
            // refusing that would be refusing a correct code.
            code: (string) preg_replace('/[\s-]/', '', mb_trim((string) ($data['code'] ?? ''))),
        );
    }
}
