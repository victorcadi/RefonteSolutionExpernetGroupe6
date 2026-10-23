<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Competence;
use App\Entity\EvaluationCompetence;
use App\Entity\Examen;
use App\Entity\Formation;
use App\Entity\Promotion;
use App\Entity\Utilisateur;
use App\Enum\NiveauAcquisition;
use App\Enum\TypeExamen;
use App\Repository\EvaluationCompetenceRepository;
use App\Repository\ExamenRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Examens : planification par l'administration, saisie des grilles par le jury, clôture, résultats.
 *
 * Les examens créés ici ont lieu en mars 2030, loin des données de démonstration.
 */
final class ExamenTest extends WebTestCase
{
    use ClockSensitiveTrait;

    private const DOMAINE = '@demo.expernet.test';
    private const AVANT_EPREUVE = '2030-03-01 10:00:00';
    private const PENDANT_EPREUVE = '2030-03-11 10:00:00';
    private const DEBUT_EPREUVE = '2030-03-11 09:00:00';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    // --- Planification -----------------------------------------------------

    public function testLAdministrateurPlanifieUnExamen(): void
    {
        $this->horloge(self::AVANT_EPREUVE);
        $this->connecter('admin');

        $crawler = $this->client->request('GET', '/ent/admin/examens/nouveau');
        self::assertResponseIsSuccessful();
        // Seuls les comptes habilités sont proposés pour le jury.
        self::assertSelectorNotExists(\sprintf('input[name="examen[jury][]"][value="%d"]', $this->compte('etudiant')->getId()));

        $this->soumettre($crawler, 'Planifier l’examen', [
            'intitule' => 'Oral de synthèse – bloc 1',
            'type' => TypeExamen::Oral->value,
            'promotion' => (string) $this->promotion()->getId(),
            'dateExamen' => '2030-03-11T09:00',
            'dureeMinutes' => '60',
            'jury' => [(string) $this->compte('formateur')->getId(), (string) $this->compte('jury')->getId()],
            'competences' => array_map(static fn (Competence $c): string => (string) $c->getId(), $this->competences(1)),
        ]);

        self::assertResponseRedirects('/ent/admin/examens', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'L’examen « Oral de synthèse – bloc 1 » est planifié.');

        $examen = $this->examens()->findOneBy(['intitule' => 'Oral de synthèse – bloc 1']);
        self::assertNotNull($examen);
        self::assertCount(2, $examen->getJury());
        self::assertCount(\count($this->competences(1)), $examen->getCompetences());
    }

