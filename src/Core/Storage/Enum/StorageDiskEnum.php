<?php

declare(strict_types=1);

namespace Aurora\Core\Storage\Enum;

use Aurora\Core\Storage\Adapter\StorageAdapterInterface;
use Aurora\Core\Storage\StorageManager;

/**
 * Where a stored object's bytes actually live.
 *
 * One case per {@see StorageAdapterInterface}
 * implementation Aurora ships, and the value persisted alongside a file so a
 * later read knows who to ask. It is written to database columns, so a case's
 * value is part of the schema: rename one and existing rows stop resolving.
 *
 * Deliberately holds only what has an adapter behind it. A case with no
 * implementation would let a column carry a disk that
 * {@see StorageManager} cannot answer for, which is a
 * runtime failure dressed up as a type.
 */
enum StorageDiskEnum: string
{
    case Local = 'local';
}
