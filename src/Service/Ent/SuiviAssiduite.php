<?php

declare(strict_types=1);

namespace App\Service\Ent;

use App\Entity\Utilisateur;

/**
 * Ligne du suivi d'assiduité d'une promotion : un étudiant et son bilan.
 */
final readonly class SuiviAssiduite
{
    public function __construct(
        public Utilisateur $etudiant,
        public Assiduite $assiduite,
    ) {
    }
}
