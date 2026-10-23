<?php

declare(strict_types=1);

namespace App\Controller\Ent;

use App\Entity\Creneau;
use App\Entity\Utilisateur;
use App\Enum\ModeSignature;
use App\Form\SignatureEmargementType;
use App\Repository\EmargementRepository;
use App\Security\Voter\FeuilleEmargementVoter;
use App\Service\Emargement\EmargementImpossible;
use App\Service\Emargement\Emargeur;
use App\Service\Emargement\EmpreinteEmargement;
use App\Service\Pdf\GenerateurPdf;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Émargement : signature par l'étudiant pendant le cours, feuille d'émargement du formateur.
 */
#[Route('/ent')]
final class EmargementController extends AbstractController
{
    public function __construct(
        private readonly ClockInterface $horloge,
    ) {
    }

    #[Route('/etudiant/emargement/{id}', name: 'app_ent_emargement_signer', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ETUDIANT')]
    public function signer(Creneau $creneau, #[CurrentUser] Utilisateur $etudiant, Request $request, Emargeur $emargeur): Response
    {
        try {
            $emargeur->verifierPeutSigner($creneau, $etudiant);
        } catch (EmargementImpossible $refus) {
            return $this->refus($creneau, $refus, $request);
        }

        $form = $this->createForm(SignatureEmargementType::class, options: ['nom_attendu' => $etudiant->getNomComplet()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Sans JavaScript, le choix du mode n'est pas proposé : c'est le nom saisi.
            $mode = $form->get('mode')->getData() ?? ModeSignature::Saisie;
            $champ = ModeSignature::Manuscrite === $mode ? 'signature' : 'nom';

            try {
                $emargeur->signer($creneau, $etudiant, $mode, (string) $form->get($champ)->getData(), $request->getClientIp());
                $this->addFlash('success', \sprintf('Votre présence au cours « %s » est enregistrée.', $creneau->getIntitule()));

                return $this->redirectToRoute('app_ent_etudiant', status: Response::HTTP_SEE_OTHER);
            } catch (EmargementImpossible $refus) {
                if (EmargementImpossible::SIGNATURE_INVALIDE !== $refus->raison) {
                    return $this->refus($creneau, $refus, $request);
                }
                $form->get($champ)->addError(new FormError($refus->getMessage()));
            }
        }

        return $this->render('ent/emargement/signer.html.twig', [
            'creneau' => $creneau,
            'form' => $form,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/formateur/creneau/{id}', name: 'app_ent_feuille_emargement', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(FeuilleEmargementVoter::CONSULTER, subject: 'creneau')]
    public function feuille(Creneau $creneau, EmargementRepository $emargements, EmpreinteEmargement $empreinte): Response
    {
        return $this->render('ent/emargement/feuille.html.twig', $this->donneesFeuille($creneau, $emargements, $empreinte));
    }

    #[Route('/formateur/creneau/{id}/pdf', name: 'app_ent_feuille_emargement_pdf', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(FeuilleEmargementVoter::CONSULTER, subject: 'creneau')]
    public function feuillePdf(Creneau $creneau, EmargementRepository $emargements, EmpreinteEmargement $empreinte, GenerateurPdf $pdf): Response
    {
        $contenu = $pdf->depuisGabarit('ent/emargement/feuille_pdf.html.twig', $this->donneesFeuille($creneau, $emargements, $empreinte));
        $fichier = \sprintf('emargement-%s-%s.pdf', $creneau->getDebut()?->format('Y-m-d'), $creneau->getDebut()?->format('Hi'));

        return new Response($contenu, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $fichier),
        ]);
    }

    /**
     * Une ligne par étudiant de la promotion : émargement éventuel et vérification du sceau.
     *
     * @return array<string, mixed>
     */
    private function donneesFeuille(Creneau $creneau, EmargementRepository $emargements, EmpreinteEmargement $empreinte): array
    {
        $parEtudiant = $emargements->findPourCreneauParEtudiant($creneau);
        $lignes = [];
        $presents = 0;
        $sceauxAlteres = 0;

        foreach ($creneau->getPromotion()?->getEtudiants() ?? [] as $etudiant) {
            $emargement = $parEtudiant[$etudiant->getId()] ?? null;
            $integre = null === $emargement || $empreinte->estIntegre($emargement);
            if (null !== $emargement) {
                ++$presents;
            }
            if (!$integre) {
                ++$sceauxAlteres;
            }
            $lignes[] = ['etudiant' => $etudiant, 'emargement' => $emargement, 'integre' => $integre];
        }

        $maintenant = \DateTimeImmutable::createFromInterface($this->horloge->now());

        return [
            'creneau' => $creneau,
            'lignes' => $lignes,
            'presents' => $presents,
            'sceaux_alteres' => $sceauxAlteres,
            'termine' => $creneau->getFin() <= $maintenant,
            'en_cours' => $creneau->estOuvertALaSignature($maintenant),
            'genere_le' => $maintenant,
        ];
    }

    private function refus(Creneau $creneau, EmargementImpossible $refus, Request $request): Response
    {
        // Un cours d'une autre promotion n'est pas révélé.
        if (\in_array($refus->raison, [EmargementImpossible::AUTRE_PROMOTION, EmargementImpossible::PAS_ETUDIANT], true)) {
            throw $this->createNotFoundException();
        }

        return $this->render('ent/emargement/indisponible.html.twig', [
            'creneau' => $creneau,
            'refus' => $refus,
        ], new Response(status: $request->isMethod('POST') ? Response::HTTP_CONFLICT : Response::HTTP_OK));
    }
}
