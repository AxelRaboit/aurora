<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Dto;

use Aurora\Module\Accounting\Contract\Enum\ContractTemplateKindEnum;

interface ContractTemplateInputInterface
{
    public function getName(): string;

    public function getKind(): ContractTemplateKindEnum;
}
