<?php

declare(strict_types=1);

namespace Aurora\Module\Ged\DocumentFolder\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\Collection;

interface DocumentFolderInterface
{
    public function getId(): ?int;

    public function getName(): string;

    public function setName(string $name): static;

    public function getParent(): ?DocumentFolderInterface;

    public function setParent(?DocumentFolderInterface $parent): static;

    /** @return Collection<int, DocumentFolderInterface> */
    public function getChildren(): Collection;

    public function getPosition(): int;

    public function setPosition(int $position): static;

    public function getDeletedAt(): ?DateTimeImmutable;

    public function setDeletedAt(?DateTimeImmutable $deletedAt): static;

    /** Whether this folder sits in the trash rather than in the tree. */
    public function isTrashed(): bool;

    /** The folder whose deletion took this one down, if any. */
    public function getTrashedWithFolderId(): ?int;

    public function setTrashedWithFolderId(?int $trashedWithFolderId): static;
}