    public function testLaPlanificationRefuseUneDatePasseeEtUnJuryIncomplet(): void
    {
        $this->horloge(self::AVANT_EPREUVE);
        $this->connecter('admin');

        $crawler = $this->client->request('GET', '/ent/admin/examens/nouveau');
        $this->soumettre($crawler, 'Planifier l’examen', [
            'intitule' => 'Épreuve mal planifiée',
            'type' => TypeExamen::Ecrit->value,
            'promotion' => (string) $this->promotion()->getId(),
            'dateExamen' => '2030-02-20T09:00',
            'dureeMinutes' => '60',
            'jury' => [(string) $this->compte('formateur')->getId()],
            'competences' => [],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('title', 'Erreur');
        self::assertAnySelectorTextContains('.alert-danger li', 'L’examen doit être planifié à une date à venir.');
        self::assertAnySelectorTextContains('.alert-danger li', 'Le jury doit compter au moins 2 membres.');
        self::assertAnySelectorTextContains('.alert-danger li', 'Choisissez au moins une compétence à évaluer.');
    }

    public function testLaPlanificationRefuseLesChevauchementsDeLaPromotionEtDuJury(): void
    {
        $this->horloge(self::AVANT_EPREUVE);
        $this->examen();
        $this->connecter('admin');

        // Même promotion, même matinée, et Sophie Payet déjà au jury de l'autre épreuve.
        $crawler = $this->client->request('GET', '/ent/admin/examens/nouveau');
        $this->soumettre($crawler, 'Planifier l’examen', [
            'intitule' => 'Épreuve en conflit',
            'type' => TypeExamen::Ecrit->value,
            'promotion' => (string) $this->promotion()->getId(),
            'dateExamen' => '2030-03-11T11:00',
            'dureeMinutes' => '90',
            'jury' => [(string) $this->compte('formateur')->getId(), (string) $this->compte('formateur2')->getId()],
            'competences' => [(string) $this->competences(2)[0]->getId()],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertAnySelectorTextContains('.alert-danger li', 'La promotion passe déjà l’épreuve « Mise en situation de test »');
        self::assertAnySelectorTextContains('.alert-danger li', 'Sophie Payet siège déjà au jury de l’épreuve « Mise en situation de test »');
    }

    public function testLaPlanificationRefuseUnEtudiantAuJuryEtUneCompetenceHorsReferentiel(): void
    {
        $autreFormation = $this->em()->getRepository(Formation::class)->createQueryBuilder('f')
            ->andWhere('f <> :cda')->setParameter('cda', $this->promotion()->getFormation())
            ->setMaxResults(1)->getQuery()->getSingleResult();
        $horsReferentiel = (new Competence())->setFormation($autreFormation)->setBloc('Autre bloc')->setLibelle('Compétence d’une autre formation');
        $this->em()->persist($horsReferentiel);
        $this->em()->flush();

        $examen = $this->nouvelExamen('2030-03-11 09:00', [$this->compte('formateur'), $this->compte('etudiant')], [$horsReferentiel]);
        $messages = array_map(
            static fn ($v): string => $v->getPropertyPath().' : '.$v->getMessage(),
            iterator_to_array(self::getContainer()->get(ValidatorInterface::class)->validate($examen)),
        );

        self::assertContains('jury : Lucas Técher n’est pas habilité(e) à siéger dans un jury.', $messages);
        self::assertContains('competences : La compétence « Compétence d’une autre formation » n’appartient pas au référentiel de la formation de cette promotion.', $messages);
    }

    public function testSeulLAdministrateurGereLesExamens(): void
    {
        $this->connecter('formateur');
        $this->client->request('GET', '/ent/admin/examens');
        self::assertResponseStatusCodeSame(403);
    }

    public function testUnExamenAvecDesGrillesNeSeSupprimePasEtVerrouilleSonHoraire(): void
    {
        $this->horloge(self::PENDANT_EPREUVE);
        $examen = $this->examen();
        $this->evaluer($examen, 'etudiant', $this->competences(1)[0], NiveauAcquisition::Acquis);
        $this->connecter('admin');

        $crawler = $this->client->request('GET', '/ent/admin/examens/'.$examen->getId().'/modifier');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#examen_dateExamen[disabled]');
        self::assertSelectorExists('#examen_promotion[disabled]');
        self::assertSelectorNotExists('form[name="suppression_examen"]');

        // Retirer une compétence déjà évaluée est refusé.
        $this->soumettre($crawler, 'Enregistrer les modifications', [
            'competences' => [(string) $this->competences(1)[1]->getId()],
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertAnySelectorTextContains('.alert-danger li', 'Des compétences déjà évaluées par le jury ne peuvent pas être retirées de la grille.');
    }

    public function testLAdministrateurSupprimeUnExamenSansGrille(): void
    {
        $this->horloge(self::AVANT_EPREUVE);
        $examen = $this->examen();
        $id = $examen->getId();
        $this->connecter('admin');

        $this->client->request('GET', '/ent/admin/examens/'.$id.'/modifier');
        $this->client->submitForm('Supprimer l’examen');

        self::assertResponseRedirects('/ent/admin/examens', 303);
        $this->em()->clear();
        self::assertNull($this->examens()->find($id));
    }

    // --- Jury --------------------------------------------------------------

    public function testUnFormateurHorsDuJuryNAccedePasALExamen(): void
    {
        $examen = $this->examen();
        $this->connecter('formateur2');

        $this->client->request('GET', '/ent/formateur/examens/'.$examen->getId());
        self::assertResponseStatusCodeSame(403);
    }

    public function testLaGrilleNEstPasModifiableAvantLeDebutDeLEpreuve(): void
    {
        $this->horloge(self::AVANT_EPREUVE);
        $examen = $this->examen();
        $this->connecter('formateur');

        $this->client->request('GET', '/ent/formateur/examens/'.$examen->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#titre-avancement', 'À venir');
        self::assertSelectorTextContains('main', 'La saisie s’ouvrira au début de l’épreuve.');

        $this->client->request('POST', $this->urlGrille($examen, 'etudiant'));
        self::assertResponseStatusCodeSame(403);
    }

    public function testLeJurySaisitUneGrilleEnPlusieursFois(): void
    {
        $this->horloge(self::PENDANT_EPREUVE);
        $examen = $this->examen();
        [$c1, $c2] = $this->competences(1);
        $this->connecter('jury');

        $crawler = $this->client->request('GET', $this->urlGrille($examen, 'etudiant'));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Grille de Lucas Técher');
        // Un groupe de boutons radio par compétence, avec sa légende.
        self::assertSelectorTextContains('fieldset legend', $c1->getLibelle());

        $this->soumettre($crawler, 'Enregistrer la grille', [
            'niveau_'.$c1->getId() => NiveauAcquisition::Acquis->value,
            'niveau_'.$c2->getId() => NiveauAcquisition::EnCours->value,
            'commentaire_'.$c2->getId() => 'Tests à compléter.',
        ], 'grille_candidat');

        self::assertResponseRedirects('/ent/formateur/examens/'.$examen->getId(), 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Grille de Lucas Técher enregistrée : 2 compétences évaluées sur 4.');
        self::assertAnySelectorTextContains('tbody tr', '2 / 4');

        $evaluations = $this->evaluations($examen, 'etudiant');
        self::assertCount(2, $evaluations);
        self::assertSame(NiveauAcquisition::EnCours, $evaluations[$c2->getId()]->getNiveau());
        self::assertSame('Tests à compléter.', $evaluations[$c2->getId()]->getCommentaire());
        self::assertSame('jury'.self::DOMAINE, $evaluations[$c2->getId()]->getEvaluateur()?->getEmail());
        self::assertSame(self::PENDANT_EPREUVE, $evaluations[$c2->getId()]->getSaisieLe()?->format('Y-m-d H:i:s'));
    }

    public function testUnCommentaireSansNiveauEstRefuse(): void
    {
        $this->horloge(self::PENDANT_EPREUVE);
        $examen = $this->examen();
        $c1 = $this->competences(1)[0];
        $this->connecter('formateur');

        $crawler = $this->client->request('GET', $this->urlGrille($examen, 'etudiant'));
        $this->soumettre($crawler, 'Enregistrer la grille', ['commentaire_'.$c1->getId() => 'Sans niveau.'], 'grille_candidat');

        self::assertResponseStatusCodeSame(422);
        self::assertAnySelectorTextContains('.alert-danger li', 'Choisissez un niveau : un commentaire seul n’est pas enregistré.');
        self::assertCount(0, $this->evaluations($examen, 'etudiant'));
    }

    public function testUnCandidatHorsPromotionEstIntrouvable(): void
    {
        $this->horloge(self::PENDANT_EPREUVE);
        $examen = $this->examen();
        $this->connecter('formateur');

        $this->client->request('GET', $this->urlGrille($examen, 'formateur2'));
        self::assertResponseStatusCodeSame(404);
    }

    public function testLaClotureExigeDesGrillesCompletesPuisFigeLExamen(): void
    {
        $this->horloge(self::PENDANT_EPREUVE);
        $examen = $this->examen();
        $this->connecter('formateur');

        // Grilles incomplètes : aucun bouton, et une requête directe est refusée.
        $this->client->request('GET', '/ent/formateur/examens/'.$examen->getId());
        self::assertSelectorTextContains('main', 'La clôture sera possible quand toutes les grilles seront complètes.');
        $this->client->request('POST', '/ent/formateur/examens/'.$examen->getId().'/cloturer');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
        $this->em()->clear();
        $examen = $this->examens()->find($examen->getId());
        self::assertFalse($examen->estCloture());

        // Toutes les grilles remplies : la clôture est possible.
        foreach ($this->promotion()->getEtudiants() as $etudiant) {
            foreach ($this->competences(1) as $competence) {
                $this->evaluer($examen, $etudiant, $competence, NiveauAcquisition::Acquis);
            }
        }
        $this->client->request('GET', '/ent/formateur/examens/'.$examen->getId());
        $this->client->submitForm('Clôturer l’examen');
        self::assertResponseRedirects('/ent/formateur/examens/'.$examen->getId(), 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'L’examen est clôturé');

        $this->em()->clear();
        $examen = $this->examens()->find($examen->getId());
        self::assertTrue($examen->estCloture());
        self::assertSame('formateur'.self::DOMAINE, $examen->getCloturePar()?->getEmail());

        // Après la clôture : grille en lecture seule, modification refusée.
        $this->client->request('GET', $this->urlGrille($examen, 'etudiant'));
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('form[name="grille_candidat"]');
        self::assertSelectorTextContains('.alert-info', 'L’examen est clôturé');
        $this->client->request('POST', $this->urlGrille($examen, 'etudiant'));
        self::assertResponseStatusCodeSame(403);

        $this->connecter('admin');
        $this->client->request('GET', '/ent/admin/examens/'.$examen->getId().'/modifier');
        self::assertResponseStatusCodeSame(403);
    }

    // --- Étudiant ----------------------------------------------------------

    public function testLEtudiantNeVoitSesResultatsQuApresLaCloture(): void
    {
        $this->connecter('etudiant');

        $this->client->request('GET', '/ent/etudiant/examens');
        self::assertResponseIsSuccessful();

        // Données de démonstration : bloc 2 clôturé, bloc 3 en cours d'évaluation, bloc 1 à venir.
        self::assertSelectorTextContains('#resultat-1', 'Épreuve écrite – bloc 2');
        self::assertSelectorTextContains('#titre-correction + ul', 'bloc 3');
        self::assertSelectorTextNotContains('main', 'Démarche correcte, à consolider', 'Résultat d’un autre étudiant ou d’un examen non clôturé.');
        self::assertSelectorTextContains('#titre-a-venir + ul', 'bloc 1');
        self::assertSelectorNotExists('#resultat-2');
    }

    // --- Outils ------------------------------------------------------------

    private function horloge(string $maintenant): void
    {
        // Date créée après le démarrage du noyau, qui fixe le fuseau horaire de La Réunion.
        self::mockTime(new \DateTimeImmutable($maintenant));
    }

    /**
     * Remplit le formulaire de la page puis l'envoie, champs multiples compris.
     *
     * @param array<string, string|list<string>> $champs
     */
    private function soumettre(Crawler $crawler, string $bouton, array $champs, string $nom = 'examen'): void
    {
        $form = $crawler->selectButton($bouton)->form();
        $valeurs = $form->getPhpValues();
        foreach ($champs as $champ => $valeur) {
            $valeurs[$nom][$champ] = $valeur;
        }
        $this->client->request($form->getMethod(), $form->getUri(), $valeurs);
    }

    /** Examen de la promotion de démonstration (bloc 1), jury : Sophie Payet et Claire Grondin. */
    private function examen(): Examen
    {
        $examen = $this->nouvelExamen(self::DEBUT_EPREUVE, [$this->compte('formateur'), $this->compte('jury')], $this->competences(1));
        $this->em()->persist($examen);
        $this->em()->flush();

        return $examen;
    }

    /**
     * @param list<Utilisateur> $jury
     * @param list<Competence>  $competences
     */
    private function nouvelExamen(string $debut, array $jury, array $competences): Examen
    {
        $examen = (new Examen())
            ->setIntitule('Mise en situation de test')
            ->setType(TypeExamen::MiseEnSituation)
            ->setPromotion($this->promotion())
            ->setDateExamen(new \DateTimeImmutable($debut))
            ->setDureeMinutes(180);
        foreach ($jury as $membre) {
            $examen->ajouterMembreJury($membre);
        }
        foreach ($competences as $competence) {
            $examen->ajouterCompetence($competence);
        }

        return $examen;
    }

    private function evaluer(Examen $examen, Utilisateur|string $etudiant, Competence $competence, NiveauAcquisition $niveau): void
    {
        $evaluation = (new EvaluationCompetence())
            ->setExamen($examen)
            ->setEtudiant(\is_string($etudiant) ? $this->compte($etudiant) : $etudiant)
            ->setCompetence($competence)
            ->setNiveau($niveau)
            ->setEvaluateur($this->compte('formateur'))
            ->setSaisieLe(new \DateTimeImmutable(self::PENDANT_EPREUVE));
        // Le gestionnaire d'entités est partagé avec l'application : la collection est tenue à jour.
        $examen->getEvaluations()->add($evaluation);
        $this->em()->persist($evaluation);
        $this->em()->flush();
    }

    /** @return array<int, EvaluationCompetence> indexées par compétence */
    private function evaluations(Examen $examen, string $etudiant): array
    {
        $this->em()->clear();

        return self::getContainer()->get(EvaluationCompetenceRepository::class)->findPourExamenIndexees(
            $this->examens()->find($examen->getId()),
        )[$this->compte($etudiant)->getId()] ?? [];
    }

    private function urlGrille(Examen $examen, string $etudiant): string
    {
        return \sprintf('/ent/formateur/examens/%d/candidat/%d', $examen->getId(), $this->compte($etudiant)->getId());
    }

    /**
     * Compétences d'un bloc du référentiel CDA (1, 2 ou 3).
     *
     * @return list<Competence>
     */
    private function competences(int $bloc): array
    {
        $blocs = [];
        foreach ($this->promotion()->getFormation()->getCompetences() as $competence) {
            $blocs[$competence->getBloc()][] = $competence;
        }

        return array_values($blocs)[$bloc - 1];
    }

    private function promotion(): Promotion
    {
        $promotion = $this->em()->getRepository(Promotion::class)->findOneBy(['nom' => 'CDA – promotion démo']);
        self::assertNotNull($promotion);

        return $promotion;
    }

    private function examens(): ExamenRepository
    {
        return self::getContainer()->get(ExamenRepository::class);
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
