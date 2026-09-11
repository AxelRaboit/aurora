<?php

declare(strict_types=1);

namespace Aurora\Tests\Unit\Core\Storage;

use Aurora\Core\Storage\Adapter\StorageAdapterInterface;
use Aurora\Core\Storage\Adapter\StoredObject;
use Aurora\Core\Storage\Enum\StorageDiskEnum;
use Aurora\Core\Storage\Exception\StorageException;
use Aurora\Core\Storage\Workspace\LocalWorkspace;
use DateTimeImmutable;
use Generator;

/**
 * A backend that is deliberately not a filesystem.
 *
 * It exists so the tests can exercise the remote branch of
 * {@see LocalWorkspace} before any remote
 * backend is written: nothing here has a path, so any code that assumed one
 * fails here rather than in production against a bucket.
 *
 * Notably it does NOT implement `LocalPathAware`, which is the whole point.
 */
final class InMemoryStorageAdapter implements StorageAdapterInterface
{
    /** @var array<string, string> */
    public array $objects = [];

    /** Every key ever read out to a local file, in order. */
    public array $reads = [];

    /** Every key ever written, in order. */
    public array $writes = [];

    public function disk(): StorageDiskEnum
    {
        // The enum holds only the disks Aurora ships. A fake borrowing the
        // local case is harmless: nothing here indexes adapters by disk.
        return StorageDiskEnum::Local;
    }

    public function writeFromLocalFile(string $key, string $sourceAbsolutePath): void
    {
        if (!is_file($sourceAbsolutePath)) {
            throw StorageException::writeFailed($key, 'source is not a file');
        }

        $this->objects[$key] = (string) file_get_contents($sourceAbsolutePath);
        $this->writes[] = $key;
    }

    public function write(string $key, string $contents): void
    {
        $this->objects[$key] = $contents;
        $this->writes[] = $key;
    }

    public function read(string $key): string
    {
        return $this->objects[$key] ?? throw StorageException::missingKey($key);
    }

    public function copyToLocalFile(string $key, string $targetAbsolutePath): void
    {
        if (!isset($this->objects[$key])) {
            throw StorageException::missingKey($key);
        }

        file_put_contents($targetAbsolutePath, $this->objects[$key]);
        $this->reads[] = $key;
    }

    public function exists(string $key): bool
    {
        return isset($this->objects[$key]);
    }

    public function delete(string $key): void
    {
        unset($this->objects[$key]);
    }

    public function deleteMany(array $keys): void
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
    }

    public function list(string $prefix): Generator
    {
        foreach ($this->objects as $key => $contents) {
            if (str_starts_with($key, $prefix)) {
                yield new StoredObject($key, mb_strlen($contents, '8bit'), new DateTimeImmutable());
            }
        }
    }

    public function stat(string $key): ?StoredObject
    {
        if (!isset($this->objects[$key])) {
            return null;
        }

        return new StoredObject($key, mb_strlen($this->objects[$key], '8bit'), new DateTimeImmutable());
    }
}
