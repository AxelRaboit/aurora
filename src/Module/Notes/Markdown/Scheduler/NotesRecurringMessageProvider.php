<?php

declare(strict_types=1);

namespace Aurora\Module\Notes\Markdown\Scheduler;

use Aurora\Core\Scheduler\RecurringMessageProviderInterface;
use Aurora\Module\Notes\Markdown\Message\PurgeTrashedNotesMessage;
use Symfony\Component\Scheduler\RecurringMessage;

/**
 * Notes' recurring jobs, contributed to core's main schedule.
 *
 * Nightly, at the hour the other trashes empty: nobody is waiting on it, and
 * one hour for all of them is easier to reason about than three.
 */
final class NotesRecurringMessageProvider implements RecurringMessageProviderInterface
{
    public function getRecurringMessages(): iterable
    {
        yield RecurringMessage::cron('0 3 * * *', new PurgeTrashedNotesMessage());
    }
}
