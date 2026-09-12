<?php

declare(strict_types=1);

namespace Aurora\Module\Ged\Trash;

use Aurora\Core\Trash\TrashSourceInterface;
use Aurora\Core\Trash\TrashSummary;
use Aurora\Module\Ged\Document\Repository\DocumentRepository;

final readonly class DocumentsTrashSource implements TrashSourceInterface
{
    public function __construct(private DocumentRepository $documentRepository) {}

    public function getModuleKey(): string
    {
        return 'ged';
    }

    public function getRequiredPrivilege(): string
    {
        return 'ged.documents.view';
    }

    public function getSummary(): TrashSummary
    {
        return new TrashSummary(
            key: 'ged_documents',
            labelKey: 'backend.nav.documents',
            icon: 'folder-open',
            count: $this->documentRepository->countTrashed(),
            oldestDeletedAt: $this->documentRepository->oldestTrashedAt(),
            route: 'backend_ged_documents',
            routeParameters: ['trashed' => 1],
        );
    }
}
