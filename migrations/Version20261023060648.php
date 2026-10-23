<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261023060648 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schéma initial Doctrine : catalogue, formulaires publics et ENT (remplace les scripts Flyway sql/V1 à V3).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE candidat (id INT AUTO_INCREMENT NOT NULL, prenom VARCHAR(100) NOT NULL, nom VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, telephone VARCHAR(20) NOT NULL, adresse VARCHAR(255) DEFAULT NULL, code_postal VARCHAR(10) DEFAULT NULL, ville VARCHAR(120) DEFAULT NULL, diplome VARCHAR(255) NOT NULL, souhaite_contact_referent_handicap TINYINT DEFAULT 0 NOT NULL, UNIQUE INDEX UNIQ_6AB5B471E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE candidature (id INT AUTO_INCREMENT NOT NULL, message LONGTEXT DEFAULT NULL, consentement TINYINT NOT NULL, statut VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, candidat_id INT NOT NULL, formation_id INT NOT NULL, session_id INT DEFAULT NULL, INDEX IDX_E33BD3B88D0EB82 (candidat_id), INDEX IDX_E33BD3B85200282E (formation_id), INDEX IDX_E33BD3B8613FECDF (session_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE chapitre_programme (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, position SMALLINT DEFAULT 0 NOT NULL, formation_id INT NOT NULL, INDEX IDX_4C2837AB5200282E (formation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE competence (id INT AUTO_INCREMENT NOT NULL, bloc VARCHAR(255) NOT NULL, libelle VARCHAR(255) NOT NULL, formation_id INT NOT NULL, INDEX IDX_94D4687F5200282E (formation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE creneau (id INT AUTO_INCREMENT NOT NULL, intitule VARCHAR(200) NOT NULL, salle VARCHAR(60) DEFAULT NULL, debut DATETIME NOT NULL, fin DATETIME NOT NULL, promotion_id INT NOT NULL, formateur_id INT DEFAULT NULL, INDEX idx_creneau_debut (debut), INDEX IDX_F9668B5F139DF194 (promotion_id), INDEX IDX_F9668B5F155D8F51 (formateur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE demande_contact (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, prenom VARCHAR(100) NOT NULL, nom VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, telephone VARCHAR(20) DEFAULT NULL, entreprise VARCHAR(150) DEFAULT NULL, message LONGTEXT NOT NULL, consentement TINYINT NOT NULL, created_at DATETIME NOT NULL, statut VARCHAR(20) NOT NULL, formation_id INT DEFAULT NULL, INDEX idx_demande_statut (statut, created_at), INDEX IDX_7C955D975200282E (formation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE element_fiche (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, libelle VARCHAR(500) NOT NULL, position SMALLINT DEFAULT 0 NOT NULL, formation_id INT NOT NULL, INDEX idx_element_formation_type (formation_id, type), INDEX IDX_8831B8E15200282E (formation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE emargement (id INT AUTO_INCREMENT NOT NULL, signe_le DATETIME NOT NULL, signature LONGTEXT NOT NULL, empreinte VARCHAR(64) NOT NULL, adresse_ip VARCHAR(45) DEFAULT NULL, creneau_id INT NOT NULL, etudiant_id INT NOT NULL, UNIQUE INDEX uniq_emargement_creneau_etudiant (creneau_id, etudiant_id), INDEX IDX_71BBB2507D0729A9 (creneau_id), INDEX IDX_71BBB250DDEAB1A3 (etudiant_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE evaluation_competence (id INT AUTO_INCREMENT NOT NULL, niveau VARCHAR(20) NOT NULL, commentaire LONGTEXT DEFAULT NULL, saisie_le DATETIME NOT NULL, examen_id INT NOT NULL, etudiant_id INT NOT NULL, competence_id INT NOT NULL, evaluateur_id INT DEFAULT NULL, UNIQUE INDEX uniq_evaluation (examen_id, etudiant_id, competence_id), INDEX IDX_7ED32F8D5C8659A (examen_id), INDEX IDX_7ED32F8DDDEAB1A3 (etudiant_id), INDEX IDX_7ED32F8D15761DAB (competence_id), INDEX IDX_7ED32F8D231F139 (evaluateur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE examen (id INT AUTO_INCREMENT NOT NULL, intitule VARCHAR(200) NOT NULL, type VARCHAR(30) NOT NULL, date_examen DATETIME NOT NULL, duree_minutes INT NOT NULL, salle VARCHAR(60) DEFAULT NULL, promotion_id INT NOT NULL, site_id INT DEFAULT NULL, INDEX IDX_514C8FEC139DF194 (promotion_id), INDEX IDX_514C8FECF6BD1646 (site_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE examen_jury (examen_id INT NOT NULL, utilisateur_id INT NOT NULL, INDEX IDX_CC2E54355C8659A (examen_id), INDEX IDX_CC2E5435FB88E14F (utilisateur_id), PRIMARY KEY (examen_id, utilisateur_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE filiere (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(120) NOT NULL, slug VARCHAR(140) NOT NULL, position INT DEFAULT 0 NOT NULL, UNIQUE INDEX UNIQ_2ED05D9E989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE formation (id INT AUTO_INCREMENT NOT NULL, intitule VARCHAR(180) NOT NULL, slug VARCHAR(200) NOT NULL, type VARCHAR(20) NOT NULL, resume VARCHAR(300) NOT NULL, description LONGTEXT DEFAULT NULL, public VARCHAR(255) NOT NULL, duree VARCHAR(120) NOT NULL, duree_heures INT DEFAULT NULL, modalite VARCHAR(20) NOT NULL, prix NUMERIC(10, 2) DEFAULT NULL, eligible_cpf TINYINT DEFAULT 0 NOT NULL, niveau_rncp SMALLINT DEFAULT NULL, code_rncp VARCHAR(20) DEFAULT NULL, certification VARCHAR(255) DEFAULT NULL, rythme VARCHAR(255) DEFAULT NULL, selection LONGTEXT DEFAULT NULL, programme_pdf VARCHAR(255) DEFAULT NULL, image VARCHAR(255) DEFAULT NULL, publiee TINYINT DEFAULT 0 NOT NULL, mise_en_avant TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, thematique_id INT NOT NULL, UNIQUE INDEX UNIQ_404021BF989D9B62 (slug), INDEX idx_formation_catalogue (type, publiee), INDEX IDX_404021BF476556AF (thematique_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE module_programme (id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(255) NOT NULL, position SMALLINT DEFAULT 0 NOT NULL, chapitre_id INT NOT NULL, INDEX IDX_67BDA6371FBEEF7B (chapitre_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE note (id INT AUTO_INCREMENT NOT NULL, intitule VARCHAR(200) NOT NULL, valeur NUMERIC(4, 2) NOT NULL, date DATE NOT NULL, etudiant_id INT NOT NULL, formateur_id INT DEFAULT NULL, INDEX IDX_CFBDFA14DDEAB1A3 (etudiant_id), INDEX IDX_CFBDFA14155D8F51 (formateur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE promotion (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(150) NOT NULL, session_id INT NOT NULL, formateur_referent_id INT DEFAULT NULL, INDEX IDX_C11D7DD1613FECDF (session_id), INDEX IDX_C11D7DD1B5D40EDC (formateur_referent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE session (id INT AUTO_INCREMENT NOT NULL, date_debut DATE NOT NULL, date_fin DATE NOT NULL, places INT DEFAULT NULL, statut VARCHAR(20) NOT NULL, formation_id INT NOT NULL, site_id INT DEFAULT NULL, INDEX idx_session_debut (date_debut), INDEX IDX_D044D5D45200282E (formation_id), INDEX IDX_D044D5D4F6BD1646 (site_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE site (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(120) NOT NULL, adresse VARCHAR(255) NOT NULL, complement_adresse VARCHAR(255) DEFAULT NULL, code_postal VARCHAR(10) NOT NULL, ville VARCHAR(120) NOT NULL, departement VARCHAR(20) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE thematique (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(120) NOT NULL, slug VARCHAR(140) NOT NULL, filiere_id INT NOT NULL, UNIQUE INDEX UNIQ_3A8ED5A8989D9B62 (slug), INDEX IDX_3A8ED5A8180AA129 (filiere_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE utilisateur (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, prenom VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, role VARCHAR(20) NOT NULL, peut_etre_jure TINYINT DEFAULT 0 NOT NULL, mot_de_passe VARCHAR(255) NOT NULL, totp_secret VARCHAR(255) DEFAULT NULL, totp_active TINYINT DEFAULT 0 NOT NULL, actif TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, promotion_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_1D1C63B3E7927C74 (email), INDEX IDX_1D1C63B3139DF194 (promotion_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE candidature ADD CONSTRAINT FK_E33BD3B88D0EB82 FOREIGN KEY (candidat_id) REFERENCES candidat (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE candidature ADD CONSTRAINT FK_E33BD3B85200282E FOREIGN KEY (formation_id) REFERENCES formation (id)');
        $this->addSql('ALTER TABLE candidature ADD CONSTRAINT FK_E33BD3B8613FECDF FOREIGN KEY (session_id) REFERENCES session (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE chapitre_programme ADD CONSTRAINT FK_4C2837AB5200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE competence ADD CONSTRAINT FK_94D4687F5200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE creneau ADD CONSTRAINT FK_F9668B5F139DF194 FOREIGN KEY (promotion_id) REFERENCES promotion (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE creneau ADD CONSTRAINT FK_F9668B5F155D8F51 FOREIGN KEY (formateur_id) REFERENCES utilisateur (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE demande_contact ADD CONSTRAINT FK_7C955D975200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE element_fiche ADD CONSTRAINT FK_8831B8E15200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE emargement ADD CONSTRAINT FK_71BBB2507D0729A9 FOREIGN KEY (creneau_id) REFERENCES creneau (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE emargement ADD CONSTRAINT FK_71BBB250DDEAB1A3 FOREIGN KEY (etudiant_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE evaluation_competence ADD CONSTRAINT FK_7ED32F8D5C8659A FOREIGN KEY (examen_id) REFERENCES examen (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE evaluation_competence ADD CONSTRAINT FK_7ED32F8DDDEAB1A3 FOREIGN KEY (etudiant_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE evaluation_competence ADD CONSTRAINT FK_7ED32F8D15761DAB FOREIGN KEY (competence_id) REFERENCES competence (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE evaluation_competence ADD CONSTRAINT FK_7ED32F8D231F139 FOREIGN KEY (evaluateur_id) REFERENCES utilisateur (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE examen ADD CONSTRAINT FK_514C8FEC139DF194 FOREIGN KEY (promotion_id) REFERENCES promotion (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE examen ADD CONSTRAINT FK_514C8FECF6BD1646 FOREIGN KEY (site_id) REFERENCES site (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE examen_jury ADD CONSTRAINT FK_CC2E54355C8659A FOREIGN KEY (examen_id) REFERENCES examen (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE examen_jury ADD CONSTRAINT FK_CC2E5435FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE formation ADD CONSTRAINT FK_404021BF476556AF FOREIGN KEY (thematique_id) REFERENCES thematique (id)');
        $this->addSql('ALTER TABLE module_programme ADD CONSTRAINT FK_67BDA6371FBEEF7B FOREIGN KEY (chapitre_id) REFERENCES chapitre_programme (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE note ADD CONSTRAINT FK_CFBDFA14DDEAB1A3 FOREIGN KEY (etudiant_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE note ADD CONSTRAINT FK_CFBDFA14155D8F51 FOREIGN KEY (formateur_id) REFERENCES utilisateur (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE promotion ADD CONSTRAINT FK_C11D7DD1613FECDF FOREIGN KEY (session_id) REFERENCES session (id)');
        $this->addSql('ALTER TABLE promotion ADD CONSTRAINT FK_C11D7DD1B5D40EDC FOREIGN KEY (formateur_referent_id) REFERENCES utilisateur (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE session ADD CONSTRAINT FK_D044D5D45200282E FOREIGN KEY (formation_id) REFERENCES formation (id)');
        $this->addSql('ALTER TABLE session ADD CONSTRAINT FK_D044D5D4F6BD1646 FOREIGN KEY (site_id) REFERENCES site (id)');
        $this->addSql('ALTER TABLE thematique ADD CONSTRAINT FK_3A8ED5A8180AA129 FOREIGN KEY (filiere_id) REFERENCES filiere (id)');
        $this->addSql('ALTER TABLE utilisateur ADD CONSTRAINT FK_1D1C63B3139DF194 FOREIGN KEY (promotion_id) REFERENCES promotion (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE candidature DROP FOREIGN KEY FK_E33BD3B88D0EB82');
        $this->addSql('ALTER TABLE candidature DROP FOREIGN KEY FK_E33BD3B85200282E');
        $this->addSql('ALTER TABLE candidature DROP FOREIGN KEY FK_E33BD3B8613FECDF');
        $this->addSql('ALTER TABLE chapitre_programme DROP FOREIGN KEY FK_4C2837AB5200282E');
        $this->addSql('ALTER TABLE competence DROP FOREIGN KEY FK_94D4687F5200282E');
        $this->addSql('ALTER TABLE creneau DROP FOREIGN KEY FK_F9668B5F139DF194');
        $this->addSql('ALTER TABLE creneau DROP FOREIGN KEY FK_F9668B5F155D8F51');
        $this->addSql('ALTER TABLE demande_contact DROP FOREIGN KEY FK_7C955D975200282E');
        $this->addSql('ALTER TABLE element_fiche DROP FOREIGN KEY FK_8831B8E15200282E');
        $this->addSql('ALTER TABLE emargement DROP FOREIGN KEY FK_71BBB2507D0729A9');
        $this->addSql('ALTER TABLE emargement DROP FOREIGN KEY FK_71BBB250DDEAB1A3');
        $this->addSql('ALTER TABLE evaluation_competence DROP FOREIGN KEY FK_7ED32F8D5C8659A');
        $this->addSql('ALTER TABLE evaluation_competence DROP FOREIGN KEY FK_7ED32F8DDDEAB1A3');
        $this->addSql('ALTER TABLE evaluation_competence DROP FOREIGN KEY FK_7ED32F8D15761DAB');
        $this->addSql('ALTER TABLE evaluation_competence DROP FOREIGN KEY FK_7ED32F8D231F139');
        $this->addSql('ALTER TABLE examen DROP FOREIGN KEY FK_514C8FEC139DF194');
        $this->addSql('ALTER TABLE examen DROP FOREIGN KEY FK_514C8FECF6BD1646');
        $this->addSql('ALTER TABLE examen_jury DROP FOREIGN KEY FK_CC2E54355C8659A');
        $this->addSql('ALTER TABLE examen_jury DROP FOREIGN KEY FK_CC2E5435FB88E14F');
        $this->addSql('ALTER TABLE formation DROP FOREIGN KEY FK_404021BF476556AF');
        $this->addSql('ALTER TABLE module_programme DROP FOREIGN KEY FK_67BDA6371FBEEF7B');
        $this->addSql('ALTER TABLE note DROP FOREIGN KEY FK_CFBDFA14DDEAB1A3');
        $this->addSql('ALTER TABLE note DROP FOREIGN KEY FK_CFBDFA14155D8F51');
        $this->addSql('ALTER TABLE promotion DROP FOREIGN KEY FK_C11D7DD1613FECDF');
        $this->addSql('ALTER TABLE promotion DROP FOREIGN KEY FK_C11D7DD1B5D40EDC');
        $this->addSql('ALTER TABLE session DROP FOREIGN KEY FK_D044D5D45200282E');
        $this->addSql('ALTER TABLE session DROP FOREIGN KEY FK_D044D5D4F6BD1646');
        $this->addSql('ALTER TABLE thematique DROP FOREIGN KEY FK_3A8ED5A8180AA129');
        $this->addSql('ALTER TABLE utilisateur DROP FOREIGN KEY FK_1D1C63B3139DF194');
        $this->addSql('DROP TABLE candidat');
        $this->addSql('DROP TABLE candidature');
        $this->addSql('DROP TABLE chapitre_programme');
        $this->addSql('DROP TABLE competence');
        $this->addSql('DROP TABLE creneau');
        $this->addSql('DROP TABLE demande_contact');
        $this->addSql('DROP TABLE element_fiche');
        $this->addSql('DROP TABLE emargement');
        $this->addSql('DROP TABLE evaluation_competence');
        $this->addSql('DROP TABLE examen');
        $this->addSql('DROP TABLE examen_jury');
        $this->addSql('DROP TABLE filiere');
        $this->addSql('DROP TABLE formation');
        $this->addSql('DROP TABLE module_programme');
        $this->addSql('DROP TABLE note');
        $this->addSql('DROP TABLE promotion');
        $this->addSql('DROP TABLE session');
        $this->addSql('DROP TABLE site');
        $this->addSql('DROP TABLE thematique');
        $this->addSql('DROP TABLE utilisateur');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
