<?php

declare(strict_types=1);

namespace App\Service\Ent;

use App\Entity\Utilisateur;
use App\Repository\CreneauRepository;
use App\Repository\EmargementRepository;

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

        return new Assiduite(
            $this->creneaux->compterTerminesPourPromotion($promotion, $maintenant),
            $this->emargements->compterSurCreneauxTermines($etudiant, $maintenant),
        );
    }
}
