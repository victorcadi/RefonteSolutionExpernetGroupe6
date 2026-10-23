<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Creneau;
use App\Entity\Emargement;
use App\Entity\Promotion;
use App\Entity\Utilisateur;
use App\Enum\ModeSignature;
use App\Enum\RoleUtilisateur;
use App\Repository\UtilisateurRepository;
use App\Service\Emargement\EmpreinteEmargement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Suivi de l'assiduité par les formateurs : alertes, historique et droits d'accès.
 *
 * Une promotion dédiée suit dix cours en mars 2030 (heure simulée au 20 mars) :
 * - Inès Grosset : présente à tout ;
 * - Paul Lebon : présent sauf aux trois derniers cours (décrochage récent, 70 %) ;
 * - Zoé Hoarau : absente à cinq des sept premiers cours, présente aux trois derniers (50 %).
 */
final class AssiduiteTest extends WebTestCase
{
    use ClockSensitiveTrait;

    private const DOMAINE = '@demo.expernet.test';

    private KernelBrowser $client;
    private Promotion $promotion;
    /** @var array<string, Utilisateur> */
    private array $etudiants = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        self::mockTime(new \DateTimeImmutable('2030-03-20 10:00:00'));

        $demo = $this->em()->getRepository(Promotion::class)->findOneBy(['nom' => 'CDA – promotion démo']);
        $this->promotion = (new Promotion())
            ->setNom('Promotion de test assiduité')
            ->setSession($demo->getSession())
            ->setFormateurReferent($this->compte('formateur2'));
        $this->em()->persist($this->promotion);

        foreach (['ines' => ['Grosset', 'Inès'], 'paul' => ['Lebon', 'Paul'], 'zoe' => ['Hoarau', 'Zoé']] as $cle => [$nom, $prenom]) {
            $etudiant = (new Utilisateur())->setNom($nom)->setPrenom($prenom)->setEmail($cle.'.test'.self::DOMAINE)
                ->setRole(RoleUtilisateur::Etudiant)->setPromotion($this->promotion)->setPassword('!');
            $this->promotion->getEtudiants()->add($etudiant);
            $this->em()->persist($etudiant);
            $this->etudiants[$cle] = $etudiant;
        }

        $creneaux = [];
        for ($i = 0; $i < 10; ++$i) {
            $debut = new \DateTimeImmutable(\sprintf('2030-03-%02d 08:30', 4 + $i));
            $creneau = (new Creneau())->setPromotion($this->promotion)->setFormateur($this->compte('formateur2'))
                ->setIntitule('Cours '.($i + 1))->setDebut($debut)->setFin($debut->modify('+3 hours 30 minutes'));
            $this->em()->persist($creneau);
            $creneaux[] = $creneau;
        }
        // Cours à venir : ne compte pas.
        $this->em()->persist((new Creneau())->setPromotion($this->promotion)->setIntitule('Cours à venir')
            ->setDebut(new \DateTimeImmutable('2030-03-21 08:30'))->setFin(new \DateTimeImmutable('2030-03-21 12:00')));
        $this->em()->flush();

