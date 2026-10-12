<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261012134353 extends AbstractMigration
{
    private const TILE_COLUMNS = 'dashboard_id, widget, grid_column, grid_row, width, height, schedule_weekdays, schedule_start_time, schedule_end_time, place_id, trip_id';
    private const EVERY_DAY = "'[1,2,3,4,5,6,7]'";

    public function getDescription(): string
    {
        return 'Dashboards en grille : tuiles avec horaires, trajets et lieux simplifiés';
    }

    public function up(Schema $schema): void
    {
        // Données à convertir, mises de côté avant la reconstruction des tables place et trip
        $this->addSql('CREATE TEMPORARY TABLE __grid_weather AS SELECT id AS place_id FROM place WHERE weather_location = 1 ORDER BY id LIMIT 1');
        $this->addSql('CREATE TEMPORARY TABLE __grid_commute AS SELECT id AS trip_id, weekdays, start_time, end_time FROM trip WHERE enabled = 1');

        $this->addSql('CREATE TABLE dashboard (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(100) NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5C94FFF85E237E06 ON dashboard (name)');
        $this->addSql('CREATE TABLE tile (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, grid_column INTEGER NOT NULL, grid_row INTEGER NOT NULL, width INTEGER NOT NULL, height INTEGER NOT NULL, schedule_weekdays CLOB NOT NULL, schedule_start_time TIME DEFAULT NULL, schedule_end_time TIME DEFAULT NULL, dashboard_id INTEGER NOT NULL, widget VARCHAR(32) NOT NULL, trip_id INTEGER DEFAULT NULL, place_id INTEGER DEFAULT NULL, CONSTRAINT FK_768FA904B9D04D2B FOREIGN KEY (dashboard_id) REFERENCES dashboard (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_768FA904A5BC2E0E FOREIGN KEY (trip_id) REFERENCES trip (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_768FA904DA6A219 FOREIGN KEY (place_id) REFERENCES place (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_768FA904B9D04D2B ON tile (dashboard_id)');
        $this->addSql('CREATE INDEX IDX_768FA904A5BC2E0E ON tile (trip_id)');
        $this->addSql('CREATE INDEX IDX_768FA904DA6A219 ON tile (place_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__device AS SELECT id, name, token_hash, refresh_seconds, enabled, created_at, last_seen_at FROM device');
        $this->addSql('DROP TABLE device');
        $this->addSql('CREATE TABLE device (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(100) NOT NULL, token_hash VARCHAR(64) DEFAULT NULL, refresh_seconds INTEGER NOT NULL, enabled BOOLEAN NOT NULL, created_at DATETIME NOT NULL, last_seen_at DATETIME DEFAULT NULL, dashboard_id INTEGER DEFAULT NULL, CONSTRAINT FK_92FB68EB9D04D2B FOREIGN KEY (dashboard_id) REFERENCES dashboard (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO device (id, name, token_hash, refresh_seconds, enabled, created_at, last_seen_at) SELECT id, name, token_hash, refresh_seconds, enabled, created_at, last_seen_at FROM __temp__device');
        $this->addSql('DROP TABLE __temp__device');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_92FB68EB3BC57DA ON device (token_hash)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_92FB68E5E237E06 ON device (name)');
        $this->addSql('CREATE INDEX IDX_92FB68EB9D04D2B ON device (dashboard_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__place AS SELECT id, label, address, latitude, longitude FROM place');
        $this->addSql('DROP TABLE place');
        $this->addSql('CREATE TABLE place (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, label VARCHAR(100) NOT NULL, address VARCHAR(255) NOT NULL, latitude DOUBLE PRECISION DEFAULT NULL, longitude DOUBLE PRECISION DEFAULT NULL)');
        $this->addSql('INSERT INTO place (id, label, address, latitude, longitude) SELECT id, label, address, latitude, longitude FROM __temp__place');
        $this->addSql('DROP TABLE __temp__place');
        $this->addSql('CREATE TEMPORARY TABLE __temp__trip AS SELECT id, avoid_tolls, origin_id, destination_id FROM trip');
        $this->addSql('DROP TABLE trip');
        $this->addSql('CREATE TABLE trip (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, avoid_tolls BOOLEAN NOT NULL, origin_id INTEGER NOT NULL, destination_id INTEGER NOT NULL, CONSTRAINT FK_7656F53B56A273CC FOREIGN KEY (origin_id) REFERENCES place (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_7656F53B816C6140 FOREIGN KEY (destination_id) REFERENCES place (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO trip (id, avoid_tolls, origin_id, destination_id) SELECT id, avoid_tolls, origin_id, destination_id FROM __temp__trip');
        $this->addSql('DROP TABLE __temp__trip');
        $this->addSql('CREATE INDEX IDX_7656F53B816C6140 ON trip (destination_id)');
        $this->addSql('CREATE INDEX IDX_7656F53B56A273CC ON trip (origin_id)');

        // Conversion : un dashboard « Principal » reprend la disposition de l'ancien écran, assigné à toutes
        // les liseuses ; chaque trajet actif devient une tuile au même emplacement, avec ses horaires.
        $this->addSql("INSERT INTO dashboard (name) SELECT 'Principal' WHERE EXISTS (SELECT 1 FROM device) OR EXISTS (SELECT 1 FROM __grid_weather) OR EXISTS (SELECT 1 FROM __grid_commute)");
        $this->addSql("UPDATE device SET dashboard_id = (SELECT id FROM dashboard WHERE name = 'Principal')");
        $this->addSql('INSERT INTO tile ('.self::TILE_COLUMNS.") SELECT id, 'clock', 1, 1, 12, 2, ".self::EVERY_DAY.", NULL, NULL, NULL, NULL FROM dashboard WHERE name = 'Principal'");
        $this->addWeatherTile('current_weather', 3, 3);
        $this->addWeatherTile('rain', 6, 2);
        $this->addWeatherTile('hourly_forecast', 8, 4);
        $this->addSql('INSERT INTO tile ('.self::TILE_COLUMNS.") SELECT d.id, 'commute', 1, 12, 12, 5, c.weekdays, c.start_time, c.end_time, NULL, c.trip_id FROM dashboard d, __grid_commute c WHERE d.name = 'Principal'");
        $this->addSql('DROP TABLE __grid_weather');
        $this->addSql('DROP TABLE __grid_commute');
    }

    private function addWeatherTile(string $widget, int $row, int $height): void
    {
        $this->addSql('INSERT INTO tile ('.self::TILE_COLUMNS.") SELECT d.id, '$widget', 1, $row, 12, $height, ".self::EVERY_DAY.", NULL, NULL, w.place_id, NULL FROM dashboard d, __grid_weather w WHERE d.name = 'Principal'");
    }

    public function down(Schema $schema): void
    {
        // Les horaires et emplacements des tuiles ne peuvent pas être reconvertis en trajets
        $this->throwIrreversibleMigrationException();
    }
}
