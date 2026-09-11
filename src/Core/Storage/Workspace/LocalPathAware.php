<?php

declare(strict_types=1);

namespace Aurora\Core\Storage\Workspace;

/**
 * Marks an adapter whose objects already are files on this machine.
 *
 * {@see LocalWorkspace} uses it to skip the whole download-work-upload dance
 * and hand out the stored file directly, which is what keeps the local
 * backend exactly as fast and exactly as behaved as it was before the
 * abstraction existed.
 *
 * Only a backend that can honestly say "this key is that file, right here"
 * implements it. A cache of remote objects must not: the path would be a
 * copy, and {@see LocalWorkspace::writable()} would store nothing.
 */
interface LocalPathAware
{
    /**
     * Absolute path of the file behind `$key`, whether or not it exists yet.
     */
    public function localPath(string $key): string;
}
