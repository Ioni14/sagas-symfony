<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220415215343 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE buyers_remorse_state_entity (id BINARY(16) NOT NULL COMMENT \'(DC2Type:ulid)\', order_id BINARY(16) NOT NULL COMMENT \'(DC2Type:ulid)\', UNIQUE INDEX UNIQ_3193CC068D9F6D38 (order_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE ship_order_state_entity (id BINARY(16) NOT NULL COMMENT \'(DC2Type:ulid)\', order_id BINARY(16) NOT NULL COMMENT \'(DC2Type:ulid)\', shipment_accepted_by_maple TINYINT(1) NOT NULL, shipment_order_sent_to_alpine TINYINT(1) NOT NULL, shipment_accepted_by_alpine TINYINT(1) NOT NULL, UNIQUE INDEX UNIQ_96AB216C8D9F6D38 (order_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE buyers_remorse_state_entity');
        $this->addSql('DROP TABLE ship_order_state_entity');
    }
}
