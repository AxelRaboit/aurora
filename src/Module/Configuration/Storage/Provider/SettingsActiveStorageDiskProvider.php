<?php

declare(strict_types=1);

namespace Aurora\Module\Configuration\Storage\Provider;

use Aurora\Core\Storage\ActiveStorageDiskProviderInterface;
use Aurora\Core\Storage\Enum\StorageDiskEnum;
use Aurora\Module\Configuration\Storage\Setting\StorageSettings;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * The disk an administrator chose, replacing Core's always-local answer.
 *
 * {@see StorageSettings::activeDisk()} is where the refusal to return a
 * backend that cannot work lives, so this is a pass-through on purpose: one
 * place decides, and the settings screen and the writing path cannot disagree
 * about what is on.
 */
#[AsAlias(ActiveStorageDiskProviderInterface::class)]
final readonly class SettingsActiveStorageDiskProvider implements ActiveStorageDiskProviderInterface
{
    public function __construct(
        private StorageSettings $settings,
    ) {}

    public function activeDisk(): StorageDiskEnum
    {
        return $this->settings->activeDisk();
    }
}
