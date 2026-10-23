<?php

namespace App\Service\Emargement;

use App\Entity\Emargement;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Calcule et vérifie le sceau d'un émargement.
 *
 * HMAC-SHA256 (clé APP_SECRET) sur l'étudiant, le créneau, l'horodatage et la signature :
 * une ligne modifiée directement en base ne correspond plus à son empreinte.
 */
final class EmpreinteEmargement
{
    public function __construct(
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
    ) {
    }

    public function calculer(Emargement $emargement): string
    {
        $donnees = implode('|', [
            $emargement->getEtudiant()?->getEmail(),
            $emargement->getCreneau()?->getDebut()?->format(\DATE_ATOM),
            $emargement->getCreneau()?->getIntitule(),
            $emargement->getSigneLe()?->format(\DATE_ATOM),
            hash('sha256', (string) $emargement->getSignature()),
        ]);

        return hash_hmac('sha256', $donnees, $this->secret);
    }

    public function estIntegre(Emargement $emargement): bool
    {
        return hash_equals((string) $emargement->getEmpreinte(), $this->calculer($emargement));
    }
}
