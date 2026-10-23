<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Pages publiques : accueil, catalogue, recherche, filtres et fiches.
 *
 * Prérequis : base de test chargée avec les données de démonstration
 * (php bin/console doctrine:fixtures:load --env=test -n).
 */
final class CataloguePublicTest extends WebTestCase
{
    private const FICHE_ALTERNANCE = '/formations/alternance/concepteur-developpeur-d-applications';

    public function testAccueilPresenteLesDeuxUnivers(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, 'h1');
        self::assertSelectorExists('a.lien-evitement[href="#contenu"]');
        self::assertSelectorExists('.choix-univers a[href="/formations/continue"]');
        self::assertSelectorExists('.choix-univers a[href="/formations/alternance"]');
        self::assertSame('fr', $crawler->filter('html')->attr('lang'));
    }

    public function testCatalogueListeLesFormationsPubliees(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/formations');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Catalogue des formations');
        self::assertGreaterThan(0, $crawler->filter('article.carte')->count());
        self::assertSelectorExists('form[role="search"]');
    }

    public function testRechercheParMotCleEtCpf(): void
    {
        $client = static::createClient();
        $client->request('GET', '/formations/continue', ['q' => 'excel', 'cpf' => '1']);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[role="status"]', 'correspond');
        self::assertSelectorTextContains('[role="status"]', '« excel »');
        self::assertSelectorExists('meta[name="robots"][content="noindex, follow"]');
    }

    public function testRechercheSansResultatAfficheUnEtatVide(): void
    {
        $client = static::createClient();
        $client->request('GET', '/formations', ['q' => 'aucuneformationnecorrespond']);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[role="status"]', 'Aucune formation ne correspond');
        self::assertSelectorNotExists('article.carte');
    }

    public function testFiltreParNiveauNeGardeQueLesFormationsDuNiveau(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/formations/alternance', ['niveau' => '6']);

        self::assertResponseIsSuccessful();
        $cartes = $crawler->filter('article.carte');
        self::assertGreaterThan(0, $cartes->count());
        $cartes->each(static fn ($carte) => self::assertStringContainsString('Certification de niveau 6', $carte->text()));
    }

    public function testValeurDeFiltreInvalideEstIgnoree(): void
    {
        $client = static::createClient();
        $client->request('GET', '/formations', ['niveau' => '99', 'page' => '-3']);

        self::assertResponseIsSuccessful();
    }

    public function testFicheAlternanceAfficheLesRubriquesAttendues(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', self::FICHE_ALTERNANCE);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Concepteur développeur d’applications');
        self::assertSelectorTextContains('#titre-admission', 'admission');
        self::assertSelectorExists('nav[aria-label="Fil d’Ariane"]');
        self::assertSelectorExists('a[href="'.self::FICHE_ALTERNANCE.'/programme.pdf"]');
        self::assertSame(
            'http://localhost'.self::FICHE_ALTERNANCE,
            $crawler->filter('link[rel="canonical"]')->attr('href'),
        );
    }

    public function testFicheDansLeMauvaisUniversEstRedirigeeDefinitivement(): void
    {
        $client = static::createClient();
        $client->request('GET', '/formations/continue/concepteur-developpeur-d-applications');

        self::assertResponseRedirects(self::FICHE_ALTERNANCE, 301);
    }

    public function testProgrammeEstTelechargeableEnPdf(): void
    {
        $client = static::createClient();
        $client->request('GET', self::FICHE_ALTERNANCE.'/programme.pdf');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertStringStartsWith('%PDF', (string) $client->getInternalResponse()->getContent());
    }

    /** @return iterable<string, array{string}> */
    public static function adressesInconnues(): iterable
    {
        yield 'univers inconnu' => ['/formations/inconnu'];
        yield 'formation inconnue' => ['/formations/continue/formation-inexistante'];
        yield 'programme inconnu' => ['/formations/continue/formation-inexistante/programme.pdf'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('adressesInconnues')]
    public function testAdresseInconnueRenvoieUne404(string $adresse): void
    {
        $client = static::createClient();
        $client->request('GET', $adresse);

        self::assertResponseStatusCodeSame(404);
    }
}
