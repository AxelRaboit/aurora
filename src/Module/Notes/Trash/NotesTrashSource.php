<?php

declare(strict_types=1);

namespace Aurora\Module\Notes\Trash;

use Aurora\Core\Trash\TrashSourceInterface;
use Aurora\Core\Trash\TrashSummary;
use Aurora\Module\Notes\Markdown\Repository\MarkdownNoteRepository;
use Aurora\Module\Platform\User\Entity\CoreUserInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * The reader's own notes, and nobody else's.
 *
 * Notes belong to their author: the other rows on the overview count what the
 * installation holds, this one counts what the person looking at it deleted.
 * Without a user in the session there is nothing to count, and the row is
 * shown empty rather than hidden, so the page does not change shape depending
 * on how it was reached.
 */
final readonly class NotesTrashSource implements TrashSourceInterface
{
    public function __construct(
        private MarkdownNoteRepository $noteRepository,
        private Security $security,
    ) {}

    public function getModuleKey(): string
    {
        return 'notes';
    }

    public function getRequiredPrivilege(): string
    {
        return 'notes.markdown.use';
    }

    public function getSummary(): TrashSummary
    {
        $user = $this->security->getUser();
        $mine = $user instanceof CoreUserInterface;

        return new TrashSummary(
            key: 'notes_markdown',
            labelKey: 'backend.nav.notes_markdown',
            icon: 'notebook-pen',
            count: $mine ? $this->noteRepository->countTrashedForUser($user) : 0,
            oldestDeletedAt: $mine ? $this->noteRepository->oldestTrashedAtForUser($user) : null,
            // No parameter: the notes trash is a panel over the workspace
            // rather than a second list, so the destination is the workspace.
            route: 'backend_notes_markdown',
        );
    }
}
