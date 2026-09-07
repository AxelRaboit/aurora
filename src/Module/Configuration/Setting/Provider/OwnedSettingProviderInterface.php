<?php

declare(strict_types=1);

namespace Aurora\Module\Configuration\Setting\Provider;

/**
 * Declares settings rows that belong to a screen of their own.
 *
 * `aurora:application-parameter` runs on every deploy and deletes any row
 * whose key no parameter provider claims - which is right, and is how a
 * setting retired from an enum stops lingering in the table.
 *
 * But a row can be legitimate and still have no business on the generic
 * settings screen: an API key must never be echoed into a page, and a record
 * of somebody accepting terms is not a value to retype. Those settings are
 * written and read by a tab of their own, so their keys are absent from every
 * `ApplicationParameterEnumInterface` - and the sync was therefore deleting
 * them at each release. The Pexels integration lost its key that way: it was
 * configured, it worked, and the next deploy silently emptied it.
 *
 * So the two questions are asked separately. A parameter provider says "draw
 * this field and keep this row"; this one says only "this row is mine, leave
 * it alone". The command creates nothing from these and shows nothing of
 * them - it just stops treating them as debris.
 *
 * Auto-tagged via `_instanceof` in services.yaml, like every other provider
 * here, so a client module protects its own rows by implementing it.
 */
interface OwnedSettingProviderInterface
{
    /**
     * The setting keys this owner is responsible for.
     *
     * @return iterable<string>
     */
    public function getOwnedSettingKeys(): iterable;
}
