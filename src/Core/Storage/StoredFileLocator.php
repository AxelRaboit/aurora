<?php

declare(strict_types=1);

namespace Aurora\Core\Storage;

use Aurora\Core\Storage\Adapter\StorageAdapterInterface;
use Aurora\Core\Storage\Enum\StorageDiskEnum;
use Aurora\Core\Storage\Workspace\LocalPathAware;

/**
 * Which backend holds the bytes behind a `/uploads/{path}` request.
 *
 * The serving endpoint receives a path and nothing else. It could look the
 * path up in the database to find the document that owns it, but that would be
 * a query per image on every page, and the answer is cheaper than that: ask
 * the local disk, whose check is a syscall.
 *
 * So local first, then the active backend. A file present locally is served
 * locally, which is both correct and free. A file that is not is on the other
 * side, and no remote request was spent finding that out.
 *
 * The one moment a path exists on both sides is while a document is being
 * moved. Local wins then, and serves the same bytes the other side holds, so
 * nobody notices.
 */
final readonly class StoredFileLocator
{
    public function __construct(
        private StorageManager $storageManager,
    ) {}

    /**
     * The adapter that can serve `$key`, or null when nothing holds it.
     */
    public function locate(string $key): ?StorageAdapterInterface
    {
        $local = $this->storageManager->forDisk(StorageDiskEnum::Local);

        // Deliberately not `exists()`: on a local adapter that is the syscall
        // we want, and this branch must not become a billed request the day
        // some other backend implements LocalPathAware.
        if ($local instanceof LocalPathAware && is_file($local->localPath($key))) {
            return $local;
        }

        $active = $this->storageManager->active();

        if ($active === $local) {
            return null;
        }

        return $active->exists($key) ? $active : null;
    }
}
