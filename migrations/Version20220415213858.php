<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220415213858 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shipping_policy_state_entity (
                id BINARY(16) NOT NULL COMMENT \'(DC2Type:ulid)\',
                order_id BINARY(16) NOT NULL COMMENT \'(DC2Type:ulid)\',
                order_placed TINYINT(1) NOT NULL,
                order_billed TINYINT(1) NOT NULL,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8B5AF20D8D9F6D38 ON shipping_policy_state_entity (order_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_8B5AF20D8D9F6D38 ON shipping_policy_state_entity');
        $this->addSql('DROP TABLE shipping_policy_state_entity');
    }
}
