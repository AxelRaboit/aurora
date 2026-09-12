<?php

declare(strict_types=1);

namespace Aurora\Core\Storage\Adapter;

use Aurora\Core\Storage\Enum\StorageDiskEnum;
use Aurora\Core\Storage\Exception\StorageException;
use Aurora\Core\Storage\StorageManager;

/**
 * What Aurora needs from a place that holds bytes.
 *
 * Deliberately narrow. Every method here exists because some caller in the
 * codebase already does this against the local filesystem, and the point of
 * the interface is that those callers stop knowing which filesystem it is.
 *
 * **Keys, not paths.** A key is the address of an object relative to the
 * storage root - `ged/2026/09/facture-a1b2c3.pdf`. It is what the database
 * already stores in `filePath`, unchanged, so moving a backend under a
 * document does not rewrite a single row. Never absolute, never leading
 * slash, never a `..` segment: adapters reject those rather than resolve
 * them.
 *
 * **Absence is not failure.** `exists()` answers, `delete()` is idempotent.
 * Only the operations that were asked to touch real bytes and could not throw
 * {@see StorageException}.
 *
 * **Listings carry metadata.** See {@see StoredObject} for why `list()` never
 * yields bare keys.
 *
 * Implementations are registered with the `aurora.storage_adapter` tag and
 * resolved through {@see StorageManager}; adding a
 * backend means implementing this interface and adding a
 * {@see StorageDiskEnum} case, nothing else.
 */
interface StorageAdapterInterface
{
    /** Which disk this adapter answers for. Unique across adapters. */
    public function disk(): StorageDiskEnum;

    /**
     * Whether this backend can be asked anything at all.
     *
     * A remote backend nobody configured has no address, no credentials and
     * no answers: calling it throws. Callers that sweep the registered
     * adapters looking for a file need to skip it without catching an
     * exception, because an exception is how a *configured* backend reports a
     * real failure, and the two must not read the same.
     *
     * Answered locally and cheaply: no request, no round trip.
     */
    public function isReady(): bool;

    /**
     * Copies a local file's bytes to `$key`, overwriting whatever was there.
     *
     * The source is left in place. Callers holding a file they no longer want
     * (an upload in its temporary spot, a freshly rendered derivative) delete
     * it themselves, so this never surprises anyone by consuming its input.
     *
     * @throws StorageException when the source is unreadable or the write fails
     */
    public function writeFromLocalFile(string $key, string $sourceAbsolutePath): void;

    /**
     * Stores `$contents` at `$key`, overwriting whatever was there.
     *
     * @throws StorageException when the write fails
     */
    public function write(string $key, string $contents): void;

    /**
     * @throws StorageException when the key holds nothing, or cannot be read
     */
    public function read(string $key): string;

    /**
     * The object's bytes, a chunk at a time.
     *
     * For everything too big to hold in memory, which on a storage layer meant
     * to take video is most of the interesting cases. A caller streaming to a
     * response must use this; `read()` is for the small and the known.
     *
     * @return iterable<string>
     *
     * @throws StorageException when the key holds nothing, or cannot be read
     */
    public function readStream(string $key): iterable;

    /**
     * Places the object's bytes at a local absolute path, creating parent
     * directories as needed. The caller owns the resulting file.
     *
     * @throws StorageException when the key holds nothing, or the copy fails
     */
    public function copyToLocalFile(string $key, string $targetAbsolutePath): void;

    public function exists(string $key): bool;

    /** Never throws on a key that holds nothing. */
    public function delete(string $key): void;

    /**
     * Removes many keys, skipping those that hold nothing.
     *
     * Separate from `delete()` because backends that bill per request can do
     * this in batches, and a caller pruning a thousand orphans should not have
     * to know whether this one does.
     *
     * @param list<string> $keys
     */
    public function deleteMany(array $keys): void;

    /**
     * Every object whose key starts with `$prefix`, with its metadata.
     *
     * Lazy: an implementation over a paginated backend yields page by page, so
     * a caller that stops early has not paid for the rest.
     *
     * @return iterable<StoredObject>
     */
    public function list(string $prefix): iterable;

    /** Metadata for one object, or null when the key holds nothing. */
    public function stat(string $key): ?StoredObject;
}
