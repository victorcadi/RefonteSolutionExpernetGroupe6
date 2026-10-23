<?php

namespace App\DataFixtures;

use App\Entity\ChapitreProgramme;
use App\Entity\Competence;
use App\Entity\Filiere;
use App\Entity\Formation;
use App\Entity\Session;
use App\Entity\Site;
use App\Entity\Thematique;
use App\Enum\Departement;
use App\Enum\Modalite;
use App\Enum\StatutSession;
use App\Enum\TypeElementFiche;
use App\Enum\TypeFormation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Catalogue de DÉMONSTRATION.
 *
 * Les filières, thématiques et adresses des locaux reprennent le site expernet.re.
 * Les formations, tarifs, durées, programmes et dates de session sont FICTIFS :
 * ils servent à faire fonctionner le site et ne doivent pas être publiés tels quels.
 * Le bandeau « démonstration » (APP_DEMO=1) le rappelle aux visiteurs.
 */
class CatalogueFixtures extends Fixture
{
    public const SITE_LE_PORT = 'site-le-port';
    public const SITE_SAINTE_CLOTILDE = 'site-sainte-clotilde';
    public const SITE_MAMOUDZOU = 'site-mamoudzou';
    public const FORMATION_CDA = 'formation-cda';
    public const SESSION_CDA = 'session-cda';

    private const THEMATIQUES = [
        'Informatique' => [
            'Analyse de données', 'Bureautique', 'CAO', 'Cybersécurité', 'Intelligence artificielle',
            'Langages et développement', 'Linux – Open source', 'Microsoft Technique', 'Réseaux',
            'Remise à niveau numérique',
        ],
        'Marketing digital' => [
            'Communication digitale', 'Gestion de projets', 'Internet – Web', 'PAO',
        ],
    ];

    /** @var array<string, Thematique> */
    private array $thematiques = [];

    /** @var array<string, Site> */
    private array $sites = [];

    public function load(ObjectManager $manager): void
    {
        $this->chargerReferentiels($manager);

        foreach ($this->formationsContinues() as $donnees) {
            $manager->persist($this->creerFormation(TypeFormation::Continue, $donnees));
        }

        foreach ($this->formationsAlternance() as $donnees) {
            $formation = $this->creerFormation(TypeFormation::Alternance, $donnees);
            $manager->persist($formation);

            if ('Concepteur développeur d’applications' === $donnees['intitule']) {
                $this->addReference(self::FORMATION_CDA, $formation);
                // Session en cours : support de la promotion de démonstration dans l'ENT.
                $session = (new Session())
                    ->setSite($this->sites['Sainte-Clotilde'])
                    ->setDateDebut((new \DateTimeImmutable('monday this week'))->modify('-17 weeks'))
                    ->setDateFin(new \DateTimeImmutable('+20 months'))
                    ->setStatut(StatutSession::Complete);
                $formation->ajouterSession($session);
                $this->addReference(self::SESSION_CDA, $session);
            }
        }

        $manager->flush();
    }

    private function chargerReferentiels(ObjectManager $manager): void
    {
        $position = 0;
        foreach (self::THEMATIQUES as $nomFiliere => $noms) {
            $filiere = (new Filiere())->setNom($nomFiliere)->setPosition($position++);
            $manager->persist($filiere);

            foreach ($noms as $nom) {
                $thematique = (new Thematique())->setNom($nom)->setFiliere($filiere);
                $manager->persist($thematique);
                $this->thematiques[$nom] = $thematique;
            }
        }

        // Adresses publiées sur expernet.re.
        $sites = [
            ['Le Port', '3 avenue Théodore Drouhet', null, '97420', 'Le Port', Departement::Reunion, self::SITE_LE_PORT],
            ['Sainte-Clotilde', '13 rue Maurice Tomi', 'Au sein des locaux de One to One', '97490', 'Sainte-Clotilde', Departement::Reunion, self::SITE_SAINTE_CLOTILDE],
            ['Mamoudzou', 'ZI Kawéni, route de la mangrove', 'Bâtiment SOMIBAT', '97600', 'Mamoudzou', Departement::Mayotte, self::SITE_MAMOUDZOU],
        ];
        foreach ($sites as [$nom, $adresse, $complement, $cp, $ville, $departement, $reference]) {
            $site = (new Site())
                ->setNom($nom)
                ->setAdresse($adresse)
                ->setComplementAdresse($complement)
                ->setCodePostal($cp)
                ->setVille($ville)
                ->setDepartement($departement);
            $manager->persist($site);
            $this->sites[$nom] = $site;
            $this->addReference($reference, $site);
        }
    }

