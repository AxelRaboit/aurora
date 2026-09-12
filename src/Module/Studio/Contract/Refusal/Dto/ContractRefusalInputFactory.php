<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Contract\Refusal\Dto;

use Aurora\Core\Support\Str;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ContractRefusalInputFactoryInterface::class)]
class ContractRefusalInputFactory implements ContractRefusalInputFactoryInterface
{
    /** @param array<string, mixed> $data */
    public function fromArray(array $data): ContractRefusalInputInterface
    {
        return new ContractRefusalInput(
            reason: Str::trimFromArray($data, 'reason'),
        );
    }
}
