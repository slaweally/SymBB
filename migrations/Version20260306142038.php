<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260306142038 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create mybb_attachments table for post attachments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS mybb_attachments (
            aid INT AUTO_INCREMENT NOT NULL,
            pid INT NOT NULL,
            tid INT NOT NULL,
            uid INT NOT NULL,
            filename VARCHAR(120) NOT NULL,
            filetype VARCHAR(120) NOT NULL,
            filesize INT NOT NULL,
            attachname VARCHAR(120) NOT NULL,
            downloads INT NOT NULL DEFAULT 0,
            dateline INT NOT NULL,
            PRIMARY KEY(aid),
            INDEX IDX_ATTACHMENTS_PID (pid),
            INDEX IDX_ATTACHMENTS_TID (tid)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS mybb_attachments');
    }
}