    /**
     * @param array<string, mixed> $d
     */
    private function creerFormation(TypeFormation $type, array $d): Formation
    {
        $formation = (new Formation())
            ->setType($type)
            ->setIntitule($d['intitule'])
            ->setThematique($this->thematiques[$d['thematique']])
            ->setResume($d['resume'])
            ->setDescription($d['description'] ?? null)
            ->setPublic($d['public'])
            ->setDuree($d['duree'])
            ->setDureeHeures($d['heures'] ?? null)
            ->setModalite($d['modalite'] ?? Modalite::Presentiel)
            ->setPrix($d['prix'] ?? null)
            ->setEligibleCpf($d['cpf'] ?? false)
            ->setNiveauRncp($d['niveau'] ?? null)
            ->setCodeRncp($d['codeRncp'] ?? null)
            ->setCertification($d['certification'] ?? null)
            ->setRythme($d['rythme'] ?? null)
            ->setSelection($d['selection'] ?? null)
            ->setPubliee($d['publiee'] ?? true)
            ->setMiseEnAvant($d['avant'] ?? false);

        foreach ($d['elements'] as $typeElement => $libelles) {
            foreach ($libelles as $libelle) {
                $formation->ajouterElement(TypeElementFiche::from($typeElement), $libelle);
            }
        }

        foreach ($d['programme'] as $titre => $modules) {
            $chapitre = new ChapitreProgramme($titre);
            foreach ($modules as $module) {
                $chapitre->ajouterModule($module);
            }
            $formation->ajouterChapitre($chapitre);
        }

        foreach ($d['competences'] ?? [] as $bloc => $libelles) {
            foreach ($libelles as $libelle) {
                $formation->ajouterCompetence((new Competence())->setBloc($bloc)->setLibelle($libelle));
            }
        }

        // Sessions : décalage en jours depuis aujourd'hui, durée en jours, site.
        foreach ($d['sessions'] as [$dansJours, $dureeJours, $nomSite]) {
            $debut = (new \DateTimeImmutable('today'))->modify("+{$dansJours} days");
            $formation->ajouterSession((new Session())
                ->setDateDebut($debut)
                ->setDateFin($debut->modify('+'.max(0, $dureeJours - 1).' days'))
                ->setSite(null === $nomSite ? null : $this->sites[$nomSite])
                ->setPlaces($type === TypeFormation::Alternance ? 16 : 10));
        }

        return $formation;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function formationsContinues(): array
    {
        $moyensPresentiel = [
            'Salle équipée d’ordinateurs récents, d’un vidéoprojecteur et d’une connexion très haut débit',
            'Formateur professionnel en activité dans le domaine enseigné',
            'Support de cours numérique remis à chaque participant',
        ];
        $methodes = [
            'Alternance d’apports théoriques et d’exercices pratiques',
            'Cas concrets issus du contexte professionnel des participants',
        ];
        $evaluation = [
            'Évaluation des acquis par des exercices pratiques en fin de module',
            'Questionnaire de satisfaction à chaud',
            'Attestation de fin de formation',
        ];
        $acces = [
            'Inscription jusqu’à 10 jours ouvrés avant le début de la session',
            'Locaux accessibles aux personnes à mobilité réduite : contactez notre référente handicap',
        ];

        return [
            [
                'intitule' => 'Copilot pour Microsoft 365',
                'thematique' => 'Intelligence artificielle',
                'resume' => 'Exploitez l’assistant IA de Microsoft dans Word, Excel, Outlook et Teams pour gagner du temps au quotidien.',
                'public' => 'Utilisateurs réguliers de Microsoft 365',
                'duree' => '1 jour (7 heures)', 'heures' => 7, 'prix' => '450.00', 'avant' => true,
                'modalite' => Modalite::Hybride,
                'elements' => [
                    'objectif' => ['Rédiger des requêtes efficaces', 'Automatiser la synthèse de documents et de réunions', 'Connaître les limites et les bonnes pratiques de confidentialité'],
                    'prerequis' => ['Utiliser couramment Word, Excel et Outlook', 'Disposer d’une licence Microsoft 365 Copilot'],
                    'moyen' => $moyensPresentiel, 'methode' => $methodes, 'evaluation' => $evaluation, 'acces' => $acces,
                ],
                'programme' => [
                    'Découvrir Copilot' => ['Principes de l’IA générative', 'Rédiger une bonne requête'],
                    'Copilot dans les applications' => ['Word et Outlook : rédiger et résumer', 'Excel : analyser des données', 'Teams : comptes rendus de réunion'],
                ],
                'sessions' => [[12, 1, 'Sainte-Clotilde'], [40, 1, 'Le Port'], [75, 1, null]],
            ],
            [
                'intitule' => 'Fortinet FortiGate – niveau 1',
                'thematique' => 'Cybersécurité',
                'resume' => 'Installez, configurez et administrez un pare-feu FortiGate pour protéger le réseau de votre entreprise.',
                'public' => 'Administrateurs réseaux et systèmes débutants ou intermédiaires, techniciens support',
                'duree' => '3 jours (21 heures)', 'heures' => 21, 'prix' => '1650.00', 'avant' => true,
                'elements' => [
                    'objectif' => ['Déployer un FortiGate dans une architecture réseau', 'Configurer les règles de filtrage et le NAT', 'Mettre en place un VPN IPsec et SSL'],
                    'prerequis' => ['Connaître les bases des réseaux TCP/IP', 'Savoir configurer un poste de travail en réseau'],
                    'moyen' => $moyensPresentiel, 'methode' => $methodes, 'evaluation' => $evaluation, 'acces' => $acces,
                    'debouche' => ['Administrateur sécurité', 'Technicien réseaux'],
                ],
                'programme' => [
                    'Premiers pas' => ['Architecture FortiOS', 'Interfaces et routage'],
                    'Filtrage' => ['Politiques de pare-feu', 'NAT et objets'],
                    'Accès distants' => ['VPN IPsec', 'VPN SSL'],
                ],
                'sessions' => [[20, 3, 'Le Port'], [62, 3, 'Mamoudzou']],
            ],
            [
                'intitule' => 'Palo Alto Networks – niveau 1',
                'thematique' => 'Cybersécurité',
                'resume' => 'Prenez en main les pare-feux de nouvelle génération Palo Alto Networks et leurs fonctions de sécurité.',
                'public' => 'Administrateurs réseaux et intégrateurs débutant sur les solutions Palo Alto',
                'duree' => '3 jours (21 heures)', 'heures' => 21, 'prix' => '1800.00', 'avant' => true,
                'elements' => [
                    'objectif' => ['Configurer un pare-feu Palo Alto', 'Définir des politiques fondées sur les applications', 'Superviser le trafic et les menaces'],
                    'prerequis' => ['Connaître les bases des réseaux TCP/IP'],
                    'moyen' => $moyensPresentiel, 'methode' => $methodes, 'evaluation' => $evaluation, 'acces' => $acces,
                ],
                'programme' => [
                    'Configuration initiale' => ['Zones et interfaces', 'Administration'],
                    'Politiques de sécurité' => ['App-ID et User-ID', 'Profils de sécurité'],
                ],
                'sessions' => [[33, 3, 'Sainte-Clotilde']],
            ],
            [
                'intitule' => 'Excel – perfectionnement',
                'thematique' => 'Bureautique',
                'resume' => 'Maîtrisez les fonctions avancées, les tableaux croisés dynamiques et les graphiques pour analyser vos données.',
                'public' => 'Utilisateurs d’Excel souhaitant gagner en efficacité',
                'duree' => '2 jours (14 heures)', 'heures' => 14, 'prix' => '590.00', 'cpf' => true,
                'elements' => [
                    'objectif' => ['Utiliser les fonctions de recherche et les fonctions logiques', 'Construire des tableaux croisés dynamiques', 'Réaliser des graphiques adaptés'],
                    'prerequis' => ['Connaître les bases d’Excel : saisie, formules simples, mise en forme'],
                    'moyen' => $moyensPresentiel, 'methode' => $methodes,
                    'evaluation' => ['Passage de la certification TOSA Excel en fin de formation', 'Questionnaire de satisfaction à chaud'],
                    'acces' => $acces,
                ],
                'programme' => [
                    'Formules avancées' => ['RECHERCHEX et INDEX/EQUIV', 'Fonctions SI imbriquées'],
                    'Analyse de données' => ['Tableaux croisés dynamiques', 'Graphiques et segments'],
                ],
                'sessions' => [[8, 2, 'Le Port'], [29, 2, 'Sainte-Clotilde'], [55, 2, 'Mamoudzou']],
            ],
            [
                'intitule' => 'Power BI – analyse et visualisation de données',
                'thematique' => 'Analyse de données',
                'resume' => 'Connectez vos sources, modélisez vos données et publiez des tableaux de bord interactifs avec Power BI.',
                'public' => 'Contrôleurs de gestion, chargés d’études, managers',
                'duree' => '3 jours (21 heures)', 'heures' => 21, 'prix' => '1290.00', 'cpf' => true,
                'modalite' => Modalite::Hybride,
                'elements' => [
                    'objectif' => ['Importer et transformer des données avec Power Query', 'Créer un modèle de données', 'Concevoir des rapports interactifs'],
                    'prerequis' => ['Bonne maîtrise d’Excel'],
                    'moyen' => $moyensPresentiel, 'methode' => $methodes, 'evaluation' => $evaluation, 'acces' => $acces,
                ],
                'programme' => [
                    'Préparer les données' => ['Power Query', 'Nettoyage et fusion'],
                    'Modéliser' => ['Relations', 'Premières mesures DAX'],
                    'Restituer' => ['Visuels', 'Publication et partage'],
                ],
                'sessions' => [[45, 3, 'Sainte-Clotilde']],
            ],
            [
                'intitule' => 'Python – les fondamentaux',
                'thematique' => 'Langages et développement',
                'resume' => 'Apprenez à programmer en Python pour automatiser des tâches et traiter des fichiers de données.',
                'public' => 'Techniciens, développeurs débutants, toute personne souhaitant automatiser des tâches',
                'duree' => '4 jours (28 heures)', 'heures' => 28, 'prix' => '1560.00', 'cpf' => true,
                'elements' => [
                    'objectif' => ['Écrire des scripts Python structurés', 'Manipuler des fichiers CSV et JSON', 'Utiliser les bibliothèques standard'],
                    'prerequis' => ['Être à l’aise avec l’outil informatique', 'Aucune expérience en programmation n’est nécessaire'],
                    'moyen' => $moyensPresentiel, 'methode' => $methodes, 'evaluation' => $evaluation, 'acces' => $acces,
                ],
                'programme' => [
                    'Bases du langage' => ['Variables et types', 'Conditions et boucles', 'Fonctions'],
                    'Données' => ['Listes et dictionnaires', 'Fichiers CSV et JSON'],
                    'Aller plus loin' => ['Modules et paquets', 'Gestion des erreurs'],
                ],
                'sessions' => [[18, 4, 'Le Port'], [90, 4, null]],
            ],
            [
                'intitule' => 'Linux – administration système',
                'thematique' => 'Linux – Open source',
                'resume' => 'Installez et administrez un serveur Linux : utilisateurs, services, sécurité et sauvegardes.',
                'public' => 'Techniciens et administrateurs systèmes',
                'duree' => '5 jours (35 heures)', 'heures' => 35, 'prix' => '1950.00',
                'elements' => [
                    'objectif' => ['Administrer un serveur Debian', 'Gérer les utilisateurs et les droits', 'Sécuriser et sauvegarder le système'],
                    'prerequis' => ['Connaître les bases de l’informatique et des réseaux'],
                    'moyen' => $moyensPresentiel, 'methode' => $methodes, 'evaluation' => $evaluation, 'acces' => $acces,
                ],
                'programme' => [
                    'Système' => ['Installation', 'Ligne de commande', 'Gestion des paquets'],
                    'Administration' => ['Utilisateurs et droits', 'Services systemd', 'Journaux'],
                    'Sécurité' => ['Pare-feu', 'SSH', 'Sauvegardes'],
                ],
                'sessions' => [[50, 5, 'Sainte-Clotilde']],
            ],
            [
                'intitule' => 'Réseaux – les fondamentaux TCP/IP',
                'thematique' => 'Réseaux',
                'resume' => 'Comprenez le fonctionnement d’un réseau d’entreprise : adressage, routage, commutation et services.',
                'public' => 'Techniciens support, futurs administrateurs réseaux',
                'duree' => '3 jours (21 heures)', 'heures' => 21, 'prix' => '1190.00',
                'elements' => [
                    'objectif' => ['Comprendre le modèle TCP/IP', 'Réaliser un plan d’adressage', 'Diagnostiquer une panne réseau'],
                    'prerequis' => ['Être à l’aise avec l’outil informatique'],
                    'moyen' => $moyensPresentiel, 'methode' => $methodes, 'evaluation' => $evaluation, 'acces' => $acces,
                ],
                'programme' => [
                    'Modèles et protocoles' => ['Modèle OSI et TCP/IP', 'Adressage IPv4 et IPv6'],
                    'Équipements' => ['Commutation et VLAN', 'Routage'],
                ],
                'sessions' => [[26, 3, 'Mamoudzou'], [68, 3, 'Le Port']],
            ],
            [
                'intitule' => 'Remise à niveau numérique',
                'thematique' => 'Remise à niveau numérique',
                'resume' => 'Reprenez confiance avec l’ordinateur, Internet et la messagerie pour être autonome au quotidien.',
                'public' => 'Personnes débutantes ou éloignées du numérique',
                'duree' => '5 jours (35 heures)', 'heures' => 35, 'prix' => '875.00', 'cpf' => true,
                'elements' => [
                    'objectif' => ['Utiliser un ordinateur et ranger ses fichiers', 'Naviguer sur Internet en sécurité', 'Échanger par e-mail'],
                    'prerequis' => ['Aucun'],
                    'moyen' => $moyensPresentiel, 'methode' => $methodes,
                    'evaluation' => ['Passage de la certification PIX en fin de formation'], 'acces' => $acces,
                ],
                'programme' => [
                    'L’ordinateur' => ['Clavier et souris', 'Fichiers et dossiers'],
                    'Internet' => ['Navigation', 'Démarches en ligne', 'Sécurité'],
                ],
                'sessions' => [[5, 5, 'Le Port'], [35, 5, 'Mamoudzou']],
            ],
            [
                'intitule' => 'Réseaux sociaux pour les professionnels',
                'thematique' => 'Communication digitale',
                'resume' => 'Définissez votre ligne éditoriale et animez les réseaux sociaux de votre entreprise avec méthode.',
                'public' => 'Chefs d’entreprise, chargés de communication, commerçants',
                'duree' => '2 jours (14 heures)', 'heures' => 14, 'prix' => '690.00', 'avant' => true,
                'elements' => [
                    'objectif' => ['Choisir les réseaux adaptés à sa cible', 'Construire un calendrier éditorial', 'Mesurer ses résultats'],
                    'prerequis' => ['Utiliser un ordinateur et un smartphone'],
                    'moyen' => $moyensPresentiel, 'methode' => $methodes, 'evaluation' => $evaluation, 'acces' => $acces,
                ],
                'programme' => [
                    'Stratégie' => ['Objectifs et cibles', 'Ligne éditoriale'],
                    'Animation' => ['Créer des visuels', 'Planifier ses publications', 'Statistiques'],
                ],
                'sessions' => [[15, 2, 'Sainte-Clotilde'], [58, 2, 'Le Port']],
            ],
            [
                'intitule' => 'Créer son site avec WordPress',
                'thematique' => 'Internet – Web',
                'resume' => 'Concevez, publiez et faites vivre un site vitrine professionnel avec WordPress.',
                'public' => 'Entrepreneurs, associations, chargés de communication',
                'duree' => '3 jours (21 heures)', 'heures' => 21, 'prix' => '990.00', 'cpf' => true,
                'elements' => [
                    'objectif' => ['Installer et paramétrer WordPress', 'Créer des pages et des articles', 'Optimiser le référencement naturel'],
                    'prerequis' => ['Être à l’aise avec la navigation sur Internet'],
                    'moyen' => $moyensPresentiel, 'methode' => $methodes, 'evaluation' => $evaluation, 'acces' => $acces,
                ],
                'programme' => [
                    'Mise en place' => ['Hébergement et installation', 'Thèmes et extensions'],
                    'Contenus' => ['Pages et articles', 'Médias', 'Référencement'],
                ],
                'sessions' => [[22, 3, 'Le Port']],
            ],
            [
                'intitule' => 'Gestion de projet agile',
                'thematique' => 'Gestion de projets',
                'resume' => 'Pilotez vos projets avec Scrum et Kanban pour livrer plus souvent et mieux impliquer vos équipes.',
                'public' => 'Chefs de projet, managers, membres d’équipes projet',
                'duree' => '2 jours (14 heures)', 'heures' => 14, 'prix' => '820.00',
                'modalite' => Modalite::Distanciel,
                'elements' => [
                    'objectif' => ['Comprendre les principes agiles', 'Animer les rituels Scrum', 'Visualiser le flux avec Kanban'],
                    'prerequis' => ['Avoir participé à au moins un projet'],
                    'moyen' => ['Classe virtuelle avec outils collaboratifs en ligne', 'Formateur certifié Scrum Master'],
                    'methode' => $methodes, 'evaluation' => $evaluation, 'acces' => $acces,
                ],
                'programme' => [
                    'Les fondamentaux' => ['Manifeste agile', 'Rôles Scrum'],
                    'En pratique' => ['Rituels', 'Tableau Kanban', 'Indicateurs'],
                ],
                'sessions' => [[30, 2, null]],
            ],
            [
                'intitule' => 'Photoshop et Illustrator – les bases de la PAO',
                'thematique' => 'PAO',
                'resume' => 'Retouchez vos images et créez des supports de communication imprimés et numériques.',
                'public' => 'Chargés de communication, commerçants, graphistes débutants',
                'duree' => '4 jours (28 heures)', 'heures' => 28, 'prix' => '1240.00', 'cpf' => true,
                'elements' => [
                    'objectif' => ['Retoucher et détourer une image', 'Créer un logo vectoriel', 'Préparer un fichier pour l’impression'],
                    'prerequis' => ['Être à l’aise avec l’outil informatique'],
                    'moyen' => $moyensPresentiel, 'methode' => $methodes, 'evaluation' => $evaluation, 'acces' => $acces,
                ],
                'programme' => [
                    'Photoshop' => ['Calques et sélections', 'Retouche', 'Export web'],
                    'Illustrator' => ['Tracés vectoriels', 'Typographie', 'Préparation à l’impression'],
                ],
                'sessions' => [[40, 4, 'Sainte-Clotilde']],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function formationsAlternance(): array
    {
        $moyens = [
            'Plateau technique équipé et accès aux outils professionnels',
            'Formateurs experts en activité',
            'Suivi individuel en entreprise par un référent pédagogique',
        ];
        $methodes = [
            'Pédagogie par projet, en mode agile',
            'Alternance de cours, ateliers pratiques et missions en entreprise',
        ];
        $acces = [
            'Candidature en ligne, puis entretien de motivation',
            'Recherche d’entreprise accompagnée par notre équipe',
            'Formation gratuite pour l’alternant et rémunérée',
        ];

        return [
            [
                'intitule' => 'Concepteur développeur d’applications',
                'thematique' => 'Langages et développement',
                'resume' => 'Concevez et développez des applications sécurisées, de la base de données à l’interface, en alternance.',
                'public' => 'Titulaires d’un Bac+2 en informatique ou personnes justifiant d’une expérience en développement',
                'duree' => '24 mois', 'heures' => 1085, 'niveau' => 6, 'codeRncp' => 'RNCP37873',
                'certification' => 'Titre professionnel Concepteur développeur d’applications',
                'rythme' => '31 semaines en formation et 70 semaines en entreprise sur 2 ans',
                'selection' => 'CV et lettre de motivation, puis entretien personnel de motivation',
                'avant' => true,
                'elements' => [
                    'objectif' => ['Développer une application sécurisée', 'Concevoir et développer une application organisée en couches', 'Préparer le déploiement d’une application sécurisée'],
                    'prerequis' => ['Bac+2 en informatique ou expérience équivalente', 'Maîtrise des bases d’un langage de programmation'],
                    'moyen' => $moyens, 'methode' => $methodes,
                    'evaluation' => ['Évaluations en cours de formation', 'Dossier professionnel', 'Mise en situation professionnelle devant un jury'],
                    'acces' => $acces,
                    'debouche' => ['Développeur d’applications', 'Développeur full stack', 'Concepteur d’applications'],
                ],
                'programme' => [
                    'Développer une application sécurisée' => ['Installer et configurer son environnement', 'Développer des interfaces utilisateur', 'Développer des composants métier'],
                    'Concevoir une application en couches' => ['Analyser les besoins et maquetter', 'Définir l’architecture logicielle', 'Concevoir et mettre en place une base de données'],
                    'Préparer le déploiement' => ['Préparer et exécuter les plans de tests', 'Préparer le déploiement', 'Contribuer à la mise en production DevOps'],
                ],
                'competences' => [
                    'Développer une application sécurisée' => ['Installer et configurer son environnement de travail', 'Développer des interfaces utilisateur', 'Développer des composants métier', 'Contribuer à la gestion d’un projet informatique'],
                    'Concevoir et développer une application en couches' => ['Analyser les besoins et maquetter une application', 'Définir l’architecture logicielle', 'Concevoir et mettre en place une base de données relationnelle', 'Développer des composants d’accès aux données'],
                    'Préparer le déploiement d’une application sécurisée' => ['Préparer et exécuter les plans de tests', 'Préparer et documenter le déploiement', 'Contribuer à la mise en production dans une démarche DevOps'],
                ],
                'sessions' => [[300, 730, 'Sainte-Clotilde']],
            ],
            [
                'intitule' => 'Développeur web et web mobile',
                'thematique' => 'Langages et développement',
                'resume' => 'Réalisez des sites et applications web responsives, côté interface comme côté serveur.',
                'public' => 'Titulaires du Bac ou personnes en reconversion ayant découvert la programmation',
                'duree' => '12 mois', 'heures' => 700, 'niveau' => 5,
                'certification' => 'Titre professionnel Développeur web et web mobile',
                'rythme' => '2 jours en formation et 3 jours en entreprise par semaine',
                'selection' => 'CV, tests de logique, puis entretien de motivation',
                'avant' => true,
                'elements' => [
                    'objectif' => ['Développer la partie front-end d’une application web', 'Développer la partie back-end d’une application web'],
                    'prerequis' => ['Niveau Bac', 'Première approche de HTML ou d’un langage de programmation'],
                    'moyen' => $moyens, 'methode' => $methodes,
                    'evaluation' => ['Évaluations en cours de formation', 'Mise en situation professionnelle devant un jury'],
                    'acces' => $acces,
                    'debouche' => ['Développeur web', 'Intégrateur web', 'Développeur front-end'],
                ],
                'programme' => [
                    'Front-end' => ['HTML, CSS et accessibilité', 'JavaScript', 'Maquettage'],
                    'Back-end' => ['Bases de données', 'PHP et framework', 'Sécurité des applications'],
                ],
                'sessions' => [[320, 365, 'Le Port']],
            ],
            [
                'intitule' => 'Administrateur d’infrastructures sécurisées',
                'thematique' => 'Cybersécurité',
                'resume' => 'Administrez et sécurisez les infrastructures systèmes, réseaux et cloud d’une organisation.',
                'public' => 'Titulaires d’un Bac+2 en systèmes et réseaux',
                'duree' => '24 mois', 'heures' => 1050, 'niveau' => 6,
                'certification' => 'Titre professionnel Administrateur d’infrastructures sécurisées',
                'rythme' => '1 semaine en formation et 2 semaines en entreprise',
                'selection' => 'CV et lettre de motivation, test technique, puis entretien',
                'elements' => [
                    'objectif' => ['Administrer et sécuriser les infrastructures', 'Concevoir et mettre en œuvre une solution en réponse à un besoin d’évolution', 'Participer à la gestion de la cybersécurité'],
                    'prerequis' => ['Bac+2 en informatique, option systèmes et réseaux'],
                    'moyen' => $moyens, 'methode' => $methodes,
                    'evaluation' => ['Évaluations en cours de formation', 'Mise en situation professionnelle devant un jury'],
                    'acces' => $acces,
                    'debouche' => ['Administrateur systèmes et réseaux', 'Administrateur sécurité', 'Responsable d’infrastructure'],
                ],
                'programme' => [
                    'Infrastructures' => ['Virtualisation', 'Services d’annuaire', 'Supervision'],
                    'Sécurité' => ['Durcissement', 'Gestion des incidents', 'Veille'],
                ],
                'sessions' => [[310, 730, 'Le Port']],
            ],
            [
                'intitule' => 'Technicien supérieur systèmes et réseaux',
                'thematique' => 'Réseaux',
                'resume' => 'Assistez les utilisateurs et maintenez les systèmes et réseaux d’une entreprise en alternance.',
                'public' => 'Titulaires du Bac, passionnés d’informatique',
                'duree' => '18 mois', 'heures' => 900, 'niveau' => 5,
                'certification' => 'Titre professionnel Technicien supérieur systèmes et réseaux',
                'rythme' => '1 semaine en formation et 2 semaines en entreprise',
                'selection' => 'CV, tests de positionnement, puis entretien',
                'elements' => [
                    'objectif' => ['Assister les utilisateurs', 'Maintenir et exploiter le système d’information', 'Mettre en œuvre la sécurité'],
                    'prerequis' => ['Niveau Bac', 'Bonne pratique de l’outil informatique'],
                    'moyen' => $moyens, 'methode' => $methodes,
                    'evaluation' => ['Évaluations en cours de formation', 'Mise en situation professionnelle devant un jury'],
                    'acces' => $acces,
                    'debouche' => ['Technicien systèmes et réseaux', 'Technicien support'],
                ],
                'programme' => [
                    'Support' => ['Assistance utilisateurs', 'Postes de travail'],
                    'Exploitation' => ['Serveurs Windows et Linux', 'Réseaux', 'Sauvegardes'],
                ],
                'sessions' => [[315, 540, 'Mamoudzou']],
            ],
            [
                'intitule' => 'Chargé de communication digitale',
                'thematique' => 'Communication digitale',
                'resume' => 'Élaborez et pilotez la stratégie de communication numérique d’une entreprise ou d’une collectivité.',
                'public' => 'Titulaires d’un Bac+2 en communication, marketing ou commerce',
                'duree' => '12 mois', 'heures' => 560, 'niveau' => 6,
                'rythme' => '2 jours en formation et 3 jours en entreprise par semaine',
                'selection' => 'CV et lettre de motivation, puis entretien',
                'elements' => [
                    'objectif' => ['Définir une stratégie de communication digitale', 'Produire des contenus multimédias', 'Analyser les performances'],
                    'prerequis' => ['Bac+2 dans le domaine de la communication ou du commerce'],
                    'moyen' => $moyens, 'methode' => $methodes,
                    'evaluation' => ['Études de cas', 'Soutenance d’un projet devant un jury'],
                    'acces' => $acces,
                    'debouche' => ['Chargé de communication', 'Community manager', 'Responsable marketing digital'],
                ],
                'programme' => [
                    'Stratégie' => ['Plan de communication', 'Personas'],
                    'Production' => ['Rédaction web', 'Vidéo et visuels', 'Réseaux sociaux'],
                    'Pilotage' => ['Indicateurs', 'Budget'],
                ],
                'sessions' => [[330, 365, 'Sainte-Clotilde']],
            ],
        ];
    }
}
