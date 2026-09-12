<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Contract\Refusal\Dto;

interface ContractRefusalInputFactoryInterface
{
    /** @param array<string, mixed> $data */
    public function fromArray(array $data): ContractRefusalInputInterface;
}
