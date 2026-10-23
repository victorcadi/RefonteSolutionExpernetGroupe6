<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use OTPHP\TOTP;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Espace formation : connexion, double authentification obligatoire et droits par rôle.
 */
final class EspaceFormationTest extends WebTestCase
{
    private const DOMAINE = '@demo.expernet.test';

    public function testEspaceFormationExigeUneConnexion(): void
    {
        $client = static::createClient();
        $client->request('GET', '/ent/etudiant');

        self::assertResponseRedirects('/login');
    }

    public function testPageDeConnexionEnFrancais(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Connexion');
        self::assertSelectorExists('label[for="identifiant"]');
        self::assertSelectorExists('input#mot-de-passe[autocomplete="current-password"]');
    }

    public function testMauvaisMotDePasseAfficheUneErreur(): void
    {
        $client = static::createClient();
        $this->seConnecter($client, 'etudiant', 'mauvais-mot-de-passe');
        $client->followRedirect();

        self::assertSelectorTextContains('[role="alert"]', 'Identifiants invalides');
        self::assertSelectorExists('#identifiant[aria-invalid="true"]');
    }

    public function testCompteDesactiveEstRefuse(): void
    {
        $client = static::createClient();
        $this->compte('etudiant6')->setActif(false);
        $this->em()->flush();

        $this->seConnecter($client, 'etudiant6');
        $client->followRedirect();

        self::assertSelectorTextContains('[role="alert"]', 'Ce compte est désactivé');
    }

    public function testDoubleAuthentificationEstObligatoireEtSActive(): void
    {
        $client = static::createClient();
        $this->seConnecter($client, 'etudiant');
        self::assertResponseRedirects('/ent');

        // Sans double authentification, tout l'ENT renvoie vers l'activation.
        $client->request('GET', '/ent/etudiant');
        self::assertResponseRedirects('/ent/securite/double-authentification');

        $crawler = $client->request('GET', '/ent/securite/double-authentification');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('img.qr-code[alt]');
        $secret = str_replace(' ', '', $crawler->filter('.cle-totp code')->text());

        $client->submitForm('Activer', ['form[code]' => '000000']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'Ce code ne correspond pas');

        $client->submitForm('Activer', ['form[code]' => TOTP::createFromSecret($secret)->now()]);
        self::assertResponseRedirects('/ent');
        self::assertTrue($this->compte('etudiant')->isTotpAuthenticationEnabled());

        $client->request('GET', '/ent/etudiant');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Bonjour');
    }

    public function testConnexionAvecCodeDeVerification(): void
    {
        $client = static::createClient();
        $totp = $this->activerDoubleAuthentification('formateur');

        $this->seConnecter($client, 'formateur');
        self::assertResponseRedirects('/ent');
        $client->followRedirect();
        self::assertResponseRedirects('/2fa');

        // Tant que le code n'est pas saisi, l'ENT reste inaccessible.
        $client->request('GET', '/ent/formateur');
        self::assertResponseRedirects('/2fa');

        $client->request('GET', '/2fa');
        $client->submitForm('Valider', ['_auth_code' => '000000']);
        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'code de vérification n\'est pas valide');

        // Le bon code renvoie vers la page demandée avant la vérification.
        $client->submitForm('Valider', ['_auth_code' => $totp->now()]);
        self::assertResponseRedirects('/ent/formateur');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testCodesErronesRepetesSontBloques(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $totp = $this->activerDoubleAuthentification('etudiant2');
        $this->seConnecter($client, 'etudiant2');

        for ($essai = 1; $essai <= 5; ++$essai) {
            $client->request('GET', '/2fa');
            $client->submitForm('Valider', ['_auth_code' => '000000']);
        }

        // Même le bon code est refusé tant que la limite est atteinte.
        $client->request('GET', '/2fa');
        $client->submitForm('Valider', ['_auth_code' => $totp->now()]);
        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Trop de codes erronés');
    }

    /** @return iterable<string, array{string, string, int}> */
    public static function droits(): iterable
    {
        yield 'étudiant → son tableau de bord' => ['etudiant', '/ent/etudiant', 200];
        yield 'étudiant → espace formateur' => ['etudiant', '/ent/formateur', 403];
        yield 'étudiant → administration' => ['etudiant', '/ent/admin', 403];
        yield 'formateur → son tableau de bord' => ['formateur', '/ent/formateur', 200];
        yield 'formateur → administration' => ['formateur', '/ent/admin', 403];
        yield 'formateur → espace étudiant' => ['formateur', '/ent/etudiant', 403];
        yield 'admin → administration' => ['admin', '/ent/admin', 200];
        yield 'admin → espace formateur (hiérarchie)' => ['admin', '/ent/formateur', 200];
    }

    #[DataProvider('droits')]
    public function testDroitsParRole(string $identifiant, string $adresse, int $statut): void
    {
        $client = static::createClient();
        $this->activerDoubleAuthentification($identifiant);
        $client->loginUser($this->compte($identifiant));

        $client->request('GET', $adresse);

        self::assertResponseStatusCodeSame($statut);
    }

    /** @return iterable<string, array{string, string}> */
    public static function accueils(): iterable
    {
        yield 'étudiant' => ['etudiant', '/ent/etudiant'];
        yield 'formateur' => ['formateur', '/ent/formateur'];
        yield 'admin' => ['admin', '/ent/admin'];
    }

    #[DataProvider('accueils')]
    public function testAccueilDeLEspaceRenvoieVersLeTableauDeBordDuRole(string $identifiant, string $tableau): void
    {
        $client = static::createClient();
        $this->activerDoubleAuthentification($identifiant);
        $client->loginUser($this->compte($identifiant));

        $client->request('GET', '/ent');
        self::assertResponseRedirects($tableau);

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('nav.ent-nav a[aria-current="page"]');
    }

    public function testDeconnexion(): void
    {
        $client = static::createClient();
        $this->activerDoubleAuthentification('etudiant');
        $client->loginUser($this->compte('etudiant'));

        $crawler = $client->request('GET', '/ent/etudiant');
        $client->click($crawler->filter('.ent-nav a:contains("Se déconnecter")')->link());
        self::assertResponseRedirects('/');

        $client->request('GET', '/ent/etudiant');
        self::assertResponseRedirects('/login');
    }

    private function seConnecter(KernelBrowser $client, string $identifiant, ?string $motDePasse = null): void
    {
        $client->request('GET', '/login');
        $client->submitForm('Se connecter', [
            '_username' => $identifiant.self::DOMAINE,
            '_password' => $motDePasse ?? ($_SERVER['FIXTURES_MOT_DE_PASSE'] ?? 'Expernet-Demo-2026'),
        ]);
    }

    /** Active la double authentification avec un secret aléatoire propre au test. */
    private function activerDoubleAuthentification(string $identifiant): TOTP
    {
        $totp = TOTP::generate();
        $this->compte($identifiant)->setTotpSecret($totp->getSecret())->setTotpActive(true);
        $this->em()->flush();

        return $totp;
    }

    private function compte(string $identifiant): Utilisateur
    {
        $utilisateur = self::getContainer()->get(UtilisateurRepository::class)->findOneBy(['email' => $identifiant.self::DOMAINE]);
        self::assertNotNull($utilisateur);

        return $utilisateur;
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
