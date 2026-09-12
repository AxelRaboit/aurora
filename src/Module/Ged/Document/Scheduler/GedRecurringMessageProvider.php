<?php

declare(strict_types=1);

namespace Aurora\Module\Ged\Document\Scheduler;

use Aurora\Core\Scheduler\RecurringMessageProviderInterface;
use Aurora\Module\Ged\Document\Message\PurgeTrashedDocumentsMessage;
use Symfony\Component\Scheduler\RecurringMessage;

/**
 * GED's recurring jobs, contributed to core's main schedule.
 *
 * Nightly, at the same hour as the editorial purge: nobody is waiting on it,
 * and the two emptying the trash together is easier to reason about than two
 * unrelated hours.
 */
final class GedRecurringMessageProvider implements RecurringMessageProviderInterface
{
    public function getRecurringMessages(): iterable
    {
        yield RecurringMessage::cron('0 3 * * *', new PurgeTrashedDocumentsMessage());
    }
}
