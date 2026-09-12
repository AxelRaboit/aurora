<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Contract\Entity;

use Aurora\Module\Studio\Contract\Repository\ContractTemplateVersionTranslationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContractTemplateVersionTranslationRepository::class)]
#[ORM\Table(name: 'core_contract_template_version_translations')]
#[ORM\UniqueConstraint(name: 'uniq_contract_version_translation_locale', columns: ['version_id', 'locale'])]
class ContractTemplateVersionTranslation extends AbstractContractTemplateVersionTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\SequenceGenerator(sequenceName: 'seq_core_contract_version_translation_id', allocationSize: 1)]
    #[ORM\Column]
    protected ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
