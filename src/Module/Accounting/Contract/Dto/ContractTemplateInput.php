<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Dto;

use Aurora\Module\Accounting\Contract\Enum\ContractTemplateKindEnum;
use Symfony\Component\Validator\Constraints as Assert;

class ContractTemplateInput implements ContractTemplateInputInterface
{
    public function __construct(
        #[Assert\NotBlank(message: 'backend.accounting.contract_templates.errors.name_required')]
        #[Assert\Length(max: 180, maxMessage: 'backend.accounting.contract_templates.errors.name_too_long')]
        public readonly string $name = '',
        public readonly ContractTemplateKindEnum $kind = ContractTemplateKindEnum::Body,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getKind(): ContractTemplateKindEnum
    {
        return $this->kind;
    }
}