        $absences = ['ines' => [], 'paul' => [7, 8, 9], 'zoe' => [0, 1, 2, 4, 5]];
        foreach ($absences as $cle => $absent) {
            foreach ($creneaux as $i => $creneau) {
                if (!\in_array($i, $absent, true)) {
                    $this->signer($creneau, $this->etudiants[$cle]);
                }
            }
        }
        $this->em()->flush();
    }

    public function testLeReferentVoitLesEtudiantsASuivreEnPremier(): void
    {
        $this->connecter('formateur2');
        $this->client->request('GET', $this->url());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.alert-warning', '2 étudiants à suivre');
        $lignes = $this->client->getCrawler()->filter('tbody tr')->each(static fn ($l): string => preg_replace('/\s+/', ' ', trim($l->text())));
        self::assertCount(3, $lignes);
        self::assertStringStartsWith('HOARAU Zoé 50 % 5 / 10', $lignes[0]);
        self::assertStringContainsString('Moins de 80 % de présence', $lignes[0]);
        self::assertStringNotContainsString('derniers cours manqués', $lignes[0]);
        self::assertStringStartsWith('LEBON Paul 70 % 7 / 10', $lignes[1]);
        self::assertStringContainsString('3 derniers cours manqués', $lignes[1]);
        self::assertStringStartsWith('GROSSET Inès 100 % 10 / 10', $lignes[2]);
        self::assertStringContainsString('Assidu(e)', $lignes[2]);
    }

    public function testLeTableauDeBordDuFormateurSignaleLesEtudiantsASuivre(): void
    {
        $this->connecter('formateur2');
        $this->client->request('GET', '/ent/formateur');

        self::assertSelectorTextContains('#titre-alertes', 'étudiants à suivre');
        self::assertAnySelectorTextContains('section[aria-labelledby="titre-alertes"] li', 'Paul Lebon (Promotion de test assiduité) : 70 % de présence, 3 derniers cours manqués.');
        self::assertAnySelectorTextContains('section[aria-labelledby="titre-alertes"] li', 'Zoé Hoarau');
    }

    public function testLAdministrateurVoitLeNombreDEtudiantsASuivreParPromotion(): void
    {
        $this->connecter('admin');
        $this->client->request('GET', '/ent/admin');

        self::assertAnySelectorTextContains('#titre-promotions + ul li', 'Promotion de test assiduité 2 étudiants à suivre');
        $this->client->request('GET', $this->url());
        self::assertResponseIsSuccessful();
    }

    public function testLHistoriqueDUnEtudiantListeSesPresencesEtAbsences(): void
    {
        $this->connecter('formateur2');
        $this->client->request('GET', $this->url($this->etudiants['paul']));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Présences de Paul Lebon');
        self::assertSelectorTextContains('.chiffre-assiduite', '70 %');
        self::assertSelectorCount(10, 'tbody tr');
        self::assertSelectorTextContains('tbody tr:first-child', 'Cours 10');
        self::assertSelectorTextContains('tbody tr:first-child', 'Absent(e)');
        self::assertSelectorTextContains('tbody tr:last-child', 'Présent(e)');
        // Le formateur du cours accède à la feuille d'émargement.
        self::assertSelectorExists('tbody tr:first-child a[href^="/ent/formateur/creneau/"]');
    }

    public function testUnEtudiantDUneAutrePromotionEstIntrouvable(): void
    {
        $this->connecter('formateur2');
        $this->client->request('GET', $this->url($this->compte('etudiant')));

        self::assertResponseStatusCodeSame(404);
    }

    public function testSeulsLesFormateursDeLaPromotionAccedentAuSuivi(): void
    {
        // Claire Grondin n'anime aucun cours de cette promotion et n'en est pas référente.
        $this->connecter('jury');
        $this->client->request('GET', $this->url());
        self::assertResponseStatusCodeSame(403);

        $this->connecter('etudiant');
        $this->client->request('GET', $this->url());
        self::assertResponseStatusCodeSame(403);
    }

    private function url(?Utilisateur $etudiant = null): string
    {
        return '/ent/formateur/promotions/'.$this->promotion->getId().'/assiduite'.($etudiant ? '/'.$etudiant->getId() : '');
    }

    private function signer(Creneau $creneau, Utilisateur $etudiant): void
    {
        $emargement = (new Emargement())->setCreneau($creneau)->setEtudiant($etudiant)
            ->setSigneLe($creneau->getDebut()->modify('+5 minutes'))
            ->setMode(ModeSignature::Saisie)->setSignature($etudiant->getNomComplet());
        self::getContainer()->get(EmpreinteEmargement::class)->sceller($emargement);
        $this->em()->persist($emargement);
    }

    private function connecter(string $identifiant): void
    {
        $compte = $this->compte($identifiant)->setTotpSecret('JBSWY3DPEHPK3PXP')->setTotpActive(true);
        $this->em()->flush();
        $this->client->loginUser($compte);
    }

    private function compte(string $identifiant): Utilisateur
    {
        $compte = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['email' => $identifiant.self::DOMAINE]);
        self::assertNotNull($compte);

        return $compte;
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
