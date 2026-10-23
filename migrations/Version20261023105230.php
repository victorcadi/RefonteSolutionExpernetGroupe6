<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261023105230 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Émargement : mode de signature (tracée ou nom saisi)';
    }

    public function up(Schema $schema): void
    {
        // Les émargements existants ont été tracés : valeur par défaut « manuscrite ».
        $this->addSql('ALTER TABLE emargement ADD mode VARCHAR(20) DEFAULT \'manuscrite\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE emargement DROP mode');
    }
}
