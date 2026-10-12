<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261012144026 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mise en page automatique : mode du dashboard, ordre, largeur et hauteur des tuiles';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE dashboard ADD COLUMN layout VARCHAR(8) DEFAULT \'auto\' NOT NULL');
        $this->addSql('ALTER TABLE tile ADD COLUMN position INTEGER DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE tile ADD COLUMN auto_width VARCHAR(8) DEFAULT \'full\' NOT NULL');
        $this->addSql('ALTER TABLE tile ADD COLUMN auto_size INTEGER DEFAULT 2 NOT NULL');

        // Les dashboards existants gardent leur grille ; leurs tuiles sont prêtes pour la mise en page
        // automatique, dans l'ordre de lecture de la grille et à des tailles proches
        $this->addSql('UPDATE dashboard SET layout = \'grid\'');
        $this->addSql(<<<'SQL'
            UPDATE tile SET
                position = (
                    SELECT COUNT(*) FROM tile other
                    WHERE other.dashboard_id = tile.dashboard_id
                      AND (other.grid_row, other.grid_column, other.id) <= (tile.grid_row, tile.grid_column, tile.id)
                ),
                auto_width = CASE WHEN width <= 6 THEN 'half' ELSE 'full' END,
                auto_size = CASE WHEN height <= 2 THEN 1 WHEN height = 3 THEN 2 ELSE 3 END
            SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__dashboard AS SELECT id, name FROM dashboard');
        $this->addSql('DROP TABLE dashboard');
        $this->addSql('CREATE TABLE dashboard (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(100) NOT NULL)');
        $this->addSql('INSERT INTO dashboard (id, name) SELECT id, name FROM __temp__dashboard');
        $this->addSql('DROP TABLE __temp__dashboard');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5C94FFF85E237E06 ON dashboard (name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__tile AS SELECT id, grid_column, grid_row, width, height, schedule_weekdays, schedule_start_time, schedule_end_time, dashboard_id, widget, trip_id, days, place_id FROM tile');
        $this->addSql('DROP TABLE tile');
        $this->addSql('CREATE TABLE tile (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, grid_column INTEGER NOT NULL, grid_row INTEGER NOT NULL, width INTEGER NOT NULL, height INTEGER NOT NULL, schedule_weekdays CLOB NOT NULL, schedule_start_time TIME DEFAULT NULL, schedule_end_time TIME DEFAULT NULL, dashboard_id INTEGER NOT NULL, widget VARCHAR(32) NOT NULL, trip_id INTEGER DEFAULT NULL, days INTEGER DEFAULT NULL, place_id INTEGER DEFAULT NULL, CONSTRAINT FK_768FA904B9D04D2B FOREIGN KEY (dashboard_id) REFERENCES dashboard (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_768FA904A5BC2E0E FOREIGN KEY (trip_id) REFERENCES trip (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_768FA904DA6A219 FOREIGN KEY (place_id) REFERENCES place (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO tile (id, grid_column, grid_row, width, height, schedule_weekdays, schedule_start_time, schedule_end_time, dashboard_id, widget, trip_id, days, place_id) SELECT id, grid_column, grid_row, width, height, schedule_weekdays, schedule_start_time, schedule_end_time, dashboard_id, widget, trip_id, days, place_id FROM __temp__tile');
        $this->addSql('DROP TABLE __temp__tile');
        $this->addSql('CREATE INDEX IDX_768FA904B9D04D2B ON tile (dashboard_id)');
        $this->addSql('CREATE INDEX IDX_768FA904A5BC2E0E ON tile (trip_id)');
        $this->addSql('CREATE INDEX IDX_768FA904DA6A219 ON tile (place_id)');
    }
}
