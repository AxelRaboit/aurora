<?php

declare(strict_types=1);

namespace Aurora\Module\Configuration\Storage\Setting;

use Aurora\Module\Configuration\Setting\Provider\OwnedSettingProviderInterface;

/**
 * The rows the storage tab owns, so the deploy-time sync leaves them be.
 *
 * Without this, `aurora:application-parameter` treats every one of them as an
 * obsolete row and deletes it: the endpoint, the bucket, both credentials and
 * the verification date. It ran on 12/09/2026 against a production that had
 * been configured an hour earlier and removed all eight, which is how the
 * omission was found - a deployment, not a test, and the site was pointed back
 * at its local disk without a word.
 *
 * Not an `ApplicationParameterProviderInterface`, for the same reason
 * {@see StorageSettingEnum} is not one: the generic settings screen would draw
 * a secret access key into a page. This says the narrower thing - these rows
 * exist and are somebody's - which is all the sync needs to stop deleting
 * them.
 *
 * @see PexelsOwnedSettingProvider the same omission, found the same way, on
 *      the Pexels API key
 */
final readonly class StorageOwnedSettingProvider implements OwnedSettingProviderInterface
{
    public function getOwnedSettingKeys(): iterable
    {
        foreach (StorageSettingEnum::cases() as $case) {
            yield $case->value;
        }
    }
}
