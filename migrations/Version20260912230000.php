<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The address that opens a deck without an account.
 *
 * The token is a plain unique column, not a selector and a hash. That is the
 * note share link's arrangement rather than the contract's, and the difference
 * is the stake: a contract's token stands between a database dump and a
 * signature in somebody else's name, while a deck has nothing to forge - a
 * database of decks that leaks has already leaked the decks.
 *
 * `ON DELETE CASCADE`: a link to a deleted deck is an address that can only
 * answer 404, and keeping it would be keeping a secret for nothing.
 */
final class Version20260912230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Share links for Studio decks';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE seq_core_deck_share_link_id INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql("CREATE TABLE core_deck_share_links (id INT NOT NULL, deck_id INT NOT NULL, token VARCHAR(64) NOT NULL, label VARCHAR(120) DEFAULT '' NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))");
        $this->addSql('CREATE UNIQUE INDEX UNIQ_71BDA2525F37A13B ON core_deck_share_links (token)');
        $this->addSql('CREATE INDEX IDX_71BDA252111948DC ON core_deck_share_links (deck_id)');
        $this->addSql('ALTER TABLE core_deck_share_links ADD CONSTRAINT FK_71BDA252111948DC FOREIGN KEY (deck_id) REFERENCES core_decks (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE core_deck_share_links DROP CONSTRAINT FK_71BDA252111948DC');
        $this->addSql('DROP TABLE core_deck_share_links');
        $this->addSql('DROP SEQUENCE seq_core_deck_share_link_id CASCADE');
    }
}
