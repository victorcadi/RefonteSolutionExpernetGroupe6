<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261023112802 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Examens : compétences évaluées (grille) et clôture par le jury';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE examen_competence (examen_id INT NOT NULL, competence_id INT NOT NULL, INDEX IDX_6C94F8A05C8659A (examen_id), INDEX IDX_6C94F8A015761DAB (competence_id), PRIMARY KEY (examen_id, competence_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE examen_competence ADD CONSTRAINT FK_6C94F8A05C8659A FOREIGN KEY (examen_id) REFERENCES examen (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE examen_competence ADD CONSTRAINT FK_6C94F8A015761DAB FOREIGN KEY (competence_id) REFERENCES competence (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE examen ADD cloture_le DATETIME DEFAULT NULL, ADD cloture_par_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE examen ADD CONSTRAINT FK_514C8FECD596D79F FOREIGN KEY (cloture_par_id) REFERENCES utilisateur (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_514C8FECD596D79F ON examen (cloture_par_id)');

        // Examens existants : la grille reprend tout le référentiel de la formation de la promotion.
        $this->addSql('INSERT INTO examen_competence (examen_id, competence_id)
            SELECT x.id, c.id FROM examen x
            JOIN promotion p ON p.id = x.promotion_id
            JOIN session s ON s.id = p.session_id
            JOIN competence c ON c.formation_id = s.formation_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE examen_competence DROP FOREIGN KEY FK_6C94F8A05C8659A');
        $this->addSql('ALTER TABLE examen_competence DROP FOREIGN KEY FK_6C94F8A015761DAB');
        $this->addSql('DROP TABLE examen_competence');
        $this->addSql('ALTER TABLE examen DROP FOREIGN KEY FK_514C8FECD596D79F');
        $this->addSql('DROP INDEX IDX_514C8FECD596D79F ON examen');
        $this->addSql('ALTER TABLE examen DROP cloture_le, DROP cloture_par_id');
    }
}
