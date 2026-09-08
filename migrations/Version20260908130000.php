<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The customer: who a contract is signed with.
 *
 * The columns are the identity block a French service contract opens with,
 * rather than a generic address book, because that block is what has to be
 * filled in before anything can be signed. Two fields are NOT NULL and the
 * rest are not: the legal name, which names the row everywhere, and the
 * contractual email, which is where a signing link goes. A customer usually
 * exists as a prospect weeks before anyone has their SIRET.
 *
 * `siret` is unique and nullable at once, which PostgreSQL allows because it
 * treats nulls as distinct: any number of customers can wait without a number,
 * and none of them can be recorded twice once they have one.
 *
 * `user_id` is ON DELETE SET NULL. Closing an account must not take the
 * company's accounting history with it.
 *
 * Written by hand rather than kept from `make:migration`: the diff against a
 * dev database still holding the tables of the extracted modules (Agency,
 * Project, Ecommerce, …) wanted to drop sixty of them. Only the two statements
 * about this table belong in this file.
 */
final class Version20260908130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add core_customers: the counterparty of a contract';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE seq_core_customer_id INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE core_customers (
            id INT NOT NULL,
            user_id INT DEFAULT NULL,
            legal_name VARCHAR(180) NOT NULL,
            legal_form VARCHAR(60) DEFAULT NULL,
            share_capital_cents INT DEFAULT NULL,
            share_capital_currency VARCHAR(3) DEFAULT NULL,
            registered_office TEXT DEFAULT NULL,
            siret VARCHAR(14) DEFAULT NULL,
            trade_register VARCHAR(120) DEFAULT NULL,
            vat_number VARCHAR(30) DEFAULT NULL,
            activity_sector VARCHAR(180) DEFAULT NULL,
            representative_first_name VARCHAR(100) DEFAULT NULL,
            representative_last_name VARCHAR(100) DEFAULT NULL,
            representative_role VARCHAR(120) DEFAULT NULL,
            contractual_email VARCHAR(180) NOT NULL,
            phone VARCHAR(30) DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2D337E0526E94372 ON core_customers (siret)');
        $this->addSql('CREATE INDEX IDX_2D337E05A76ED395 ON core_customers (user_id)');
        $this->addSql('ALTER TABLE core_customers ADD CONSTRAINT FK_2D337E05A76ED395 FOREIGN KEY (user_id) REFERENCES core_users (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE core_customers DROP CONSTRAINT FK_2D337E05A76ED395');
        $this->addSql('DROP TABLE core_customers');
        $this->addSql('DROP SEQUENCE seq_core_customer_id');
    }
}
