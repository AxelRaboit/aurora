<?php

declare(strict_types=1);

namespace Aurora\Module\Ged\Document\Message;

use Aurora\Core\Storage\Enum\StorageDiskEnum;

/**
 * Move this document's bytes to that backend, when the request cannot wait.
 *
 * Carries the id rather than the entity: by the time a worker picks this up
 * the document may have been edited, and the version worth moving is the one
 * that exists then.
 */
final readonly class RelocateDocumentMessage
{
    public function __construct(
        public int $documentId,
        public StorageDiskEnum $target,
    ) {}
}
