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

    /** À partir de ce nombre de cours manqués d'affilée (les plus récents), l'étudiant est signalé. */
    public const SEUIL_ABSENCES_CONSECUTIVES = 3;

    public function __construct(
        public int $creneauxTermines,
        public int $presences,
        /** Cours manqués d'affilée, en remontant depuis le dernier cours terminé. */
        public int $absencesConsecutives = 0,
        public ?\DateTimeImmutable $derniereAbsence = null,
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

    /** Taux de présence sous le seuil. */
    public function estEnAlerte(): bool
    {
        $taux = $this->getTaux();

        return null !== $taux && $taux < self::SEUIL_ALERTE;
    }

    /** Absences répétées sur les derniers cours, même si le taux global reste correct (décrochage récent). */
    public function aDesAbsencesRepetees(): bool
    {
        return $this->absencesConsecutives >= self::SEUIL_ABSENCES_CONSECUTIVES;
    }

    /** L'étudiant doit être signalé au formateur. */
    public function necessiteUnSuivi(): bool
    {
        return $this->estEnAlerte() || $this->aDesAbsencesRepetees();
    }
}
