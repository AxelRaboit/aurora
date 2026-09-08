<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Dto;

interface ContractInputFactoryInterface
{
    /** @param array<string, mixed> $data */
    public function fromArray(array $data): ContractInputInterface;
}
