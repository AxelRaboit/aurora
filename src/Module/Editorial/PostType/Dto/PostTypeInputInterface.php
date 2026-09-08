<?php

declare(strict_types=1);

namespace Aurora\Module\Editorial\PostType\Dto;

interface PostTypeInputInterface
{
    public function getSlug(): string;

    public function getLabel(): string;

    public function getDescription(): ?string;

    public function getIcon(): ?string;

    public function hasArchive(): bool;

    /** @return list<string> */
    public function getSupports(): array;

    public function getArchiveTitle(): ?string;

    public function getArchivePostId(): ?int;

    /** Whether the listing page still lists, once it has content of its own. */
    public function archiveShowsList(): bool;
}
