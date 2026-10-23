<?php

declare(strict_types=1);

namespace App\Controller\Ent;

use App\Entity\Utilisateur;
use App\Enum\StatutExamen;
use App\Repository\EvaluationCompetenceRepository;
use App\Repository\ExamenRepository;
use App\Service\Examen\GrilleCompetences;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Examens de l'étudiant : convocations à venir et résultats publiés après clôture par le jury.
 */
final class EtudiantExamenController extends AbstractController
{
    #[Route('/ent/etudiant/examens', name: 'app_ent_etudiant_examens', methods: ['GET'])]
    #[IsGranted('ROLE_ETUDIANT')]
    public function index(
        #[CurrentUser] Utilisateur $etudiant,
        ExamenRepository $examens,
        EvaluationCompetenceRepository $evaluations,
        ClockInterface $horloge,
    ): Response {
        $maintenant = \DateTimeImmutable::createFromInterface($horloge->now());
        $promotion = $etudiant->getPromotion();
        $parStatut = array_fill_keys(array_map(static fn (StatutExamen $s): string => $s->value, StatutExamen::cases()), []);

        foreach ($promotion ? $examens->findPourPromotion($promotion) : [] as $examen) {
            $parStatut[$examen->getStatut($maintenant)->value][] = $examen;
        }

        // Les résultats ne sont visibles qu'une fois l'examen clôturé, et seulement les siens.
        $clotures = array_reverse($parStatut[StatutExamen::Cloture->value]);
        $mesEvaluations = $evaluations->findPourEtudiantIndexees($etudiant, $clotures);
        $resultats = [];
        foreach ($clotures as $examen) {
            $resultats[] = new GrilleCompetences($examen, [(int) $etudiant->getId() => $mesEvaluations[(int) $examen->getId()] ?? []]);
        }

        return $this->render('ent/examen/etudiant/index.html.twig', [
            'a_venir' => $parStatut[StatutExamen::AVenir->value],
            'en_correction' => $parStatut[StatutExamen::EnCorrection->value],
            'resultats' => $resultats,
        ]);
    }
}
