<?php

declare(strict_types=1);

namespace Aurora\Module\Editorial\Captcha;

use Aurora\Module\Configuration\Setting\Configuration\ConfigurationTab;
use Aurora\Module\Configuration\Setting\Configuration\ConfigurationTabProviderInterface;

/**
 * Contributes the "Anti-robots" tab to the settings screen.
 *
 * No fields, for the reason the Pexels tab has none: the secret would be
 * shipped to the browser by the generic renderer, and the choice of provider
 * needs a form that explains what each one sends where.
 */
final readonly class CaptchaConfigurationTabProvider implements ConfigurationTabProviderInterface
{
    public function getTabs(): array
    {
        return [
            new ConfigurationTab(
                id: 'captcha',
                priority: 75,
                fields: [],
                alwaysVisible: true,
                componentName: 'captcha',
            ),
        ];
    }
}
