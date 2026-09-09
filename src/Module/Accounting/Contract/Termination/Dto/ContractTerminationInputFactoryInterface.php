<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Termination\Dto;

interface ContractTerminationInputFactoryInterface
{
    /** @param array<string, mixed> $data */
    public function fromArray(array $data): ContractTerminationInputInterface;
}
