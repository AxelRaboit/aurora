<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A refusal, and the chasing of an unanswered contract.
 *
 * Two additions to `core_contracts`, both purely additive so an existing row
 * needs no backfill: a contract nobody declined has no refusal date, and one
 * nobody chased has been chased zero times.
 *
 * The refusal keeps the same evidence a signature keeps - when, from where,
 * with what agent - because it is the same kind of act by the same person,
 * even though it binds nobody. The reason is text and nullable: it is offered,
 * never required.
 *
 * The reminder counter lives here rather than on the access link because a
 * reminder hands out a new address; counted on the link it would restart at
 * zero every time and the ceiling would never be reached.
 *
 * Nothing here is about retention: the duration is a setting, and the date a
 * contract is kept until is `frozen_at` plus that setting. Storing it would
 * be storing a value that changes when the policy does.
 */
final class Version20260911090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the refusal trace and the reminder counters to core_contracts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE core_contracts ADD refused_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE core_contracts ADD refusal_reason TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE core_contracts ADD refused_from_ip VARCHAR(45) DEFAULT NULL');
        $this->addSql('ALTER TABLE core_contracts ADD refused_user_agent TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE core_contracts ADD reminder_count INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE core_contracts ADD last_reminder_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE core_contracts DROP refused_at');
        $this->addSql('ALTER TABLE core_contracts DROP refusal_reason');
        $this->addSql('ALTER TABLE core_contracts DROP refused_from_ip');
        $this->addSql('ALTER TABLE core_contracts DROP refused_user_agent');
        $this->addSql('ALTER TABLE core_contracts DROP reminder_count');
        $this->addSql('ALTER TABLE core_contracts DROP last_reminder_at');
    }
}
