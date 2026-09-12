<?php

declare(strict_types=1);

namespace Aurora\Module\Configuration\Storage\Provider;

use Aurora\Core\Storage\Enum\StorageDeliveryModeEnum;
use Aurora\Core\Storage\StorageDeliveryModeProviderInterface;
use Aurora\Module\Configuration\Storage\Setting\StorageSettings;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(StorageDeliveryModeProviderInterface::class)]
final readonly class SettingsStorageDeliveryModeProvider implements StorageDeliveryModeProviderInterface
{
    public function __construct(
        private StorageSettings $settings,
    ) {}

    public function deliveryMode(): StorageDeliveryModeEnum
    {
        return $this->settings->deliveryMode();
    }
}
