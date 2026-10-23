<?php

declare(strict_types=1);

namespace App\Service\Ent;

/**
 * Bilan d'assiduité d'un étudiant sur les créneaux terminés de sa promotion.
 */
final readonly class Assiduite
{
    /** En dessous de ce taux de présence, l'étudiant est signalé au formateur. */
    public const SEUIL_ALERTE = 80.0;

    public function __construct(
        public int $creneauxTermines,
        public int $presences,
    ) {
    }

    public function getAbsences(): int
    {
        return max(0, $this->creneauxTermines - $this->presences);
    }

    /** Taux de présence en pourcentage, ou null s'il n'y a encore aucun créneau terminé. */
    public function getTaux(): ?float
    {
        if (0 === $this->creneauxTermines) {
            return null;
        }

        return round(min(100, 100 * $this->presences / $this->creneauxTermines), 1);
    }

    public function estEnAlerte(): bool
    {
        $taux = $this->getTaux();

        return null !== $taux && $taux < self::SEUIL_ALERTE;
    }
}
