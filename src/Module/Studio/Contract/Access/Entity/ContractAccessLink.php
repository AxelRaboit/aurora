<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Contract\Access\Entity;

use Aurora\Module\Studio\Contract\Access\Repository\ContractAccessLinkRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContractAccessLinkRepository::class)]
#[ORM\Table(name: 'core_contract_access_links')]
class ContractAccessLink extends AbstractContractAccessLink
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\SequenceGenerator(sequenceName: 'seq_core_contract_access_link_id', allocationSize: 1)]
    #[ORM\Column]
    protected ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
