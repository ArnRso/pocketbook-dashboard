<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261012145548 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mise en page automatique : largeur et hauteur des tuiles facultatives, toutes remises en automatique';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__tile AS SELECT id, grid_column, grid_row, width, height, schedule_weekdays, schedule_start_time, schedule_end_time, dashboard_id, widget, trip_id, place_id, days, position, auto_width, auto_size FROM tile');
        $this->addSql('DROP TABLE tile');
        $this->addSql('CREATE TABLE tile (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, grid_column INTEGER NOT NULL, grid_row INTEGER NOT NULL, width INTEGER NOT NULL, height INTEGER NOT NULL, schedule_weekdays CLOB NOT NULL, schedule_start_time TIME DEFAULT NULL, schedule_end_time TIME DEFAULT NULL, dashboard_id INTEGER NOT NULL, widget VARCHAR(32) NOT NULL, trip_id INTEGER DEFAULT NULL, place_id INTEGER DEFAULT NULL, days INTEGER DEFAULT NULL, position INTEGER DEFAULT 0 NOT NULL, auto_width VARCHAR(8) DEFAULT NULL, auto_size INTEGER DEFAULT NULL, CONSTRAINT FK_768FA904B9D04D2B FOREIGN KEY (dashboard_id) REFERENCES dashboard (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_768FA904A5BC2E0E FOREIGN KEY (trip_id) REFERENCES trip (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_768FA904DA6A219 FOREIGN KEY (place_id) REFERENCES place (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        // Toutes les tuiles prennent la taille automatique : la plus grande possible
        $this->addSql('INSERT INTO tile (id, grid_column, grid_row, width, height, schedule_weekdays, schedule_start_time, schedule_end_time, dashboard_id, widget, trip_id, place_id, days, position, auto_width, auto_size) SELECT id, grid_column, grid_row, width, height, schedule_weekdays, schedule_start_time, schedule_end_time, dashboard_id, widget, trip_id, place_id, days, position, NULL, NULL FROM __temp__tile');
        $this->addSql('DROP TABLE __temp__tile');
        $this->addSql('CREATE INDEX IDX_768FA904DA6A219 ON tile (place_id)');
        $this->addSql('CREATE INDEX IDX_768FA904A5BC2E0E ON tile (trip_id)');
        $this->addSql('CREATE INDEX IDX_768FA904B9D04D2B ON tile (dashboard_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__tile AS SELECT id, grid_column, grid_row, width, height, position, auto_width, auto_size, schedule_weekdays, schedule_start_time, schedule_end_time, dashboard_id, widget, trip_id, days, place_id FROM tile');
        $this->addSql('DROP TABLE tile');
        $this->addSql('CREATE TABLE tile (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, grid_column INTEGER NOT NULL, grid_row INTEGER NOT NULL, width INTEGER NOT NULL, height INTEGER NOT NULL, position INTEGER DEFAULT 0 NOT NULL, auto_width VARCHAR(8) DEFAULT \'full\' NOT NULL, auto_size INTEGER DEFAULT 2 NOT NULL, schedule_weekdays CLOB NOT NULL, schedule_start_time TIME DEFAULT NULL, schedule_end_time TIME DEFAULT NULL, dashboard_id INTEGER NOT NULL, widget VARCHAR(32) NOT NULL, trip_id INTEGER DEFAULT NULL, days INTEGER DEFAULT NULL, place_id INTEGER DEFAULT NULL, CONSTRAINT FK_768FA904B9D04D2B FOREIGN KEY (dashboard_id) REFERENCES dashboard (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_768FA904A5BC2E0E FOREIGN KEY (trip_id) REFERENCES trip (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_768FA904DA6A219 FOREIGN KEY (place_id) REFERENCES place (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO tile (id, grid_column, grid_row, width, height, position, auto_width, auto_size, schedule_weekdays, schedule_start_time, schedule_end_time, dashboard_id, widget, trip_id, days, place_id) SELECT id, grid_column, grid_row, width, height, position, COALESCE(auto_width, \'full\'), COALESCE(auto_size, 2), schedule_weekdays, schedule_start_time, schedule_end_time, dashboard_id, widget, trip_id, days, place_id FROM __temp__tile');
        $this->addSql('DROP TABLE __temp__tile');
        $this->addSql('CREATE INDEX IDX_768FA904B9D04D2B ON tile (dashboard_id)');
        $this->addSql('CREATE INDEX IDX_768FA904A5BC2E0E ON tile (trip_id)');
        $this->addSql('CREATE INDEX IDX_768FA904DA6A219 ON tile (place_id)');
    }
}
