<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Contract\Entity;

use Aurora\Module\Studio\Contract\Repository\ContractTemplateVersionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContractTemplateVersionRepository::class)]
#[ORM\Table(name: 'core_contract_template_versions')]
#[ORM\UniqueConstraint(name: 'uniq_contract_template_version_number', columns: ['template_id', 'number'])]
class ContractTemplateVersion extends AbstractContractTemplateVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\SequenceGenerator(sequenceName: 'seq_core_contract_template_version_id', allocationSize: 1)]
    #[ORM\Column]
    protected ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
