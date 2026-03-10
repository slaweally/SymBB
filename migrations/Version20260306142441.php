<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260306142441 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create mybb_reputation table and add reputation column to mybb_users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS mybb_reputation (
            rid INT AUTO_INCREMENT NOT NULL,
            uid INT NOT NULL,
            adduid INT NOT NULL,
            pid INT NOT NULL DEFAULT 0,
            reputation INT NOT NULL,
            comments LONGTEXT NOT NULL,
            dateline INT NOT NULL,
            PRIMARY KEY(rid),
            INDEX IDX_REP_UID (uid),
            INDEX IDX_REP_ADDUID_PID (adduid, pid)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        $conn = $this->connection;
        $columns = $conn->createSchemaManager()->listTableColumns('mybb_users');
        if (!isset($columns['reputation'])) {
            $this->addSql('ALTER TABLE mybb_users ADD COLUMN reputation INT NOT NULL DEFAULT 0');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS mybb_reputation');
        $conn = $this->connection;
        $columns = $conn->createSchemaManager()->listTableColumns('mybb_users');
        if (isset($columns['reputation'])) {
            $this->addSql('ALTER TABLE mybb_users DROP COLUMN reputation');
        }
    }
}
