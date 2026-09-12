<?php

declare(strict_types=1);

namespace Aurora\Core\Storage;

use Aurora\Core\Storage\Enum\StorageDeliveryModeEnum;

/**
 * How remotely stored bytes should reach a browser.
 *
 * A port for the same reason as {@see ActiveStorageDiskProviderInterface}: the
 * answer is an administrator's choice, kept in the settings table that belongs
 * to a module, and the controller that needs it is core infrastructure.
 */
interface StorageDeliveryModeProviderInterface
{
    public function deliveryMode(): StorageDeliveryModeEnum;
}
