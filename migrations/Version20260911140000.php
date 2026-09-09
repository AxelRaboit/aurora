<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Amendments, and the end of a relationship.
 *
 * An amendment is a contract row pointing at the one it changes, because a
 * sealed contract is immutable by design: "modify the annex of a signed
 * contract" has no implementation that is not a lie. The self-reference is
 * `SET NULL`, with the parent's reference copied into a column beside it - once
 * the parent's retention runs out and somebody deletes it, the amendment still
 * says what it amended. The same reason the document keeps its own snapshot of
 * the wording instead of reading it back from the template.
 *
 * The termination is four columns rather than a status. A concluded contract
 * stays concluded: it was signed, and that does not expire. What ends is the
 * relationship, and it has two dates because a notice period is exactly the
 * gap between them.
 *
 * Everything is nullable, so no existing row needs a backfill: a contract that
 * amends nothing has no parent, and one nobody ended has no dates.
 */
final class Version20260911140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the amendment link and the termination record to core_contracts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE core_contracts ADD amends_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE core_contracts ADD amends_reference VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE core_contracts ADD amendment_rank INT DEFAULT NULL');
        $this->addSql('ALTER TABLE core_contracts ADD termination_noticed_at DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE core_contracts ADD termination_effective_at DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE core_contracts ADD termination_origin VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE core_contracts ADD termination_reason TEXT DEFAULT NULL');

        $this->addSql('ALTER TABLE core_contracts ADD CONSTRAINT FK_core_contracts_amends FOREIGN KEY (amends_id) REFERENCES core_contracts (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_core_contracts_amends ON core_contracts (amends_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE core_contracts DROP CONSTRAINT FK_core_contracts_amends');
        $this->addSql('DROP INDEX IDX_core_contracts_amends');

        $this->addSql('ALTER TABLE core_contracts DROP amends_id');
        $this->addSql('ALTER TABLE core_contracts DROP amends_reference');
        $this->addSql('ALTER TABLE core_contracts DROP amendment_rank');
        $this->addSql('ALTER TABLE core_contracts DROP termination_noticed_at');
        $this->addSql('ALTER TABLE core_contracts DROP termination_effective_at');
        $this->addSql('ALTER TABLE core_contracts DROP termination_origin');
        $this->addSql('ALTER TABLE core_contracts DROP termination_reason');
    }
}
