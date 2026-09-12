<?php

declare(strict_types=1);

namespace Aurora\Module\Configuration\Storage\Setting;

use Aurora\Core\Storage\Enum\StorageDiskEnum;

/**
 * The rows the storage configuration keeps in the settings table.
 *
 * Not an `ApplicationParameterEnumInterface`, for the reason the Pexels enum
 * gives: that interface exists so the generic settings screen can draw a field
 * and ship its current value to the browser, and a secret access key has no
 * business being echoed into a page. So the tab draws itself and
 * {@see StorageSettings} is the only way in.
 */
enum StorageSettingEnum: string
{
    /** Which disk new files are written to. A {@see StorageDiskEnum} value. */
    case ActiveDisk = 'storage_active_disk';

    case R2Endpoint = 'storage_r2_endpoint';

    case R2Bucket = 'storage_r2_bucket';

    /** Stored encrypted; see {@see StorageSettings}. */
    case R2AccessKeyId = 'storage_r2_access_key_id';

    /** Stored encrypted; see {@see StorageSettings}. */
    case R2SecretAccessKey = 'storage_r2_secret_access_key';

    /** Hostname mapped onto the bucket, when an administrator mapped one. */
    case R2PublicBaseUrl = 'storage_r2_public_base_url';

    /** A {@see StorageDeliveryModeEnum} value. */
    case DeliveryMode = 'storage_delivery_mode';

    /**
     * When the doctor last confirmed the backend actually works.
     *
     * A record of something that happened, not a value to retype, which is the
     * other reason this enum stays out of the generic renderer.
     */
    case R2VerifiedAt = 'storage_r2_verified_at';
}
