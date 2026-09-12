<?php

declare(strict_types=1);

namespace Aurora\Module\Configuration\Storage\Setting;

use Aurora\Module\Configuration\Setting\Configuration\ConfigurationTab;
use Aurora\Module\Configuration\Setting\Configuration\ConfigurationTabProviderInterface;

/**
 * Contributes the "Storage" tab to the settings screen.
 *
 * No fields: two of the values behind this tab are credentials, one is a
 * record of when a backend last answered, and the generic renderer would ship
 * all three to the browser as ordinary values. `alwaysVisible` is what lets a
 * tab with an empty field list draw at all, and `componentName` points at the
 * Vue component that draws it, the same arrangement navigation and appearance
 * use.
 */
final readonly class StorageConfigurationTabProvider implements ConfigurationTabProviderInterface
{
    public function getTabs(): array
    {
        return [
            new ConfigurationTab(
                id: 'storage',
                priority: 75,
                fields: [],
                alwaysVisible: true,
                componentName: 'storage',
            ),
        ];
    }
}
