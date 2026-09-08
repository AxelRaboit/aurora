<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Dto;

use Aurora\Core\Support\Str;
use Aurora\Module\Accounting\Contract\Enum\ContractTemplateKindEnum;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ContractTemplateInputFactoryInterface::class)]
class ContractTemplateInputFactory implements ContractTemplateInputFactoryInterface
{
    /** @param array<string, mixed> $data */
    public function fromArray(array $data): ContractTemplateInputInterface
    {
        return new ContractTemplateInput(
            name: Str::trimFromArray($data, 'name'),
            // An unreadable kind falls back to a body rather than throwing:
            // the picker only offers two values, and a body is the one that
            // stands on its own, so it is the safer of the two to assume.
            kind: ContractTemplateKindEnum::tryFrom(Str::trimFromArray($data, 'kind'))
                ?? ContractTemplateKindEnum::Body,
        );
    }
}
