<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A term's address is unique inside its taxonomy, not across all of them.
 *
 * `uniq_term_locale_slug` covered `(locale, slug)` for every taxonomy at once,
 * on the stated grounds that "`/fr/theme/boulange` names exactly one term". The
 * route disagrees: it is `/{locale}/{taxonomySlug}/{termSlug}`, and the
 * controller looks the term up inside the taxonomy the URL names. The wider
 * index was never what made the address unambiguous.
 *
 * What it did instead was refuse legitimate content. Two taxonomies cannot both
 * have a term called "Éditorial" - a tag and a documentation rubric, say - and
 * the refusal arrives as a constraint violation from the depths, with no
 * sentence for whoever typed the name.
 *
 * The uniqueness now wanted spans two tables: the taxonomy is on the term, the
 * slug is on its translation. A unique index cannot do that, so the taxonomy is
 * carried on the translation as well. Denormalised on purpose, and cheap to
 * keep honest: a translation belongs to one term for its whole life, and the
 * entity writes the column from that term rather than letting a caller pass it.
 */
final class Version20260912140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Scope the uniqueness of a term slug to its taxonomy';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE core_taxonomy_term_translations ADD taxonomy_id INT DEFAULT NULL');

        // Backfilled from the term, which is where the answer has always been.
        $this->addSql(<<<'SQL'
            UPDATE core_taxonomy_term_translations tt
            SET taxonomy_id = t.taxonomy_id
            FROM core_taxonomy_terms t
            WHERE t.id = tt.term_id
            SQL);

        $this->addSql('ALTER TABLE core_taxonomy_term_translations ALTER COLUMN taxonomy_id SET NOT NULL');
        $this->addSql('ALTER TABLE core_taxonomy_term_translations ADD CONSTRAINT FK_term_translation_taxonomy FOREIGN KEY (taxonomy_id) REFERENCES core_taxonomies (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_term_translation_taxonomy ON core_taxonomy_term_translations (taxonomy_id)');

        $this->addSql('DROP INDEX uniq_term_locale_slug');
        $this->addSql('CREATE UNIQUE INDEX uniq_term_taxonomy_locale_slug ON core_taxonomy_term_translations (taxonomy_id, locale, slug)');
    }

    public function down(Schema $schema): void
    {
        // The old index cannot always be restored: rows that are legal under
        // the narrower rule may collide under the wider one. Reversing this
        // means deciding which of the duplicates to rename, and that is not a
        // migration's decision to make.
        $this->addSql('DROP INDEX uniq_term_taxonomy_locale_slug');
        $this->addSql('CREATE UNIQUE INDEX uniq_term_locale_slug ON core_taxonomy_term_translations (locale, slug)');

        $this->addSql('ALTER TABLE core_taxonomy_term_translations DROP CONSTRAINT FK_term_translation_taxonomy');
        $this->addSql('DROP INDEX IDX_term_translation_taxonomy');
        $this->addSql('ALTER TABLE core_taxonomy_term_translations DROP taxonomy_id');
    }
}
