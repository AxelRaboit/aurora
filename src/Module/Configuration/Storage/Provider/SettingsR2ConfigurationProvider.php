<?php

declare(strict_types=1);

namespace Aurora\Module\Configuration\Storage\Provider;

use Aurora\Core\Storage\R2\R2Configuration;
use Aurora\Core\Storage\R2\R2ConfigurationProviderInterface;
use Aurora\Core\Storage\R2\S3ClientFactory;
use Aurora\Module\Configuration\Storage\Setting\StorageSettings;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * The R2 credentials, from the settings screen, with the environment winning.
 *
 * Two sources rather than one because they answer different situations. The
 * settings table is how a client configures their own bucket without touching
 * a server. The environment is how an operator keeps secrets out of a database
 * they back up nightly, and it is also what a deployment already has.
 *
 * The environment wins field by field, not wholesale: an operator who sets only
 * the endpoint should not silently lose the bucket an administrator saved. The
 * one thing that would be surprising is a half-and-half configuration nobody
 * intended, which is why {@see S3ClientFactory} names
 * the missing fields rather than failing on a signature error.
 */
#[AsAlias(R2ConfigurationProviderInterface::class)]
final readonly class SettingsR2ConfigurationProvider implements R2ConfigurationProviderInterface
{
    public function __construct(
        private StorageSettings $settings,
    ) {}

    public function current(): R2Configuration
    {
        return $this->settings->effectiveR2Configuration();
    }
}
