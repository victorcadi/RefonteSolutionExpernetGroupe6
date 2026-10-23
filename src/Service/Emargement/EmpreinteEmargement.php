<?php

namespace App\Service\Emargement;

use App\Entity\Emargement;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Calcule et vérifie le sceau d'un émargement.
 *
 * HMAC-SHA256 (clé APP_SECRET) sur l'étudiant, le créneau, l'horodatage, le mode et la signature :
 * une ligne modifiée directement en base ne correspond plus à son empreinte.
 * APP_SECRET ne doit donc plus changer une fois le site en service.
 */
final class EmpreinteEmargement
{
    /** Version du format scellé : à incrémenter si la liste des données change. */
    private const VERSION = 'v1';

    public function __construct(
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
    ) {
        if ('' === $this->secret) {
            throw new \LogicException('APP_SECRET doit être défini pour sceller les émargements.');
        }
    }

    public function calculer(Emargement $emargement): string
    {
        $creneau = $emargement->getCreneau();
        $etudiant = $emargement->getEtudiant();

        // Séparateur d'unité ASCII : aucune valeur ne peut le contenir ni le simuler.
        $donnees = implode("\x1F", [
            self::VERSION,
            $etudiant?->getId(),
            $etudiant?->getEmail(),
            $creneau?->getId(),
            $creneau?->getIntitule(),
            $creneau?->getDebut()?->format(\DATE_ATOM),
            $creneau?->getFin()?->format(\DATE_ATOM),
            $emargement->getSigneLe()?->format(\DATE_ATOM),
            $emargement->getMode()->value,
            hash('sha256', (string) $emargement->getSignature()),
        ]);

        return hash_hmac('sha256', $donnees, $this->secret);
    }

    public function sceller(Emargement $emargement): void
    {
        $emargement->setEmpreinte($this->calculer($emargement));
    }

    public function estIntegre(Emargement $emargement): bool
    {
        return hash_equals((string) $emargement->getEmpreinte(), $this->calculer($emargement));
    }
}
