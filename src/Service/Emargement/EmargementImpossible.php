<?php

namespace App\Service\Emargement;

/**
 * Émargement refusé : le message est destiné à l'étudiant.
 */
final class EmargementImpossible extends \DomainException
{
    public const PAS_ETUDIANT = 'pas_etudiant';
    public const AUTRE_PROMOTION = 'autre_promotion';
    public const HORS_CRENEAU = 'hors_creneau';
    public const DEJA_SIGNE = 'deja_signe';
    public const SIGNATURE_INVALIDE = 'signature_invalide';

    public function __construct(
        public readonly string $raison,
        string $message,
    ) {
        parent::__construct($message);
    }
}
