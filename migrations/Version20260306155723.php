<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260306155723 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pmfolders, skype, google to mybb_users for PM folders and member search';
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;
        $cols = $conn->fetchFirstColumn("SHOW COLUMNS FROM mybb_users LIKE 'pmfolders'");
        if (empty($cols)) {
            $conn->executeStatement('ALTER TABLE mybb_users ADD COLUMN pmfolders TEXT DEFAULT NULL');
        }
        $cols = $conn->fetchFirstColumn("SHOW COLUMNS FROM mybb_users LIKE 'skype'");
        if (empty($cols)) {
            $conn->executeStatement('ALTER TABLE mybb_users ADD COLUMN skype VARCHAR(80) DEFAULT NULL');
        }
        $cols = $conn->fetchFirstColumn("SHOW COLUMNS FROM mybb_users LIKE 'google'");
        if (empty($cols)) {
            $conn->executeStatement('ALTER TABLE mybb_users ADD COLUMN google VARCHAR(80) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        // Optional: drop columns - may break MyBB compatibility
    }
}
