<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Decks, their slides and the categories they are filed under.
 *
 * Written by hand rather than taken from `migrations:diff`. The generated diff
 * also wanted to drop three dozen sequences left behind by the modules pulled
 * out of the monorepo - `seq_core_agency_id`, the CRM's, the e-commerce one -
 * which nothing in this change has any business touching.
 *
 * Two foreign keys, two different rules, and the difference is deliberate:
 *
 * - a slide is `ON DELETE CASCADE`, because it has no life outside its deck;
 * - a customer is `ON DELETE SET NULL`, because deleting a client must not
 *   take the deck that was presented to them. The work happened, and the
 *   document is often the only trace of it left.
 *
 * `customer_id` is nullable for the reason the entity gives: a strategy deck
 * written for oneself has no client, and a column that demanded one would make
 * the commonest internal case impossible.
 */
final class Version20260912220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Decks, slides and deck categories for the Studio module';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE seq_core_deck_id INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE seq_core_deck_slide_id INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE seq_core_deck_category_id INCREMENT BY 1 MINVALUE 1 START 1');

        $this->addSql('CREATE TABLE core_deck_categories (id INT NOT NULL, name VARCHAR(100) NOT NULL, color VARCHAR(7) DEFAULT NULL, position INT DEFAULT 0 NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');

        $this->addSql('CREATE TABLE core_decks (id INT NOT NULL, category_id INT DEFAULT NULL, customer_id INT DEFAULT NULL, title VARCHAR(200) NOT NULL, description TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_F57DE7D212469DE2 ON core_decks (category_id)');
        $this->addSql('CREATE INDEX IDX_F57DE7D29395C3F3 ON core_decks (customer_id)');

        $this->addSql('CREATE TABLE core_deck_slides (id INT NOT NULL, deck_id INT NOT NULL, layout VARCHAR(20) NOT NULL, content JSON DEFAULT \'{}\' NOT NULL, speaker_notes TEXT DEFAULT NULL, position INT DEFAULT 0 NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_5F851CE8111948DC ON core_deck_slides (deck_id)');

        $this->addSql('ALTER TABLE core_decks ADD CONSTRAINT FK_F57DE7D212469DE2 FOREIGN KEY (category_id) REFERENCES core_deck_categories (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE core_decks ADD CONSTRAINT FK_F57DE7D29395C3F3 FOREIGN KEY (customer_id) REFERENCES core_customers (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE core_deck_slides ADD CONSTRAINT FK_5F851CE8111948DC FOREIGN KEY (deck_id) REFERENCES core_decks (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE core_deck_slides DROP CONSTRAINT FK_5F851CE8111948DC');
        $this->addSql('ALTER TABLE core_decks DROP CONSTRAINT FK_F57DE7D212469DE2');
        $this->addSql('ALTER TABLE core_decks DROP CONSTRAINT FK_F57DE7D29395C3F3');

        $this->addSql('DROP TABLE core_deck_slides');
        $this->addSql('DROP TABLE core_decks');
        $this->addSql('DROP TABLE core_deck_categories');

        $this->addSql('DROP SEQUENCE seq_core_deck_category_id CASCADE');
        $this->addSql('DROP SEQUENCE seq_core_deck_slide_id CASCADE');
        $this->addSql('DROP SEQUENCE seq_core_deck_id CASCADE');
    }
}
