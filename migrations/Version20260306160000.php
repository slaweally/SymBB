<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260306160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add lastpost columns to mybb_forums';
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;
        $columns = $conn->createSchemaManager()->listTableColumns('mybb_forums');
        $existing = array_map('strtolower', array_keys($columns));

        if (!in_array('lastpost', $existing, true)) {
            $this->addSql('ALTER TABLE mybb_forums ADD COLUMN lastpost INT NOT NULL DEFAULT 0');
        }
        if (!in_array('lastposter', $existing, true)) {
            $this->addSql('ALTER TABLE mybb_forums ADD COLUMN lastposter VARCHAR(120) NOT NULL DEFAULT \'\'');
        }
        if (!in_array('lastposteruid', $existing, true)) {
            $this->addSql('ALTER TABLE mybb_forums ADD COLUMN lastposteruid INT NOT NULL DEFAULT 0');
        }
        if (!in_array('lastposttid', $existing, true)) {
            $this->addSql('ALTER TABLE mybb_forums ADD COLUMN lastposttid INT NOT NULL DEFAULT 0');
        }
        if (!in_array('lastpostsubject', $existing, true)) {
            $this->addSql('ALTER TABLE mybb_forums ADD COLUMN lastpostsubject VARCHAR(120) NOT NULL DEFAULT \'\'');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mybb_forums DROP COLUMN IF EXISTS lastpost');
        $this->addSql('ALTER TABLE mybb_forums DROP COLUMN IF EXISTS lastposter');
        $this->addSql('ALTER TABLE mybb_forums DROP COLUMN IF EXISTS lastposteruid');
        $this->addSql('ALTER TABLE mybb_forums DROP COLUMN IF EXISTS lastposttid');
        $this->addSql('ALTER TABLE mybb_forums DROP COLUMN IF EXISTS lastpostsubject');
    }
}
