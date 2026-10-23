<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Candidat;
use App\Entity\Candidature;
use App\Entity\DemandeContact;
use App\Enum\TypeDemande;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Formulaires publics : contact, devis, candidature.
 * Chaque test est annulé en fin d'exécution (transaction DAMA).
 */
final class FormulairesPublicsTest extends WebTestCase
{
    private const CDA = 'concepteur-developpeur-d-applications';

    public function testPageContactAfficheUnFormulaireAccessible(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/contact');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Nous contacter');
        self::assertSelectorExists('form[novalidate]');
        self::assertSelectorExists('label[for="demande_contact_email"] .champ-obligatoire[aria-hidden="true"]');
        self::assertSame('demande_contact_email_help', $crawler->filter('#demande_contact_email')->attr('aria-describedby'));
        self::assertSelectorExists('.champ-piege[aria-hidden="true"] input[tabindex="-1"]');
    }

    public function testDevisDepuisUneFichePreselectionneLaFormation(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/devis?formation=copilot-pour-microsoft-365');

        self::assertResponseIsSuccessful();
        self::assertSame('devis', $crawler->filter('input[name="demande_contact[type]"][checked]')->attr('value'));
        self::assertStringContainsString(
            'Copilot',
            $crawler->filter('#demande_contact_formation option[selected]')->text(),
        );
    }

    public function testEnvoiInvalideAfficheLesErreursSansEnregistrer(): void
    {
        $client = static::createClient();
        $client->request('GET', '/contact');
        $crawler = $client->submitForm('Envoyer ma demande', [
            'demande_contact[prenom]' => '',
            'demande_contact[nom]' => 'Payet',
            'demande_contact[email]' => 'adresse-invalide',
            'demande_contact[message]' => 'Court',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('title', 'Erreur');
        self::assertSelectorTextContains('[role="alert"]', 'Le formulaire contient des erreurs');
        self::assertSelectorExists('[role="alert"] a[href="#demande_contact_prenom"]');
        self::assertSame('true', $crawler->filter('#demande_contact_email')->attr('aria-invalid'));
        self::assertStringContainsString('demande_contact_email_error1', (string) $crawler->filter('#demande_contact_email')->attr('aria-describedby'));
        self::assertSelectorTextContains('#demande_contact_email_error1', 'n’est pas valide');
        self::assertSame(0, $this->compter(DemandeContact::class));
    }

    public function testDemandeDeDevisValideEstEnregistreeEtNotifiee(): void
    {
        $client = static::createClient();
        $client->request('GET', '/devis');
        $client->submitForm('Envoyer ma demande', $this->demandeValide());

        self::assertResponseRedirects('/devis', 303);
        self::assertQueuedEmailCount(2);
        $equipe = self::getMailerMessage(0);
        self::assertNotNull($equipe);
        self::assertEmailAddressContains($equipe, 'To', 'formation@expernet.re');
        self::assertEmailAddressContains($equipe, 'Reply-To', 'marie.hoarau@exemple.test');
        self::assertEmailHtmlBodyContains($equipe, 'Former 8 personnes');
        $accuse = self::getMailerMessage(1);
        self::assertNotNull($accuse);
        self::assertEmailAddressContains($accuse, 'To', 'marie.hoarau@exemple.test');

        $demande = $this->em()->getRepository(DemandeContact::class)->findOneBy(['email' => 'marie.hoarau@exemple.test']);
        self::assertNotNull($demande);
        self::assertSame(TypeDemande::Devis, $demande->getType());

        $client->followRedirect();
        self::assertSelectorTextContains('[role="status"]', 'Merci Marie');
    }

    public function testChampPiegeRempliBloqueLEnvoi(): void
    {
        $client = static::createClient();
        $client->request('GET', '/contact');
        $client->submitForm('Envoyer ma demande', $this->demandeValide() + ['demande_contact[site_web]' => 'https://spam.example']);

        self::assertResponseStatusCodeSame(422);
        self::assertQueuedEmailCount(0);
        self::assertSame(0, $this->compter(DemandeContact::class));
    }

    public function testEnvoisRepetesSontLimites(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        for ($envoi = 1; $envoi <= 5; ++$envoi) {
            $client->request('GET', '/contact');
            $client->submitForm('Envoyer ma demande', $this->demandeValide());
            self::assertResponseRedirects('/contact', 303, sprintf('Envoi n°%d refusé à tort.', $envoi));
        }

        $client->request('GET', '/contact');
        $client->submitForm('Envoyer ma demande', $this->demandeValide());

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'plusieurs demandes en peu de temps');
        self::assertSame(5, $this->compter(DemandeContact::class));
    }

    public function testCandidatureAlternanceAvecChoixDeSession(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/candidature?formation='.self::CDA);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Candidater');
        $session = $crawler->filter('#candidature_session option')->eq(1)->attr('value');
        self::assertNotEmpty($session);

        $client->submitForm('Envoyer ma candidature', $this->candidatureValide() + ['candidature[session]' => $session]);

        self::assertResponseRedirects('/candidature', 303);
        self::assertQueuedEmailCount(2);

        $candidature = $this->em()->getRepository(Candidature::class)->findOneBy([], ['id' => 'DESC']);
        self::assertNotNull($candidature);
        self::assertSame(self::CDA, $candidature->getFormation()?->getSlug());
        self::assertSame((int) $session, $candidature->getSession()?->getId());
        self::assertTrue($candidature->getCandidat()?->isSouhaiteContactReferentHandicap());
    }

    public function testCandidatDejaConnuEstMisAJourSansDoublon(): void
    {
        $client = static::createClient();

        foreach (['0692 11 22 33', '0693 44 55 66'] as $telephone) {
            $client->request('GET', '/candidature?formation='.self::CDA);
            $client->submitForm('Envoyer ma candidature', ['candidature[candidat][telephone]' => $telephone] + $this->candidatureValide());
            self::assertResponseRedirects('/candidature', 303);
        }

        $candidats = $this->em()->getRepository(Candidat::class)->findBy(['email' => 'lucas.grondin@exemple.test']);
        self::assertCount(1, $candidats);
        self::assertSame('0693 44 55 66', $candidats[0]->getTelephone());
        self::assertSame(2, $this->compter(Candidature::class));
    }

    public function testCandidatureSansConsentementEstRefusee(): void
    {
        $client = static::createClient();
        $client->request('GET', '/candidature?formation='.self::CDA);
        $form = $client->getCrawler()->selectButton('Envoyer ma candidature')->form($this->candidatureValide());
        $form['candidature[consentement]']->untick();
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#candidature_consentement_error1', 'accepter le traitement');
        self::assertSame(0, $this->compter(Candidature::class));
    }

    /** @return array<string, string|bool> */
    private function demandeValide(): array
    {
        return [
            'demande_contact[prenom]' => 'Marie',
            'demande_contact[nom]' => 'Hoarau',
            'demande_contact[email]' => 'marie.hoarau@exemple.test',
            'demande_contact[telephone]' => '0262 12 34 56',
            'demande_contact[entreprise]' => 'Entreprise de test',
            'demande_contact[message]' => 'Former 8 personnes à Excel en janvier, sur notre site de Saint-Denis.',
            'demande_contact[consentement]' => true,
        ];
    }

    /** @return array<string, string|bool> */
    private function candidatureValide(): array
    {
        return [
            'candidature[candidat][prenom]' => 'Lucas',
            'candidature[candidat][nom]' => 'Grondin',
            'candidature[candidat][email]' => 'lucas.grondin@exemple.test',
            'candidature[candidat][telephone]' => '0692 11 22 33',
            'candidature[candidat][codePostal]' => '97400',
            'candidature[candidat][ville]' => 'Saint-Denis',
            'candidature[candidat][diplome]' => 'BTS SIO, obtenu en 2025',
            'candidature[candidat][souhaiteContactReferentHandicap]' => true,
            'candidature[consentement]' => true,
        ];
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    /** @param class-string $classe */
    private function compter(string $classe): int
    {
        return $this->em()->getRepository($classe)->count([]);
    }
}
