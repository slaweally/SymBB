<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260306180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add rules columns to mybb_forums for misc.php rules action';
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;
        $columns = $conn->createSchemaManager()->listTableColumns('mybb_forums');
        if (!isset($columns['rules'])) {
            $this->addSql('ALTER TABLE mybb_forums ADD COLUMN rules TEXT NOT NULL DEFAULT \'\'');
        }
        if (!isset($columns['rulestitle'])) {
            $this->addSql('ALTER TABLE mybb_forums ADD COLUMN rulestitle VARCHAR(200) NOT NULL DEFAULT \'\'');
        }
        if (!isset($columns['rulestype'])) {
            $this->addSql('ALTER TABLE mybb_forums ADD COLUMN rulestype TINYINT NOT NULL DEFAULT 0');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mybb_forums DROP COLUMN IF EXISTS rules');
        $this->addSql('ALTER TABLE mybb_forums DROP COLUMN IF EXISTS rulestitle');
        $this->addSql('ALTER TABLE mybb_forums DROP COLUMN IF EXISTS rulestype');
    }
}
