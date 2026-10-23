<?php

namespace App\Enum;

/**
 * Façon dont l'étudiant a signé son émargement.
 *
 * La saisie du nom est l'alternative au tracé pour les personnes qui ne peuvent pas
 * dessiner une signature (clavier seul, lecteur d'écran, handicap moteur).
 */
enum ModeSignature: string
{
    case Manuscrite = 'manuscrite';
    case Saisie = 'saisie';

    public function libelle(): string
    {
        return match ($this) {
            self::Manuscrite => 'Signature tracée',
            self::Saisie => 'Nom saisi',
        };
    }
}
