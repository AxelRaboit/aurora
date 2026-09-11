<?php

declare(strict_types=1);

namespace Aurora\Core\Storage\Workspace;

use Aurora\Core\Storage\Adapter\StorageAdapterInterface;
use Aurora\Core\Storage\Exception\StorageException;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;

use function sprintf;

/**
 * Lends a real path on the real filesystem to code that cannot work without
 * one.
 *
 * GD, `pdftoppm` and Ghostscript take filenames. They will not take a bucket,
 * and rewriting them to stream would mean reimplementing them. So instead of
 * teaching every image and PDF routine about remote storage, they keep
 * receiving a path, and this decides where that path comes from.
 *
 * On the local disk it is the stored file itself - no copy, no temporary, the
 * exact behaviour Aurora had before any of this existed. On a remote backend
 * the same call downloads to a temporary file, runs the work, sends back what
 * changed, and deletes the temporary whatever happened. The caller reads the
 * same either way.
 *
 * Three verbs, because "I need a path" hides three different intentions, and
 * conflating them is how a derivative silently fails to persist:
 *
 *  - {@see readable()} - look at these bytes, do not change them.
 *  - {@see writable()} - change these bytes in place; the result is stored.
 *  - {@see target()} - here is a path to create something new at; what you
 *    leave there is stored under that key.
 *
 * Temporaries follow the Aurora convention: the `aurora_storage_workspace_`
 * prefix, removed in a `finally`, and declared in
 * `CleanTempFilesHandler::TMP_PREFIXES` so a crashed process still gets swept
 * within the hour.
 */
final readonly class LocalWorkspace
{
    public const string TMP_PREFIX = 'aurora_storage_workspace_';

    public function __construct(
        private Filesystem $filesystem,
    ) {}

    /**
     * Runs `$work` against a readable local path for `$key`.
     *
     * Anything `$work` writes to that path is discarded on a remote backend
     * and kept on a local one, which is precisely the inconsistency this
     * method's name exists to prevent. Use {@see writable()} to modify.
     *
     * @template T
     *
     * @param callable(string): T $work
     *
     * @return T
     *
     * @throws StorageException when the key holds nothing
     */
    public function readable(StorageAdapterInterface $adapter, string $key, callable $work): mixed
    {
        if ($adapter instanceof LocalPathAware) {
            return $work($adapter->localPath($key));
        }

        $temporary = $this->temporaryFile($key);

        try {
            $adapter->copyToLocalFile($key, $temporary);

            return $work($temporary);
        } finally {
            $this->filesystem->remove($temporary);
        }
    }

    /**
     * Runs `$work` against a local path for `$key` whose contents it may
     * change; what is left there becomes the stored object.
     *
     * The write-back happens only when `$work` returns normally. A routine
     * that throws half way through re-encoding leaves the stored bytes as
     * they were, rather than replacing them with a truncated file.
     *
     * @template T
     *
     * @param callable(string): T $work
     *
     * @return T
     *
     * @throws StorageException when the key holds nothing
     */
    public function writable(StorageAdapterInterface $adapter, string $key, callable $work): mixed
    {
        if ($adapter instanceof LocalPathAware) {
            return $work($adapter->localPath($key));
        }

        $temporary = $this->temporaryFile($key);

        try {
            $adapter->copyToLocalFile($key, $temporary);
            $result = $work($temporary);
            $adapter->writeFromLocalFile($key, $temporary);

            return $result;
        } finally {
            $this->filesystem->remove($temporary);
        }
    }

    /**
     * Hands `$work` a local path to create a new object at, and stores
     * whatever it leaves there under `$key`.
     *
     * `$work` is free to write nothing - a PDF with no first page to render, a
     * crop that turned out impossible. Nothing is then stored, and the
     * caller's own return value says so; an empty object at the key would be
     * worse than none.
     *
     * @template T
     *
     * @param callable(string): T $work
     *
     * @return T
     */
    public function target(StorageAdapterInterface $adapter, string $key, callable $work): mixed
    {
        if ($adapter instanceof LocalPathAware) {
            $path = $adapter->localPath($key);
            $this->filesystem->mkdir(dirname($path));

            return $work($path);
        }

        $temporary = $this->temporaryFile($key);

        try {
            $result = $work($temporary);

            if (is_file($temporary) && filesize($temporary) > 0) {
                $adapter->writeFromLocalFile($key, $temporary);
            }

            return $result;
        } finally {
            $this->filesystem->remove($temporary);
        }
    }

    /**
     * An empty temporary file carrying the key's extension.
     *
     * The extension matters more than it looks: GD picks its encoder from the
     * filename in several places, and `pdftoppm` appends its own suffix to
     * what it is given.
     */
    private function temporaryFile(string $key): string
    {
        $path = tempnam(sys_get_temp_dir(), self::TMP_PREFIX);

        if (false === $path) {
            throw new StorageException('Could not create a temporary file for the storage workspace.');
        }

        $extension = pathinfo($key, PATHINFO_EXTENSION);

        if ('' === $extension) {
            return $path;
        }

        $suffixed = sprintf('%s.%s', $path, $extension);

        try {
            $this->filesystem->rename($path, $suffixed, true);
        } catch (Throwable) {
            return $path;
        }

        return $suffixed;
    }
}
