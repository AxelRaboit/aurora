<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Signature\Dto;

interface ContractSignatureInputFactoryInterface
{
    /** @param array<string, mixed> $data */
    public function fromArray(array $data): ContractSignatureInputInterface;
}
