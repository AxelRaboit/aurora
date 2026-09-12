<?php

declare(strict_types=1);

namespace Aurora\Module\Ged\Document\Entity;

use Aurora\Core\Storage\Enum\StorageDiskEnum;
use DateTimeImmutable;

interface DocumentVersionInterface
{
    public function getId(): ?int;

    public function getDocument(): DocumentInterface;

    public function setDocument(DocumentInterface $document): static;

    public function getFilePath(): string;

    public function setFilePath(string $filePath): static;

    public function getStorageDisk(): StorageDiskEnum;

    public function setStorageDisk(StorageDiskEnum $storageDisk): static;

    public function getFileName(): string;

    public function setFileName(string $fileName): static;

    public function getOriginalName(): string;

    public function setOriginalName(string $originalName): static;

    public function getMimeType(): string;

    public function setMimeType(string $mimeType): static;

    public function getSize(): int;

    public function setSize(int $size): static;

    public function getVersionNumber(): int;

    public function setVersionNumber(int $versionNumber): static;

    public function getCreatedAt(): DateTimeImmutable;

    public function getNote(): ?string;

    public function setNote(?string $note): static;
}
