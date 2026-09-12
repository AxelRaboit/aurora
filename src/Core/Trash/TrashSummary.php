<?php

declare(strict_types=1);

namespace Aurora\Core\Trash;

use DateTimeImmutable;

/**
 * What one trash has in it, said in the few terms the overview needs.
 *
 * Deliberately a count and a date rather than the rows themselves. The
 * overview answers "is there anything waiting, and is it about to expire",
 * which five listings would answer at five times the cost, in a page nobody
 * opens twice a week. Whoever wants the rows follows the link and lands in the
 * screen that owns them, where restoring and deleting already live.
 */
final readonly class TrashSummary
{
    /**
     * @param string                $key             stable id for the row, e.g. `ged_documents`
     * @param string                $labelKey        translation key naming what is in this trash
     * @param string                $icon            lucide icon name, as the side menu uses
     * @param int                   $count           rows currently in the trash
     * @param ?DateTimeImmutable    $oldestDeletedAt when the oldest of them was deleted, null when empty
     * @param ?string               $route           the screen that owns this trash, null when it has none
     * @param array<string, scalar> $routeParameters what that route needs to open on its trash
     */
    public function __construct(
        public string $key,
        public string $labelKey,
        public string $icon,
        public int $count,
        public ?DateTimeImmutable $oldestDeletedAt = null,
        public ?string $route = null,
        public array $routeParameters = [],
    ) {}
}
