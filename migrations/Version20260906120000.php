<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Let a document point at a picture it does not own.
 *
 * Stock photo providers require it: Unsplash's terms forbid re-hosting what
 * their API returns, so a picture chosen from there has an address and a
 * credit but no file of ours. `file_path` stays null for those, and the two
 * attribution columns carry the photographer's name and profile - displaying
 * them is a condition of using the API, so they belong beside the URL rather
 * than being fetched again at render time.
 *
 * All three nullable: every document that exists predates them and holds its
 * own bytes.
 */
final class Version20260906120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add remote source and attribution columns to core_ged_documents';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE core_ged_documents ADD source_url VARCHAR(1024) DEFAULT NULL');
        $this->addSql('ALTER TABLE core_ged_documents ADD attribution_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE core_ged_documents ADD attribution_url VARCHAR(1024) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE core_ged_documents DROP source_url');
        $this->addSql('ALTER TABLE core_ged_documents DROP attribution_name');
        $this->addSql('ALTER TABLE core_ged_documents DROP attribution_url');
    }
}
