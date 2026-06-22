<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260508111025 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bingo_card DROP CONSTRAINT fk_3999e97c59027487');
        $this->addSql('ALTER TABLE bingo_card ADD CONSTRAINT FK_3999E97C59027487 FOREIGN KEY (theme_id) REFERENCES theme (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE red_flag DROP CONSTRAINT fk_8b4f0b1659027487');
        $this->addSql('ALTER TABLE red_flag ADD archived_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE red_flag ADD CONSTRAINT FK_8B4F0B1659027487 FOREIGN KEY (theme_id) REFERENCES theme (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE INDEX idx_red_flag_archived_at ON red_flag (archived_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bingo_card DROP CONSTRAINT FK_3999E97C59027487');
        $this->addSql('ALTER TABLE bingo_card ADD CONSTRAINT fk_3999e97c59027487 FOREIGN KEY (theme_id) REFERENCES theme (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE red_flag DROP CONSTRAINT FK_8B4F0B1659027487');
        $this->addSql('DROP INDEX idx_red_flag_archived_at');
        $this->addSql('ALTER TABLE red_flag DROP archived_at');
        $this->addSql('ALTER TABLE red_flag ADD CONSTRAINT fk_8b4f0b1659027487 FOREIGN KEY (theme_id) REFERENCES theme (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
