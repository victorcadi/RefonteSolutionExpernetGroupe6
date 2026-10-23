<?php

declare(strict_types=1);

namespace App\Service\Ent;

use App\Entity\Creneau;
use App\Entity\Promotion;
use App\Entity\Utilisateur;
use App\Repository\CreneauRepository;
use App\Repository\EmargementRepository;

/**
 * Assiduité calculée à partir des émargements sur les créneaux terminés de la promotion.
 */
final readonly class CalculAssiduite
{
    public function __construct(
        private CreneauRepository $creneaux,
        private EmargementRepository $emargements,
    ) {
    }

    public function pour(Utilisateur $etudiant, \DateTimeImmutable $maintenant): Assiduite
    {
        $promotion = $etudiant->getPromotion();
        if (null === $promotion) {
            return new Assiduite(0, 0);
        }

        $termines = $this->creneaux->findTerminesPourPromotion($promotion, $maintenant);

        return $this->bilan($termines, $this->emargements->findCreneauxSignesParEtudiant($promotion, $maintenant)[(int) $etudiant->getId()] ?? []);
    }

    /**
     * Bilan de chaque étudiant de la promotion : ceux à suivre d'abord, puis par taux croissant.
     *
     * @return list<SuiviAssiduite>
     */
    public function pourPromotion(Promotion $promotion, \DateTimeImmutable $maintenant): array
    {
        $termines = $this->creneaux->findTerminesPourPromotion($promotion, $maintenant);
        $signes = $this->emargements->findCreneauxSignesParEtudiant($promotion, $maintenant);

        $suivis = [];
        foreach ($promotion->getEtudiants() as $etudiant) {
            $suivis[] = new SuiviAssiduite($etudiant, $this->bilan($termines, $signes[(int) $etudiant->getId()] ?? []));
        }

        usort($suivis, static fn (SuiviAssiduite $a, SuiviAssiduite $b): int => [
            !$a->assiduite->necessiteUnSuivi(), $a->assiduite->getTaux() ?? 100.0, $a->etudiant->getNom(),
        ] <=> [
            !$b->assiduite->necessiteUnSuivi(), $b->assiduite->getTaux() ?? 100.0, $b->etudiant->getNom(),
        ]);

        return $suivis;
    }

    /**
     * Étudiants à suivre dans plusieurs promotions.
     *
     * @param iterable<Promotion> $promotions
     *
     * @return list<SuiviAssiduite>
     */
    public function aSuivre(iterable $promotions, \DateTimeImmutable $maintenant): array
    {
        $aSuivre = [];
        foreach ($promotions as $promotion) {
            foreach ($this->pourPromotion($promotion, $maintenant) as $suivi) {
                if ($suivi->assiduite->necessiteUnSuivi()) {
                    $aSuivre[] = $suivi;
                }
            }
        }

        return $aSuivre;
    }

    /**
     * @param list<Creneau>    $termines créneaux terminés, du plus récent au plus ancien
     * @param array<int, true> $signes   identifiants des créneaux signés
     */
    private function bilan(array $termines, array $signes): Assiduite
    {
        $presences = 0;
        $consecutives = 0;
        $serieEnCours = true;
        $derniereAbsence = null;

        foreach ($termines as $creneau) {
            if (isset($signes[(int) $creneau->getId()])) {
                ++$presences;
                $serieEnCours = false;
                continue;
            }
            $derniereAbsence ??= $creneau->getDebut();
            if ($serieEnCours) {
                ++$consecutives;
            }
        }

        return new Assiduite(\count($termines), $presences, $consecutives, $derniereAbsence);
    }
}
