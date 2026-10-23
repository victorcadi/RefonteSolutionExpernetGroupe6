<?php

declare(strict_types=1);

namespace App\Controller\Ent;

use App\Entity\Utilisateur;
use App\Enum\RoleUtilisateur;
use App\Repository\CandidatureRepository;
use App\Repository\CreneauRepository;
use App\Repository\DemandeContactRepository;
use App\Repository\EmargementRepository;
use App\Repository\ExamenRepository;
use App\Repository\NoteRepository;
use App\Repository\PromotionRepository;
use App\Repository\UtilisateurRepository;
use App\Service\Ent\CalculAssiduite;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Tableaux de bord de l'espace formation, un par rôle.
 */
#[Route('/ent')]
final class TableauDeBordController extends AbstractController
{
    public function __construct(
        private readonly ClockInterface $horloge,
    ) {
    }

    /**
     * Point d'entrée de l'ENT : renvoie vers le tableau de bord du rôle.
     */
    #[Route('', name: 'app_ent_accueil', methods: ['GET'])]
    public function accueil(#[CurrentUser] Utilisateur $utilisateur): Response
    {
        return $this->redirectToRoute(match ($utilisateur->getRole()) {
            RoleUtilisateur::Admin => 'app_ent_admin',
            RoleUtilisateur::Formateur => 'app_ent_formateur',
            default => 'app_ent_etudiant',
        });
    }

    #[Route('/etudiant', name: 'app_ent_etudiant', methods: ['GET'])]
    #[IsGranted('ROLE_ETUDIANT')]
    public function etudiant(
        #[CurrentUser] Utilisateur $etudiant,
        CreneauRepository $creneaux,
        EmargementRepository $emargements,
        NoteRepository $notes,
        ExamenRepository $examens,
        CalculAssiduite $assiduite,
    ): Response {
        $maintenant = \DateTimeImmutable::createFromInterface($this->horloge->now());
        $promotion = $etudiant->getPromotion();
        $finDeJournee = $maintenant->setTime(23, 59, 59);

        $aujourdhui = $promotion ? $creneaux->findPourPromotionEntre($promotion, $maintenant->setTime(0, 0), $finDeJournee) : [];
        $prochains = $promotion ? $creneaux->findPourPromotionEntre($promotion, $finDeJournee, $maintenant->modify('+14 days'), 5) : [];

        return $this->render('ent/tableau_de_bord/etudiant.html.twig', [
            'promotion' => $promotion,
            'aujourdhui' => $aujourdhui,
            'signes' => $emargements->findCreneauxSignes($etudiant, $aujourdhui),
            'prochains' => $prochains,
            'assiduite' => $assiduite->pour($etudiant, $maintenant),
            'notes' => $notes->findDernieresPourEtudiant($etudiant),
            'examens' => $promotion ? $examens->findAVenirPourPromotion($promotion, $maintenant) : [],
            'maintenant' => $maintenant,
        ]);
    }

    #[Route('/formateur', name: 'app_ent_formateur', methods: ['GET'])]
    #[IsGranted('ROLE_FORMATEUR')]
    public function formateur(
        #[CurrentUser] Utilisateur $formateur,
        CreneauRepository $creneaux,
        PromotionRepository $promotions,
        ExamenRepository $examens,
        CalculAssiduite $assiduite,
    ): Response {
        $maintenant = \DateTimeImmutable::createFromInterface($this->horloge->now());
        $suivies = $promotions->findSuiviesPar($formateur);

        return $this->render('ent/tableau_de_bord/formateur.html.twig', [
            'creneaux' => $creneaux->findAVenirPourFormateur($formateur, $maintenant),
            'promotions' => $suivies,
            'a_suivre' => $assiduite->aSuivre($suivies, $maintenant),
            'jurys' => $examens->findAVenirPourMembreDuJury($formateur, $maintenant),
            'maintenant' => $maintenant,
        ]);
    }

    #[Route('/admin', name: 'app_ent_admin', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function admin(
        UtilisateurRepository $utilisateurs,
        PromotionRepository $promotions,
        DemandeContactRepository $demandes,
        CandidatureRepository $candidatures,
        CalculAssiduite $assiduite,
    ): Response {
        $maintenant = \DateTimeImmutable::createFromInterface($this->horloge->now());
        $suiviPromotions = [];
        $aSuivre = 0;
        foreach ($promotions->findBy([], ['nom' => 'ASC']) as $promotion) {
            $nombre = \count($assiduite->aSuivre([$promotion], $maintenant));
            $aSuivre += $nombre;
            $suiviPromotions[] = ['promotion' => $promotion, 'a_suivre' => $nombre];
        }

        return $this->render('ent/tableau_de_bord/admin.html.twig', [
            'comptes' => $utilisateurs->compterActifsParRole(),
            'nombre_promotions' => \count($suiviPromotions),
            'suivi_promotions' => $suiviPromotions,
            'nombre_a_suivre' => $aSuivre,
            'demandes' => $demandes->findNouvelles(),
            'nombre_demandes' => $demandes->compterNouvelles(),
            'candidatures' => $candidatures->findNouvelles(),
            'nombre_candidatures' => $candidatures->compterNouvelles(),
        ]);
    }
}
