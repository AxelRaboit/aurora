<?php

declare(strict_types=1);

namespace Aurora\Module\Ged\Trash;

use Aurora\Core\Trash\TrashSourceInterface;
use Aurora\Core\Trash\TrashSummary;
use Aurora\Module\Ged\DocumentCategory\Repository\DocumentCategoryRepository;

final readonly class CategoriesTrashSource implements TrashSourceInterface
{
    public function __construct(private DocumentCategoryRepository $categoryRepository) {}

    public function getModuleKey(): string
    {
        return 'ged';
    }

    public function getRequiredPrivilege(): string
    {
        return 'ged.categories.view';
    }

    public function getSummary(): TrashSummary
    {
        return new TrashSummary(
            key: 'ged_categories',
            labelKey: 'backend.nav.ged_categories',
            icon: 'tags',
            count: $this->categoryRepository->countTrashed(),
            oldestDeletedAt: $this->categoryRepository->oldestTrashedAt(),
            route: 'backend_ged_categories',
            routeParameters: ['trashed' => 1],
        );
    }
}
