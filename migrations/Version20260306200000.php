<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260306200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add warnings, warningtypes tables and warningpoints column to users';
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;
        $sm = $conn->createSchemaManager();

        // Add warningpoints to mybb_users if missing
        $columns = $sm->listTableColumns('mybb_users');
        if (!isset($columns['warningpoints'])) {
            $this->addSql('ALTER TABLE mybb_users ADD COLUMN warningpoints INT NOT NULL DEFAULT 0');
        }

        // Create mybb_warningtypes if not exists
        $this->addSql('CREATE TABLE IF NOT EXISTS mybb_warningtypes (
                tid INT AUTO_INCREMENT NOT NULL,
                title VARCHAR(200) NOT NULL,
                points INT NOT NULL DEFAULT 1,
                expirationtime INT NOT NULL DEFAULT 0,
                PRIMARY KEY(tid)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        // Create mybb_warnings if not exists
        $this->addSql('CREATE TABLE IF NOT EXISTS mybb_warnings (
                wid INT AUTO_INCREMENT NOT NULL,
                uid INT NOT NULL,
                issuedby INT NOT NULL,
                tid INT NOT NULL,
                pid INT NOT NULL DEFAULT 0,
                title VARCHAR(200) NOT NULL DEFAULT \'\',
                points INT NOT NULL DEFAULT 0,
                dateline INT NOT NULL DEFAULT 0,
                notes LONGTEXT NOT NULL,
                PRIMARY KEY(wid),
                INDEX idx_uid (uid),
                INDEX idx_issuedby (issuedby)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS mybb_warnings');
        $this->addSql('DROP TABLE IF EXISTS mybb_warningtypes');
        $this->addSql('ALTER TABLE mybb_users DROP COLUMN IF EXISTS warningpoints');
    }
}
