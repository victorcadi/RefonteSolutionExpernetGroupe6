<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Creneau;
use App\Entity\Emargement;
use App\Entity\Promotion;
use App\Entity\Utilisateur;
use App\Enum\ModeSignature;
use App\Repository\EmargementRepository;
use App\Repository\UtilisateurRepository;
use App\Service\Emargement\EmpreinteEmargement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Émargement : signature pendant le cours, refus, feuille du formateur, planning et attestations.
 *
 * L'heure est simulée (mercredi 25 novembre 2026, 10 h) : les cours créés ici
 * n'interfèrent pas avec ceux des données de démonstration.
 */
final class EmargementTest extends WebTestCase
{
    use ClockSensitiveTrait;

    private const DOMAINE = '@demo.expernet.test';
    private const MAINTENANT = '2026-11-25 10:00:00';
    private const FORMULAIRE = 'signature_emargement';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Date créée après le démarrage du noyau, qui fixe le fuseau horaire de La Réunion.
        self::mockTime(new \DateTimeImmutable(self::MAINTENANT));
    }

    // --- Signature ---------------------------------------------------------

    public function testLEtudiantSigneEnTracantSaSignaturePendantLeCours(): void
    {
        $creneau = $this->creneau('08:30', '12:00');
        $this->connecter('etudiant');

        $this->client->request('GET', '/ent/etudiant/emargement/'.$creneau->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Signer l’émargement');
        self::assertSelectorExists('canvas[role="img"][aria-label]');

        $this->client->submitForm('Signer l’émargement', [
            self::FORMULAIRE.'[mode]' => 'manuscrite',
            self::FORMULAIRE.'[signature]' => self::signaturePng(),
            self::FORMULAIRE.'[certification]' => true,
        ]);

        self::assertResponseRedirects('/ent/etudiant', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Votre présence au cours « API REST » est enregistrée.');

        $emargement = $this->emargement($creneau, 'etudiant');
        self::assertNotNull($emargement);
        self::assertSame(ModeSignature::Manuscrite, $emargement->getMode());
        self::assertSame('2026-11-25 10:00:00', $emargement->getSigneLe()->format('Y-m-d H:i:s'), 'Heure du serveur.');
        self::assertSame('127.0.0.1', $emargement->getAdresseIp());
        self::assertTrue(self::getContainer()->get(EmpreinteEmargement::class)->estIntegre($emargement), 'Émargement scellé.');
    }

    public function testSansScriptLEtudiantSigneEnSaisissantSonNom(): void
    {
        $creneau = $this->creneau('08:30', '12:00');
        $this->connecter('etudiant');

        // Sans JavaScript, aucun mode n'est envoyé : c'est la saisie du nom (casse et accents ignorés).
        $this->client->request('GET', '/ent/etudiant/emargement/'.$creneau->getId());
        $this->client->submitForm('Signer l’émargement', [
            self::FORMULAIRE.'[nom]' => '  lucas   TECHER ',
            self::FORMULAIRE.'[certification]' => true,
        ]);

        self::assertResponseRedirects('/ent/etudiant', 303);
        $emargement = $this->emargement($creneau, 'etudiant');
        self::assertSame(ModeSignature::Saisie, $emargement?->getMode());
        self::assertSame('lucas   TECHER', $emargement?->getSignature());
    }

    public function testUnNomQuiNeCorrespondPasEstRefuse(): void
    {
        $creneau = $this->creneau('08:30', '12:00');
        $this->connecter('etudiant');

        $this->client->request('GET', '/ent/etudiant/emargement/'.$creneau->getId());
        $this->client->submitForm('Signer l’émargement', [
            self::FORMULAIRE.'[nom]' => 'Emma Rivière',
            self::FORMULAIRE.'[certification]' => true,
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'ne correspond pas à celui de votre compte (Lucas Técher)');
        self::assertSelectorTextContains('[role="alert"]', 'Votre prénom et votre nom');
        self::assertNull($this->emargement($creneau, 'etudiant'));
    }

    public function testUnCadreDeSignatureVideEstRefuse(): void
    {
        $creneau = $this->creneau('08:30', '12:00');
        $this->connecter('etudiant');

        $this->client->request('GET', '/ent/etudiant/emargement/'.$creneau->getId());
        $this->client->submitForm('Signer l’émargement', [
            self::FORMULAIRE.'[mode]' => 'manuscrite',
            self::FORMULAIRE.'[signature]' => self::signaturePng(vide: true),
            self::FORMULAIRE.'[certification]' => true,
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.zone-signature + .invalid-feedback', 'La signature est vide');
        // Le récapitulatif renvoie vers le cadre de signature.
        self::assertSelectorExists('[role="alert"] a[href="#'.self::FORMULAIRE.'_signature"]');
        self::assertSelectorExists('#'.self::FORMULAIRE.'_signature.zone-signature');
        self::assertNull($this->emargement($creneau, 'etudiant'));
    }

    public function testLaPresenceDoitEtreCertifiee(): void
    {
        $creneau = $this->creneau('08:30', '12:00');
        $this->connecter('etudiant');

        $this->client->request('GET', '/ent/etudiant/emargement/'.$creneau->getId());
        $this->client->submitForm('Signer l’émargement', [self::FORMULAIRE.'[nom]' => 'Lucas Técher']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'Cochez la case pour certifier votre présence.');
        self::assertNull($this->emargement($creneau, 'etudiant'));
    }

    public function testOnNePeutPasSignerAvantLeDebutNiApresLaFin(): void
    {
        $aVenir = $this->creneau('13:00', '16:30');
        $termine = $this->creneau('06:00', '09:59');
        $this->connecter('etudiant');

        foreach ([$aVenir, $termine] as $creneau) {
            $this->client->request('GET', '/ent/etudiant/emargement/'.$creneau->getId());
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h1', 'Émargement indisponible');
            self::assertSelectorTextContains('[role="status"]', 'L’émargement se signe pendant le cours');
            self::assertSelectorNotExists('form[name="'.self::FORMULAIRE.'"]');
        }
    }

    public function testUnEnvoiHorsCreneauEstRefuseMemeSiLeFormulaireAEteOuvertAvant(): void
    {
        $creneau = $this->creneau('08:30', '10:05');
        $this->connecter('etudiant');
        $this->client->request('GET', '/ent/etudiant/emargement/'.$creneau->getId());

        // Le cours se termine pendant que la page est ouverte.
        self::mockTime(new \DateTimeImmutable('2026-11-25 10:06:00'));
        $this->client->submitForm('Signer l’émargement', [
            self::FORMULAIRE.'[nom]' => 'Lucas Técher',
            self::FORMULAIRE.'[certification]' => true,
        ]);

        self::assertResponseStatusCodeSame(409);
        self::assertNull($this->emargement($creneau, 'etudiant'));
    }

    public function testOnNeSignePasDeuxFoisLeMemeCours(): void
    {
        $creneau = $this->creneau('08:30', '12:00');
        $this->connecter('etudiant');
        $this->client->request('GET', '/ent/etudiant/emargement/'.$creneau->getId());
        $formulaire = $this->client->getCrawler()->selectButton('Signer l’émargement')->form([
            self::FORMULAIRE.'[nom]' => 'Lucas Técher',
            self::FORMULAIRE.'[certification]' => true,
        ]);

        $this->client->submit($formulaire);
        self::assertResponseRedirects('/ent/etudiant', 303);

        // Second envoi du même formulaire (double clic, retour arrière).
        $this->client->submit($formulaire);
        self::assertResponseStatusCodeSame(409);
        self::assertSelectorTextContains('h1', 'Émargement indisponible');
        self::assertAnySelectorTextContains('[role="status"]', 'Vous avez déjà signé');

        self::assertCount(1, self::getContainer()->get(EmargementRepository::class)->findBy(['creneau' => $creneau]));
    }

    public function testUnCoursDUneAutrePromotionNEstPasAccessible(): void
    {
        $promotion = (new Promotion())
            ->setNom('Autre promotion')
            ->setSession($this->compte('etudiant')->getPromotion()->getSession());
        $this->em()->persist($promotion);
        $creneau = $this->creneau('08:30', '12:00', $promotion);
        $this->connecter('etudiant');

        $this->client->request('GET', '/ent/etudiant/emargement/'.$creneau->getId());

        self::assertResponseStatusCodeSame(404);
    }

    public function testUnFormateurNeSignePasLEmargementDesEtudiants(): void
    {
        $creneau = $this->creneau('08:30', '12:00');
        $this->connecter('formateur');

        $this->client->request('GET', '/ent/etudiant/emargement/'.$creneau->getId());

        self::assertResponseStatusCodeSame(403);
    }

    // --- Feuille d'émargement ----------------------------------------------

    public function testLeFormateurConsulteLaFeuilleEtDetecteUneModification(): void
    {
        $creneau = $this->creneau('08:30', '12:00');
        $emargement = $this->signerDirectement($creneau, 'etudiant');
        $this->signerDirectement($creneau, 'etudiant2');
        $this->connecter('formateur');

        $this->client->request('GET', '/ent/formateur/creneau/'.$creneau->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Feuille d’émargement');
        self::assertSelectorCount(6, 'table tbody tr');
        self::assertSelectorTextContains('.chiffre-assiduite', '2 / 6');
        self::assertSelectorCount(2, 'table tbody .badge-expernet--succes:contains("Vérifié")');
        self::assertSelectorNotExists('.alert-danger');

        // Modification directe en base : l'heure de signature est avancée.
        $this->em()->getConnection()->executeStatement(
            'UPDATE emargement SET signe_le = :heure WHERE id = :id',
            ['heure' => '2026-11-25 08:31:00', 'id' => $emargement->getId()],
        );

        $this->client->request('GET', '/ent/formateur/creneau/'.$creneau->getId());
        self::assertSelectorTextContains('.alert-danger', '1 émargement a été modifié après la signature');
        self::assertSelectorTextContains('table tbody', 'Modifié');
    }

    public function testLaFeuilleSeTelechargeEnPdf(): void
    {
        $creneau = $this->creneau('08:30', '12:00');
        $this->signerDirectement($creneau, 'etudiant');
        $this->connecter('formateur');

        $this->client->request('GET', '/ent/formateur/creneau/'.$creneau->getId().'/pdf');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertResponseHeaderSame('Content-Disposition', 'attachment; filename=emargement-2026-11-25-0830.pdf');
        self::assertStringStartsWith('%PDF-', (string) $this->client->getResponse()->getContent());
    }

    /** @return iterable<string, array{string, int}> */
    public static function accesFeuille(): iterable
    {
        yield 'formateur du cours' => ['formateur2', 200];
        yield 'formateur référent de la promotion' => ['formateur', 200];
        yield 'administrateur' => ['admin', 200];
        yield 'formateur sans lien avec le cours' => ['jury', 403];
        yield 'étudiant' => ['etudiant', 403];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('accesFeuille')]
    public function testAccesALaFeuille(string $identifiant, int $statut): void
    {
        $creneau = $this->creneau('08:30', '12:00')->setFormateur($this->compte('formateur2'));
        $this->em()->flush();
        $this->connecter($identifiant);

        $this->client->request('GET', '/ent/formateur/creneau/'.$creneau->getId());

        self::assertResponseStatusCodeSame($statut);
    }

    // --- Planning ----------------------------------------------------------

    public function testLePlanningDeLEtudiantProposeDeSignerLeCoursEnCours(): void
    {
        $enCours = $this->creneau('08:30', '12:00');
        $this->creneau('13:00', '16:30');
        $this->connecter('etudiant');

        $this->client->request('GET', '/ent/etudiant/planning');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.navigation-semaine h2', 'Semaine du 23 novembre 2026 au 29 novembre 2026');
        self::assertSelectorTextContains('.planning-jour--aujourdhui h3', 'Mercredi 25 novembre 2026');
        self::assertSelectorCount(1, '.planning-jour--aujourdhui a[href="/ent/etudiant/emargement/'.$enCours->getId().'"]');
        self::assertSelectorExists('a[href="/ent/etudiant/planning?semaine=2026-W47"]');
        self::assertSelectorExists('a[href="/ent/etudiant/planning?semaine=2026-W49"]');
        self::assertSelectorExists('nav.ent-nav a[href="/ent/etudiant/planning"][aria-current="page"]');
    }

    public function testUneSemaineInvalideAfficheLaSemaineEnCours(): void
    {
        $this->connecter('etudiant');

        $this->client->request('GET', '/ent/etudiant/planning?semaine=n-importe-quoi');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.navigation-semaine h2', 'Semaine du 23 novembre 2026');
    }

    public function testLePlanningDuFormateurDonneAccesAuxFeuilles(): void
    {
        $creneau = $this->creneau('08:30', '12:00');
        $this->connecter('formateur');

        $this->client->request('GET', '/ent/formateur/planning?semaine=2026-W48');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/ent/formateur/creneau/'.$creneau->getId().'"]');
    }

    // --- Attestations ------------------------------------------------------

    public function testLEtudiantTelechargeSonAttestationMensuelle(): void
    {
        $creneau = $this->creneau('06:00', '09:30');
        $this->signerDirectement($creneau, 'etudiant');
        $this->connecter('etudiant');

        $this->client->request('GET', '/ent/etudiant/attestations');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.liste-creneaux li:first-child', 'Novembre 2026');
        self::assertSelectorExists('a[href="/ent/etudiant/attestations/2026-11"]');

        $this->client->request('GET', '/ent/etudiant/attestations/2026-11');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertResponseHeaderSame('Content-Disposition', 'attachment; filename=attestation-presence-2026-11.pdf');
        self::assertStringStartsWith('%PDF-', (string) $this->client->getResponse()->getContent());
    }

    public function testPasDAttestationPourUnMoisFuturOuHorsFormation(): void
    {
        $this->connecter('etudiant');

        $this->client->request('GET', '/ent/etudiant/attestations/2026-12');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/ent/etudiant/attestations/2020-01');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/ent/etudiant/attestations/2026-13');
        self::assertResponseStatusCodeSame(404);
    }

    // --- Outils ------------------------------------------------------------

    /** Cours le jour simulé, pour la promotion de démonstration par défaut. */
    private function creneau(string $debut, string $fin, ?Promotion $promotion = null): Creneau
    {
        $creneau = (new Creneau())
            ->setPromotion($promotion ?? $this->compte('etudiant')->getPromotion())
            ->setFormateur($this->compte('formateur'))
            ->setIntitule('API REST')
            ->setSalle('Salle 1')
            ->setDebut(new \DateTimeImmutable('2026-11-25 '.$debut))
            ->setFin(new \DateTimeImmutable('2026-11-25 '.$fin));
        $this->em()->persist($creneau);
        $this->em()->flush();

        return $creneau;
    }

    /** Émargement créé sans passer par le formulaire (données de départ d'un test). */
    private function signerDirectement(Creneau $creneau, string $identifiant): Emargement
    {
        $emargement = (new Emargement())
            ->setCreneau($creneau)
            ->setEtudiant($this->compte($identifiant))
            ->setSigneLe($creneau->getDebut()->modify('+10 minutes'))
            ->setMode(ModeSignature::Manuscrite)
            ->setSignature(self::signaturePng());
        self::getContainer()->get(EmpreinteEmargement::class)->sceller($emargement);
        $this->em()->persist($emargement);
        $this->em()->flush();

        return $emargement;
    }

    private function connecter(string $identifiant): void
    {
        $compte = $this->compte($identifiant)->setTotpSecret('JBSWY3DPEHPK3PXP')->setTotpActive(true);
        $this->em()->flush();
        $this->client->loginUser($compte);
    }

    private function emargement(Creneau $creneau, string $identifiant): ?Emargement
    {
        $this->em()->clear();

        return self::getContainer()->get(EmargementRepository::class)->findOneBy([
            'creneau' => $creneau->getId(),
            'etudiant' => $this->compte($identifiant)->getId(),
        ]);
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

    /** Signature tracée (courbe) ou cadre vide, au format envoyé par le navigateur. */
    private static function signaturePng(bool $vide = false): string
    {
        $image = imagecreatetruecolor(400, 150);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 255, 255, 255, 127));
        if (!$vide) {
            imagesetthickness($image, 3);
            $encre = imagecolorallocate($image, 15, 58, 75);
            for ($x = 10; $x < 380; $x += 4) {
                imageline($image, $x, (int) (75 + 25 * sin($x / 20)), $x + 4, (int) (75 + 25 * sin(($x + 4) / 20)), $encre);
            }
        }
        ob_start();
        imagepng($image);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }
}
