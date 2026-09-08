<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Let a listing page stop listing.
 *
 * A listing page already borrows its header from a publication it designates.
 * It can now borrow that publication's content grid as well, which turns an
 * archive into a page an author composes - a card beside the words that sell
 * it, rather than a row of cards and nothing else.
 *
 * That raises a question the product did not have to answer before: whether
 * the automatic list still follows. For an archive of articles it should - the
 * composed part is an introduction and the list is the point. For a page like
 * Services, where the composed part already places every entry, it should not:
 * the list would show each of them a second time as a card nobody arranged.
 *
 * Defaults to true, which is what every existing archive does today.
 */
final class Version20260908180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the listing switch to core_post_types';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE core_post_types ADD archive_shows_list BOOLEAN DEFAULT true NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE core_post_types DROP archive_shows_list');
    }
}
