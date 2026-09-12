<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A GED document can sit in the trash instead of disappearing.
 *
 * Deleting a document used to take its row, its variants and its file in one
 * gesture, with nothing to come back to: the bytes of an uploaded document
 * often exist nowhere else. `deleted_at` splits that gesture in two - the
 * listing stops showing the document, the disk keeps it - and the purge does
 * the irreversible half once the retention window has passed.
 *
 * Indexed because every listing query now carries `deleted_at IS NULL`: the
 * library is read far more often than the trash, and an unindexed null check
 * on the hot path is the kind of cost that only shows up once the library is
 * large.
 */
final class Version20260912200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Trash for GED documents: deleted_at on core_ged_documents';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE core_ged_documents ADD deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_ged_documents_deleted_at ON core_ged_documents (deleted_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_ged_documents_deleted_at');
        $this->addSql('ALTER TABLE core_ged_documents DROP deleted_at');
    }
}
