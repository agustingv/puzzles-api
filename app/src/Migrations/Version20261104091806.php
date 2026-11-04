<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261104091806 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The rankings: the ten best times of all players on each game and level.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE score (id BINARY(16) NOT NULL, game VARCHAR(32) NOT NULL, variant VARCHAR(16) NOT NULL, seconds INT NOT NULL, played_at DATETIME NOT NULL, sequence BIGINT NOT NULL, player_id BINARY(16) NOT NULL, INDEX score_ranking (game, variant, seconds, sequence), INDEX IDX_3299375199E6F5DF (player_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE score ADD CONSTRAINT FK_3299375199E6F5DF FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE score DROP FOREIGN KEY FK_3299375199E6F5DF');
        $this->addSql('DROP TABLE score');
    }
}
