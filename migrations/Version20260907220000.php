<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Let a menu entry declare the content type it heads.
 *
 * The highlight follows the address: an entry pointing at /fr/projets stays
 * lit on /fr/projets/onyx because one path sits under the other. That covers
 * a section whose publications live beneath it, and nothing else.
 *
 * A hub page is the case it does not cover. /fr/page/aurora introduces
 * publications at /fr/aurora/…, and the two addresses have no prefix in
 * common - so following a card from the hub took the navigation dark, on a
 * page that is plainly inside that section.
 *
 * Naming the type here says what the addresses cannot. An id with no foreign
 * key, like the post type's own archive publication: `core_post_types` is
 * already reachable from the other side, and a second real key closes a cycle
 * the fixtures purger cannot untangle. The id is resolved on the way out and
 * ignored when it names nothing.
 *
 * Nullable: every entry predates it, and an entry heading no section keeps the
 * behaviour it has always had.
 */
final class Version20260907220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the content type a menu entry heads to core_menu_items';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE core_menu_items ADD section_post_type_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE core_menu_items DROP section_post_type_id');
    }
}
