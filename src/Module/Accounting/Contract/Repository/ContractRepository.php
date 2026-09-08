<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Repository;

use Aurora\Core\Repository\ResolveTargetEntityRepository;
use Aurora\Module\Accounting\Contract\Entity\Contract;
use Aurora\Module\Accounting\Contract\Entity\ContractInterface;
use Doctrine\Common\Collections\Order;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ResolveTargetEntityRepository<ContractInterface>
 */
class ContractRepository extends ResolveTargetEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contract::class, ContractInterface::class);
    }

    /**
     * Newest first, with the customer joined.
     *
     * Unlike the customer list, this one is read to see what is happening now:
     * what went out this week, what is waiting for a signature.
     *
     * @return list<ContractInterface>
     */
    public function findAllForIndex(): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('cu')
            ->innerJoin('c.customer', 'cu')
            ->orderBy('c.createdAt', Order::Descending->value)
            ->getQuery()
            ->getResult();
    }

    /**
     * Every frozen contract, for the verification command.
     *
     * Ordered by id so a run reports in a stable order and two runs can be
     * compared line by line.
     *
     * @return list<ContractInterface>
     */
    public function findFrozen(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.frozenAt IS NOT NULL')
            ->orderBy('c.id', Order::Ascending->value)
            ->getQuery()
            ->getResult();
    }
}
