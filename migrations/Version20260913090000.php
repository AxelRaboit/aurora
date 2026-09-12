<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A GED category can sit in the trash.
 *
 * Deleting one never destroyed a document either: the foreign key is
 * `SET NULL`, so the documents simply lost their classification, silently and
 * for good. Keeping the row leaves them pointing at it, which is what lets a
 * restore put the classification back.
 *
 * The slug is unique across the table, so a trashed category is parked under
 * `trashed-<id>-<slug>` while it waits. Without that, creating a new category
 * under a name the trash still holds would fail on a constraint, over a row
 * nothing displays.
 */
final class Version20260913090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Trash for GED categories';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE core_ged_document_categories ADD deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_ged_categories_deleted_at ON core_ged_document_categories (deleted_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_ged_categories_deleted_at');
        $this->addSql('ALTER TABLE core_ged_document_categories DROP deleted_at');
    }
}
