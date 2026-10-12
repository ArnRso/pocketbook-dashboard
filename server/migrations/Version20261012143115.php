<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261012143115 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rappels et leurs plages hebdomadaires';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE reminder (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, text VARCHAR(200) NOT NULL, enabled BOOLEAN NOT NULL)');
        $this->addSql('CREATE TABLE reminder_window (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, start_day INTEGER NOT NULL, start_time TIME NOT NULL, end_day INTEGER NOT NULL, end_time TIME NOT NULL, reminder_id INTEGER NOT NULL, CONSTRAINT FK_40C7EF35D987BE75 FOREIGN KEY (reminder_id) REFERENCES reminder (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_40C7EF35D987BE75 ON reminder_window (reminder_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE reminder_window');
        $this->addSql('DROP TABLE reminder');
    }
}
