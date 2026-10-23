<?php

namespace App\Enum;

/**
 * Niveau d'acquisition d'une compétence dans une grille d'évaluation d'examen.
 */
enum NiveauAcquisition: string
{
    case Acquis = 'acquis';
    case EnCours = 'en_cours';
    case NonAcquis = 'non_acquis';

    public function libelle(): string
    {
        return match ($this) {
            self::Acquis => 'Acquis',
            self::EnCours => 'En cours d’acquisition',
            self::NonAcquis => 'Non acquis',
        };
    }
}
