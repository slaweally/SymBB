<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260306230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create mybb_searchlog table for search result caching (SID pagination)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE IF NOT EXISTS mybb_searchlog (
                sid VARCHAR(32) NOT NULL PRIMARY KEY,
                uid INT UNSIGNED NOT NULL DEFAULT 0,
                dateline INT UNSIGNED NOT NULL DEFAULT 0,
                ipaddress VARCHAR(255) NULL,
                threads TEXT NULL,
                posts TEXT NULL,
                resulttype VARCHAR(20) NOT NULL DEFAULT 'posts',
                querycache TEXT NULL,
                keywords VARCHAR(255) NULL
            )
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS mybb_searchlog');
    }
}
