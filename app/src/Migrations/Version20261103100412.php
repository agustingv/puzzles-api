<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261103100412 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The avatar the player put together in the app.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE player ADD avatar VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE player DROP avatar');
    }
}
