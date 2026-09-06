<?php

declare(strict_types=1);

namespace Aurora\Module\Ged\Pexels\Setting;

use Aurora\Module\Configuration\Setting\Configuration\ConfigurationTab;
use Aurora\Module\Configuration\Setting\Configuration\ConfigurationTabProviderInterface;

/**
 * Contributes the "Pexels" tab to the settings screen.
 *
 * No fields: everything this tab holds is either a secret or a record of a
 * decision, and the generic renderer would ship both to the browser as
 * ordinary values. `alwaysVisible` is what lets a tab with an empty field
 * list draw at all, and `componentName` points at the Vue component that
 * draws it - the same arrangement navigation and appearance use.
 */
final readonly class PexelsConfigurationTabProvider implements ConfigurationTabProviderInterface
{
    public function getTabs(): array
    {
        return [
            new ConfigurationTab(
                id: 'pexels',
                priority: 70,
                fields: [],
                alwaysVisible: true,
                componentName: 'pexels',
            ),
        ];
    }
}
