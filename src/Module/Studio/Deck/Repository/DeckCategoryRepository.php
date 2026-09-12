<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Deck\Repository;

use Aurora\Core\Repository\ResolveTargetEntityRepository;
use Aurora\Module\Studio\Deck\Entity\DeckCategory;
use Aurora\Module\Studio\Deck\Entity\DeckCategoryInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ResolveTargetEntityRepository<DeckCategoryInterface>
 */
class DeckCategoryRepository extends ResolveTargetEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeckCategory::class, DeckCategoryInterface::class);
    }

    /**
     * In the order somebody arranged them, name only to break a tie.
     *
     * @return list<DeckCategoryInterface>
     */
    public function findOrdered(): array
    {
        /** @var list<DeckCategoryInterface> $categories */
        $categories = $this->createQueryBuilder('c')
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $categories;
    }

    public function nextPosition(): int
    {
        /** @var int|null $max */
        $max = $this->createQueryBuilder('c')
            ->select('MAX(c.position)')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $max + 1;
    }
}
