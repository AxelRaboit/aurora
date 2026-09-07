<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Give a listing page a name of its own, and a header to borrow.
 *
 * The label names one thing - a publication *is* a Service - and the page
 * listing them all is called Services. Until now the archive printed the
 * label, so every listing page announced itself in the singular.
 *
 * `archive_post_id` rather than a picture: a banner of its own here would be a
 * second banner, with none of the real one's settings and no words per
 * language, configured on the screen that describes the site's structure
 * rather than in the editor where every other visual decision is made. So the
 * page points at a publication and borrows its header whole.
 *
 * An id with no foreign key, deliberately: `core_posts` already points at
 * `core_post_types`, and pointing back would close a cycle that the fixtures
 * purger cannot untangle - it truncates in dependency order, and a cycle has
 * none. The homepage designates a publication the same way, and the reader is
 * protected the same way: the id is resolved on the way out and refused when
 * it names nothing.
 *
 * Both nullable: every post type predates them, the title falls back to the
 * label, and a listing page with nothing designated keeps the plain header it
 * has always had.
 */
final class Version20260907060000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add archive title and archive header publication to core_post_types';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE core_post_types ADD archive_title VARCHAR(150) DEFAULT NULL');
        $this->addSql('ALTER TABLE core_post_types ADD archive_post_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE core_post_types DROP archive_post_id');
        $this->addSql('ALTER TABLE core_post_types DROP archive_title');
    }
}
