<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261012154544 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Niveau de batterie transmis par les liseuses';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE device ADD COLUMN battery_level INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE device ADD COLUMN charging BOOLEAN DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__device AS SELECT id, name, token_hash, refresh_seconds, enabled, created_at, last_seen_at, dashboard_id FROM device');
        $this->addSql('DROP TABLE device');
        $this->addSql('CREATE TABLE device (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(100) NOT NULL, token_hash VARCHAR(64) DEFAULT NULL, refresh_seconds INTEGER NOT NULL, enabled BOOLEAN NOT NULL, created_at DATETIME NOT NULL, last_seen_at DATETIME DEFAULT NULL, dashboard_id INTEGER DEFAULT NULL, CONSTRAINT FK_92FB68EB9D04D2B FOREIGN KEY (dashboard_id) REFERENCES dashboard (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO device (id, name, token_hash, refresh_seconds, enabled, created_at, last_seen_at, dashboard_id) SELECT id, name, token_hash, refresh_seconds, enabled, created_at, last_seen_at, dashboard_id FROM __temp__device');
        $this->addSql('DROP TABLE __temp__device');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_92FB68E5E237E06 ON device (name)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_92FB68EB3BC57DA ON device (token_hash)');
        $this->addSql('CREATE INDEX IDX_92FB68EB9D04D2B ON device (dashboard_id)');
    }
}
