<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220422175820 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE buyers_remorse_state_entity ADD version INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE ship_order_state_entity ADD version INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE shipping_policy_state_entity ADD version INT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE buyers_remorse_state_entity DROP version');
        $this->addSql('ALTER TABLE ship_order_state_entity DROP version');
        $this->addSql('ALTER TABLE shipping_policy_state_entity DROP version');
    }
}
