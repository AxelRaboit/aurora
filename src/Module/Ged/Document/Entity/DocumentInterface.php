<?php

declare(strict_types=1);

namespace Aurora\Module\Ged\Document\Entity;

use Aurora\Core\Timestampable\TimestampableInterface;
use Aurora\Module\Ged\DocumentCategory\Entity\DocumentCategoryInterface;
use Aurora\Module\Ged\DocumentFolder\Entity\DocumentFolderInterface;
use Aurora\Module\Ged\DocumentTag\Entity\DocumentTagInterface;
use Aurora\Module\Ged\Enum\DocumentStatusEnum;
use Doctrine\Common\Collections\Collection;

interface DocumentInterface extends TimestampableInterface
{
    public function getId(): ?int;

    public function getReference(): ?string;

    public function setReference(?string $reference): static;

    public function getTitle(): string;

    public function setTitle(string $title): static;

    public function getDescription(): ?string;

    public function setDescription(?string $description): static;

    public function getStatus(): DocumentStatusEnum;

    public function setStatus(DocumentStatusEnum $status): static;

    public function getCategory(): ?DocumentCategoryInterface;

    public function setCategory(?DocumentCategoryInterface $category): static;

    public function getFilePath(): ?string;

    public function setFilePath(?string $filePath): static;

    public function getFileName(): ?string;

    public function setFileName(?string $fileName): static;

    public function getOriginalName(): ?string;

    public function setOriginalName(?string $originalName): static;

    public function getMimeType(): ?string;

    public function setMimeType(?string $mimeType): static;

    public function getSize(): ?int;

    public function setSize(?int $size): static;

    public function getWidth(): ?int;

    public function setWidth(?int $width): static;

    public function getHeight(): ?int;

    public function setHeight(?int $height): static;

    public function getThumbnailPath(): ?string;

    public function setThumbnailPath(?string $thumbnailPath): static;

    public function getAlt(): ?string;

    public function setAlt(?string $alt): static;

    public function getCaption(): ?string;

    public function setCaption(?string $caption): static;

    /** @return Collection<int, DocumentTagInterface> */
    public function getTags(): Collection;

    public function addTag(DocumentTagInterface $tag): static;

    public function removeTag(DocumentTagInterface $tag): static;

    public function clearTags(): static;

    public function getFolder(): ?DocumentFolderInterface;

    public function setFolder(?DocumentFolderInterface $folder): static;

    public function getFocalX(): ?float;

    public function setFocalX(?float $focalX): static;

    public function getFocalY(): ?float;

    public function setFocalY(?float $focalY): static;

    /** @return array<string, string> */
    public function getVariants(): array;

    /** @param array<string, string> $variants */
    public function setVariants(array $variants): static;

    public function getSourceUrl(): ?string;

    public function setSourceUrl(?string $sourceUrl): static;

    /** True when the bytes live on a provider's CDN rather than in var/uploads. */
    public function isRemote(): bool;

    public function getAttributionName(): ?string;

    public function setAttributionName(?string $attributionName): static;

    public function getAttributionUrl(): ?string;

    public function setAttributionUrl(?string $attributionUrl): static;
}
