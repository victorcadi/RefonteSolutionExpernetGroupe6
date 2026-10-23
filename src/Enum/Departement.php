<?php

namespace App\Enum;

enum Departement: string
{
    case Reunion = 'La Réunion';
    case Mayotte = 'Mayotte';

    public function fuseauHoraire(): string
    {
        return match ($this) {
            self::Reunion => 'Indian/Reunion',
            self::Mayotte => 'Indian/Mayotte',
        };
    }
}
