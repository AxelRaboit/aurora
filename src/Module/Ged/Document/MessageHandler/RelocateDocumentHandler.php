<?php

declare(strict_types=1);

namespace Aurora\Module\Ged\Document\MessageHandler;

use Aurora\Module\Ged\Document\Message\RelocateDocumentMessage;
use Aurora\Module\Ged\Document\Repository\DocumentRepository;
use Aurora\Module\Ged\Document\Service\DocumentRelocator;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Runs a move a request was too small to wait for.
 *
 * Deliberately thin: everything that decides what a move does, including the
 * lock and the order of operations, is in {@see DocumentRelocator}, shared with
 * the inline path. A worker and a button must not be able to move a document
 * differently.
 *
 * A document deleted between the dispatch and here is not an error. The message
 * asked for bytes to be somewhere, and bytes that no longer exist are already
 * as moved as they will ever be.
 */
#[AsMessageHandler]
final readonly class RelocateDocumentHandler
{
    public function __construct(
        private DocumentRepository $documentRepository,
        private DocumentRelocator $relocator,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function __invoke(RelocateDocumentMessage $message): void
    {
        $document = $this->documentRepository->find($message->documentId);

        if (null === $document) {
            $this->logger->info('Relocation skipped: document {id} no longer exists.', ['id' => $message->documentId]);

            return;
        }

        $relocation = $this->relocator->relocate($document, $message->target);

        if (!$relocation->ok && !$relocation->busy) {
            // Thrown so Messenger retries, and so a move that never works ends
            // up in the failure transport where somebody can find it. The
            // document itself already carries the reason.
            throw new RuntimeException(sprintf('Relocating document %d failed: %s', $message->documentId, $relocation->error ?? 'unknown reason'));
        }
    }
}
