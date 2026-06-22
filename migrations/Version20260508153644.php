<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260508153644 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bingo_card ADD bingo_reached_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_bingo_card_bingo_reached_at ON bingo_card (bingo_reached_at)');
        $this->addSql('CREATE INDEX idx_bingo_card_created_at ON bingo_card (created_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_bingo_card_bingo_reached_at');
        $this->addSql('DROP INDEX idx_bingo_card_created_at');
        $this->addSql('ALTER TABLE bingo_card DROP bingo_reached_at');
    }
}
