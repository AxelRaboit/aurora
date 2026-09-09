<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The blanks a trame leaves to one contract.
 *
 * A JSON map rather than columns, because the keys belong to the wording: a
 * trame that asks for a kilometric threshold declares it by writing
 * `{{contract.custom.seuil_km}}`, and a schema change per clause would make
 * editing a trame a deployment.
 *
 * Defaults to an empty object, which is what every existing contract carries:
 * the trames in force use no custom field, so nothing to backfill.
 */
final class Version20260910190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add per-contract custom fields to core_contracts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE core_contracts ADD custom_fields JSON DEFAULT '{}' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE core_contracts DROP custom_fields');
    }
}
