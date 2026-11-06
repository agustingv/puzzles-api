<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261106082848 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Avatars with several accessories: room for animal:color and up to eight of them.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE player CHANGE avatar avatar VARCHAR(200) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Fails if an avatar no longer fits, rather than cutting it.
        $this->addSql('ALTER TABLE player CHANGE avatar avatar VARCHAR(50) DEFAULT NULL');
    }
}
