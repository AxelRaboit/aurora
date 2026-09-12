<?php

declare(strict_types=1);

namespace Aurora\Module\Ged\DocumentCategory\Manager;

use Aurora\Module\Dev\Audit\Service\AuditLogger;
use Aurora\Module\Ged\DocumentCategory\Dto\DocumentCategoryInputInterface;
use Aurora\Module\Ged\DocumentCategory\Entity\DocumentCategory;
use Aurora\Module\Ged\DocumentCategory\Entity\DocumentCategoryInterface;
use Aurora\Module\Ged\DocumentCategory\Repository\DocumentCategoryRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[AsAlias(DocumentCategoryManagerInterface::class)]
class DocumentCategoryManager implements DocumentCategoryManagerInterface
{
    /** Prefix that parks a trashed category's slug out of the unique index. */
    private const string TRASHED_SLUG_PREFIX = 'trashed-';

    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
        protected readonly DocumentCategoryRepository $categoryRepository,
        protected readonly AuditLogger $auditLogger,
    ) {}

    public function create(DocumentCategoryInputInterface $input): DocumentCategoryInterface
    {
        $category = $this->createDocumentCategory();
        $this->applyInput($category, $input);
        $this->entityManager->persist($category);
        $this->entityManager->flush();

        $this->auditCreated($category);

        return $category;
    }

    public function update(DocumentCategoryInterface $category, DocumentCategoryInputInterface $input): void
    {
        $this->applyInput($category, $input);
        $this->entityManager->flush();

        $this->auditUpdated($category);
    }

    /**
     * Moves a category to the trash.
     *
     * The documents keep pointing at it, which is what lets a restore put the
     * classification back: the `SET NULL` that used to scatter them only fires
     * on a real delete, and it was the part nobody could undo.
     *
     * The slug is parked out of the way at the same time. It is unique across
     * the table, and a trashed category holding "factures" would make creating
     * a new one under that name fail on a constraint the screen cannot explain,
     * over a row nothing displays.
     */
    public function delete(DocumentCategoryInterface $category): void
    {
        if ($category->isTrashed()) {
            return;
        }

        $category->setDeletedAt(new DateTimeImmutable());
        $category->setSlug(self::TRASHED_SLUG_PREFIX.$category->getId().'-'.$category->getSlug());

        $this->entityManager->flush();

        $this->auditTrashed($category);
    }

    /**
     * Brings a category back, under a slug that is free again.
     *
     * Recomputed from the name rather than restored from the parked value: the
     * original may well have been taken by a category created in the meantime,
     * and coming back as `factures-2` beats failing on a constraint.
     */
    public function restore(DocumentCategoryInterface $category): void
    {
        if (!$category->isTrashed()) {
            return;
        }

        $category->setDeletedAt(null);
        $category->setSlug($this->uniqueSlug($category->getName(), $category->getId()));

        $this->entityManager->flush();

        $this->auditRestored($category);
    }

    /**
     * Deletes a category for good.
     *
     * The documents that carried it lose it here, through the database's own
     * `SET NULL`. That is the destructive half the trash exists to postpone.
     */
    public function forceDelete(DocumentCategoryInterface $category): void
    {
        $this->auditDeleted($category);

        $this->entityManager->remove($category);
        $this->entityManager->flush();
    }

    public function emptyTrash(): int
    {
        $categories = $this->categoryRepository->findAllTrashed();
        if ([] === $categories) {
            return 0;
        }

        foreach ($categories as $category) {
            $this->auditDeleted($category);
            $this->entityManager->remove($category);
        }

        $this->entityManager->flush();

        return count($categories);
    }

    protected function createDocumentCategory(): DocumentCategoryInterface
    {
        return new DocumentCategory();
    }

    protected function applyInput(DocumentCategoryInterface $category, DocumentCategoryInputInterface $input): void
    {
        $category->setName($input->getName());
        $category->setDescription($input->getDescription());
        $category->setSlug($this->uniqueSlug($input->getName(), $category->getId()));
    }

    protected function auditTrashed(DocumentCategoryInterface $category): void
    {
        $this->auditLogger->log('ged', 'category.trashed', 'DocumentCategory', $category->getId(), $this->auditPayload($category));
    }

    protected function auditRestored(DocumentCategoryInterface $category): void
    {
        $this->auditLogger->log('ged', 'category.restored', 'DocumentCategory', $category->getId(), $this->auditPayload($category));
    }

    protected function auditCreated(DocumentCategoryInterface $category): void
    {
        $this->auditLogger->log('ged', 'category.created', 'DocumentCategory', $category->getId(), $this->auditPayload($category));
    }

    protected function auditUpdated(DocumentCategoryInterface $category): void
    {
        $this->auditLogger->log('ged', 'category.updated', 'DocumentCategory', $category->getId(), $this->auditPayload($category));
    }

    protected function auditDeleted(DocumentCategoryInterface $category): void
    {
        $this->auditLogger->log('ged', 'category.deleted', 'DocumentCategory', $category->getId(), $this->auditPayload($category));
    }

    protected function auditPayload(DocumentCategoryInterface $category): array
    {
        return ['name' => $category->getName()];
    }

    private function uniqueSlug(string $name, ?int $excludeId): string
    {
        $base = mb_strtolower(new AsciiSlugger()->slug($name)->toString());
        $slug = $base;
        $i = 2;
        while ($this->slugExists($slug, $excludeId)) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?int $excludeId): bool
    {
        $qb = $this->categoryRepository->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.slug = :slug')
            ->setParameter('slug', $slug);

        if (null !== $excludeId) {
            $qb->andWhere('c.id != :id')->setParameter('id', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
