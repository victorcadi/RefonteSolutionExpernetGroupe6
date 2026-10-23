<?php

namespace App\Enum;

/**
 * Rôles métier de l'ENT. Les valeurs reprennent celles du schéma initial.
 */
enum RoleUtilisateur: string
{
    case Admin = 'Admin';
    case Formateur = 'Formateur';
    case Etudiant = 'Etudiant';

    public function roleSymfony(): string
    {
        return match ($this) {
            self::Admin => 'ROLE_ADMIN',
            self::Formateur => 'ROLE_FORMATEUR',
            self::Etudiant => 'ROLE_ETUDIANT',
        };
    }

    public function libelle(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::Formateur => 'Formateur',
            self::Etudiant => 'Étudiant',
        };
    }
}
