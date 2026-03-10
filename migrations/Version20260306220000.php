<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260306220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add lostpwcode to users, visible to posts for drafts';
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;
        $sm = $conn->createSchemaManager();

        $columns = $sm->listTableColumns('mybb_users');
        if (!isset($columns['lostpwcode'])) {
            $this->addSql('ALTER TABLE mybb_users ADD COLUMN lostpwcode VARCHAR(50) NULL DEFAULT NULL');
        }

        $postColumns = $sm->listTableColumns('mybb_posts');
        if (!isset($postColumns['visible'])) {
            $this->addSql('ALTER TABLE mybb_posts ADD COLUMN visible INT NOT NULL DEFAULT 1');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mybb_users DROP COLUMN IF EXISTS lostpwcode');
        $this->addSql('ALTER TABLE mybb_posts DROP COLUMN IF EXISTS visible');
    }
}
