<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Refusal\Dto;

interface ContractRefusalInputInterface
{
    public function getReason(): string;
}
