<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260306190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add lastvisit to mybb_users for markread';
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;
        $columns = $conn->createSchemaManager()->listTableColumns('mybb_users');
        if (!isset($columns['lastvisit'])) {
            $this->addSql('ALTER TABLE mybb_users ADD COLUMN lastvisit INT NULL DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mybb_users DROP COLUMN IF EXISTS lastvisit');
    }
}
