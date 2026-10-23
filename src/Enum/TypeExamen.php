<?php

namespace App\Enum;

enum TypeExamen: string
{
    case Ecrit = 'ecrit';
    case Oral = 'oral';
    case MiseEnSituation = 'mise_en_situation';
    case SoutenanceProjet = 'soutenance';

    public function libelle(): string
    {
        return match ($this) {
            self::Ecrit => 'Épreuve écrite',
            self::Oral => 'Entretien oral',
            self::MiseEnSituation => 'Mise en situation professionnelle',
            self::SoutenanceProjet => 'Présentation d’un projet',
        };
    }
}
