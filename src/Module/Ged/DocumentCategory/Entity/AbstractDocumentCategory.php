<?php

declare(strict_types=1);

namespace Aurora\Module\Ged\DocumentCategory\Entity;

use Aurora\Core\Timestampable\TimestampableTrait;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
#[ORM\HasLifecycleCallbacks]
abstract class AbstractDocumentCategory implements DocumentCategoryInterface
{
    use TimestampableTrait;

    #[ORM\Column(length: 150)]
    protected string $name;

    #[ORM\Column(length: 180, unique: true)]
    protected string $slug;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    protected ?string $description = null;

    /** When the category was moved to the trash. */
    #[ORM\Column(nullable: true)]
    protected ?DateTimeImmutable $deletedAt = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
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
}
