<?php

declare(strict_types=1);

namespace Aurora\Module\Ged\DocumentFolder\Entity;

use Aurora\Core\Timestampable\TimestampableTrait;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
#[ORM\HasLifecycleCallbacks]
abstract class AbstractDocumentFolder implements DocumentFolderInterface
{
    use TimestampableTrait;

    #[ORM\Column(length: 150)]
    protected string $name;

    #[ORM\ManyToOne(targetEntity: DocumentFolderInterface::class, inversedBy: 'children')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    protected ?DocumentFolderInterface $parent = null;

    /** @var Collection<int, DocumentFolderInterface> */
    #[ORM\OneToMany(targetEntity: DocumentFolderInterface::class, mappedBy: 'parent')]
    protected Collection $children;

    #[ORM\Column(options: ['default' => 0])]
    protected int $position = 0;

    /** When the folder was moved to the trash. */
    #[ORM\Column(nullable: true)]
    protected ?DateTimeImmutable $deletedAt = null;

    /**
     * The folder whose deletion took this one down with it.
     *
     * Null when somebody trashed this folder directly. Restoring folder X
     * brings back everything carrying X here, and nothing else: without it,
     * restoring a branch would also resurrect what had been deleted on its own
     * days earlier, and the only way to tell the two apart would be comparing
     * timestamps and hoping.
     */
    #[ORM\Column(nullable: true)]
    protected ?int $trashedWithFolderId = null;

    public function __construct()
    {
        $this->children = new ArrayCollection();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function isTrashed(): bool
    {
        return $this->deletedAt instanceof DateTimeImmutable;
    }

    public function getTrashedWithFolderId(): ?int
    {
        return $this->trashedWithFolderId;
    }

    public function setTrashedWithFolderId(?int $trashedWithFolderId): static
    {
        $this->trashedWithFolderId = $trashedWithFolderId;

        return $this;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getParent(): ?DocumentFolderInterface
    {
        return $this->parent;
    }

    public function setParent(?DocumentFolderInterface $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }
}
