<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260306240000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add User location and invisible columns for online tracking';
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;
        try {
            $sm = $conn->createSchemaManager();
            $columns = $sm->listTableColumns('mybb_users');
        } catch (\Throwable) {
            return;
        }
        if (!isset($columns['location'])) {
            $this->addSql('ALTER TABLE mybb_users ADD COLUMN location VARCHAR(255) NULL DEFAULT NULL');
        }
        if (!isset($columns['invisible'])) {
            $this->addSql('ALTER TABLE mybb_users ADD COLUMN invisible INT NOT NULL DEFAULT 0');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mybb_users DROP COLUMN IF EXISTS location');
        $this->addSql('ALTER TABLE mybb_users DROP COLUMN IF EXISTS invisible');
    }
}
