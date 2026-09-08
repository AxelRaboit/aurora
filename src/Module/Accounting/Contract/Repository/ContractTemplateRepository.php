<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Repository;

use Aurora\Core\Repository\ResolveTargetEntityRepository;
use Aurora\Module\Accounting\Contract\Entity\ContractTemplate;
use Aurora\Module\Accounting\Contract\Entity\ContractTemplateInterface;
use Aurora\Module\Accounting\Contract\Enum\ContractTemplateKindEnum;
use Doctrine\Common\Collections\Order;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ResolveTargetEntityRepository<ContractTemplateInterface>
 */
class ContractTemplateRepository extends ResolveTargetEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContractTemplate::class, ContractTemplateInterface::class);
    }

    /**
     * Every template with its versions, bodies before annexes.
     *
     * The versions are joined rather than looked up per row: the list shows
     * each template's state (published number, draft open or not), which is
     * one query here and one per template without it.
     *
     * @return list<ContractTemplateInterface>
     */
    public function findAllForIndex(): array
    {
        return $this->createQueryBuilder('t')
            ->addSelect('v')
            ->leftJoin('t.versions', 'v')
            ->orderBy('t.kind', Order::Ascending->value)
            ->addOrderBy('t.name', Order::Ascending->value)
            ->getQuery()
            ->getResult();
    }

    /**
     * The templates a contract can be built from today: live, and published.
     *
     * An archived template is out, and so is one that has only ever been a
     * draft - offering either would mean offering a document that cannot be
     * sent.
     *
     * @return list<ContractTemplateInterface>
     */
    public function findSelectable(ContractTemplateKindEnum $kind): array
    {
        return $this->createQueryBuilder('t')
            ->innerJoin('t.versions', 'v')
            ->andWhere('t.kind = :kind')
            ->andWhere('t.archivedAt IS NULL')
            ->andWhere('v.publishedAt IS NOT NULL')
            ->setParameter('kind', $kind)
            ->orderBy('t.name', Order::Ascending->value)
            ->getQuery()
            ->getResult();
    }
}
