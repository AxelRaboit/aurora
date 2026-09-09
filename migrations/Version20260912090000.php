<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * An explicit reading order for a publication.
 *
 * Everything a visitor sees is ordered by publication date, which is right for
 * a blog and wrong for anything meant to be read in sequence. The tour of the
 * product at /fr/page/aurora already fakes an order by spacing its twenty-three
 * cards one minute apart in `published_at` - insert a card between two others
 * and every timestamp after it has to be recomputed. A documentation of a
 * hundred and thirty-four pages cannot be held together that way.
 *
 * Nullable, and null on every existing row, because null means "no opinion":
 * the ordering falls through to the date, which is exactly what every site
 * does today. Nothing moves until somebody numbers something.
 *
 * Not a per-type "manual or chronological" switch, which was the other option
 * and the more elaborate one. It would have to be set before a position could
 * be given, it can disagree with the positions actually stored, and it answers
 * a question nobody asks: an author numbers the pages they want in an order
 * and leaves the rest alone.
 */
final class Version20260912090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the explicit reading position to core_posts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE core_posts ADD position INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE core_posts DROP position');
    }
}
