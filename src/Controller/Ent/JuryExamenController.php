<?php

declare(strict_types=1);

namespace App\Controller\Ent;

use App\Entity\Examen;
use App\Entity\Utilisateur;
use App\Form\GrilleCandidatType;
use App\Repository\EvaluationCompetenceRepository;
use App\Repository\ExamenRepository;
use App\Security\Voter\ExamenVoter;
use App\Service\Examen\GrilleCompetences;
use App\Service\Examen\SaisieGrille;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Espace du jury : suivi des examens, saisie des grilles de compétences et clôture.
 */
#[Route('/ent/formateur/examens')]
final class JuryExamenController extends AbstractController
{
    public function __construct(
        private readonly ClockInterface $horloge,
        private readonly EvaluationCompetenceRepository $evaluations,
    ) {
    }

    #[Route('', name: 'app_ent_jury_examens', methods: ['GET'])]
    public function index(#[CurrentUser] Utilisateur $membre, ExamenRepository $examens): Response
    {
        return $this->render('ent/examen/jury/index.html.twig', [
            'examens' => $examens->findPourMembreDuJury($membre),
            'maintenant' => $this->maintenant(),
        ]);
    }

    #[Route('/{id}', name: 'app_ent_jury_examen', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(ExamenVoter::CONSULTER, subject: 'examen')]
    public function examen(Examen $examen): Response
    {
        return $this->render('ent/examen/jury/examen.html.twig', [
            'grille' => $this->grille($examen),
            'cloture' => $this->formulaireCloture($examen),
            'maintenant' => $this->maintenant(),
        ]);
    }

    #[Route('/{id}/candidat/{etudiant}', name: 'app_ent_jury_grille', requirements: ['id' => '\d+', 'etudiant' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(ExamenVoter::CONSULTER, subject: 'examen')]
    public function grilleCandidat(
        Examen $examen,
        #[MapEntity(id: 'etudiant')] Utilisateur $candidat,
        #[CurrentUser] Utilisateur $membre,
        Request $request,
        SaisieGrille $saisie,
    ): Response {
        // Seuls les étudiants de la promotion sont candidats.
        if (null === $candidat->getPromotion() || $candidat->getPromotion() !== $examen->getPromotion()) {
            throw $this->createNotFoundException();
        }

        $grille = $this->grille($examen);
        $modifiable = $this->isGranted(ExamenVoter::EVALUER, $examen);
        $form = null;

        if ($modifiable) {
            $form = $this->createForm(GrilleCandidatType::class, $saisie->donnees($examen, $candidat), [
                'competences' => $examen->getCompetences(),
            ]);
            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                /** @var array<string, mixed> $donnees */
                $donnees = $form->getData();
                foreach ($saisie->commentairesSansNiveau($examen, $donnees) as $champ) {
                    $form->get($champ)->addError(new FormError('Choisissez un niveau : un commentaire seul n’est pas enregistré.'));
                }

                if ($form->isValid()) {
                    $evaluees = $saisie->enregistrer($examen, $candidat, $membre, $donnees, $this->maintenant());
                    $this->addFlash('success', \sprintf(
                        'Grille de %s enregistrée : %d compétence%s évaluée%s sur %d.',
                        $candidat->getNomComplet(),
                        $evaluees,
                        $evaluees > 1 ? 's' : '',
                        $evaluees > 1 ? 's' : '',
                        $grille->nombreCompetences(),
                    ));

                    return $this->redirectToRoute('app_ent_jury_examen', ['id' => $examen->getId()], Response::HTTP_SEE_OTHER);
                }
            }
        } elseif ($request->isMethod('POST')) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('ent/examen/jury/grille.html.twig', [
            'grille' => $grille,
            'candidat' => $candidat,
            'form' => $form,
            'maintenant' => $this->maintenant(),
        ], new Response(status: $form?->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}/cloturer', name: 'app_ent_jury_examen_cloturer', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ExamenVoter::CLOTURER, subject: 'examen')]
    public function cloturer(Examen $examen, #[CurrentUser] Utilisateur $membre, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->formulaireCloture($examen);
        $form->handleRequest($request);
        $grille = $this->grille($examen);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('danger', 'La demande de clôture n’a pas pu être vérifiée. Réessayez.');
        } elseif (!$grille->estComplete()) {
            $this->addFlash('danger', \sprintf(
                'L’examen ne peut pas être clôturé : %d grille%s incomplète%s.',
                \count($grille->getCandidatsIncomplets()),
                \count($grille->getCandidatsIncomplets()) > 1 ? 's sont' : ' est',
                \count($grille->getCandidatsIncomplets()) > 1 ? 's' : '',
            ));
        } else {
            $examen->cloturer($membre, $this->maintenant());
            $em->flush();
            $this->addFlash('success', 'L’examen est clôturé : les grilles ne sont plus modifiables et les candidats peuvent consulter leurs résultats.');
        }

        return $this->redirectToRoute('app_ent_jury_examen', ['id' => $examen->getId()], Response::HTTP_SEE_OTHER);
    }

    private function grille(Examen $examen): GrilleCompetences
    {
        return new GrilleCompetences($examen, $this->evaluations->findPourExamenIndexees($examen));
    }

    private function formulaireCloture(Examen $examen): FormInterface
    {
        return $this->container->get('form.factory')->createNamedBuilder('cloture_examen')
            ->setAction($this->generateUrl('app_ent_jury_examen_cloturer', ['id' => $examen->getId()]))
            ->getForm();
    }

    private function maintenant(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($this->horloge->now());
    }
}
