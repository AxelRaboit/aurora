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
 * The slug's uniqueness becomes partial at the same time. A name is taken
 * only by a category that is actually in the list: one waiting in the trash
 * keeps its slug, readable, without holding the name hostage, and two trashed
 * "factures" can coexist. PostgreSQL enforces it with a `WHERE`, so nothing in
 * the code has to remember to disguise a value.
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

        $this->addSql('DROP INDEX uniq_2d74479a989d9b62');
        $this->addSql('CREATE UNIQUE INDEX uniq_ged_category_slug_live ON core_ged_document_categories (slug) WHERE deleted_at IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_ged_category_slug_live');
        $this->addSql('CREATE UNIQUE INDEX uniq_2d74479a989d9b62 ON core_ged_document_categories (slug)');

        $this->addSql('DROP INDEX idx_ged_categories_deleted_at');
        $this->addSql('ALTER TABLE core_ged_document_categories DROP deleted_at');
    }
}
