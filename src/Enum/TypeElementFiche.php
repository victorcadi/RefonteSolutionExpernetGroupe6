<?php

namespace App\Enum;

/**
 * Rubriques listées d'une fiche formation (une ligne = un élément).
 */
enum TypeElementFiche: string
{
    case Objectif = 'objectif';
    case Prerequis = 'prerequis';
    case Moyen = 'moyen';
    case Methode = 'methode';
    case Evaluation = 'evaluation';
    case Acces = 'acces';
    case Debouche = 'debouche';

    public function libelle(): string
    {
        return match ($this) {
            self::Objectif => 'Objectifs pédagogiques',
            self::Prerequis => 'Prérequis',
            self::Moyen => 'Moyens techniques et humains',
            self::Methode => 'Méthodes pédagogiques mobilisées',
            self::Evaluation => 'Modalités d’évaluation',
            self::Acces => 'Modalités et délais d’accès',
            self::Debouche => 'Débouchés et métiers visés',
        };
    }
}
