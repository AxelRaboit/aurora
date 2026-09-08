<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Signatures, and the codes that prove who gave them.
 *
 * `core_contract_signatures` exists because `core_audit_logs` cannot carry this
 * evidence: it has no IP column, no user agent, and its actor is null for a
 * guest - which is exactly who signs here. A signature provable only by an
 * audit row would be a signature with no proof.
 *
 * Its columns fall in three groups, and the split is the design. What the
 * signer DECLARED (name, email, place, date): a statement, worth what a
 * statement is worth. What the server OBSERVED (signed_at, ip_address,
 * user_agent, link_selector, challenge_verified_at, user_id): typed by nobody.
 * And WHAT WAS SIGNED (signed_content_hash), copied rather than read back, so
 * that if the document ever moves this row still says what was agreed to.
 *
 * `contract_id` is RESTRICT, alone in this module: every other relation
 * degrades on delete, this one must fail loudly, because what it would take
 * with it is the evidence.
 *
 * `(contract_id, role)` is unique: one signature per party. A second would make
 * "who signed" ambiguous, and a re-signature is a new contract.
 *
 * `signature_image` is encrypted at rest. It is a trace of a real person's
 * hand, it has no use outside its document, and a database dump should not hand
 * out images of people's signatures.
 *
 * `core_contract_signature_challenges` holds the six-digit codes. Only a hash,
 * because a code readable in a dump would let somebody sign without ever
 * seeing the mailbox - the one thing it exists to prove. Tied to the LINK
 * rather than the contract, so revoking an address kills the codes issued
 * through it.
 */
final class Version20260909140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add contract signatures and their email challenges';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE seq_core_contract_signature_id INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE seq_core_contract_challenge_id INCREMENT BY 1 MINVALUE 1 START 1');

        $this->addSql('CREATE TABLE core_contract_signatures (
            id INT NOT NULL,
            contract_id INT NOT NULL,
            user_id INT DEFAULT NULL,
            role VARCHAR(16) NOT NULL,
            declared_first_name VARCHAR(100) NOT NULL,
            declared_last_name VARCHAR(100) NOT NULL,
            declared_email VARCHAR(180) NOT NULL,
            declared_place VARCHAR(120) NOT NULL,
            declared_date DATE NOT NULL,
            signed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            link_selector VARCHAR(32) DEFAULT NULL,
            challenge_verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            signed_content_hash VARCHAR(64) NOT NULL,
            signature_image TEXT DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        )');

        $this->addSql('CREATE UNIQUE INDEX uniq_contract_signature_role ON core_contract_signatures (contract_id, role)');
        $this->addSql('CREATE INDEX IDX_contract_signature_user ON core_contract_signatures (user_id)');
        $this->addSql('ALTER TABLE core_contract_signatures ADD CONSTRAINT FK_contract_signature_contract FOREIGN KEY (contract_id) REFERENCES core_contracts (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE core_contract_signatures ADD CONSTRAINT FK_contract_signature_user FOREIGN KEY (user_id) REFERENCES core_users (id) ON DELETE SET NULL NOT DEFERRABLE');

        $this->addSql('CREATE TABLE core_contract_signature_challenges (
            id INT NOT NULL,
            link_id INT NOT NULL,
            hashed_code VARCHAR(64) NOT NULL,
            sent_to VARCHAR(180) NOT NULL,
            expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            attempts INT DEFAULT 0 NOT NULL,
            consumed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        )');

        // The manager reads the newest code for a link and counts those issued
        // in the last hour: both walk this index.
        $this->addSql('CREATE INDEX IDX_contract_challenge_link ON core_contract_signature_challenges (link_id, created_at)');
        $this->addSql('ALTER TABLE core_contract_signature_challenges ADD CONSTRAINT FK_contract_challenge_link FOREIGN KEY (link_id) REFERENCES core_contract_access_links (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE core_contract_signature_challenges DROP CONSTRAINT FK_contract_challenge_link');
        $this->addSql('ALTER TABLE core_contract_signatures DROP CONSTRAINT FK_contract_signature_user');
        $this->addSql('ALTER TABLE core_contract_signatures DROP CONSTRAINT FK_contract_signature_contract');
        $this->addSql('DROP TABLE core_contract_signature_challenges');
        $this->addSql('DROP TABLE core_contract_signatures');
        $this->addSql('DROP SEQUENCE seq_core_contract_challenge_id');
        $this->addSql('DROP SEQUENCE seq_core_contract_signature_id');
    }
}
