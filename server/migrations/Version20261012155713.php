<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261012155713 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Arrêts TBM et tuile des prochains passages';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE tile_transit_stop (transit_tile_id INTEGER NOT NULL, transit_stop_id INTEGER NOT NULL, PRIMARY KEY (transit_tile_id, transit_stop_id), CONSTRAINT FK_BEB351BCD7693403 FOREIGN KEY (transit_tile_id) REFERENCES tile (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_BEB351BC8DE1C6B5 FOREIGN KEY (transit_stop_id) REFERENCES transit_stop (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_BEB351BCD7693403 ON tile_transit_stop (transit_tile_id)');
        $this->addSql('CREATE INDEX IDX_BEB351BC8DE1C6B5 ON tile_transit_stop (transit_stop_id)');
        $this->addSql('CREATE TABLE transit_stop (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, stop_area VARCHAR(64) NOT NULL, stop_name VARCHAR(100) NOT NULL, line VARCHAR(64) NOT NULL, line_code VARCHAR(16) NOT NULL, direction VARCHAR(8) NOT NULL, destination VARCHAR(100) NOT NULL)');
        $this->addSql('ALTER TABLE tile ADD COLUMN departures INTEGER DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE tile_transit_stop');
        $this->addSql('DROP TABLE transit_stop');
        $this->addSql('CREATE TEMPORARY TABLE __temp__tile AS SELECT id, grid_column, grid_row, width, height, position, auto_width, auto_size, schedule_weekdays, schedule_start_time, schedule_end_time, dashboard_id, widget, trip_id, days, place_id FROM tile');
        $this->addSql('DROP TABLE tile');
        $this->addSql('CREATE TABLE tile (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, grid_column INTEGER NOT NULL, grid_row INTEGER NOT NULL, width INTEGER NOT NULL, height INTEGER NOT NULL, position INTEGER DEFAULT 0 NOT NULL, auto_width VARCHAR(8) DEFAULT NULL, auto_size INTEGER DEFAULT NULL, schedule_weekdays CLOB NOT NULL, schedule_start_time TIME DEFAULT NULL, schedule_end_time TIME DEFAULT NULL, dashboard_id INTEGER NOT NULL, widget VARCHAR(32) NOT NULL, trip_id INTEGER DEFAULT NULL, days INTEGER DEFAULT NULL, place_id INTEGER DEFAULT NULL, CONSTRAINT FK_768FA904B9D04D2B FOREIGN KEY (dashboard_id) REFERENCES dashboard (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_768FA904A5BC2E0E FOREIGN KEY (trip_id) REFERENCES trip (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_768FA904DA6A219 FOREIGN KEY (place_id) REFERENCES place (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO tile (id, grid_column, grid_row, width, height, position, auto_width, auto_size, schedule_weekdays, schedule_start_time, schedule_end_time, dashboard_id, widget, trip_id, days, place_id) SELECT id, grid_column, grid_row, width, height, position, auto_width, auto_size, schedule_weekdays, schedule_start_time, schedule_end_time, dashboard_id, widget, trip_id, days, place_id FROM __temp__tile');
        $this->addSql('DROP TABLE __temp__tile');
        $this->addSql('CREATE INDEX IDX_768FA904B9D04D2B ON tile (dashboard_id)');
        $this->addSql('CREATE INDEX IDX_768FA904A5BC2E0E ON tile (trip_id)');
        $this->addSql('CREATE INDEX IDX_768FA904DA6A219 ON tile (place_id)');
    }
}
