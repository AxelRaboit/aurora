<?php

declare(strict_types=1);

namespace Aurora\Tests\Integration\Core\Storage;

use Aurora\Core\Storage\Adapter\LocalStorageAdapter;
use Aurora\Core\Storage\Adapter\StorageAdapterInterface;
use Aurora\Core\Storage\Adapter\StoredObject;
use Aurora\Core\Storage\Enum\StorageDiskEnum;

/**
 * A second backend, backed by another directory.
 *
 * Delegation rather than inheritance because {@see LocalStorageAdapter}
 * is final, which is the right call for it and merely inconvenient here.
 *
 * It answers for the R2 disk while being nothing of the sort. That is the
 * point: what the relocation tests exercise is ordering, locking and which
 * rows get updated, none of which care what is on the other side. Keeping the
 * pair local means they run in CI with no credentials.
 */
final readonly class SecondDiskAdapter implements StorageAdapterInterface
{
    public function __construct(
        private StorageAdapterInterface $inner,
    ) {}

    public function disk(): StorageDiskEnum
    {
        return StorageDiskEnum::R2;
    }

    /** A local directory is always ready, like the adapter it delegates to. */
    public function isReady(): bool
    {
        return true;
    }

    public function writeFromLocalFile(string $key, string $sourceAbsolutePath): void
    {
        $this->inner->writeFromLocalFile($key, $sourceAbsolutePath);
    }

    public function write(string $key, string $contents): void
    {
        $this->inner->write($key, $contents);
    }

    public function read(string $key): string
    {
        return $this->inner->read($key);
    }

    public function readStream(string $key): iterable
    {
        return $this->inner->readStream($key);
    }

    public function copyToLocalFile(string $key, string $targetAbsolutePath): void
    {
        $this->inner->copyToLocalFile($key, $targetAbsolutePath);
    }

    public function exists(string $key): bool
    {
        return $this->inner->exists($key);
    }

    public function delete(string $key): void
    {
        $this->inner->delete($key);
    }

    public function deleteMany(array $keys): void
    {
        $this->inner->deleteMany($keys);
    }

    public function list(string $prefix): iterable
    {
        return $this->inner->list($prefix);
    }

    public function stat(string $key): ?StoredObject
    {
        return $this->inner->stat($key);
    }
}
