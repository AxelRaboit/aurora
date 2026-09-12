<?php

declare(strict_types=1);

namespace Aurora\Core\Trash;

use Aurora\Core\Dashboard\DashboardStatsProviderInterface;

/**
 * A trash a module owns, offered to the overview screen.
 *
 * Lives in core for the same reason {@see DashboardStatsProviderInterface}
 * does: the General shell must never import a business module's repositories.
 * Each module ships its own source, auto-registered through the
 * `aurora.trash_source` tag, and a module that is absent simply contributes
 * no row.
 *
 * A source answers for the user who is looking. Documents and categories are
 * shared, so their answer is the same for everyone; notes belong to their
 * author, so theirs is not. The overview does not know the difference and does
 * not need to.
 */
interface TrashSourceInterface
{
    /**
     * Module id gating this source, matched against the modules the overview
     * reports enabled (e.g. 'ged', 'notes', 'editorial').
     */
    public function getModuleKey(): string;

    /**
     * The privilege that opens the screen this trash belongs to, null when it
     * needs none.
     *
     * The overview drops a source the reader may not open: a count and a link
     * to a screen that answers 403 says something about content they were not
     * given, and offers no way to act on it.
     */
    public function getRequiredPrivilege(): ?string;

    public function getSummary(): TrashSummary;
}
