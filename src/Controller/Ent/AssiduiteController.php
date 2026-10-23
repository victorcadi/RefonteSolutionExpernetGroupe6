<?php

declare(strict_types=1);

namespace App\Controller\Ent;

use App\Entity\Promotion;
use App\Entity\Utilisateur;
use App\Repository\CreneauRepository;
use App\Repository\EmargementRepository;
use App\Security\Voter\PromotionVoter;
use App\Service\Ent\CalculAssiduite;
use Psr\Clock\ClockInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Suivi de l'assiduité d'une promotion par ses formateurs : alertes et historique de présence.
 */
#[Route('/ent/formateur/promotions/{id}/assiduite', requirements: ['id' => '\d+'])]
#[IsGranted(PromotionVoter::SUIVRE, subject: 'promotion')]
final class AssiduiteController extends AbstractController
{
    public function __construct(
        private readonly ClockInterface $horloge,
        private readonly CalculAssiduite $assiduite,
    ) {
    }

    #[Route('', name: 'app_ent_assiduite_promotion', methods: ['GET'])]
    public function promotion(Promotion $promotion): Response
    {
        $suivis = $this->assiduite->pourPromotion($promotion, $this->maintenant());

        return $this->render('ent/assiduite/promotion.html.twig', [
            'promotion' => $promotion,
            'suivis' => $suivis,
            'a_suivre' => \count(array_filter($suivis, static fn ($s): bool => $s->assiduite->necessiteUnSuivi())),
        ]);
    }

    #[Route('/{etudiant}', name: 'app_ent_assiduite_etudiant', requirements: ['etudiant' => '\d+'], methods: ['GET'])]
    public function etudiant(
        Promotion $promotion,
        #[MapEntity(id: 'etudiant')] Utilisateur $etudiant,
        CreneauRepository $creneaux,
        EmargementRepository $emargements,
    ): Response {
        if ($etudiant->getPromotion() !== $promotion) {
            throw $this->createNotFoundException();
        }
        $maintenant = $this->maintenant();

        return $this->render('ent/assiduite/etudiant.html.twig', [
            'promotion' => $promotion,
            'etudiant' => $etudiant,
            'assiduite' => $this->assiduite->pour($etudiant, $maintenant),
            'creneaux' => $creneaux->findTerminesPourPromotion($promotion, $maintenant),
            'emargements' => $emargements->findPourEtudiantParCreneau($etudiant),
        ]);
    }

    private function maintenant(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($this->horloge->now());
    }
}
