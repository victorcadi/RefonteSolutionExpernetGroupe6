<?php

namespace App\DataFixtures;

use App\Entity\Creneau;
use App\Entity\Emargement;
use App\Entity\Examen;
use App\Entity\Formation;
use App\Entity\Note;
use App\Entity\Promotion;
use App\Entity\Session;
use App\Entity\Site;
use App\Entity\Utilisateur;
use App\Enum\ModeSignature;
use App\Enum\RoleUtilisateur;
use App\Enum\TypeExamen;
use App\Service\Emargement\EmpreinteEmargement;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Comptes et données de DÉMONSTRATION de l'ENT (personnes fictives).
 *
 * Tous les comptes utilisent le mot de passe défini par FIXTURES_MOT_DE_PASSE
 * (« Expernet-Demo-2026 » par défaut). La double authentification est demandée
 * à la première connexion.
 */
class EntFixtures extends Fixture implements DependentFixtureInterface
{
    public const DOMAINE = 'demo.expernet.test';

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        private readonly EmpreinteEmargement $empreinte,
    ) {
    }

    public function getDependencies(): array
    {
        return [CatalogueFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $motDePasse = $_SERVER['FIXTURES_MOT_DE_PASSE'] ?? $_ENV['FIXTURES_MOT_DE_PASSE'] ?? 'Expernet-Demo-2026';

        $admin = $this->utilisateur('Admin', 'Démo', 'admin', RoleUtilisateur::Admin, $motDePasse);
        $formateur = $this->utilisateur('Payet', 'Sophie', 'formateur', RoleUtilisateur::Formateur, $motDePasse)->setPeutEtreJure(true);
        $formateur2 = $this->utilisateur('Hoarau', 'David', 'formateur2', RoleUtilisateur::Formateur, $motDePasse)->setPeutEtreJure(true);
        $jureExterne = $this->utilisateur('Grondin', 'Claire', 'jury', RoleUtilisateur::Formateur, $motDePasse)->setPeutEtreJure(true);
        foreach ([$admin, $formateur, $formateur2, $jureExterne] as $u) {
            $manager->persist($u);
        }

        /** @var Session $session */
        $session = $this->getReference(CatalogueFixtures::SESSION_CDA, Session::class);
        $promotion = (new Promotion())
            ->setNom('CDA – promotion démo')
            ->setSession($session)
            ->setFormateurReferent($formateur);
        $manager->persist($promotion);

        $noms = [
            ['Técher', 'Lucas'], ['Rivière', 'Emma'], ['Fontaine', 'Noah'],
            ['Boyer', 'Léa'], ['Maillot', 'Hugo'], ['Ah-Kiem', 'Chloé'],
        ];
        $etudiants = [];
        foreach ($noms as $i => [$nom, $prenom]) {
            $identifiant = 0 === $i ? 'etudiant' : 'etudiant'.($i + 1);
            $etudiant = $this->utilisateur($nom, $prenom, $identifiant, RoleUtilisateur::Etudiant, $motDePasse)
                ->setPromotion($promotion);
            $manager->persist($etudiant);
            $etudiants[] = $etudiant;
        }

        $this->creerCreneauxEtEmargements($manager, $promotion, [$formateur, $formateur2], $etudiants);
        $this->creerNotes($manager, $etudiants, $formateur);
        $this->creerExamen($manager, $promotion, [$formateur, $jureExterne]);

        $manager->flush();
    }

    private function utilisateur(string $nom, string $prenom, string $identifiant, RoleUtilisateur $role, string $motDePasse): Utilisateur
    {
        $utilisateur = (new Utilisateur())
            ->setNom($nom)
            ->setPrenom($prenom)
            ->setEmail($identifiant.'@'.self::DOMAINE)
            ->setRole($role);
        $utilisateur->setPassword($this->hasher->hashPassword($utilisateur, $motDePasse));

        return $utilisateur;
    }

    /**
     * Quatre semaines passées, la semaine en cours et la suivante, du lundi au vendredi,
     * avec deux demi-journées (8h30-12h00 et 13h00-16h30).
     *
     * @param list<Utilisateur> $formateurs
     * @param list<Utilisateur> $etudiants
     */
    private function creerCreneauxEtEmargements(ObjectManager $manager, Promotion $promotion, array $formateurs, array $etudiants): void
    {
        $modules = ['Conception UML', 'Symfony – sécurité', 'Base de données relationnelle', 'Tests automatisés', 'API REST', 'Déploiement Docker'];
        $maintenant = new \DateTimeImmutable();
        $lundi = (new \DateTimeImmutable('monday this week'))->modify('-4 weeks');

        $creneaux = [];
        for ($jour = 0; $jour < 42; ++$jour) {
            $date = $lundi->modify("+{$jour} days");
            if ((int) $date->format('N') > 5) {
                continue;
            }

            foreach ([['08:30', '12:00'], ['13:00', '16:30']] as $j => [$debut, $fin]) {
                $creneau = (new Creneau())
                    ->setPromotion($promotion)
                    ->setFormateur($formateurs[($jour + $j) % 2])
                    ->setIntitule($modules[($jour + $j) % \count($modules)])
                    ->setSalle('Salle '.(1 + $j))
                    ->setDebut(new \DateTimeImmutable($date->format('Y-m-d').' '.$debut))
                    ->setFin(new \DateTimeImmutable($date->format('Y-m-d').' '.$fin));
                $manager->persist($creneau);
                $creneaux[] = [$creneau, $jour, $j];
            }
        }

        // Le sceau inclut les identifiants : créneaux et comptes doivent être enregistrés avant.
        $manager->flush();

        foreach ($creneaux as [$creneau, $jour, $j]) {
            if ($creneau->getFin() >= $maintenant) {
                continue;
            }

            // Créneaux passés : la plupart des étudiants ont signé.
            // L'étudiant n°5 est souvent absent pour illustrer l'alerte d'absentéisme ;
            // l'étudiante n°4 signe en saisissant son nom (alternative au tracé).
            foreach ($etudiants as $k => $etudiant) {
                $absent = (4 === $k && 0 !== $jour % 3) || (0 === ($jour * 7 + $k * 3 + $j) % 23);
                if ($absent) {
                    continue;
                }
                $saisie = 3 === $k;
                $emargement = (new Emargement())
                    ->setCreneau($creneau)
                    ->setEtudiant($etudiant)
                    ->setSigneLe($creneau->getDebut()->modify('+'.(5 + $k).' minutes'))
                    ->setMode($saisie ? ModeSignature::Saisie : ModeSignature::Manuscrite)
                    ->setSignature($saisie ? $etudiant->getNomComplet() : $this->signatureDemo($k))
                    ->setAdresseIp('127.0.0.1');
                $this->empreinte->sceller($emargement);
                $manager->persist($emargement);
            }
        }
    }

    /**
     * @param list<Utilisateur> $etudiants
     */
    private function creerNotes(ObjectManager $manager, array $etudiants, Utilisateur $formateur): void
    {
        $tps = ['TP Maquettage', 'TP Base de données', 'TP Symfony', 'TP Tests unitaires'];
        foreach ($etudiants as $k => $etudiant) {
            foreach ($tps as $i => $tp) {
                $manager->persist((new Note())
                    ->setEtudiant($etudiant)
                    ->setFormateur($formateur)
                    ->setIntitule($tp)
                    ->setValeur(number_format(10 + (($k * 3 + $i * 5) % 10) + 0.5 * ($i % 2), 2, '.', ''))
                    ->setDate((new \DateTimeImmutable('today'))->modify('-'.(7 * (4 - $i)).' days')));
            }
        }
    }

    /**
     * @param list<Utilisateur> $jury
     */
    private function creerExamen(ObjectManager $manager, Promotion $promotion, array $jury): void
    {
        $examen = (new Examen())
            ->setIntitule('Mise en situation – bloc 1 : développer une application sécurisée')
            ->setType(TypeExamen::MiseEnSituation)
            ->setPromotion($promotion)
            ->setDateExamen((new \DateTimeImmutable('monday next week'))->modify('+2 weeks')->setTime(9, 0))
            ->setDureeMinutes(180)
            ->setSite($this->getReference(CatalogueFixtures::SITE_SAINTE_CLOTILDE, Site::class))
            ->setSalle('Salle 1');
        foreach ($jury as $membre) {
            $examen->ajouterMembreJury($membre);
        }
        $manager->persist($examen);

        // La formation est chargée pour s'assurer que ses compétences existent (grille).
        $this->getReference(CatalogueFixtures::FORMATION_CDA, Formation::class);
    }

    /** @var array<int, string> */
    private array $signatures = [];

    /**
     * Tracé de démonstration (courbe propre à chaque étudiant), généré avec GD.
     */
    private function signatureDemo(int $graine): string
    {
        if (isset($this->signatures[$graine])) {
            return $this->signatures[$graine];
        }

        $image = imagecreatetruecolor(360, 120);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 255, 255, 255, 127));
        $encre = imagecolorallocate($image, 15, 58, 75);
        imagesetthickness($image, 3);

        $precedent = null;
        for ($x = 20; $x <= 340; $x += 4) {
            $y = (int) (60 + 28 * sin(($x + 17 * $graine) / (18 + 3 * $graine)) + 10 * sin($x / 7));
            if (null !== $precedent) {
                imageline($image, $precedent[0], $precedent[1], $x, $y, $encre);
            }
            $precedent = [$x, $y];
        }

        ob_start();
        imagepng($image);

        return $this->signatures[$graine] = 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }
}
