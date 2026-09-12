<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Contract\Termination\Dto;

use Aurora\Core\Support\Str;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ContractTerminationInputFactoryInterface::class)]
class ContractTerminationInputFactory implements ContractTerminationInputFactoryInterface
{
    /** @param array<string, mixed> $data */
    public function fromArray(array $data): ContractTerminationInputInterface
    {
        return new ContractTerminationInput(
            noticedAt: Str::trimFromArray($data, 'noticedAt'),
            effectiveAt: Str::trimFromArray($data, 'effectiveAt'),
            origin: Str::trimFromArray($data, 'origin'),
            reason: Str::trimFromArray($data, 'reason'),
        );
    }
}
