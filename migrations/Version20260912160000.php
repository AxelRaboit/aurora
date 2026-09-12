<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A document says which backend holds its bytes.
 *
 * Until now the answer was implicit and always the same: the server's disk,
 * under `var/uploads/`. With a second backend available, the setting says
 * where the NEXT file goes and says nothing about the ones already written.
 * Without this column, switching a backend would strand every existing file,
 * and there would be no way to move one document across and back.
 *
 * Defaulted to `local` and written on every existing row, because that is
 * where every existing file genuinely is. Nothing moves here: this migration
 * records a fact, it does not change one.
 *
 * Carried on versions too, and separately: a document can be moved after a
 * version was recorded, so an older version may sit on the other side from the
 * document that owns it.
 */
final class Version20260912160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Record which storage backend holds each GED document and version';
    }

    public function up(Schema $schema): void
    {
        foreach (['core_ged_documents', 'core_ged_document_versions'] as $table) {
            $this->addSql(sprintf(
                "ALTER TABLE %s ADD storage_disk VARCHAR(20) DEFAULT 'local' NOT NULL",
                $table,
            ));
        }
    }

    public function down(Schema $schema): void
    {
        foreach (['core_ged_documents', 'core_ged_document_versions'] as $table) {
            $this->addSql(sprintf('ALTER TABLE %s DROP storage_disk', $table));
        }
    }
}
