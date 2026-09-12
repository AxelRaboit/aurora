<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A GED folder can sit in the trash, and what fell with it remembers why.
 *
 * Deleting a folder never destroyed a document - the foreign keys are
 * `SET NULL`, so documents and sub-folders surfaced at the root. What it
 * destroyed was the filing: which document lived where, irrecoverably. Keeping
 * the row instead of removing it keeps those links intact, which is what makes
 * a restore able to put the branch back exactly as it was.
 *
 * `trashed_with_folder_id` records the folder whose deletion took a row down.
 * Restoring folder X brings back what carries X, and only that: a document
 * trashed by hand last week stays in the trash when the folder above it is
 * restored today. Deriving the same thing from timestamps would guess.
 *
 * Both columns are indexed for the same reason as the documents' own
 * `deleted_at`: every tree query now filters on them.
 */
final class Version20260913080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Trash for GED folders, and the origin of a cascaded deletion';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE core_ged_document_folders ADD deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE core_ged_document_folders ADD trashed_with_folder_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE core_ged_documents ADD trashed_with_folder_id INT DEFAULT NULL');

        $this->addSql('CREATE INDEX idx_ged_folders_deleted_at ON core_ged_document_folders (deleted_at)');
        $this->addSql('CREATE INDEX idx_ged_folders_trashed_with ON core_ged_document_folders (trashed_with_folder_id)');
        $this->addSql('CREATE INDEX idx_ged_documents_trashed_with ON core_ged_documents (trashed_with_folder_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_ged_documents_trashed_with');
        $this->addSql('DROP INDEX idx_ged_folders_trashed_with');
        $this->addSql('DROP INDEX idx_ged_folders_deleted_at');

        $this->addSql('ALTER TABLE core_ged_documents DROP trashed_with_folder_id');
        $this->addSql('ALTER TABLE core_ged_document_folders DROP trashed_with_folder_id');
        $this->addSql('ALTER TABLE core_ged_document_folders DROP deleted_at');
    }
}
