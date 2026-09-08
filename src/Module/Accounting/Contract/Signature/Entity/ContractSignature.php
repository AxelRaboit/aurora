<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Signature\Entity;

use Aurora\Module\Accounting\Contract\Signature\Repository\ContractSignatureRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContractSignatureRepository::class)]
#[ORM\Table(name: 'core_contract_signatures')]
#[ORM\UniqueConstraint(name: 'uniq_contract_signature_role', columns: ['contract_id', 'role'])]
class ContractSignature extends AbstractContractSignature
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\SequenceGenerator(sequenceName: 'seq_core_contract_signature_id', allocationSize: 1)]
    #[ORM\Column]
    protected ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
