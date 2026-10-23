<?php

namespace App\Enum;

enum StatutSession: string
{
    case Ouverte = 'ouverte';
    case Complete = 'complete';
    case Annulee = 'annulee';

    public function libelle(): string
    {
        return match ($this) {
            self::Ouverte => 'Inscriptions ouvertes',
            self::Complete => 'Complète',
            self::Annulee => 'Annulée',
        };
    }
}
