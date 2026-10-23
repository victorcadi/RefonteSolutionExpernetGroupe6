<?php

namespace App\Enum;

/**
 * Suivi interne des demandes de contact et des candidatures.
 */
enum StatutTraitement: string
{
    case Nouvelle = 'nouvelle';
    case EnCours = 'en_cours';
    case Acceptee = 'acceptee';
    case Refusee = 'refusee';
    case Close = 'close';

    public function libelle(): string
    {
        return match ($this) {
            self::Nouvelle => 'Nouvelle',
            self::EnCours => 'En cours de traitement',
            self::Acceptee => 'Acceptée',
            self::Refusee => 'Refusée',
            self::Close => 'Close',
        };
    }
}
