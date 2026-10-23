<?php

namespace App\Enum;

/**
 * Étape d'un examen, déduite de sa date et de sa clôture par le jury.
 */
enum StatutExamen: string
{
    case AVenir = 'a_venir';
    case EnCorrection = 'en_correction';
    case Cloture = 'cloture';

    public function libelle(): string
    {
        return match ($this) {
            self::AVenir => 'À venir',
            self::EnCorrection => 'Évaluation en cours',
            self::Cloture => 'Clôturé',
        };
    }

    /** Variante de badge de la charte. */
    public function badge(): string
    {
        return match ($this) {
            self::AVenir => '',
            self::EnCorrection => 'badge-expernet--alerte',
            self::Cloture => 'badge-expernet--succes',
        };
    }
}
