<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A document says whether it is being moved between backends, and why the last
 * attempt gave up.
 *
 * `storage_transfer_state` is the lock as well as the display. A move is
 * claimed by writing `pending` only over a row that is not already pending, in
 * one conditional statement, so a second click finds the door shut rather than
 * copying the same bytes twice and racing over which deletion wins. Using the
 * displayed state as the lock rather than adding a hidden one is deliberate:
 * two mechanisms saying the same thing eventually disagree, and the invisible
 * one is the one that stays wrong.
 *
 * `storage_transfer_error` exists so a failure can be read by whoever pressed
 * the button. A move that fails silently is a button that appears broken.
 */
final class Version20260912180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track whether a GED document is being moved between storage backends';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE core_ged_documents ADD storage_transfer_state VARCHAR(20) DEFAULT 'idle' NOT NULL");
        $this->addSql('ALTER TABLE core_ged_documents ADD storage_transfer_error TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE core_ged_documents DROP storage_transfer_state');
        $this->addSql('ALTER TABLE core_ged_documents DROP storage_transfer_error');
    }
}
