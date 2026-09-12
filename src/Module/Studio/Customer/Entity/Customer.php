<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Customer\Entity;

use Aurora\Module\Studio\Customer\Repository\CustomerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CustomerRepository::class)]
#[ORM\Table(name: 'core_customers')]
class Customer extends AbstractCustomer
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\SequenceGenerator(sequenceName: 'seq_core_customer_id', allocationSize: 1)]
    #[ORM\Column]
    protected ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
