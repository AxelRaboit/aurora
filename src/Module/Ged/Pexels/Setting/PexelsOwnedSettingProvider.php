<?php

declare(strict_types=1);

namespace Aurora\Module\Ged\Pexels\Setting;

use Aurora\Module\Configuration\Setting\Provider\OwnedSettingProviderInterface;

/**
 * The four rows the Pexels tab owns, so the deploy-time sync leaves them be.
 *
 * Not an `ApplicationParameterProviderInterface`, for the reason
 * {@see PexelsSettingEnum} gives: the generic settings screen would draw the
 * API key into a page. This says the narrower thing - the rows exist and are
 * somebody's - which is all the sync needs to stop deleting them.
 */
final readonly class PexelsOwnedSettingProvider implements OwnedSettingProviderInterface
{
    public function getOwnedSettingKeys(): iterable
    {
        foreach (PexelsSettingEnum::cases() as $case) {
            yield $case->value;
        }
    }
}
