<?php

declare(strict_types=1);

namespace Aurora\Module\Editorial\Captcha;

use Aurora\Module\Configuration\Setting\Provider\OwnedSettingProviderInterface;

/**
 * The four rows the anti-robot tab owns, so the deploy-time sync leaves them
 * be.
 *
 * Without this, `aurora:application-parameter` would delete them at the next
 * release, exactly as it once deleted the Pexels key: the sync removes any row
 * no parameter provider claims, and these are claimed by a screen of their own
 * rather than by the generic settings list.
 */
final readonly class CaptchaOwnedSettingProvider implements OwnedSettingProviderInterface
{
    public function getOwnedSettingKeys(): iterable
    {
        foreach (CaptchaSettingEnum::cases() as $case) {
            yield $case->value;
        }
    }
}
