<?php

declare(strict_types=1);

namespace Aurora\Module\Editorial\Trash;

use Aurora\Core\Trash\TrashSourceInterface;
use Aurora\Core\Trash\TrashSummary;
use Aurora\Module\Editorial\Post\Repository\PostRepository;

final readonly class PostsTrashSource implements TrashSourceInterface
{
    public function __construct(private PostRepository $postRepository) {}

    public function getModuleKey(): string
    {
        return 'editorial';
    }

    public function getRequiredPrivilege(): string
    {
        return 'editorial.posts.view';
    }

    public function getSummary(): TrashSummary
    {
        return new TrashSummary(
            key: 'editorial_posts',
            labelKey: 'backend.nav.posts',
            icon: 'file-text',
            count: $this->postRepository->countTrashed(),
            oldestDeletedAt: $this->postRepository->oldestTrashedAt(),
            route: 'backend_editorial_posts',
            routeParameters: ['trashed' => 1],
        );
    }
}
