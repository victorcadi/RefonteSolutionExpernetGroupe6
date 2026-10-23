<?php

namespace App\Enum;

enum Modalite: string
{
    case Presentiel = 'presentiel';
    case Distanciel = 'distanciel';
    case Hybride = 'hybride';

    public function libelle(): string
    {
        return match ($this) {
            self::Presentiel => 'Présentiel',
            self::Distanciel => 'À distance',
            self::Hybride => 'Hybride',
        };
    }
}
