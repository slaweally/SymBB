<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260306160123 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'MyBB native: statustime, readtime, receipt (privatemessages), icq (users)';
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;
        foreach (['statustime', 'readtime', 'receipt'] as $col) {
            $cols = $conn->fetchFirstColumn("SHOW COLUMNS FROM mybb_privatemessages LIKE '{$col}'");
            if (empty($cols)) {
                $type = $col === 'receipt' ? 'INT DEFAULT NULL' : 'INT UNSIGNED DEFAULT NULL';
                $conn->executeStatement("ALTER TABLE mybb_privatemessages ADD COLUMN {$col} {$type}");
            }
        }
        $cols = $conn->fetchFirstColumn("SHOW COLUMNS FROM mybb_users LIKE 'icq'");
        if (empty($cols)) {
            $conn->executeStatement('ALTER TABLE mybb_users ADD COLUMN icq VARCHAR(20) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
    }
}
