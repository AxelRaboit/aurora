<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Contract templates, their versions and the wording of each.
 *
 * Three tables because three things have different lifetimes. A template keeps
 * a stable identity while it is renamed or retired. A version is one numbered
 * state of its wording, and stops changing the moment it is published. A
 * translation is that wording in one language, and a contract has to be
 * reproducible years later in the language it was signed in.
 *
 * Two indexes carry rules rather than performance:
 *
 * - `(template_id, number)` unique: "version 3" has to name one document for
 *   the life of a template. Numbers therefore continue past discarded drafts
 *   instead of being reused.
 * - `published_at IS NULL` unique per template, a partial index: at most one
 *   draft is open at a time. Two would make "the version being edited"
 *   ambiguous, and the answer would be settled by whichever screen saved last.
 *   Written here by hand because a partial index has no attribute to declare
 *   it, and it is the invariant the application checks in the manager - the
 *   database is what makes it true under a double click.
 */
final class Version20260908160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add contract templates, their versions and the wording of each';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE seq_core_contract_template_id INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE seq_core_contract_template_version_id INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE seq_core_contract_version_translation_id INCREMENT BY 1 MINVALUE 1 START 1');

        $this->addSql('CREATE TABLE core_contract_templates (
            id INT NOT NULL,
            name VARCHAR(180) NOT NULL,
            kind VARCHAR(16) NOT NULL,
            archived_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            version_counter INT DEFAULT 0 NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        )');

        $this->addSql('CREATE TABLE core_contract_template_versions (
            id INT NOT NULL,
            template_id INT NOT NULL,
            number INT NOT NULL,
            published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE INDEX IDX_contract_template_version_template ON core_contract_template_versions (template_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_contract_template_version_number ON core_contract_template_versions (template_id, number)');
        $this->addSql('CREATE UNIQUE INDEX uniq_contract_template_one_draft ON core_contract_template_versions (template_id) WHERE published_at IS NULL');
        $this->addSql('ALTER TABLE core_contract_template_versions ADD CONSTRAINT FK_contract_template_version_template FOREIGN KEY (template_id) REFERENCES core_contract_templates (id) ON DELETE CASCADE NOT DEFERRABLE');

        $this->addSql('CREATE TABLE core_contract_template_version_translations (
            id INT NOT NULL,
            version_id INT NOT NULL,
            locale VARCHAR(10) NOT NULL,
            title VARCHAR(250) NOT NULL,
            content JSON DEFAULT \'{}\' NOT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE INDEX IDX_contract_version_translation_version ON core_contract_template_version_translations (version_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_contract_version_translation_locale ON core_contract_template_version_translations (version_id, locale)');
        $this->addSql('ALTER TABLE core_contract_template_version_translations ADD CONSTRAINT FK_contract_version_translation_version FOREIGN KEY (version_id) REFERENCES core_contract_template_versions (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE core_contract_template_version_translations DROP CONSTRAINT FK_contract_version_translation_version');
        $this->addSql('ALTER TABLE core_contract_template_versions DROP CONSTRAINT FK_contract_template_version_template');
        $this->addSql('DROP TABLE core_contract_template_version_translations');
        $this->addSql('DROP TABLE core_contract_template_versions');
        $this->addSql('DROP TABLE core_contract_templates');
        $this->addSql('DROP SEQUENCE seq_core_contract_version_translation_id');
        $this->addSql('DROP SEQUENCE seq_core_contract_template_version_id');
        $this->addSql('DROP SEQUENCE seq_core_contract_template_id');
    }
}
