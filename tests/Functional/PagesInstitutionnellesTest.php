<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Pages légales, plan du site, fichiers pour les moteurs de recherche et pages d'erreur.
 */
final class PagesInstitutionnellesTest extends WebTestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function pages(): iterable
    {
        yield 'mentions légales' => ['/mentions-legales', 'Mentions légales'];
        yield 'confidentialité' => ['/confidentialite', 'Politique de confidentialité'];
        yield 'accessibilité' => ['/accessibilite', 'Accessibilité'];
        yield 'plan du site' => ['/plan-du-site', 'Plan du site'];
    }

    #[DataProvider('pages')]
    public function testPageInstitutionnelleEstAccessibleDepuisLePiedDePage(string $adresse, string $titre): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        self::assertSelectorExists(sprintf('footer nav[aria-label="Informations légales"] a[href="%s"]', $adresse));

        $client->request('GET', $adresse);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', $titre);
        self::assertSelectorCount(1, 'h1');
    }

    public function testMentionsLegalesReprennentLesInformationsOfficielles(): void
    {
        $client = static::createClient();
        $client->request('GET', '/mentions-legales');

        self::assertSelectorTextContains('#editeur + dl', '442 764 684 00027');
        self::assertSelectorTextContains('#organisme + dl', '98 97 01198 97');
        self::assertSelectorTextContains('#organisme + dl', '23FOR01923.1');
        self::assertSelectorExists('a[href="/documents/certificat-qualiopi.pdf"]');
    }

    public function testDeclarationAccessibiliteIndiqueLEtatDeConformite(): void
    {
        $client = static::createClient();
        $client->request('GET', '/accessibilite');

        self::assertSelectorTextContains('#etat + p', 'non conforme');
        self::assertSelectorExists('a[href="https://formulaire.defenseurdesdroits.fr/"]');
    }

    public function testPlanDuSiteListeLesFichesFormation(): void
    {
        $client = static::createClient();
        $client->request('GET', '/plan-du-site');

        self::assertSelectorExists('a[href="/formations/alternance/concepteur-developpeur-d-applications"]');
        self::assertSelectorExists('a[href="/formations/continue/copilot-pour-microsoft-365"]');
    }

    public function testSitemapEstUnXmlValideQuiContientLesFiches(): void
    {
        $client = static::createClient();
        $client->request('GET', '/sitemap.xml');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/xml; charset=UTF-8');

        $xml = simplexml_load_string((string) $client->getResponse()->getContent());
        self::assertNotFalse($xml);
        $adresses = array_map(static fn ($url): string => (string) $url->loc, iterator_to_array($xml->url, false));
        self::assertContains('http://localhost/formations/alternance/concepteur-developpeur-d-applications', $adresses);
        self::assertContains('http://localhost/mentions-legales', $adresses);
    }

    public function testSiteDeDemonstrationNEstPasIndexe(): void
    {
        $client = static::createClient();
        $client->request('GET', '/robots.txt');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/plain; charset=UTF-8');
        self::assertStringContainsString("Disallow: /\n", (string) $client->getResponse()->getContent());

        $client->request('GET', '/');
        self::assertSelectorExists('meta[name="robots"][content="noindex, nofollow"]');
    }

    public function testPageIntrouvablePersonnaliseeSansDetailTechnique(): void
    {
        $client = static::createClient(['debug' => false]);
        $client->request('GET', '/cette-page-n-existe-pas');

        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('h1', 'Cette page est introuvable');
        self::assertSelectorExists('form[role="search"][action="/formations"]');
        self::assertSelectorExists('a.lien-evitement');
        self::assertStringNotContainsString('Symfony', (string) $client->getResponse()->getContent());
    }
}
