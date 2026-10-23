<?php

namespace App\Controller;

use App\Catalogue\CriteresRecherche;
use App\Entity\Formation;
use App\Enum\TypeFormation;
use App\Form\FiltreCatalogueType;
use App\Repository\FiliereRepository;
use App\Repository\FormationRepository;
use App\Repository\SessionRepository;
use App\Repository\ThematiqueRepository;
use App\Service\Catalogue\ProgrammePdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class CatalogueController extends AbstractController
{
    private const UNIVERS = 'continue|alternance';

    public function __construct(private readonly FormationRepository $formations)
    {
    }

    /**
     * Catalogue avec recherche et filtres à facettes, éventuellement limité à un univers.
     */
    #[Route('/formations/{univers}', name: 'app_catalogue', requirements: ['univers' => self::UNIVERS], defaults: ['univers' => null], methods: ['GET'])]
    public function index(
        ?string $univers,
        Request $request,
        FiliereRepository $filieres,
        ThematiqueRepository $thematiques,
        SessionRepository $sessions,
    ): Response {
        $criteres = new CriteresRecherche();
        $criteres->type = null === $univers ? null : TypeFormation::from($univers);

        // Premier passage : lecture des critères pour calculer les facettes.
        $lecture = $this->createForm(FiltreCatalogueType::class, $criteres, ['filieres' => $filieres->findBy([], ['position' => 'ASC'])]);
        $lecture->handleRequest($request);
        if ($lecture->isSubmitted() && !$lecture->isValid()) {
            // Paramètres d'URL incohérents (ex. niveau=abc) : on repart d'une recherche vierge.
            $type = $criteres->type;
            $criteres = new CriteresRecherche();
            $criteres->type = $type;
        }
        $criteres->page = max(1, $request->query->getInt('page', 1));

        $comptes = [];
        foreach (['filiere', 'niveau', 'cpf', 'modalite', 'type'] as $facette) {
            $comptes[$facette] = $this->formations->compterParFacette($criteres, $facette);
        }

        // Second formulaire, construit avec les comptes, pour l'affichage.
        $form = $this->createForm(FiltreCatalogueType::class, $criteres, [
            'filieres' => $filieres->findBy([], ['position' => 'ASC']),
            'comptes' => $comptes,
            'afficher_niveaux' => TypeFormation::Continue !== $criteres->type,
        ]);

        $resultats = $this->formations->rechercher($criteres);
        $total = \count($resultats);
        $pages = max(1, (int) ceil($total / CriteresRecherche::PAR_PAGE));
        $liste = iterator_to_array($resultats);

        $reponse = $this->render('catalogue/index.html.twig', [
            'univers' => $criteres->type,
            'criteres' => $criteres,
            'form' => $form,
            'formations' => $liste,
            'prochaines' => $sessions->findProchaineParFormation($liste),
            'total' => $total,
            'pages' => $pages,
            'comptes_univers' => $comptes['type'],
            'thematique_active' => null === $criteres->thematique ? null : $thematiques->findOneBy(['slug' => $criteres->thematique]),
        ]);

        // Une page au-delà de la dernière n'existe pas.
        if ($criteres->page > $pages) {
            $reponse->setStatusCode(Response::HTTP_NOT_FOUND);
        }

        return $reponse;
    }

    #[Route('/formations/{univers}/{slug}', name: 'app_formation_fiche', requirements: ['univers' => self::UNIVERS, 'slug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function fiche(string $univers, string $slug): Response
    {
        $formation = $this->trouverOuRediriger($univers, $slug);
        if ($formation instanceof Response) {
            return $formation;
        }

        return $this->render('formation/fiche.html.twig', [
            'formation' => $formation,
            'similaires' => $this->formations->findSimilaires($formation),
        ]);
    }

    /**
     * Programme au format PDF : le document officiel s'il a été déposé, sinon une version générée.
     */
    #[Route('/formations/{univers}/{slug}/programme.pdf', name: 'app_formation_programme', requirements: ['univers' => self::UNIVERS, 'slug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function programme(string $univers, string $slug, ProgrammePdf $programmePdf): Response
    {
        $formation = $this->trouverOuRediriger($univers, $slug);
        if ($formation instanceof Response) {
            return $formation;
        }

        $nomFichier = \sprintf('programme-%s.pdf', $formation->getSlug());
        $officiel = $programmePdf->cheminDocumentOfficiel($formation);
        if (null !== $officiel) {
            return $this->file($officiel, $nomFichier, ResponseHeaderBag::DISPOSITION_ATTACHMENT);
        }

        return new Response($programmePdf->generer($formation), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $nomFichier),
        ]);
    }

    /**
     * Trouve une formation publiée ; redirige (301) si l'univers de l'URL est erroné.
     */
    private function trouverOuRediriger(string $univers, string $slug): Formation|Response
    {
        $formation = $this->formations->findPublieeParSlug($slug)
            ?? throw new NotFoundHttpException('Cette formation n’existe pas ou n’est plus proposée.');

        if ($formation->getType()?->value !== $univers) {
            return $this->redirectToRoute('app_formation_fiche', [
                'univers' => $formation->getType()?->value,
                'slug' => $formation->getSlug(),
            ], Response::HTTP_MOVED_PERMANENTLY);
        }

        return $formation;
    }
}
