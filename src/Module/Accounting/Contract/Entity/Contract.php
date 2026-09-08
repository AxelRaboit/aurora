<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Entity;

use Aurora\Module\Accounting\Contract\Repository\ContractRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContractRepository::class)]
#[ORM\Table(name: 'core_contracts')]
class Contract extends AbstractContract
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\SequenceGenerator(sequenceName: 'seq_core_contract_id', allocationSize: 1)]
    #[ORM\Column]
    protected ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
