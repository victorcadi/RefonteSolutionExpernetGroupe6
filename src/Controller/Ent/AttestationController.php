<?php

declare(strict_types=1);

namespace App\Controller\Ent;

use App\Entity\Utilisateur;
use App\Repository\CreneauRepository;
use App\Repository\EmargementRepository;
use App\Service\Emargement\EmpreinteEmargement;
use App\Service\Pdf\GenerateurPdf;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Attestations de présence mensuelles, établies à partir des émargements scellés.
 */
#[Route('/ent/etudiant/attestations')]
#[IsGranted('ROLE_ETUDIANT')]
final class AttestationController extends AbstractController
{
    public function __construct(
        private readonly ClockInterface $horloge,
        private readonly CreneauRepository $creneaux,
    ) {
    }

    #[Route('', name: 'app_ent_attestations', methods: ['GET'])]
    public function index(#[CurrentUser] Utilisateur $etudiant): Response
    {
        return $this->render('ent/attestation/index.html.twig', [
            'mois' => $this->moisDisponibles($etudiant),
        ]);
    }

    #[Route('/{mois}', name: 'app_ent_attestation_pdf', requirements: ['mois' => '\d{4}-(0[1-9]|1[0-2])'], methods: ['GET'])]
    public function pdf(
        string $mois,
        #[CurrentUser] Utilisateur $etudiant,
        EmargementRepository $emargements,
        EmpreinteEmargement $empreinte,
        GenerateurPdf $pdf,
    ): Response {
        $debut = new \DateTimeImmutable($mois.'-01 00:00:00');
        $promotion = $etudiant->getPromotion();
        if (null === $promotion || !\in_array($mois, array_map(static fn (\DateTimeImmutable $m): string => $m->format('Y-m'), $this->moisDisponibles($etudiant)), true)) {
            throw $this->createNotFoundException('Aucune attestation pour ce mois.');
        }

        $maintenant = $this->maintenant();
        $fin = min($debut->modify('first day of next month'), $maintenant);

        // Seuls les cours terminés figurent sur l'attestation.
        $creneaux = array_values(array_filter(
            $this->creneaux->findPourPromotionEntre($promotion, $debut, $fin),
            static fn ($creneau): bool => $creneau->getDebut() >= $debut && $creneau->getFin() <= $fin,
        ));
        $signes = $emargements->findPourEtudiantEntre($etudiant, $debut, $fin);

        $lignes = [];
        $presences = 0;
        $minutesPresence = 0;
        $toutIntegre = true;
        foreach ($creneaux as $creneau) {
            $emargement = $signes[$creneau->getId()] ?? null;
            if (null !== $emargement) {
                ++$presences;
                $minutesPresence += $creneau->getDureeMinutes();
                $toutIntegre = $toutIntegre && $empreinte->estIntegre($emargement);
            }
            $lignes[] = ['creneau' => $creneau, 'emargement' => $emargement];
        }

        $contenu = $pdf->depuisGabarit('ent/attestation/attestation_pdf.html.twig', [
            'etudiant' => $etudiant,
            'promotion' => $promotion,
            'mois' => $debut,
            'lignes' => $lignes,
            'presences' => $presences,
            'minutes_presence' => $minutesPresence,
            'tout_integre' => $toutIntegre,
            'genere_le' => $maintenant,
        ]);

        return new Response($contenu, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, \sprintf('attestation-presence-%s.pdf', $mois)),
        ]);
    }

    /**
     * Mois couverts par la formation, du premier cours au mois en cours (le plus récent d'abord).
     *
     * @return list<\DateTimeImmutable> premier jour de chaque mois
     */
    private function moisDisponibles(Utilisateur $etudiant): array
    {
        $promotion = $etudiant->getPromotion();
        $premier = $promotion ? $this->creneaux->findPremierDebutPourPromotion($promotion) : null;
        if (null === $premier) {
            return [];
        }

        $mois = [];
        $courant = $this->maintenant()->modify('first day of this month')->setTime(0, 0);
        $limite = $premier->modify('first day of this month')->setTime(0, 0);
        while ($courant >= $limite && \count($mois) < 36) {
            $mois[] = $courant;
            $courant = $courant->modify('-1 month');
        }

        return $mois;
    }

    private function maintenant(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($this->horloge->now());
    }
}
