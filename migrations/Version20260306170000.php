<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260306170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create mybb_polls, mybb_pollvotes, add poll column to mybb_threads';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS mybb_polls (
            pid INT AUTO_INCREMENT NOT NULL,
            tid INT NOT NULL,
            question VARCHAR(255) NOT NULL,
            dateline INT NOT NULL,
            `options` LONGTEXT NOT NULL,
            votes LONGTEXT NOT NULL,
            numvotes INT NOT NULL DEFAULT 0,
            timeout INT NOT NULL DEFAULT 0,
            closed INT NOT NULL DEFAULT 0,
            `multiple` INT NOT NULL DEFAULT 0,
            PRIMARY KEY(pid),
            INDEX IDX_POLL_TID (tid)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        $this->addSql('CREATE TABLE IF NOT EXISTS mybb_pollvotes (
            vid INT AUTO_INCREMENT NOT NULL,
            pid INT NOT NULL,
            uid INT NOT NULL,
            voteoption INT NOT NULL,
            dateline INT NOT NULL,
            PRIMARY KEY(vid),
            INDEX IDX_POLLVOTE_PID_UID (pid, uid)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        $conn = $this->connection;
        try {
            $columns = $conn->createSchemaManager()->listTableColumns('mybb_threads');
            $existing = array_map('strtolower', array_keys($columns));
            if (!in_array('poll', $existing, true)) {
                $this->addSql('ALTER TABLE mybb_threads ADD COLUMN poll INT NOT NULL DEFAULT 0');
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS mybb_pollvotes');
        $this->addSql('DROP TABLE IF EXISTS mybb_polls');
        $this->addSql('ALTER TABLE mybb_threads DROP COLUMN IF EXISTS poll');
    }
}
