<?php

namespace App\Controller;

use App\Enum\TypeFormation;
use App\Repository\FiliereRepository;
use App\Repository\FormationRepository;
use App\Repository\SessionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AccueilController extends AbstractController
{
    /**
     * @param list<array{valeur: string, libelle: string}>              $chiffresCles
     * @param list<array{titre: string, texte: string, icone: string}> $parcours
     */
    #[Route('/', name: 'app_accueil', methods: ['GET'])]
    public function index(
        FormationRepository $formations,
        SessionRepository $sessions,
        FiliereRepository $filieres,
        #[Autowire('%expernet.chiffres_cles%')] array $chiffresCles,
        #[Autowire('%expernet.parcours%')] array $parcours,
    ): Response {
        return $this->render('accueil/index.html.twig', [
            'diplomantes' => $formations->findParFiliere(TypeFormation::Alternance),
            'a_la_une' => $formations->findMisesEnAvant(TypeFormation::Continue, 3),
            'prochaines_sessions' => $sessions->findProchaines(5),
            'filieres' => $filieres->findBy([], ['position' => 'ASC']),
            'chiffres_cles' => $chiffresCles,
            'parcours' => $parcours,
        ]);
    }
}
