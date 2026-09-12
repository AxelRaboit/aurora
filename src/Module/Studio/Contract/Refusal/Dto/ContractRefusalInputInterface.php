<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Contract\Refusal\Dto;

interface ContractRefusalInputInterface
{
    public function getReason(): string;
}
