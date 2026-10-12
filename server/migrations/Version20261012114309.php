<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261012114309 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE device (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(100) NOT NULL, token_hash VARCHAR(64) DEFAULT NULL, refresh_seconds INTEGER NOT NULL, enabled BOOLEAN NOT NULL, created_at DATETIME NOT NULL, last_seen_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_92FB68E5E237E06 ON device (name)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_92FB68EB3BC57DA ON device (token_hash)');
        $this->addSql('CREATE TABLE place (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, label VARCHAR(100) NOT NULL, address VARCHAR(255) NOT NULL, latitude DOUBLE PRECISION DEFAULT NULL, longitude DOUBLE PRECISION DEFAULT NULL, weather_location BOOLEAN NOT NULL)');
        $this->addSql('CREATE TABLE trip (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL, weekdays CLOB NOT NULL, avoid_tolls BOOLEAN NOT NULL, enabled BOOLEAN NOT NULL, origin_id INTEGER NOT NULL, destination_id INTEGER NOT NULL, CONSTRAINT FK_7656F53B56A273CC FOREIGN KEY (origin_id) REFERENCES place (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_7656F53B816C6140 FOREIGN KEY (destination_id) REFERENCES place (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_7656F53B56A273CC ON trip (origin_id)');
        $this->addSql('CREATE INDEX IDX_7656F53B816C6140 ON trip (destination_id)');
        $this->addSql('CREATE TABLE "user" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, username VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649F85E0677 ON "user" (username)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE device');
        $this->addSql('DROP TABLE place');
        $this->addSql('DROP TABLE trip');
        $this->addSql('DROP TABLE "user"');
    }
}
