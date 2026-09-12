<?php

declare(strict_types=1);

namespace Aurora\Module\Ged\DocumentCategory\Entity;

use Aurora\Module\Ged\DocumentCategory\Repository\DocumentCategoryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentCategoryRepository::class)]
#[ORM\Table(name: 'core_ged_document_categories')]
// Doctrine has no attribute for a partial index, so the `where` travels as a
// raw option: it reaches PostgreSQL through the schema tool and through
// `doctrine:schema:validate`, which is what keeps the mapping honest.
#[ORM\UniqueConstraint(name: 'uniq_ged_category_slug_live', columns: ['slug'], options: ['where' => '(deleted_at IS NULL)'])]
class DocumentCategory extends AbstractDocumentCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\SequenceGenerator(sequenceName: 'seq_core_ged_category_id', allocationSize: 1)]
    #[ORM\Column]
    protected ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
