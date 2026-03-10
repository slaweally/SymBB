<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260306150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create mybb_reportedcontent table for content reporting';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS mybb_reportedcontent (
            rid INT AUTO_INCREMENT NOT NULL,
            cid INT NOT NULL,
            type VARCHAR(50) NOT NULL DEFAULT \'post\',
            uid INT NOT NULL,
            reason LONGTEXT NOT NULL,
            dateline INT NOT NULL,
            PRIMARY KEY(rid),
            INDEX IDX_REPORTED_CID_TYPE (cid, type),
            INDEX IDX_REPORTED_UID_CID (uid, cid)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS mybb_reportedcontent');
    }
}
