<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The address that opens one contract from outside.
 *
 * Two columns hold the credential, not one. `selector` identifies the row and
 * travels in the URL; `hashed_token` is a SHA-256 of the secret half. A stolen
 * database therefore hands somebody the ability to look up rows and not the
 * ability to open, read or sign a contract - which is a different stake from
 * the note share link next door, whose token is stored in clear because a
 * leaked notes database has already leaked the notes.
 *
 * The lookup is two steps for that reason: find by selector, then compare
 * hashes in constant time. A single hashed column would have meant either
 * hashing every row on every request or storing the secret to find it by.
 *
 * `expires_at` is NOT NULL, the opposite default from a shared note. An offer
 * that can still be accepted a year later is a liability, and the paper version
 * of this always carried a validity period.
 *
 * `first_opened_at` and `last_used_at` are two columns because they answer two
 * questions: whether the document was ever read, which moves the contract's
 * status, and whether somebody came back to it.
 */
final class Version20260909090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add core_contract_access_links: the public address of a contract';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE seq_core_contract_access_link_id INCREMENT BY 1 MINVALUE 1 START 1');

        $this->addSql('CREATE TABLE core_contract_access_links (
            id INT NOT NULL,
            contract_id INT NOT NULL,
            selector VARCHAR(32) NOT NULL,
            hashed_token VARCHAR(64) NOT NULL,
            recipient_email VARCHAR(180) NOT NULL,
            expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            first_opened_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        )');

        // Unique so a collision fails loudly on insert rather than handing one
        // customer another customer's contract.
        $this->addSql('CREATE UNIQUE INDEX uniq_contract_access_selector ON core_contract_access_links (selector)');
        $this->addSql('CREATE INDEX IDX_contract_access_contract ON core_contract_access_links (contract_id)');
        $this->addSql('ALTER TABLE core_contract_access_links ADD CONSTRAINT FK_contract_access_contract FOREIGN KEY (contract_id) REFERENCES core_contracts (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE core_contract_access_links DROP CONSTRAINT FK_contract_access_contract');
        $this->addSql('DROP TABLE core_contract_access_links');
        $this->addSql('DROP SEQUENCE seq_core_contract_access_link_id');
    }
}
