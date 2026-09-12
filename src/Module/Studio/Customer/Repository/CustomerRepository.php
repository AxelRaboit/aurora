<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Customer\Repository;

use Aurora\Core\Repository\ResolveTargetEntityRepository;
use Aurora\Module\Studio\Customer\Entity\Customer;
use Aurora\Module\Studio\Customer\Entity\CustomerInterface;
use Doctrine\Common\Collections\Order;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ResolveTargetEntityRepository<CustomerInterface>
 */
class CustomerRepository extends ResolveTargetEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Customer::class, CustomerInterface::class);
    }

    /**
     * Every customer, by legal name.
     *
     * Alphabetical rather than newest-first: this list is read to find a known
     * company, not to see what changed today.
     *
     * @return list<CustomerInterface>
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('c')
            // The account is joined now rather than looked up per row: the list
            // shows whether a customer has a login, which is one query here and
            // one per customer without it.
            ->addSelect('u')
            ->leftJoin('c.user', 'u')
            ->orderBy('c.legalName', Order::Ascending->value)
            ->getQuery()
            ->getResult();
    }

    /**
     * The customer holding this SIRET, if any.
     *
     * Used to answer "this number is already recorded" with the row that holds
     * it, so the message can name the company instead of just refusing.
     */
    public function findOneBySiret(string $siret): ?CustomerInterface
    {
        return $this->findOneBy(['siret' => $siret]);
    }
}
