<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260306210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add mybb_threadsubscriptions table and lastpost to threads';
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;
        $sm = $conn->createSchemaManager();
        $columns = $sm->listTableColumns('mybb_threads');
        if (!isset($columns['lastpost'])) {
            $this->addSql('ALTER TABLE mybb_threads ADD COLUMN lastpost INT NOT NULL DEFAULT 0');
        }

        $this->addSql('CREATE TABLE IF NOT EXISTS mybb_threadsubscriptions (
                sid INT AUTO_INCREMENT NOT NULL,
                uid INT NOT NULL,
                tid INT NOT NULL,
                notification INT NOT NULL DEFAULT 0,
                dateline INT NOT NULL DEFAULT 0,
                PRIMARY KEY(sid),
                UNIQUE INDEX uid_tid (uid, tid),
                INDEX idx_uid (uid),
                INDEX idx_tid (tid)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS mybb_threadsubscriptions');
    }
}
