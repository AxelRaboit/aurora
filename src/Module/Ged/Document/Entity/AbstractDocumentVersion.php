<?php

declare(strict_types=1);

namespace Aurora\Module\Ged\Document\Entity;

use Aurora\Core\Storage\Enum\StorageDiskEnum;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
abstract class AbstractDocumentVersion implements DocumentVersionInterface
{
    #[ORM\ManyToOne(targetEntity: DocumentInterface::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    protected DocumentInterface $document;

    // ── Self-owned file storage (mirror of AbstractDocument) ─────────────
    // Each version snapshots its own file under
    // `var/uploads/ged/documents/Y/m/<reference>-v<n>.<ext>`. No coupling
    // to the Media library.

    #[ORM\Column(length: 255)]
    protected string $filePath;

    /**
     * The backend holding this version's bytes, which is not necessarily the
     * one holding the document's current file: a document can be moved after a
     * version was recorded, and an older version may lag behind.
     */
    #[ORM\Column(length: 20, enumType: StorageDiskEnum::class, options: ['default' => 'local'])]
    protected StorageDiskEnum $storageDisk = StorageDiskEnum::Local;

    #[ORM\Column(length: 255)]
    protected string $fileName;

    #[ORM\Column(length: 255)]
    protected string $originalName;

    #[ORM\Column(length: 100)]
    protected string $mimeType;

    #[ORM\Column(type: Types::INTEGER)]
    protected int $size;

    #[ORM\Column]
    protected int $versionNumber;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    protected DateTimeImmutable $createdAt;

    #[ORM\Column(length: 255, nullable: true)]
    protected ?string $note = null;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getDocument(): DocumentInterface
    {
        return $this->document;
    }

    public function setDocument(DocumentInterface $document): static
    {
        $this->document = $document;

        return $this;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): static
    {
        $this->filePath = $filePath;

        return $this;
    }

    public function getStorageDisk(): StorageDiskEnum
    {
        return $this->storageDisk;
    }

    public function setStorageDisk(StorageDiskEnum $storageDisk): static
    {
        $this->storageDisk = $storageDisk;

        return $this;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): static
    {
        $this->fileName = $fileName;

        return $this;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): static
    {
        $this->originalName = $originalName;

        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getVersionNumber(): int
    {
        return $this->versionNumber;
    }

    public function setVersionNumber(int $versionNumber): static
    {
        $this->versionNumber = $versionNumber;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }
}
