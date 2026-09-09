<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Termination\Dto;

interface ContractTerminationInputInterface
{
    public function getNoticedAt(): string;

    public function getEffectiveAt(): string;

    public function getOrigin(): string;

    public function getReason(): string;
}
