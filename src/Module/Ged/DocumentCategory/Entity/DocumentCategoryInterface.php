<?php

declare(strict_types=1);

namespace Aurora\Module\Ged\DocumentCategory\Entity;

use Aurora\Core\Timestampable\TimestampableInterface;
use DateTimeImmutable;

interface DocumentCategoryInterface extends TimestampableInterface
{
    public function getId(): ?int;

    public function getName(): string;

    public function setName(string $name): static;

    public function getSlug(): string;

    public function setSlug(string $slug): static;

    public function getDescription(): ?string;

    public function setDescription(?string $description): static;

    public function getDeletedAt(): ?DateTimeImmutable;

    public function setDeletedAt(?DateTimeImmutable $deletedAt): static;

    /** Whether this category sits in the trash rather than in the list. */
    public function isTrashed(): bool;
}
