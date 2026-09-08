<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The contract itself, and the seal that makes it evidence.
 *
 * The columns split in two along the freeze. Before it: customer, template
 * versions, locale, amount, effective date - the choices. After it:
 * `content_snapshot`, `rendered_html`, `content_hash`, `hash_algo`,
 * `canonical_version`, `frozen_at` - the document. All six are written in one
 * statement so a row can never hold half a seal.
 *
 * `canonical_version` is stored rather than assumed. A hash means nothing
 * without knowing how it was computed, and the day the canonical rule has to
 * change, contracts sealed under the old one must keep verifying instead of all
 * turning red at once.
 *
 * `customer_id` is RESTRICT, not CASCADE: deleting a company that has signed
 * something must fail loudly rather than take the contract with it. The two
 * version columns are SET NULL instead, because a contract carries its own
 * frozen copy of the wording - losing a template loses the trail, never the
 * document.
 *
 * `reference` is unique and nullable at once. A draft has no number yet (it is
 * minted at freeze, so it can be printed inside the document the hash covers),
 * and PostgreSQL treats nulls as distinct, so any number of drafts can wait
 * while no two sealed contracts can share a reference.
 */
final class Version20260908210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add core_contracts with its frozen snapshot and hash';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE seq_core_contract_id INCREMENT BY 1 MINVALUE 1 START 1');

        $this->addSql('CREATE TABLE core_contracts (
            id INT NOT NULL,
            customer_id INT NOT NULL,
            body_version_id INT DEFAULT NULL,
            annex_version_id INT DEFAULT NULL,
            reference VARCHAR(32) DEFAULT NULL,
            locale VARCHAR(10) NOT NULL,
            status VARCHAR(24) NOT NULL,
            variables JSON DEFAULT \'{}\' NOT NULL,
            amount_cents INT DEFAULT NULL,
            amount_currency VARCHAR(3) DEFAULT NULL,
            effective_date DATE DEFAULT NULL,
            frozen_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            content_snapshot JSON DEFAULT \'{}\' NOT NULL,
            rendered_html TEXT DEFAULT NULL,
            content_hash VARCHAR(64) DEFAULT NULL,
            hash_algo VARCHAR(16) DEFAULT NULL,
            canonical_version INT DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        )');

        $this->addSql('CREATE UNIQUE INDEX uniq_contract_reference ON core_contracts (reference)');
        $this->addSql('CREATE INDEX IDX_contract_customer ON core_contracts (customer_id)');
        $this->addSql('CREATE INDEX IDX_contract_body_version ON core_contracts (body_version_id)');
        $this->addSql('CREATE INDEX IDX_contract_annex_version ON core_contracts (annex_version_id)');
        // The verification command reads exactly this set, and a scheduled run
        // should not walk every draft to find the sealed ones.
        $this->addSql('CREATE INDEX IDX_contract_frozen ON core_contracts (frozen_at)');

        $this->addSql('ALTER TABLE core_contracts ADD CONSTRAINT FK_contract_customer FOREIGN KEY (customer_id) REFERENCES core_customers (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE core_contracts ADD CONSTRAINT FK_contract_body_version FOREIGN KEY (body_version_id) REFERENCES core_contract_template_versions (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE core_contracts ADD CONSTRAINT FK_contract_annex_version FOREIGN KEY (annex_version_id) REFERENCES core_contract_template_versions (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE core_contracts DROP CONSTRAINT FK_contract_annex_version');
        $this->addSql('ALTER TABLE core_contracts DROP CONSTRAINT FK_contract_body_version');
        $this->addSql('ALTER TABLE core_contracts DROP CONSTRAINT FK_contract_customer');
        $this->addSql('DROP TABLE core_contracts');
        $this->addSql('DROP SEQUENCE seq_core_contract_id');
    }
}
