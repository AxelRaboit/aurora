<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The Accounting module became Studio, and the stored strings have to follow.
 *
 * **No table is renamed, and that is not luck**: the module's tables were
 * always `core_contracts`, `core_customers`, `core_contract_templates`. The
 * module name lives nowhere in the schema. What carries it is data - setting
 * keys, and lists of identifiers kept in JSON columns - so this migration
 * rewrites values, never structure.
 *
 * Seven rewrites, and the last one is the one worth reading twice:
 *
 * 1. `core_settings.setting_key` for the three module toggles.
 * 2. `core_settings.setting_key` for the seventeen application parameters -
 *    the provider identity printed on every contract, the numbering prefix,
 *    the retention window and the reminder schedule. A key missed here is a
 *    setting read back as its default, which for `studio_provider_bank_iban`
 *    means a contract PDF with no bank details and nothing saying so.
 * 3. `core_settings.setting_group`, the settings tab those parameters sit in.
 * 4. `core_users.privileges`, which name the fourteen `accounting.*` rights.
 * 5. `core_users.disabled_modules`, the per-user module mask.
 * 6. `core_users.hidden_nav_sections`, which hold a NavSection id.
 * 7. `core_users.hidden_nav_items`, which hold route names.
 *
 * And the trap: the four `nav_*` settings keep **their own keys unchanged**
 * while the section id and the route names sit *inside* their JSON value. A
 * sweep that only looked at setting keys would leave them behind, and the
 * reader's renamed section would quietly lose the alias and the position they
 * had given it.
 *
 * Every statement is a `REPLACE` over text, which makes them idempotent: the
 * second run finds nothing left to replace. `down()` is the mirror, because a
 * rollback that leaves the identifiers rewritten would boot an application
 * whose privileges nobody holds.
 */
final class Version20260912210000 extends AbstractMigration
{
    /**
     * The JSON list columns, all on the users table, all holding identifiers
     * as plain strings.
     *
     * Rewritten as text rather than through a JSON function: the values are
     * flat lists of identifiers, the substrings are unambiguous, and a text
     * replacement works the same on PostgreSQL and on anything else a client
     * project might run.
     *
     * @var array<string, string>
     */
    private const array USER_COLUMNS = [
        'privileges' => 'accounting.',
        'disabled_modules' => 'modules_accounting_',
        'hidden_nav_sections' => '"accounting"',
        'hidden_nav_items' => 'backend_accounting_',
    ];

    public function getDescription(): string
    {
        return 'Rename the Accounting module to Studio in stored settings, privileges and menu preferences';
    }

    public function up(Schema $schema): void
    {
        $this->rename('accounting', 'studio');
    }

    public function down(Schema $schema): void
    {
        $this->rename('studio', 'accounting');
    }

    private function rename(string $from, string $to): void
    {
        // Settings: the three toggles and the seventeen parameters. Two
        // patterns rather than one, because `modules_accounting_backend` does
        // not start with `accounting_`.
        $this->addSql(sprintf(
            "UPDATE core_settings SET setting_key = REPLACE(setting_key, 'modules_%s_', 'modules_%s_') WHERE setting_key LIKE 'modules\\_%s\\_%%'",
            $from,
            $to,
            $from,
        ));
        $this->addSql(sprintf(
            "UPDATE core_settings SET setting_key = REPLACE(setting_key, '%s_', '%s_') WHERE setting_key LIKE '%s\\_%%'",
            $from,
            $to,
            $from,
        ));

        // The settings tab those parameters are grouped under.
        $this->addSql(sprintf(
            "UPDATE core_settings SET setting_group = '%s' WHERE setting_group = '%s'",
            $to,
            $from,
        ));

        // The trap: section id and route names inside the JSON value of the
        // four navigation settings, whose own keys do not change. The column is
        // literally named `value`, which PostgreSQL reserves, hence the quotes.
        $this->addSql(sprintf(
            "UPDATE core_settings SET \"value\" = REPLACE(\"value\", 'backend_%s_', 'backend_%s_') WHERE setting_key IN ('nav_section_aliases', 'nav_item_aliases', 'nav_section_order', 'nav_item_order') AND \"value\" IS NOT NULL",
            $from,
            $to,
        ));
        $this->addSql(sprintf(
            "UPDATE core_settings SET \"value\" = REPLACE(\"value\", '\"%s\"', '\"%s\"') WHERE setting_key IN ('nav_section_aliases', 'nav_section_order') AND \"value\" IS NOT NULL",
            $from,
            $to,
        ));

        foreach (self::USER_COLUMNS as $column => $needle) {
            $replacement = str_replace($from, $to, $needle);

            $this->addSql(sprintf(
                "UPDATE core_users SET %s = REPLACE(%s::text, '%s', '%s')::json WHERE %s::text LIKE '%%%s%%'",
                $column,
                $column,
                $needle,
                $replacement,
                $column,
                $needle,
            ));
        }
    }
}
