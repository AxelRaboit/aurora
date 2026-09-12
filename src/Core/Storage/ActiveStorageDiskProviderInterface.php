<?php

declare(strict_types=1);

namespace Aurora\Core\Storage;

use Aurora\Core\Storage\Enum\StorageDiskEnum;

/**
 * Which disk this installation writes new files to.
 *
 * A port, because the answer lives in the settings table and the settings
 * table belongs to a module, while storage is core infrastructure. Core
 * declares the question; the Configuration module answers it by reading what
 * an administrator chose.
 *
 * Only writes consult this. Reading a file asks the file which disk it is on,
 * which is what lets two documents sit on different backends while the
 * setting says one thing.
 */
interface ActiveStorageDiskProviderInterface
{
    public function activeDisk(): StorageDiskEnum;
}
