<?php

declare(strict_types=1);

namespace Aurora\Module\Ged\DocumentFolder\Repository;

use Aurora\Core\Repository\ResolveTargetEntityRepository;
use Aurora\Module\Ged\DocumentFolder\Entity\DocumentFolder;
use Aurora\Module\Ged\DocumentFolder\Entity\DocumentFolderInterface;
use Doctrine\Common\Collections\Order;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ResolveTargetEntityRepository<DocumentFolderInterface> */
class DocumentFolderRepository extends ResolveTargetEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentFolder::class, DocumentFolderInterface::class);
    }

    /** @return list<DocumentFolderInterface> */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.deletedAt IS NULL')
            ->orderBy('f.position', Order::Ascending->value)
            ->addOrderBy('f.name', Order::Ascending->value)
            ->getQuery()
            ->getResult();
    }

    /** @return list<DocumentFolderInterface> */
    public function findRoots(): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.deletedAt IS NULL')
            ->andWhere('f.parent IS NULL')
            ->orderBy('f.position', Order::Ascending->value)
            ->addOrderBy('f.name', Order::Ascending->value)
            ->getQuery()
            ->getResult();
    }

    /**
     * The folders in the trash, most recently deleted first.
     *
     * Only those trashed on their own: a sub-folder that fell with its parent
     * is not an entry of its own in the trash, it is part of the branch its
     * parent restores. Listing it separately would offer a restore that puts
     * a folder back under a parent that is still deleted.
     *
     * @return list<DocumentFolderInterface>
     */
    public function findTrashedRoots(): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.deletedAt IS NOT NULL')
            ->andWhere('f.trashedWithFolderId IS NULL')
            ->orderBy('f.deletedAt', Order::Descending->value)
            ->getQuery()
            ->getResult();
    }

    /**
     * Everything that fell with this folder, sub-folders included.
     *
     * @return list<DocumentFolderInterface>
     */
    public function findTrashedWith(int $folderId): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.trashedWithFolderId = :id')
            ->setParameter('id', $folderId)
            ->getQuery()
            ->getResult();
    }

    /** @return list<DocumentFolderInterface> */
    public function findChildrenOf(int $folderId, bool $trashed = false): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.parent = :id')
            ->andWhere($trashed ? 'f.deletedAt IS NOT NULL' : 'f.deletedAt IS NULL')
            ->setParameter('id', $folderId)
            ->getQuery()
            ->getResult();
    }

    public function countTrashed(): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.deletedAt IS NOT NULL')
            ->andWhere('f.trashedWithFolderId IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
