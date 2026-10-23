<?php

declare(strict_types=1);

namespace App\Controller\Ent;

use App\Entity\Utilisateur;
use App\Repository\CreneauRepository;
use App\Repository\EmargementRepository;
use App\Service\Ent\Semaine;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Planning hebdomadaire : cours de la promotion (étudiant) ou cours animés (formateur).
 * La semaine affichée est passée dans l'adresse (?semaine=2026-W43).
 */
#[Route('/ent')]
final class PlanningController extends AbstractController
{
    public function __construct(
        private readonly ClockInterface $horloge,
    ) {
    }

    #[Route('/etudiant/planning', name: 'app_ent_planning_etudiant', methods: ['GET'])]
    #[IsGranted('ROLE_ETUDIANT')]
    public function etudiant(
        #[CurrentUser] Utilisateur $etudiant,
        Request $request,
        CreneauRepository $creneaux,
        EmargementRepository $emargements,
    ): Response {
        $maintenant = $this->maintenant();
        $semaine = Semaine::depuisCode($request->query->getString('semaine') ?: null, $maintenant);
        $promotion = $etudiant->getPromotion();
        $liste = $promotion ? $creneaux->findPourPromotionEntre($promotion, $semaine->lundi, $semaine->getFin()) : [];

        return $this->render('ent/planning/semaine.html.twig', [
            'vue' => 'etudiant',
            'semaine' => $semaine,
            'creneaux' => $liste,
            'signes' => $emargements->findCreneauxSignes($etudiant, $liste),
            'promotion' => $promotion,
            'maintenant' => $maintenant,
        ]);
    }

    #[Route('/formateur/planning', name: 'app_ent_planning_formateur', methods: ['GET'])]
    #[IsGranted('ROLE_FORMATEUR')]
    public function formateur(
        #[CurrentUser] Utilisateur $formateur,
        Request $request,
        CreneauRepository $creneaux,
    ): Response {
        $maintenant = $this->maintenant();
        $semaine = Semaine::depuisCode($request->query->getString('semaine') ?: null, $maintenant);

        return $this->render('ent/planning/semaine.html.twig', [
            'vue' => 'formateur',
            'semaine' => $semaine,
            'creneaux' => $creneaux->findPourFormateurEntre($formateur, $semaine->lundi, $semaine->getFin()),
            'signes' => [],
            'promotion' => null,
            'maintenant' => $maintenant,
        ]);
    }

    private function maintenant(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($this->horloge->now());
    }
}
