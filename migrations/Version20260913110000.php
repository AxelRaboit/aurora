<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A Markdown note can sit in the trash, and remembers what it fell with.
 *
 * Deleting a note took its content, its sub-notes' parent link and the images
 * it referenced, in one gesture. The content is encrypted at rest, so there
 * was no reading it back from a dump either: what was gone was gone.
 *
 * `trashed_with_note_id` plays the part it plays for GED folders. Restoring a
 * page brings back the sub-pages that fell with it, and only those, so a note
 * deleted by hand last week stays where its author left it.
 */
final class Version20260913110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Trash for Markdown notes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE core_notes_markdown_notes ADD deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE core_notes_markdown_notes ADD trashed_with_note_id INT DEFAULT NULL');

        $this->addSql('CREATE INDEX idx_notes_md_deleted_at ON core_notes_markdown_notes (deleted_at)');
        $this->addSql('CREATE INDEX idx_notes_md_trashed_with ON core_notes_markdown_notes (trashed_with_note_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_notes_md_trashed_with');
        $this->addSql('DROP INDEX idx_notes_md_deleted_at');

        $this->addSql('ALTER TABLE core_notes_markdown_notes DROP trashed_with_note_id');
        $this->addSql('ALTER TABLE core_notes_markdown_notes DROP deleted_at');
    }
}
