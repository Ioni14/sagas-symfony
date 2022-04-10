<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220410190234 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add shipping_policy_state table.';
    }

    public function up(Schema $schema): void
    {
        /** @see https://mariadb.com/kb/en/guiduuid-performance/ */
        $this->addSql(<<<EOT
            CREATE FUNCTION UuidToBin(_uuid BINARY(36))
                RETURNS BINARY(16)
                LANGUAGE SQL  DETERMINISTIC  CONTAINS SQL  SQL SECURITY INVOKER
            RETURN
                UNHEX(CONCAT(
                        SUBSTR(_uuid, 15, 4),
                        SUBSTR(_uuid, 10, 4),
                        SUBSTR(_uuid,  1, 8),
                        SUBSTR(_uuid, 20, 4),
                        SUBSTR(_uuid, 25) ));
            ;
        EOT
        );
        $this->addSql(<<<EOT
            CREATE FUNCTION UuidFromBin(_bin BINARY(16))
                RETURNS BINARY(36)
                LANGUAGE SQL  DETERMINISTIC  CONTAINS SQL  SQL SECURITY INVOKER
            RETURN
                LCASE(CONCAT_WS('-',
                                HEX(SUBSTR(_bin,  5, 4)),
                                HEX(SUBSTR(_bin,  3, 2)),
                                HEX(SUBSTR(_bin,  1, 2)),
                                HEX(SUBSTR(_bin,  9, 2)),
                                HEX(SUBSTR(_bin, 11))
                    ));
            ;
        EOT
        );
        $this->addSql('CREATE TABLE shipping_policy_state (
            id BINARY(16) NOT NULL,
            correlation_order_id BINARY(16) NOT NULL,
            state JSON NOT NULL,
            PRIMARY KEY(id),
            INDEX idx_correlation_order_id (correlation_order_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE shipping_policy_state');
        $this->addSql('DROP FUNCTION UuidFromBin');
        $this->addSql('DROP FUNCTION UuidToBin');
    }
}
