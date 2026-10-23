<?php

namespace App\Enum;

enum TypeDemande: string
{
    case Devis = 'devis';
    case Renseignement = 'renseignement';
    case Contact = 'contact';

    public function libelle(): string
    {
        return match ($this) {
            self::Devis => 'Demande de devis',
            self::Renseignement => 'Demande de renseignements',
            self::Contact => 'Prise de contact',
        };
    }
}
