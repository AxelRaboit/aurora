<?php

declare(strict_types=1);

namespace Aurora\Module\Ged\Trash;

use Aurora\Core\Trash\TrashSourceInterface;
use Aurora\Core\Trash\TrashSummary;
use Aurora\Module\Ged\DocumentFolder\Repository\DocumentFolderRepository;

/**
 * Counts the folders deleted on their own, exactly as the side panel lists
 * them: one that fell with its parent comes back with it, and counting it
 * separately would promise a row the panel does not show.
 */
final readonly class FoldersTrashSource implements TrashSourceInterface
{
    public function __construct(private DocumentFolderRepository $folderRepository) {}

    public function getModuleKey(): string
    {
        return 'ged';
    }

    public function getRequiredPrivilege(): string
    {
        return 'ged.folders.manage';
    }

    public function getSummary(): TrashSummary
    {
        return new TrashSummary(
            key: 'ged_folders',
            labelKey: 'backend.nav.ged_folders',
            icon: 'folder',
            count: $this->folderRepository->countTrashed(),
            oldestDeletedAt: $this->folderRepository->oldestTrashedAt(),
            // The folder trash lives in the panel beside the library, so this
            // is where a reader goes to find it.
            route: 'backend_ged_documents',
        );
    }
}
