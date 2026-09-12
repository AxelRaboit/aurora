<?php

declare(strict_types=1);

namespace Aurora\Module\Ged\Document\MessageHandler;

use Aurora\Module\Configuration\Setting\Enum\ApplicationParameterEnum;
use Aurora\Module\Configuration\Setting\Repository\SettingRepository;
use Aurora\Module\Ged\Document\Manager\DocumentManagerInterface;
use Aurora\Module\Ged\Document\Message\PurgeTrashedDocumentsMessage;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Empties the GED trash of what has been in it long enough.
 *
 * Reads the same `TrashAutoPurgeDays` setting as the editorial purge rather
 * than inventing a GED-specific one: a retention window is a promise made to
 * the person who deleted something, and two windows would mean two promises
 * nobody told them about.
 *
 * The destruction itself goes through the manager, so the bytes leave with the
 * row and a file still referenced by another document is left alone - the same
 * code the trash's own buttons run.
 */
#[AsMessageHandler]
final readonly class PurgeTrashedDocumentsHandler
{
    public function __construct(
        private DocumentManagerInterface $documentManager,
        private SettingRepository $settingRepository,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(PurgeTrashedDocumentsMessage $message): void
    {
        $days = (int) $this->settingRepository->get(
            ApplicationParameterEnum::TrashAutoPurgeDays->value,
            ApplicationParameterEnum::TrashAutoPurgeDays->getDefaultValue(),
        );

        // Zero turns automatic purging off - the trash then keeps everything
        // until someone empties it by hand.
        if ($days <= 0) {
            return;
        }

        $purged = $this->documentManager->purgeTrashedBefore(new DateTimeImmutable(sprintf('-%d days', $days)));

        if (0 === $purged) {
            return;
        }

        $this->logger->info('Purged {count} trashed document(s) older than {days} days.', [
            'count' => $purged,
            'days' => $days,
        ]);
    }
}
