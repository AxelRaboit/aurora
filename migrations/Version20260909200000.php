<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Where the signed PDF lives, and what it hashes to.
 *
 * `pdf_hash` is deliberately a second hash, not a copy of `content_hash`. That
 * one covers the document - the wording, the values, the rendered HTML. This
 * one covers the bytes on disk, so a PDF swapped on the filesystem is
 * detectable even though the contract it belongs to still verifies against its
 * own seal. Two artefacts, two hashes.
 *
 * `pdf_path` being non-null is what says a file exists, and what makes a second
 * generation refuse rather than overwrite: the PDF is written once, at the
 * countersignature, because that is the first moment the document is complete
 * and any later rebuild would be whatever today's renderer produces.
 */
final class Version20260909200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the signed PDF path, hash and timestamp to core_contracts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE core_contracts ADD pdf_path VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE core_contracts ADD pdf_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE core_contracts ADD pdf_generated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE core_contracts DROP pdf_generated_at');
        $this->addSql('ALTER TABLE core_contracts DROP pdf_hash');
        $this->addSql('ALTER TABLE core_contracts DROP pdf_path');
    }
}
