<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220410213036 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add buyers_remorse_state table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE buyers_remorse_state (
            id BINARY(16) NOT NULL,
            correlation_order_id BINARY(16) NOT NULL,
            state JSON NOT NULL,
            PRIMARY KEY(id),
            INDEX idx_correlation_order_id (correlation_order_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE buyers_remorse_state');
    }
}