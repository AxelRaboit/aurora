<?php

declare(strict_types=1);

namespace Aurora\Core\Storage\R2;

/**
 * Where the R2 credentials come from.
 *
 * An interface for one implementation, on purpose. Today they come from the
 * environment; in phase 3 they come from the settings table, encrypted at
 * rest, edited from a configuration tab. Swapping that is decorating this
 * with `#[AsAlias]`, the way every other substitution in Aurora works, rather
 * than editing the adapter.
 */
interface R2ConfigurationProviderInterface
{
    public function current(): R2Configuration;
}
