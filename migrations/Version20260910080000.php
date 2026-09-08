<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Which language prevails, per version of a trame.
 *
 * Nullable, and null is the normal state of a version written in one language:
 * there is nothing to arbitrate. It becomes mandatory at publication when the
 * version carries several languages, and that rule is enforced in the manager
 * rather than by the schema - a column cannot say "required as soon as a
 * sibling table has two rows".
 *
 * No backfill: the versions already in the database carry French only, so their
 * null is the right answer and writing "fr" into it would state a clause none
 * of their documents contains.
 */
final class Version20260910080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the governing language to core_contract_template_versions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE core_contract_template_versions ADD governing_locale VARCHAR(5) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE core_contract_template_versions DROP governing_locale');
    }
}
